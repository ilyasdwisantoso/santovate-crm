<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProspectIdentityService
{
    public function normalize(array $data, ?Prospect $existing = null): array
    {
        $type = $data['entity_type'] ?? $existing?->entity_type ?? 'business';
        $identity = Validator::make([
            'entity_type'=>$type,
            'company_name'=>isset($data['company_name']) && is_string($data['company_name']) ? trim($data['company_name']) : ($data['company_name'] ?? null),
            'individual_name'=>isset($data['individual_name']) && is_string($data['individual_name']) ? trim($data['individual_name']) : ($data['individual_name'] ?? null),
        ], [
            'entity_type'=>['required', Rule::in(['business','individual'])],
            'company_name'=>[Rule::requiredIf($type === 'business'), Rule::prohibitedIf($type === 'individual'), 'nullable','string','max:255'],
            'individual_name'=>[Rule::requiredIf($type === 'individual'), Rule::prohibitedIf($type === 'business'), 'nullable','string','max:255'],
        ])->validate();

        if ($existing && $existing->entity_type !== $type) {
            throw ValidationException::withMessages(['entity_type'=>'Jenis identitas tidak dapat diubah melalui edit biasa.']);
        }
        if (!$existing && $type === 'individual' && !Prospect::nativeIndividualsEnabled()) {
            throw ValidationException::withMessages(['entity_type'=>'Pembuatan prospek individu belum diaktifkan.']);
        }

        $data['entity_type'] = $type;
        $data['company_name'] = $type === 'business' ? $identity['company_name'] : null;
        $data['individual_name'] = $type === 'individual' ? $identity['individual_name'] : null;
        unset($data['company_key'], $data['display_name']);
        if (!Prospect::hasNativeIdentitySchema()) unset($data['entity_type'], $data['individual_name']);
        return $data;
    }

    public function candidates(int $organizationId, array $data, ?int $exclude = null): Builder
    {
        $type = $data['entity_type'] ?? 'business';
        $company = trim((string) ($data['company_name'] ?? ''));
        $person = trim((string) ($data['individual_name'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phone = Prospect::normalizePhone($data['phone'] ?? null);
        $companyKey = $type === 'business' && $company ? Prospect::makeCompanyKey($company,$city,$organizationId) : null;
        $query = Prospect::query()->where('organization_id', $organizationId);
        if ($exclude) $query->whereKeyNot($exclude);
        if (!$company && !$person && !$email && !$phone) return $query->whereRaw('1=0');

        return $query->where(function (Builder $q) use ($type,$company,$person,$city,$email,$phone,$companyKey) {
            if ($companyKey) $q->orWhere(fn(Builder $business) => $business->businessIdentity()->where('company_key',$companyKey));
            if ($email) $q->orWhereRaw('LOWER(email)=?', [$email]);
            if ($phone) $q->orWhere('phone_normalized', $phone);
            if ($type === 'business' && $company) $q->orWhere(function (Builder $name) use ($company,$city) {
                $name->businessIdentity()->whereRaw('LOWER(company_name)=?', [mb_strtolower($company)]);
                if ($city) $name->whereRaw('LOWER(city)=?', [mb_strtolower($city)]);
            });
            if ($type === 'individual' && $person && Prospect::hasNativeIdentitySchema()) {
                $q->orWhere(function (Builder $name) use ($person) {
                    $name->where('entity_type','individual')->whereRaw('LOWER(individual_name)=?', [mb_strtolower($person)]);
                });
            }
        });
    }

    public function assertReviewed(User $actor, array $data): void
    {
        if (!empty($data['force_duplicate'])) return;
        if ($this->candidates((int)$actor->organization_id, $data)->exists()) {
            throw ValidationException::withMessages(['duplicate'=>'Kandidat duplikat ditemukan. Tinjau identitas, lalu konfirmasikan jika memang pelanggan berbeda.']);
        }
    }
}
