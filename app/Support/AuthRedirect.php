<?php

namespace App\Support;

use App\Models\User;

class AuthRedirect
{
    /**
     * Devuelve la URL adecuada tras login/register según el rol.
     */
    public static function for(?User $user): string
    {
        if (! $user) {
            return '/';
        }

        if ($user->hasAnyRole(['admin', 'vendedor'])) {
            return '/admin';
        }

        return '/';
    }
}
