<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = [
        'name',
        'municipality',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrderedByName($query)
    {
        return $query->orderBy('name');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'farm_barangay_code', 'id');
    }

    /**
     * Barangay primary keys allowed for validation (string ids for form posts).
     *
     * @return list<string>
     */
    public static function allowedIds(?string $municipality = null): array
    {
        $q = static::query()->orderBy('name');
        if ($municipality !== null && trim($municipality) !== '') {
            $q->where('municipality', trim($municipality));
        }

        return $q->pluck('id')->map(static fn ($id): string => (string) $id)->all();
    }

    /**
     * @return list<string>
     */
    public static function municipalities(): array
    {
        return static::query()
            ->select('municipality')
            ->distinct()
            ->orderBy('municipality')
            ->pluck('municipality')
            ->all();
    }

    public static function nameForId(?string $id): ?string
    {
        if ($id === null || trim($id) === '') {
            return null;
        }

        $name = static::query()->whereKey($id)->value('name');

        return $name !== null && trim((string) $name) !== '' ? trim((string) $name) : null;
    }
}
