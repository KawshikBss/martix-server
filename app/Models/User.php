<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getUserImageUrl()
    {
        return $this->image ? env('APP_URL') . '/storage/' . $this->image : null;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function assignRole($roleId)
    {
        $this->role_id = $roleId;
        $this->save();
    }

    public function removeRole()
    {
        $this->role_id = null;
        $this->save();
    }

    public function hasPermission($permissionName)
    {
        if ($this->role) {
            return $this->role->permissions()->where('name', $permissionName)->exists();
        }
        return false;
    }
}
