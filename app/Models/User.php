<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name','email','password','role','is_active','last_login_at'];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean','last_login_at'=>'datetime'];
    }

    public function assignedProspects(): HasMany { return $this->hasMany(Prospect::class, 'assigned_to'); }
    public function createdProspects(): HasMany { return $this->hasMany(Prospect::class, 'created_by'); }
    public function salesTargets(): HasMany { return $this->hasMany(SalesTarget::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
}
