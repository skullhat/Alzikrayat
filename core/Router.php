<?php
declare(strict_types=1);

/**
 * Class Router
 *
 * A hand-written router. It stores the routes, turns route paths such
 * as "/photo/{id}" into regular expressions, finds the route that
 * matches the current request, and calls the controller method.
 *
 * Features:
 * - GET and POST routes.
 * - Static routes ("/photos") and dynamic routes ("/photo/{id}").
 * - Numeric parameters: a placeholder named "id" or ending in "Id"
 *   accepts only positive whole numbers, and is passed as an int.
 * - 404 Not Found for unknown paths.
 * - 405 Method Not Allowed when the path exists but the method is wrong.
 * - Works when the project is inside a sub-folder (for example
 *   http://localhost/project/).
 */
class Router
{
    /** HTTP methods that can be registered. */
    private const SUPPORTED_METHODS = ['GET', 'POST'];

    /**
     * Regex for numeric placeholders: 1 to 9 digits, no leading zero.
     * Nine digits keep the value inside the MySQL INT range.
     */
    private const NUMERIC_PARAMETER_REGEX = '[1-9][0-9]{0,8}';

    /** Regex for all other placeholders: any text without a slash. */
    private const TEXT_PARAMETER_REGEX = '[^/]+';

    /** Regex that finds placeholders such as {id} or {photoId}. */
    private const PLACEHOLDER_REGEX = '/(\{[a-zA-Z][a-zA-Z0-9]*\})/';

    /**
     * @var array<int, array{httpMethod: string, routePath: string, regexPattern: string,
     *      controllerName: string, actionName: string}> All registered routes, in order.
     */
    private array $registeredRoutes = [];

    /** @var string URL prefix of the application, for example "/project". Empty at web root. */
    private string $basePath;

    /** @var callable|null Function that prints the 404 page. Null means a simple built-in page. */
    private $notFoundHandler = null;

    /**
     * Creates the router.
     *
     * @param string $basePath URL prefix of the application, for example "/project".
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Registers one route.
     *
     * Example: $router->add('GET', '/photo/{id}', ['PhotoController', 'show']);
     *
     * @param string            $httpMethod       "GET" or "POST".
     * @param string            $routePath        Path pattern, for example "/photo/{id}".
     * @param array<int,string> $controllerAction Two items: controller class name and method name.
     * @return void
     * @throws InvalidArgumentException When the method or the action format is wrong.
     */
    public function add(string $httpMethod, string $routePath, array $controllerAction): void
    {
        $httpMethod = strtoupper($httpMethod);

        if (!in_array($httpMethod, self::SUPPORTED_METHODS, true)) {
            throw new InvalidArgumentException('Unsupported HTTP method: ' . $httpMethod);
        }

        if (count($controllerAction) !== 2
            || !is_string($controllerAction[0] ?? null)
            || !is_string($controllerAction[1] ?? null)) {
            throw new InvalidArgumentException('Route action must be [ControllerName, methodName].');
        }

        $this->registeredRoutes[] = [
            'httpMethod'     => $httpMethod,
            'routePath'      => $routePath,
            'regexPattern'   => $this->buildRegexPattern($routePath),
            'controllerName' => $controllerAction[0],
            'actionName'     => $controllerAction[1],
        ];
    }

    /**
     * Short form of add() for GET routes.
     *
     * @param string            $routePath        Path pattern.
     * @param array<int,string> $controllerAction [ControllerName, methodName].
     * @return void
     */
    public function get(string $routePath, array $controllerAction): void
    {
        $this->add('GET', $routePath, $controllerAction);
    }

    /**
     * Short form of add() for POST routes.
     *
     * @param string            $routePath        Path pattern.
     * @param array<int,string> $controllerAction [ControllerName, methodName].
     * @return void
     */
    public function post(string $routePath, array $controllerAction): void
    {
        $this->add('POST', $routePath, $controllerAction);
    }

    /**
     * Sets the function that prints the 404 page.
     *
     * @param callable $notFoundHandler Function with no parameters.
     * @return void
     */
    public function setNotFoundHandler(callable $notFoundHandler): void
    {
        $this->notFoundHandler = $notFoundHandler;
    }

    /**
     * Finds the route for the current request and runs it.
     *
     * Steps:
     * 1. Clean the request path and remove the base path.
     * 2. Test each route regex in the order the routes were added.
     * 3. If the path and the method match, call the controller.
     * 4. If only the path matches, answer 405. If nothing matches, answer 404.
     *
     * @param string $httpMethod Request method from $_SERVER['REQUEST_METHOD'].
     * @param string $requestUri Request URI from $_SERVER['REQUEST_URI'].
     * @return void
     * @throws RuntimeException When a matched controller or method does not exist.
     */
    public function dispatch(string $httpMethod, string $requestUri): void
    {
        $httpMethod = strtoupper($httpMethod);

        // Browsers and tools send HEAD to check a page. It is answered like GET.
        if ($httpMethod === 'HEAD') {
            $httpMethod = 'GET';
        }

        $requestPath = $this->extractRequestPath($requestUri);
        $methodsAllowedForPath = [];

        foreach ($this->registeredRoutes as $route) {
            if (preg_match($route['regexPattern'], $requestPath, $regexMatches) !== 1) {
                continue;
            }

            if ($route['httpMethod'] !== $httpMethod) {
                $methodsAllowedForPath[] = $route['httpMethod'];
                continue;
            }

            $routeParameters = $this->collectRouteParameters($regexMatches);
            $this->callControllerAction($route['controllerName'], $route['actionName'], $routeParameters);

            return;
        }

        if ($methodsAllowedForPath !== []) {
            $this->sendMethodNotAllowed(array_values(array_unique($methodsAllowedForPath)));

            return;
        }

        $this->sendNotFound();
    }

    /**
     * Sends a 404 response. Controllers can also call this, for example
     * when a photo id does not exist in the database.
     *
     * @return void
     */
    public function sendNotFound(): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }

        if ($this->notFoundHandler !== null) {
            call_user_func($this->notFoundHandler);

            return;
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Page not found</title></head>'
            . '<body><h1>404 - Page not found</h1><p>The page you asked for does not exist.</p></body></html>';
    }

    /**
     * Finds the URL prefix of the application from the server values.
     *
     * Two setups are supported:
     * - The browser opens the public folder directly
     *   (http://localhost/project/public/photos). The prefix is "/project/public".
     * - A root .htaccess forwards requests into public/
     *   (http://localhost/project/photos). The prefix is "/project".
     *
     * @param string $scriptName Value of $_SERVER['SCRIPT_NAME'], for example "/project/public/index.php".
     * @param string $requestUri Value of $_SERVER['REQUEST_URI'].
     * @return string The prefix without a trailing slash. Empty when the app is at the web root.
     */
    public static function detectBasePath(string $scriptName, string $requestUri): string
    {
        // Windows servers may use backslashes in paths.
        $scriptFolder = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? $requestPath : '/';

        if ($scriptFolder === '' || self::pathStartsWith($requestPath, $scriptFolder)) {
            return $scriptFolder;
        }

        // The visible URL has no "/public" part, so the prefix is the parent folder.
        if (substr($scriptFolder, -7) === '/public') {
            $parentFolder = substr($scriptFolder, 0, -7);

            if ($parentFolder === '' || self::pathStartsWith($requestPath, $parentFolder)) {
                return $parentFolder;
            }
        }

        return $scriptFolder;
    }

    /**
     * Turns a route path into a full regular expression.
     *
     * The path is split into static text and placeholders. Static text is
     * escaped with preg_quote(), so characters like "." have no special
     * meaning. Each placeholder becomes a named group.
     * Example: "/photo/{id}/delete" becomes "#^/photo/(?P<id>[1-9][0-9]{0,8})/delete$#".
     *
     * @param string $routePath Path pattern, for example "/photo/{id}".
     * @return string Regular expression with start (^) and end ($) anchors.
     */
    private function buildRegexPattern(string $routePath): string
    {
        $pathParts = preg_split(
            self::PLACEHOLDER_REGEX,
            $this->normalizePath($routePath),
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $regexBody = '';
        foreach ($pathParts as $pathPart) {
            if (preg_match('/^\{([a-zA-Z][a-zA-Z0-9]*)\}$/', $pathPart, $nameMatch) === 1) {
                $parameterName = $nameMatch[1];
                $regexBody .= '(?P<' . $parameterName . '>' . $this->getParameterRegex($parameterName) . ')';
            } else {
                $regexBody .= preg_quote($pathPart, '#');
            }
        }

        return '#^' . $regexBody . '$#';
    }

    /**
     * Chooses the regex for one placeholder by its name.
     *
     * @param string $parameterName Placeholder name without braces.
     * @return string Numeric regex for "id" or names ending in "Id"; text regex otherwise.
     */
    private function getParameterRegex(string $parameterName): string
    {
        if ($parameterName === 'id' || substr($parameterName, -2) === 'Id') {
            return self::NUMERIC_PARAMETER_REGEX;
        }

        return self::TEXT_PARAMETER_REGEX;
    }

    /**
     * Gets a clean path from the request URI and removes the base path.
     *
     * The query string is dropped, %-codes are decoded, and a trailing
     * slash is removed, so "/photos/?page=2" becomes "/photos".
     *
     * @param string $requestUri Raw request URI.
     * @return string Clean path that always starts with "/".
     */
    private function extractRequestPath(string $requestUri): string
    {
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

        if ($this->basePath !== '' && self::pathStartsWith($requestPath, $this->basePath)) {
            $requestPath = substr($requestPath, strlen($this->basePath));
        }

        return $this->normalizePath($requestPath);
    }

    /**
     * Makes paths uniform: one leading slash, no trailing slash, no double slashes.
     *
     * @param string $path Any path.
     * @return string Normalized path, "/" for an empty path.
     */
    private function normalizePath(string $path): string
    {
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return '/' . trim($path, '/');
    }

    /**
     * Checks if a path is equal to a prefix or starts with "prefix/".
     *
     * "/project" matches "/project" and "/project/photos",
     * but not "/projectOld".
     *
     * @param string $path   The full path.
     * @param string $prefix The prefix to look for.
     * @return bool True when the path is inside the prefix.
     */
    private static function pathStartsWith(string $path, string $prefix): bool
    {
        return $path === $prefix || strpos($path, $prefix . '/') === 0;
    }

    /**
     * Keeps only the named groups from preg_match() results.
     * Digit-only values are converted to int.
     *
     * @param array<int|string, string> $regexMatches Result array of preg_match().
     * @return array<string, int|string> Parameter name => value.
     */
    private function collectRouteParameters(array $regexMatches): array
    {
        $routeParameters = [];

        foreach ($regexMatches as $matchKey => $matchValue) {
            if (is_string($matchKey)) {
                $routeParameters[$matchKey] = ctype_digit($matchValue) ? (int) $matchValue : $matchValue;
            }
        }

        return $routeParameters;
    }

    /**
     * Creates the controller and calls the action with the route parameters.
     *
     * @param string                    $controllerName  Class name, for example "PhotoController".
     * @param string                    $actionName      Method name, for example "show".
     * @param array<string, int|string> $routeParameters Values taken from the URL, in path order.
     * @return void
     * @throws RuntimeException When the class or the public method does not exist.
     */
    private function callControllerAction(string $controllerName, string $actionName, array $routeParameters): void
    {
        if (!class_exists($controllerName)) {
            throw new RuntimeException('Controller class was not found: ' . $controllerName);
        }

        $controller = new $controllerName();

        if (!$controller instanceof Controller) {
            throw new RuntimeException($controllerName . ' must extend the Controller base class.');
        }

        if (!is_callable([$controller, $actionName])) {
            throw new RuntimeException('Action was not found: ' . $controllerName . '@' . $actionName);
        }

        // Parameters are passed by position, in the order they appear in the path.
        $controller->$actionName(...array_values($routeParameters));
    }

    /**
     * Sends a 405 response with the Allow header.
     *
     * @param array<int, string> $allowedMethods Methods that exist for this path.
     * @return void
     */
    private function sendMethodNotAllowed(array $allowedMethods): void
    {
        if (!headers_sent()) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Method not allowed</title></head>'
            . '<body><h1>405 - Method not allowed</h1><p>This page does not accept this type of request.</p></body></html>';
    }
}
