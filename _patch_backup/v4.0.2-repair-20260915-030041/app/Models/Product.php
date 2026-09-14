<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Product extends Model {
    protected $fillable=['organization_id','sku','name','variant','price','image_url','description','is_active'];
    protected function casts(): array { return ['price'=>'integer','is_active'=>'boolean']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function prospects(): BelongsToMany { return $this->belongsToMany(Prospect::class)->withPivot('is_primary')->withTimestamps(); }
}
