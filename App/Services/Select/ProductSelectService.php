<?php

namespace App\Services\Select;
use Core\DB;

class ProductSelectService
{
    public function getAllProducts()
    {
        return DB::raw("SELECT id AS value, name as label FROM products WHERE deleted_at IS NULL");
    }
}



