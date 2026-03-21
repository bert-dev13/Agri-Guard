<?php

namespace App\Models;

use App\Support\PsgcBarangayResolver;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'farm_municipality',
        'farm_barangay',
        'farm_lat',
        'farm_lng',
        'crop_type',
        'farming_stage',
        'planting_date',
        'farm_area',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
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
            'email_verification_expires_at' => 'datetime',
            'verification_locked_until' => 'datetime',
            'planting_date' => 'date',
            'password' => 'hashed',
            'farm_area' => 'decimal:2',
        ];
    }

    /**
     * Whether the user has a pending email verification (code sent, not yet verified).
     */
    public function hasPendingEmailVerification(): bool
    {
        return $this->email_verification_code !== null
            && $this->email_verified_at === null;
    }

    /**
     * Whether verification attempts are temporarily locked (e.g. after too many failures).
     */
    public function isVerificationLocked(): bool
    {
        if ($this->verification_locked_until === null) {
            return false;
        }
        return $this->verification_locked_until->isFuture();
    }

    public function getFarmBarangayNameAttribute(): string
    {
        return PsgcBarangayResolver::resolveName($this->farm_barangay) ?? '—';
    }

    public function getFarmLocationDisplayAttribute(): string
    {
        return PsgcBarangayResolver::formatFarmLocation($this->farm_barangay, $this->farm_municipality);
    }
}
