<?php

declare(strict_types=1);

use App\Application\Lab\LabService;
use App\Application\Shop\ShopService;
use App\Application\Student\StudentService;
use App\Infrastructure\Http\JsonResponder;
use App\Infrastructure\Persistence\PostgresRepository;
use App\Infrastructure\Persistence\SchemaMigrator;
use PDO;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$settings = require dirname(__DIR__) . '/config/settings.php';
$pdo = new PDO(
    $settings['database']['dsn'],
    $settings['database']['user'],
    $settings['database']['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);
SchemaMigrator::migrate($pdo, dirname(__DIR__) . '/migrations');
$repository = new PostgresRepository($pdo);
$lab = new LabService($repository);
$students = new StudentService($repository);
$shop = new ShopService($repository, lab: $lab);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$routes = require dirname(__DIR__) . '/config/routes.php';
$routes($app, $lab, $shop, $students, $settings['teacher_token']);

$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(static function (
    $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
) use ($app) {
    $status = $exception->getCode();
    if (!is_int($status) || $status < 400 || $status > 599) {
        $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    }
    $message = $status === 500 ? 'Внутренняя ошибка учебного стенда.' : $exception->getMessage();

    return JsonResponder::write($app->getResponseFactory()->createResponse(), [
        'error' => ['code' => 'request_failed', 'message' => $message],
    ], $status);
});

$app->add(static function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('X-Lab-Environment', 'intentionally-vulnerable')
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('Referrer-Policy', 'no-referrer');
});

$app->run();
