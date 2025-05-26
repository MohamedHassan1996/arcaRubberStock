<?php

namespace App\Helpers;

use App\Enums\HttpStatusCode;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = '', HttpStatusCode $status = HttpStatusCode::OK)
    {
        http_response_code($status->value);
        header('Content-Type: application/json');
        return json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ]);
    }

    public static function error(string $message = '', mixed $errors = [], HttpStatusCode $status = HttpStatusCode::UNAUTHORIZED)
    {
        http_response_code($status->value);
        header('Content-Type: application/json');
        return json_encode([
            'success' => false,
            'message' => $message,
            'errors'  => $errors
        ]);
    }
}
