<?php

namespace App\Services;

use App\Mail\TeamInvitationMail;
use App\Models\Organization;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TeamInvitationService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /** @return array{0:TeamInvitation,1:string} */
    public function invite(User $actor, string $email, string $role): array
    {
        $email = mb_strtolower(trim($email));
        return DB::transaction(function () use ($actor, $email, $role) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($actor->organization_id);
            $this->assertEmailAvailable($email);
            $existing = TeamInvitation::query()->where('organization_id', $organization->id)
                ->where('email', $email)->lockForUpdate()->first();
            if ($existing?->isRedeemable()) {
                throw ValidationException::withMessages(['email' => 'Undangan masih berlaku. Gunakan Kirim Ulang bila diperlukan.']);
            }
            $this->assertSeatsForNewInvitation($organization);
            $token = TeamInvitationTokens::generate();
            $invitation = $existing ?? new TeamInvitation();
            $invitation->fill([
                'organization_id' => $organization->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => TeamInvitationTokens::digest($token),
                'status' => 'pending',
                'invited_by' => $actor->id,
                'accepted_user_id' => null,
                'expires_at' => now()->addHours(48),
                'accepted_at' => null,
                'revoked_at' => null,
            ])->save();
            return [$invitation, $token];
        });
    }

    /** @return array{0:TeamInvitation,1:string} */
    public function resend(User $actor, TeamInvitation $invitation): array
    {
        return DB::transaction(function () use ($actor, $invitation) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($actor->organization_id);
            $locked = TeamInvitation::query()->where('organization_id', $organization->id)
                ->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->status === 'accepted') {
                throw ValidationException::withMessages(['invitation' => 'Undangan telah digunakan.']);
            }
            if ($locked->updated_at && $locked->updated_at->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['invitation' => 'Tunggu sedikitnya 60 detik sebelum mengirim ulang.']);
            }
            $this->assertEmailAvailable($locked->email);
            // Exclude the current invitation from outstanding reservations.
            $pending = $this->pendingCount($organization, $locked->id);
            $this->entitlements->assertCanConsume($organization, 'users', $pending + 1);
            $token = TeamInvitationTokens::generate();
            $locked->update([
                'token_hash' => TeamInvitationTokens::digest($token),
                'status' => 'pending',
                'invited_by' => $actor->id,
                'expires_at' => now()->addHours(48),
                'accepted_at' => null,
                'accepted_user_id' => null,
                'revoked_at' => null,
            ]);
            return [$locked, $token];
        });
    }

    public function revoke(User $actor, TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($actor->organization_id);
            $locked = TeamInvitation::query()->where('organization_id', $organization->id)
                ->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['invitation' => 'Undangan tidak dapat dibatalkan.']);
            }
            $locked->update(['status' => 'revoked', 'revoked_at' => now()]);
        });
    }

    public function findRedeemable(string $token): ?TeamInvitation
    {
        if (!TeamInvitationTokens::valid($token)) return null;
        $invite = TeamInvitation::query()->where('token_hash', TeamInvitationTokens::digest($token))->first();
        return $invite?->isRedeemable() ? $invite : null;
    }

    public function accept(string $token, string $name, string $password): User
    {
        abort_unless(TeamInvitationTokens::valid($token), 404);
        $digest = TeamInvitationTokens::digest($token);
        // Identify org first, but reacquire locks in organization -> invitation order.
        $candidate = TeamInvitation::query()->where('token_hash', $digest)->firstOrFail();
        return DB::transaction(function () use ($candidate, $digest, $name, $password) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($candidate->organization_id);
            $invite = TeamInvitation::query()->where('organization_id', $organization->id)
                ->where('token_hash', $digest)->lockForUpdate()->firstOrFail();
            abort_unless($invite->isRedeemable(), 410, 'Undangan sudah tidak berlaku.');
            $this->assertEmailAvailable($invite->email);
            $this->entitlements->assertCanConsume($organization, 'users', 1);
            $user = User::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'email' => $invite->email,
                'password' => $password,
                'role' => $invite->role,
                'is_active' => true,
                'is_platform_admin' => false,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $invite->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_user_id' => $user->id]);
            return $user;
        });
    }

    public function mailInvite(TeamInvitation $invitation, string $token, string $actorName): void
    {
        $orgName = Organization::query()->findOrFail($invitation->organization_id)->name;
        Mail::to($invitation->email)->send(new TeamInvitationMail(
            $orgName, $actorName,
            route('team-invitation.show', ['token' => $token]),
            $invitation->expires_at->format('d-m-Y H:i T'),
        ));
    }

    private function assertEmailAvailable(string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah memiliki akun.']);
        }
    }

    private function assertSeatsForNewInvitation(Organization $organization): void
    {
        $this->entitlements->assertCanConsume($organization, 'users', $this->pendingCount($organization) + 1);
    }

    private function pendingCount(Organization $organization, ?int $exceptId = null): int
    {
        return TeamInvitation::query()->where('organization_id', $organization->id)
            ->where('status', 'pending')->where('expires_at', '>', now())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->count();
    }
}
