<?php

if (!function_exists('user_has_feature')) {
    function user_has_feature(string $key): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasAnyRole(['superadmin', 'admin', 'developer'])) return true;
        return $user->hasFeature($key);
    }
}
