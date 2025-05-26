<?php

namespace Core;

use App\Models\User;
use Core\DB;

class Auth
{
    public static function user(): ?object
    {
        $payload = JWT::check();

        if (!$payload || !isset($payload->sub)) {
            return null;
        }

        // Assuming 'sub' is the user ID (as in your login token generation)
        $user = User::find($payload->sub);

        if ($user) {
            return $user; // Return the first user as an object
        }

        return null;
    }
}
