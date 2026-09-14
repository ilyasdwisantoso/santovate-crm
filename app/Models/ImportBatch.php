<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ImportBatch extends Model {
    use HasFactory;
    protected $fillable=['organization_id','filename','assignment_mode','total_rows','imported_rows','updated_rows','skipped_rows','error_rows','errors','assignment_summary','imported_by'];
    protected function casts(): array { return ['errors'=>'array','assignment_summary'=>'array']; }
    public function importer(): BelongsTo { return $this->belongsTo(User::class,'imported_by'); }
    public function prospects(): HasMany { return $this->hasMany(Prospect::class,'import_batch_id'); }
}
