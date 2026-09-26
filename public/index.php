<?php
declare(strict_types=1);

/**
 * Front controller of Alzikrayat.
 *
 * Every request (except real files such as CSS, JS, and images) comes
 * to this file through the .htaccess rewrite rules. This file:
 * 1. Sets error reporting and the time zone.
 * 2. Registers the class autoloader.
 * 3. Starts a secure session.
 * 4. Registers all routes by hand.
 * 5. Sends the request to the Router.
 */

/** Full path of the project folder (the parent of public/). */
define('ROOT_PATH', dirname(__DIR__));

/**
 * Debug mode. When true, error details are shown on screen.
 * Set it to false before the final demo.
 */
define('APP_DEBUG', true);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// One fixed time zone for the whole app. The database connection uses the same offset.
date_default_timezone_set('Africa/Khartoum');

require_once ROOT_PATH . '/config/database.php';

/*
 * Class autoloader: loads a class file the first time the class is used.
 * It looks in core/, controllers/, and models/ for "ClassName.php".
 * Only simple class names are accepted, so a name cannot point outside
 * these folders.
 */
spl_autoload_register(static function (string $className): void {
    if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $className) !== 1) {
        return;
    }

    foreach (['core', 'controllers', 'models'] as $folderName) {
        $classFilePath = ROOT_PATH . '/' . $folderName . '/' . $className . '.php';

        if (is_file($classFilePath)) {
            require_once $classFilePath;

            return;
        }
    }
});

/** URL prefix of the app, for example "/project". Views use it to build links. */
define('APP_BASE_PATH', Router::detectBasePath(
    (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'),
    (string) ($_SERVER['REQUEST_URI'] ?? '/')
));

/** True when the site is opened with HTTPS. Cookies get the Secure flag only then. */
define('APP_IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443');

// Secure session settings.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ALZIKRAYAT_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/',
    'secure'   => APP_IS_HTTPS,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Route registration. Each line: HTTP method, path pattern, [Controller, method].
$router = new Router(APP_BASE_PATH);

$router->add('GET', '/', ['PhotoController', 'home']);
$router->add('GET', '/about', ['PhotoController', 'about']);
$router->add('GET', '/photos', ['PhotoController', 'index']);
$router->add('GET', '/photos/create', ['PhotoController', 'create']);
$router->add('POST', '/photos', ['PhotoController', 'store']);
$router->add('GET', '/photo/{id}', ['PhotoController', 'show']);
$router->add('POST', '/photo/{id}/delete', ['PhotoController', 'delete']);
$router->add('GET', '/photos/tagged/{userId}', ['PhotoController', 'tagged']);
$router->add('POST', '/photo/{id}/tags/{userId}/delete', ['PhotoController', 'removeTag']);

$router->add('GET', '/login', ['AuthController', 'showLogin']);
$router->add('POST', '/login', ['AuthController', 'login']);
$router->add('GET', '/register', ['AuthController', 'showRegister']);
$router->add('POST', '/register', ['AuthController', 'register']);
$router->add('POST', '/logout', ['AuthController', 'logout']);

$router->add('POST', '/photo/{id}/comments', ['CommentController', 'store']);

// Unknown paths show the styled 404 page inside the normal layout.
$router->setNotFoundHandler(static function (): void {
    (new PhotoController())->notFound();
});

try {
    $router->dispatch(
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        (string) ($_SERVER['REQUEST_URI'] ?? '/')
    );
} catch (Throwable $unexpectedError) {
    // The full error goes to the PHP error log. The user sees a safe message.
    error_log(sprintf(
        '[Alzikrayat] %s in %s on line %d',
        $unexpectedError->getMessage(),
        $unexpectedError->getFile(),
        $unexpectedError->getLine()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Server error</title></head><body>'
        . '<h1>500 - Something went wrong</h1><p>Please try again later.</p>';

    if (APP_DEBUG) {
        echo '<p><strong>Debug:</strong> '
            . htmlspecialchars($unexpectedError->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    echo '</body></html>';
}
