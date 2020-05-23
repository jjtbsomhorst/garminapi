<?php


namespace jsomhorst\garmin\Routes;
use dawguk\GarminConnect;
use MongoDB\Database;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use jsomhorst\garmin\Middleware\OAuth2MiddleWare;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class WorkoutApiHandler implements ApiHandlerInterface
{
    private $container;
    private $garminClient;
    private $database;

    public function __construct(ContainerInterface $container, GarminConnect $garmin, Database $database)
    {
        $this->container = $container;
        $this->garminClient = $garmin;
        $this->database = $database;
    }

    public static function addRoutes(App $app)
    {
        WorkoutApiHandler::addProtectedRoutes($app);
    }

    public function getWorkout(Request $request, Response $response, array $args) : Response{
        $collection = $this->database->selectCollection('workouts');
        $entry = $collection->findOne(['workoutId'=> (float)$args['id']]);
        $response->getBody()->write(json_encode($entry));
        return $response;
    }

    public function getWorkouts(Request $request, Response $response, array $args) : Response{
        $queryParams = $request->getQueryParams();
        $workoutCollection = $this->database->selectCollection('workouts');
        $activityCollection = $this->database->selectCollection('activities');
        $activities = $activityCollection->find(['workoutId'=> array('$ne'=>null)]);
        $workoutIds = [];
        $entries = $activities->toArray();
        foreach($entries as $entry){
            if(!array_key_exists($entry['workoutId'],$workoutIds)){
                array_push($workoutIds,$entry['workoutId']);
            }
        }

        $activities = $workoutCollection->find(['workoutId'=>['$in'=> $workoutIds]]);
        $response->getBody()->write(json_encode($activities->toArray()));
        return $response;
    }

    public static function addProtectedRoutes(App $app)
    {
        $app->group('/workouts',function(RouteCollectorProxy $group){
            $group->get('',self::class.":getWorkouts")->setName('getWorkouts');
            $group->get('/{id}',self::class.":getWorkout")->setName('getWorkout');
        })->addMiddleware($app->getContainer()->get(OAuth2MiddleWare::class));

    }
}