<?php

namespace App\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-aside helper for the CRM's expensive reads.
 *
 * Everything is written through a tag so a single mutation can invalidate a
 * whole family of keys — no stale pipeline totals after a deal moves stage.
 */
final class CrmCache
{
    public const TAG_DASHBOARD = 'crm:dashboard';

    public const TAG_REFERENCE = 'crm:reference';

    public const TAG_DEALS = 'crm:deals';

    /** Short TTLs for list/aggregate data, longer for slow-moving reference data. */
    public const TTL_STATS = 60;

    public const TTL_REFERENCE = 600;

    public static function remember(array $tags, string $key, int $ttl, Closure $callback): mixed
    {
        if (! self::supportsTags()) {
            return Cache::remember($key, $ttl, $callback);
        }

        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    public static function flush(string ...$tags): void
    {
        if (! self::supportsTags()) {
            Cache::flush();

            return;
        }

        Cache::tags($tags)->flush();
    }

    /** Everything a write to a CRM record can invalidate. */
    public static function flushAll(): void
    {
        self::flush(self::TAG_DASHBOARD, self::TAG_REFERENCE, self::TAG_DEALS);
    }

    private static function supportsTags(): bool
    {
        try {
            return Cache::getStore() instanceof TaggableStore;
        } catch (\Throwable) {
            return false;
        }
    }
}
