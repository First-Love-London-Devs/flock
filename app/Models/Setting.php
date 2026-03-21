<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description'];

    protected static ?array $cache = null;

    public static function get(string $key, $default = null)
    {
        $settings = static::loadAll();

        if (!isset($settings[$key])) {
            return $default;
        }

        $setting = $settings[$key];

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function set(string $key, $value, string $type = 'string'): void
    {
        $storeValue = is_array($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storeValue, 'type' => $type]
        );

        static::$cache = null;
    }

    public static function loadAll(): array
    {
        if (static::$cache === null) {
            try {
                static::$cache = static::all()->keyBy('key')->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        return static::$cache;
    }

    public static function clearCache(): void
    {
        static::$cache = null;
    }
}
