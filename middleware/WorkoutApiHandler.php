<?php


namespace jsomhorst\garmin\middleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\App;

class WorkoutApiHandler extends ActivityApiHandler implements ApiHandlerInterface
{



    public static function addRoutes(App $app)
    {
        // TODO: Implement addRoutes() method.
        $app->get('/workouts',self::class.":getWorkouts")->setName('getWorkouts');
        $app->get('/workouts/{id}',self::class.":getWorkout")->setName('getWorkout');
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
}