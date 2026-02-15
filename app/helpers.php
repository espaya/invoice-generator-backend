<?php

use App\Models\CompanySetting;

if (!function_exists('company_name')) {
    function company_name()
    {
        return cache()->remember('company_name', 3600, function () {
            return CompanySetting::first()?->company_name ?? config('app.name');
        });
    }
}
