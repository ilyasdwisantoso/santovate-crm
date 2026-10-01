<?php

use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\Quotation;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

Artisan::command('santovate:about', function () {
    $this->info('Santovate CRM is ready.');
})->purpose('Check Santovate CRM installation');

Artisan::command('platform:owner {email} {--name=} {--demote=}', function () {
    $email = strtolower(trim((string)$this->argument('email')));
    $validation = Validator::make(['email'=>$email], ['email'=>['required','email']]);
    if ($validation->fails()) {
        $this->error('Email Superadmin tidak valid.');
        return 1;
    }

    $internal = Organization::query()->where('slug','santovate-internal')->first();
    if (!$internal) {
        $this->error('Organization santovate-internal belum ada. Jalankan migration/seeder fondasi yang benar terlebih dahulu.');
        return 1;
    }

    $user = User::query()->where('email',$email)->first();
    if ($user && $user->organization_id !== $internal->id) {
        $this->error('User sudah ada tetapi bukan anggota Santovate Internal. Command berhenti agar tidak memindahkan tenant secara diam-diam.');
        return 1;
    }

    if (!$user) {
        $name = trim((string)($this->option('name') ?: $this->ask('Nama Platform Owner')));
        $password = (string)$this->secret('Password Superadmin (min. 12 karakter)');
        if (mb_strlen($password) < 12) {
            $this->error('Password minimal 12 karakter. Account tidak dibuat.');
            return 1;
        }
        $confirm = (string)$this->secret('Ulangi password');
        if (!hash_equals($password,$confirm)) {
            $this->error('Konfirmasi password tidak sama. Account tidak dibuat.');
            return 1;
        }

        $user = User::create([
            'organization_id'=>$internal->id,
            'name'=>$name ?: 'Santovate Platform Owner',
            'email'=>$email,
            'password'=>$password,
            'role'=>'admin',
            'is_active'=>true,
            'is_platform_admin'=>true,
            'job_title'=>'Platform Owner',
            'department'=>'Management',
        ]);
        $this->info('Platform Owner baru berhasil dibuat.');
    } else {
        $user->forceFill(['is_platform_admin'=>true,'is_active'=>true,'role'=>'admin'])->save();
        $this->info('User existing berhasil dipromosikan menjadi Platform Owner.');
    }

    $demoteEmail = strtolower(trim((string)$this->option('demote')));
    if ($demoteEmail !== '' && $demoteEmail !== $email) {
        $demoted = User::query()->where('email',$demoteEmail)->where('organization_id',$internal->id)->first();
        if ($demoted) {
            $demoted->forceFill(['is_platform_admin'=>false])->save();
            $this->info('Akses Platform Admin dicabut dari '.$demoteEmail.'. Role tenant tetap '.$demoted->role.'.');
        } else {
            $this->warn('User yang akan didemote tidak ditemukan: '.$demoteEmail);
        }
    }

    if (Schema::hasTable('platform_audit_logs')) {
        PlatformAuditLog::create([
            'actor_id'=>$user->id,
            'organization_id'=>$internal->id,
            'action'=>'platform.owner_bootstrap',
            'target_type'=>User::class,
            'target_id'=>$user->id,
            'metadata'=>['email'=>$email,'demoted'=>$demoteEmail ?: null],
        ]);
    }

    $this->newLine();
    $this->info('Login owner akan diarahkan ke /platform.');
    $this->warn('Jangan menjalankan DemoAccountSeeder sembarangan setelah pemisahan role; seeder legacy masih memiliki bootstrap admin platform untuk compatibility.');
    return 0;
})->purpose('Create/promote a Santovate Platform Owner safely and optionally demote the legacy platform admin');

Artisan::command('platform:approvals-backfill', function () {
    $count = 0;
    Quotation::query()
        ->where('status','pending_approval')
        ->where('requires_approval',true)
        ->where('approval_status','pending')
        ->orderBy('id')
        ->chunkById(100, function ($quotations) use (&$count) {
            foreach ($quotations as $quotation) {
                app(ApprovalService::class)->syncQuotation($quotation);
                $count++;
            }
        });
    $this->info("Approval request synchronized for {$count} pending quotation(s).");
    return 0;
})->purpose('Backfill generic approval requests for quotations that were pending before Batch 5A');

Artisan::command('platform:entitlements-audit {--organization=}', function () {
    $query = Organization::query()->orderBy('id');
    if ($slug = $this->option('organization')) $query->where('slug',$slug);
    $service = app(EntitlementService::class);
    $rows = $query->get()->map(function ($org) use ($service) {
        $e = $service->snapshot($org);
        return [
            $org->id,
            $org->slug,
            data_get($e,'subscription.plan.name','-'),
            data_get($e,'limits.users.unlimited') ? 'unlimited' : data_get($e,'limits.users.used',0).'/'.data_get($e,'limits.users.limit',0),
            data_get($e,'limits.prospects.unlimited') ? 'unlimited' : data_get($e,'limits.prospects.used',0).'/'.data_get($e,'limits.prospects.limit',0),
            count($e['enabled_features'] ?? []),
            data_get($e,'grants.active_count',0),
        ];
    })->all();
    $this->table(['ID','Organization','Plan','Users','Prospects','Features','Grants'],$rows);
    return 0;
})->purpose('Audit effective entitlement and usage for all organizations');
