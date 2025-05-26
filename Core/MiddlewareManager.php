<?php
    
namespace Core;

use Core\Contracts\HasMiddleware;

class MiddlewareManager
{
    public static function runMiddlewares(string $controller, string $action): bool
    {
        if (!is_subclass_of($controller, HasMiddleware::class)) {
            return true;
        }

        $middlewareList = $controller::middleware();

        foreach ($middlewareList as $middleware) {
            if (!$middleware instanceof Middleware) continue;
            if (!$middleware->appliesTo($action)) continue;

            $parts = explode(':', $middleware->name, 2);
            $type = $parts[0];
            $value = $parts[1] ?? null;

            $middlewareInstance = match ($type) {
                'auth'       => new \App\Middleware\AuthMiddleware(),
                'permission' => new \App\Middleware\PermissionMiddleware($value),
                default      => null
            };

            if ($middlewareInstance && !$middlewareInstance->handle()) {
                return false; // stop if middleware fails
            }
        }

        return true;
    }
}
