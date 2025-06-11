<?php

namespace App\Services\Select\Parameter;
use Core\DB;

class ParameterSelectService
{
    public function getAllParameters(int $parameterId)
    {
        return DB::raw("SELECT id as value, parameter_value as label FROM parameter_values WHERE parameter_id = ? AND deleted_at IS NULL", [$parameterId]);
    }

}

