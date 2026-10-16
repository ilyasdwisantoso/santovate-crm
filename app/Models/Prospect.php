<?php

namespace App\Models;

use App\Services\ProspectScoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Prospect extends Model
{
    use HasFactory;

    public const STATUSES = [
        'baru'=>'Baru','diriset'=>'Sudah Diriset','dihubungi'=>'Sudah Dihubungi','membalas'=>'Ada Balasan',
        'meeting'=>'Meeting','demo'=>'Demo','proposal'=>'Proposal','negosiasi'=>'Negosiasi','deal'=>'Deal',
        'ditolak'=>'Ditolak','tidak_cocok'=>'Tidak Cocok',
    ];

    public const PIPELINE_STATUSES = ['baru','diriset','dihubungi','membalas','meeting','demo','proposal','negosiasi','deal'];
    public const PRIORITIES = ['tinggi'=>'Tinggi','sedang'=>'Sedang','rendah'=>'Rendah'];

    protected $fillable = [
        'entity_type','individual_name','organization_id','company_key','company_name','website','city','service','route','company_size','contact_name',
        'contact_position','decision_maker_name','decision_maker_position','phone','phone_normalized','whatsapp_status','whatsapp_id','whatsapp_verified_at','whatsapp_opt_in_at',
        'whatsapp_opt_out_at','whatsapp_last_error','email','current_system','tracking_portal','pain_hypothesis','fit_score',
        'pain_score','contact_score','total_score','priority','status','qualification_status','last_contact_at','next_follow_up_at','last_outbound_at',
        'last_customer_reply_at','follow_up_snoozed_until','follow_up_count','last_follow_up_message','last_feedback_at',
        'last_feedback_status','last_feedback_note','contacted_at','replied_at','meeting_at','demo_at','proposal_at',
        'negotiation_at','deal_at','closed_at','source_name','source_url','notes','estimated_deal_value','estimated_budget','expected_timeline','target_go_live','urgency','probability','next_action','actual_deal_value',
        'assigned_to','created_by','import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'tracking_portal'=>'boolean','last_contact_at'=>'datetime','next_follow_up_at'=>'datetime','last_outbound_at'=>'datetime',
            'last_customer_reply_at'=>'datetime','follow_up_snoozed_until'=>'datetime','follow_up_count'=>'integer','last_feedback_at'=>'datetime',
            'whatsapp_verified_at'=>'datetime','whatsapp_opt_in_at'=>'datetime','whatsapp_opt_out_at'=>'datetime',
            'contacted_at'=>'datetime','replied_at'=>'datetime','meeting_at'=>'datetime','demo_at'=>'datetime','proposal_at'=>'datetime',
            'negotiation_at'=>'datetime','deal_at'=>'datetime','closed_at'=>'datetime','fit_score'=>'integer','pain_score'=>'integer',
            'contact_score'=>'integer','total_score'=>'integer','estimated_deal_value'=>'decimal:2','estimated_budget'=>'decimal:2','target_go_live'=>'date','probability'=>'integer','actual_deal_value'=>'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Prospect $prospect) {
            $type = $prospect->entity_type;
            if (!in_array($type, ['business','individual'], true)) {
                throw ValidationException::withMessages(['entity_type'=>'Jenis identitas tidak valid.']);
            }
            if ($prospect->exists && ($prospect->getRawOriginal('entity_type') ?? 'business') !== $type) {
                throw ValidationException::withMessages(['entity_type'=>'Jenis identitas tidak dapat diubah melalui edit biasa.']);
            }
            if ($type === 'individual') {
                $prospect->individual_name = trim((string)$prospect->individual_name);
                if ($prospect->individual_name === '' || filled($prospect->company_name)) {
                    throw ValidationException::withMessages(['individual_name'=>'Nama individu wajib diisi tanpa nama perusahaan.']);
                }
                $prospect->company_name = null;
                $prospect->company_key = null;
            } else {
                if (blank($prospect->company_name) || filled($prospect->individual_name)) {
                    throw ValidationException::withMessages(['company_name'=>'Nama perusahaan wajib diisi untuk prospek bisnis.']);
                }
                $prospect->company_key = static::makeCompanyKey($prospect->company_name, $prospect->city, $prospect->organization_id);
            }
            $prospect->phone_normalized = static::normalizePhone($prospect->phone);
            $scoring = app(ProspectScoringService::class)->calculate(
                (int) $prospect->fit_score,
                (int) $prospect->pain_score,
                (int) $prospect->contact_score,
            );
            $prospect->total_score = $scoring['total'];
            $prospect->priority = $scoring['priority'];
        });
    }

    public static function makeCompanyKey(?string $name, ?string $city, int|string|null $organizationId = null): string
    {
        $slug = Str::slug(trim((string) $name).'|'.trim((string) $city));
        return ($organizationId ?: '0').'-'.$slug;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (!$digits) return null;
        if (str_starts_with($digits, '0')) $digits = '62'.substr($digits, 1);
        elseif (str_starts_with($digits, '8')) $digits = '62'.$digits;
        return $digits;
    }

    public function getEntityTypeAttribute(?string $value): string { return $value ?? 'business'; }
    public function getDisplayNameAttribute(): string { return (string)($this->entity_type === 'individual' ? $this->individual_name : $this->company_name); }

    public static function hasNativeIdentitySchema(): bool
    {
        return Schema::hasColumns('prospects', ['entity_type','individual_name']);
    }

    public static function nativeIndividualsEnabled(): bool
    {
        return (bool)config('santovate.crm.native_individuals_enabled', false) && static::hasNativeIdentitySchema();
    }

    public static function identityColumns(): array
    {
        return static::hasNativeIdentitySchema() ? ['id','company_name','entity_type','individual_name'] : ['id','company_name'];
    }

    public function identityPayload(): array
    {
        return ['id'=>$this->id,'entity_type'=>$this->entity_type,'individual_name'=>$this->individual_name,'company_name'=>$this->company_name,'display_name'=>$this->display_name];
    }

    public function scopeBusinessIdentity(Builder $query): Builder
    {
        return static::hasNativeIdentitySchema() ? $query->where('entity_type','business') : $query;
    }

    public function scopeSearchIdentity(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('company_name','like','%'.$term.'%');
            if (static::hasNativeIdentitySchema()) $q->orWhere('individual_name','like','%'.$term.'%');
        });
    }

    public function scopeOrderByIdentity(Builder $query): Builder
    {
        return static::hasNativeIdentitySchema() ? $query->orderByRaw('COALESCE(individual_name, company_name)')->orderBy('id') : $query->orderBy('company_name')->orderBy('id');
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function importBatch(): BelongsTo { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
    public function activities(): HasMany { return $this->hasMany(ProspectActivity::class)->latest('occurred_at'); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class); }
    public function quotations(): HasMany { return $this->hasMany(Quotation::class); }
    public function deals(): HasMany { return $this->hasMany(Deal::class); }
    public function assignmentHistories(): HasMany { return $this->hasMany(ProspectAssignmentHistory::class)->latest(); }

    // IMPORTANT: migration creates `prospect_product`, while Laravel would infer
    // `product_prospect`. Being explicit here fixes the Prospek page SQL 500.
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'prospect_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('organization_id', $user->organization_id);
        if ($user->isAdmin()) return $query;
        return $query->where(fn (Builder $q) => $q
            ->where('assigned_to', $user->id)
            ->orWhere('created_by', $user->id));
    }

    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? ucfirst($this->status); }
    public function getPriorityLabelAttribute(): string { return self::PRIORITIES[$this->priority] ?? ucfirst($this->priority); }
    public function isClosed(): bool { return in_array($this->status, ['deal','ditolak','tidak_cocok'], true); }
    public function primaryProduct(): ?Product { return $this->products->firstWhere('pivot.is_primary', true) ?: $this->products->first(); }
}
