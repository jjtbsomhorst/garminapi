<?php

use DI\Container;
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
$container = new Container();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath("/garmin");
$container = $app->getContainer();
$container->set(/**
 * @param ContainerInterface $c
 * @return GarminConnect
 */ 'GarminConnect',function(ContainerInterface $c){

});

$container->set('Mongo',function(ContainerInterface $c) : MongoDB\Database{
    $client = new MongoDB\Client("mongodb://localhost:27017");
    return $client->selectDatabase('garmin');
});


// Add error middleware
$app->addErrorMiddleware(true, true, true);

//TODO Disable on production!!
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Content-type',' application/json');
});



//$app->get('/workouts',function(Request $request, Response $response,$args){
//
//})->setName('getWorkouts');
//
//$app->get('/workouts/{id}',function(Request $request, Response $response, $args){
//
//    $database = $this->get('Mongo');
//    $collection = $database->selectCollection('workouts');
//    $entry = $collection->findOne(['workoutId'=> (float)$args['id']]);
//    $response->getBody()->write(json_encode($entry));
//    return $response;
//})->setName('getWorkout');

ActivityApiHandler::addRoutes($app);
UserApiHandler::addRoutes($app);
WorkoutApiHandler::addRoutes($app);
StatisticsApiHandler::addRoutes($app);
$app->run();
