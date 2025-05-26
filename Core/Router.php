<?php


namespace Core;

class Router
{
    protected array $routes = [];
    protected $fallback;

        protected array $route_params = [];  // Add this property


    public function get(string $uri, $callback): void
    {
        $this->addRoute('GET', $uri, $callback);
    }

    public function post(string $uri, $callback): void
    {
        $this->addRoute('POST', $uri, $callback);
    }

    public function put(string $uri, $callback): void
    {
        $this->addRoute('PUT', $uri, $callback);
    }

    public function patch(string $uri, $callback): void
    {
        $this->addRoute('PATCH', $uri, $callback);
    }

    public function delete(string $uri, $callback): void
    {
        $this->addRoute('DELETE', $uri, $callback);
    }

    public function fallback(callable $callback): void
    {
        $this->fallback = $callback;
    }

    protected function addRoute(string $method, string $uri, $callback): void
    {
        $uri = trim($uri, '/');

        // Convert `{param}` to named regex group `(?P<param>[^/]+)`
        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $uri);

        $pattern = '#^' . $pattern . '$#';

        $this->routes[$method][$pattern] = $callback;

    }

    public function dispatch(string $url): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = trim(parse_url($url, PHP_URL_PATH), '/');

        // Remove base folder if needed (e.g. "arcaRubberStock")
        $base = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($path, $base)) {
            $path = trim(substr($path, strlen($base)), '/');
        }
        foreach ($this->routes[$method] ?? [] as $pattern => $callback) {
            if (preg_match($pattern, $path, $matches)) {
                // Extract only named capture groups
                $this->route_params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);


                // If the callback is a controller@method
                if (is_string($callback)) {
                    [$controller, $method] = explode('@', $callback);
                    $controller = 'App\\Controllers\\' . $controller;

                    if (!class_exists($controller)) {
                        http_response_code(500);
                        echo "Controller '$controller' not found";
                        return;
                    }

                     if (!\Core\MiddlewareManager::runMiddlewares($controller, $method)) {
                            return; // Middleware failed, response already sent
                    }


                    // Pass the route parameters to controller constructor
                    $instance = new $controller($this->route_params);

                    if (!method_exists($instance, $method)) {
                        http_response_code(500);
                        echo "Method '$method' not found in controller '$controller'";
                        return;
                    }

                    echo call_user_func_array([$instance, $method], array_values($this->route_params));
                    return;
                }

                // If the callback is a closure
                if (is_callable($callback)) {
                    call_user_func_array($callback, $this->route_params);
                    return;
                }

                http_response_code(500);
                echo 'Invalid route callback.';
                return;
            }
        }

        // Fallback if no match
        if (is_callable($this->fallback)) {
            call_user_func($this->fallback);
        } else {
            http_response_code(404);
            echo '404 Not Found';
        }
    }
}
