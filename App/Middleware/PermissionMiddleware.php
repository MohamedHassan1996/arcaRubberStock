<?php 

namespace App\Middleware;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Core\Auth;
use Core\MiddlewareInterface;
use Core\JWT;

class PermissionMiddleware implements MiddlewareInterface
{
    protected string $requiredPermission;

    public function __construct(string $requiredPermission)
    {
        $this->requiredPermission = $requiredPermission;
    }

    public function handle(): bool
    {
        $user = Auth::user();

        if (!$user) {
            http_response_code(HttpStatusCode::UNAUTHORIZED->value);
            echo ApiResponse::error('Unauthorized');
            return false;
        }

        foreach ($user->permissions as $permission) {
            if (
                $permission['permissionName'] === $this->requiredPermission &&
                $permission['access'] === true
            ) {
                return true;
            }
        }

        // Permission not found or not granted
        http_response_code(HttpStatusCode::FORBIDDEN->value);
        echo ApiResponse::error('you don\'t have permission');
        return false;
    }
}
