<?php

namespace App\Helpers;

use App\Models\AuthUser;

class UserHelper
{
    public static function generateUniqueId()
    {
        $prefix = 'SAK';
        $suffix = 'US';

        $latest = AuthUser::latest('id')->first();
        $number = $latest ? (int) preg_replace('/[^0-9]/', '', $latest->unique_id) + 1 : 1;

        // Format number with padding
        $formattedNumber = str_pad($number, 5, '0', STR_PAD_LEFT);

        return "{$prefix}{$formattedNumber}{$suffix}";
    }
}