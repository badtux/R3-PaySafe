<?php
class Router
{
    private $routes = [];
    private $notFoundCallback;

    public function addRoute($method, $path, $callback)
    {
        $this->routes[] = [
            'method' => $method,
            'path'   => $path,
            'callback' => $callback
        ];
    }

    public function setNotFound($callback)
    {
        $this->notFoundCallback = $callback;
    }

    public function handleRequest()
    {
        $requestedPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // normalize: strip trailing slash and ".php"
        $requestedPath = rtrim($requestedPath, '/');
        $requestedPath = preg_replace('/\.php$/', '', $requestedPath);

        $requestMethod = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $route['path'] === $requestedPath) {
                call_user_func($route['callback']);
                return;
            }
        }

        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        }
    }
}
