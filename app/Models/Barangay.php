<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Barangay extends Model
{
    /**
     * Cache key for the immutable `id => name` lookup map. Bumped when the row
     * count or the latest update changes (see `idToNameMapCacheKey()`), so any
     * write through Eloquent or migrations naturally invalidates the cache.
     */
    private const ID_NAME_MAP_CACHE_TTL_HOURS = 24;

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

        $map = static::idToNameMap();
        if (isset($map[$id])) {
            return $map[$id];
        }

        // Slow-path fallback (covers race between cache miss and a fresh insert
        // — the lookup map is then rebuilt on next request via TTL/version key).
        $name = static::query()->whereKey($id)->value('name');

        return $name !== null && trim((string) $name) !== '' ? trim((string) $name) : null;
    }

    /**
     * Cached `id => name` map for the entire barangays table.
     * Lets controllers replace per-row `nameForId()` lookups (N+1) with a single
     * in-memory hash lookup. Cache key embeds row count + latest `updated_at`
     * so the map is invalidated automatically when barangay rows change.
     *
     * @return array<string, string>
     */
    public static function idToNameMap(): array
    {
        return Cache::remember(
            static::idToNameMapCacheKey(),
            now()->addHours(self::ID_NAME_MAP_CACHE_TTL_HOURS),
            static function (): array {
                /** @var array<string, string> $map */
                $map = [];
                static::query()
                    ->select(['id', 'name'])
                    ->orderBy('name')
                    ->cursor()
                    ->each(static function ($row) use (&$map): void {
                        $name = trim((string) ($row->name ?? ''));
                        if ($name !== '') {
                            $map[(string) $row->id] = $name;
                        }
                    });

                return $map;
            }
        );
    }

    /**
     * Versioned cache key: changes naturally on insert/update/delete because
     * row count + latest timestamp shift, so we don't need explicit invalidation
     * after standard CRUD.
     */
    private static function idToNameMapCacheKey(): string
    {
        $version = (string) (static::query()->max('updated_at') ?? '0');
        $count = (string) static::query()->count();

        return 'barangays:id_name_map:v1:'.md5($version.'|'.$count);
    }
}
