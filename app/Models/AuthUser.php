<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;

class AuthUser extends Authenticatable
{
    protected $fillable = [
        'unique_id',
        'name',
        'email',
        'phone',
        'user_type',
        'password',
        'verification_otp',
        'session_token',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'session_token'
    ];

    // Encrypt the session token before saving
    public function setSessionTokenAttribute($value)
    {
        $this->attributes['session_token'] = Crypt::encryptString($value);
    }

    // Decrypt the session token when accessing
    public function getSessionTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }
}