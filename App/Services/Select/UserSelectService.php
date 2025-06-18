<?php

namespace App\Services\Select;
use Core\DB;

class UserSelectService
{
    public function getAllOperators()
    {
        return DB::raw("SELECT id AS value, name as label FROM users WHERE deleted_at IS NULL");
    }
}



