<?php

namespace App\Services\Select;
use Core\DB;

class RoleSelectService
{
    public function getAllRoles()
    {
        return DB::raw("SELECT id AS value, name as label FROM roles");
    }
}



