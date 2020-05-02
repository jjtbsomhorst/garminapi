<?php

use DI\Container;
use DI\ContainerBuilder;
use jsomhorst\garmin\middleware\ActivityApiHandler;
use jsomhorst\garmin\middleware\StatisticsApiHandler;
use jsomhorst\garmin\middleware\UserApiHandler;
use jsomhorst\garmin\middleware\WorkoutApiHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use dawguk\GarminConnect;

require __DIR__ . '/../vendor/autoload.php';

// Instantiate App
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/container.php');

AppFactory::setContainer($containerBuilder->build());
$app = AppFactory::create();

$app->setBasePath("/garmin");


// Add error middleware
$app->addErrorMiddleware(true, true, true);


$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Content-type',' application/json');
});


ActivityApiHandler::addRoutes($app);
UserApiHandler::addRoutes($app);
WorkoutApiHandler::addRoutes($app);
StatisticsApiHandler::addRoutes($app);
$app->run();
