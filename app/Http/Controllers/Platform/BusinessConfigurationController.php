<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\BusinessConfiguration;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Services\PlatformAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BusinessConfigurationController extends Controller
{
    public function index(PlatformAnalyticsService $analytics): Response
    {
        return Inertia::render('Platform/Configurations/Index',['configurations'=>$analytics->configurationAnalytics()]);
    }

    public function update(Request $request, BusinessConfiguration $configuration): RedirectResponse
    {
        $data = $request->validate([
            'name'=>['required','string','max:120'],
            'description'=>['nullable','string','max:1000'],
            'monthly_addon_price'=>['required','integer','min:0'],
            'annual_addon_price'=>['required','integer','min:0'],
            'is_active'=>['required','boolean'],
            'sort_order'=>['nullable','integer','min:0','max:9999'],
        ]);

        if (!$data['is_active']) {
            $activeSubscriptions = Subscription::query()->where('business_configuration_id',$configuration->id)
                ->where('status','active')->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))->count();
            if ($activeSubscriptions > 0) {
                throw ValidationException::withMessages([
                    'is_active'=>"Configuration masih dipakai {$activeSubscriptions} subscription aktif. Nonaktifkan setelah lifecycle client ditangani.",
                ]);
            }
        }

        $before = $configuration->only(['name','monthly_addon_price','annual_addon_price','is_active','sort_order']);
        $configuration->update($data);

        PlatformAuditLog::create([
            'actor_id'=>$request->user()->id,
            'action'=>'configuration.updated',
            'target_type'=>BusinessConfiguration::class,
            'target_id'=>$configuration->id,
            'metadata'=>['before'=>$before,'after'=>$configuration->fresh()->only(array_keys($before))],
        ]);

        return back()->with('success','Business Configuration diperbarui. Harga baru berlaku untuk subscription baru/perubahan berikutnya; snapshot subscription aktif tidak diubah.');
    }
}
