<?php 

namespace App\Middleware;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Core\MiddlewareInterface;
use Core\JWT;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (!JWT::check()) {
            http_response_code(HttpStatusCode::UNAUTHORIZED->value);
            echo ApiResponse::error('Unauthorized');
            return false;
        }

        return true;
    }
}
