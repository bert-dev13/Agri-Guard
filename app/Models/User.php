<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'farm_barangay_code',
        'farm_lat',
        'farm_lng',
        'gps_captured_at',
        'location_source',
        'crop_type',
        'farming_stage',
        'planting_date',
        'crop_timeline_offset_days',
        'crop_stage_reality_check',
        'reality_check_answered',
        'reality_check_status',
        'stage_confirmed_at',
        'farm_area',
        'field_condition',
        'role',
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
        'password_reset_code',
    ];

    /**
     * Default attribute values for new instances (verification_attempts must not read as null).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'verification_attempts' => 0,
        'password_reset_attempts' => 0,
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
            'gps_captured_at' => 'datetime',
            'password' => 'hashed',
            'farm_area' => 'decimal:2',
            'crop_timeline_offset_days' => 'integer',
            'verification_attempts' => 'integer',
            'password_reset_expires_at' => 'datetime',
            'password_reset_locked_until' => 'datetime',
            'password_reset_attempts' => 'integer',
            'reality_check_answered' => 'boolean',
            'stage_confirmed_at' => 'datetime',
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

    public function hasPendingPasswordReset(): bool
    {
        return $this->password_reset_code !== null;
    }

    public function isPasswordResetLocked(): bool
    {
        if ($this->password_reset_locked_until === null) {
            return false;
        }

        return $this->password_reset_locked_until->isFuture();
    }

    /**
     * Farm barangay is stored as the stringified primary key of barangays (column farm_barangay_code).
     *
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'farm_barangay_code', 'id');
    }

    public function getFarmBarangayNameAttribute(): string
    {
        $code = trim((string) ($this->attributes['farm_barangay_code'] ?? ''));
        if ($code !== '' && ctype_digit($code)) {
            $fromDb = Barangay::nameForId($code);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        $storedName = trim((string) ($this->attributes['farm_barangay'] ?? ''));
        if ($storedName !== '') {
            return $storedName;
        }

        if ($code !== '') {
            $fromDb = Barangay::nameForId($code);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        return '—';
    }

    public function getFarmLocationDisplayAttribute(): string
    {
        $mun = trim((string) ($this->attributes['farm_municipality'] ?? ''));
        $barangayRow = null;
        $code = trim((string) ($this->attributes['farm_barangay_code'] ?? ''));
        if ($code !== '' && ctype_digit($code)) {
            $barangayRow = Barangay::query()->find($code);
        }
        if ($barangayRow !== null) {
            $mun = trim($mun) !== '' ? $mun : $barangayRow->municipality;
            $name = $barangayRow->name;

            return 'Barangay '.$name.', '.$mun.', Cagayan';
        }

        $name = trim((string) ($this->attributes['farm_barangay'] ?? ''));
        $mun = $mun !== '' ? $mun : 'Amulung';
        if ($name !== '') {
            return 'Barangay '.$name.', '.$mun.', Cagayan';
        }

        return $mun.', Cagayan';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFarmer(): bool
    {
        return ! $this->isAdmin();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFarmers($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('role')->orWhere('role', '!=', 'admin');
        });
    }
}
