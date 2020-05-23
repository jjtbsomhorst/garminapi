<?php

use DI\ContainerBuilder;
use jsomhorst\garmin\Routes\ActivityApiHandler;
use jsomhorst\garmin\Routes\OAuthRoutes;
use jsomhorst\garmin\Routes\StatisticsApiHandler;
use jsomhorst\garmin\Routes\UserApiHandler;
use jsomhorst\garmin\Routes\WorkoutApiHandler;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Instantiate App
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions('config/container.php');

AppFactory::setContainer($containerBuilder->build());
$app = AppFactory::create();

$app->setBasePath("/garmin");


// Add error middleware
$app->addErrorMiddleware(true, true, true);
$app->addMiddleware(new \jsomhorst\garmin\Middleware\CorsMiddleWare());

ActivityApiHandler::addRoutes($app);
UserApiHandler::addRoutes($app);
WorkoutApiHandler::addRoutes($app);
StatisticsApiHandler::addRoutes($app);
OAuthRoutes::addRoutes($app);
$app->run();
