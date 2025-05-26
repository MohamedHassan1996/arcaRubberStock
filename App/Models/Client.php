<?php 

//require_once __DIR__ . '/../../vendor/autoload.php';

namespace App\Models;

class Client extends Model {
        protected static $fillable = [
        'name',
        'phone',
        'city'
    ];
}