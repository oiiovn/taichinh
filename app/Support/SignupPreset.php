<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class SignupPreset
{
    public static function exists(string $key): bool
    {
        return array_key_exists($key, config('signup_presets.presets', []));
    }

    /** @return array<string, mixed> */
    public static function userAttributes(string $key): array
    {
        return config("signup_presets.presets.$key.attributes", []);
    }

    public static function label(string $key): string
    {
        return (string) config("signup_presets.presets.$key.label", $key);
    }

    public static function redirectAfterRegister(?string $key): string
    {
        if ($key !== null && self::exists($key)) {
            $name = config("signup_presets.presets.$key.redirect_route");
            if (is_string($name) && $name !== '' && Route::has($name)) {
                return route($name);
            }
        }

        return route('dashboard');
    }
}
