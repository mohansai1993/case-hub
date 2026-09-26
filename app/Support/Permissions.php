<?php

namespace App\Support;

/**
 * Read access to the permission catalogue in config/permissions.php.
 */
final class Permissions
{
    /** @return array<string, string> key => label */
    public static function all(): array
    {
        $all = [];

        foreach (config('permissions.modules', []) as $module) {
            $all += $module['permissions'];
        }

        return $all;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * Drops anything that is not in the catalogue, so a tampered form or a
     * stale key can never be persisted as a permission.
     *
     * @param  iterable<string>  $keys
     * @return list<string>
     */
    public static function only(iterable $keys): array
    {
        $valid = self::all();

        return array_values(array_unique(array_filter(
            [...$keys],
            fn ($key) => is_string($key) && isset($valid[$key]),
        )));
    }
}
