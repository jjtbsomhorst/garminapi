<?php


namespace jsomhorst\garmin\middleware;


use dawguk\GarminConnect;
use jsomhorst\garmin\Logger;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Http\ServerRequest;
use Slim\Http\Response;

class ActivityApiHandler implements ApiHandlerInterface
{
    protected GarminConnect $garminClient;
    protected Database $database;
    protected $container = null;
    public function __construct(ContainerInterface $container, GarminConnect $garmin, Database $database)
    {
        $this->container = $container;
        $this->garminClient = $garmin;
        $this->database = $database;
    }


    public static function addRoutes(App $app)
    {
        $app->get('/activities',self::class.":handleActivityRequest")->setName('getActivities');
        $app->get('/activities/{id}',self::class.":handleActivityRequest")->setName('getActivity');
        $app->get('/activities/{id}/details',self::class.":handleActivityRequest")->setName('getActivityDetails');
        $app->get('/activities/{id}/summary',self::class.":handleActivityRequest")->setName('getActivitySummary');
        $app->get('/activities/{id}/splits',self::class.":handleActivityRequest")->setName('getActivitySplits');
        $app->get('/records',self::class.":handleRecordRequest");
        $app->get('/summary',self::class.":handleSummaryRequest");
        $app->get('/activities/compare/{ids}',self::class.":handleCompareRequest")->setName('getCompareMatrix');
    }

    public function getActivityDetails(ServerRequest $request, Response $response,array $args) : Response{

        $collection = $this->database->selectCollection('activitydetails');
        $activity = $collection->findOne(array('activityId'=>(int) $args['id']));
        if(empty($activity) || is_null($activity)){
            $details = $this->garminClient->getExtendedActivityDetails($args['id']);
            $collection->insertOne(json_decode($details,true));
            return $this->getActivityDetails($request,$response,$args);
        }else{
            $response->getBody()->write(json_encode($activity));

        }
        return $response;
    }

    private function getActivityCount(ServerRequest $request, Response $response, array $args)
    {
        $json = ['entrycount'=>$this->database->selectCollection('activities')->countDocuments()];
        $response->getBody()->write(json_encode($json));
        return $response;
    }

    private function getActivityList(ServerRequest $request, Response $response, array $args) : Response{
        $ActivityCollection = $this->database->selectCollection('activities');
        $splitCollection = $this->database->selectCollection('activitySplits');

        $queryParams = $request->getQueryParams();
        Logger::log()->debug(print_r($queryParams,true));
        $findParams = array();
        $splitParams = array();
        $options = array();
        if(array_key_exists('name',$queryParams)){
            $findParams['activityName'] = $queryParams['name'];
        }

        if(array_key_exists('type',$queryParams)){
            $findParams["activityType.typeId"] = (int)$queryParams['type'];
        }

        if(array_key_exists('limit',$queryParams)){
            $options['limit'] = (int) $queryParams['limit'];
        }

        if(array_key_exists('startDate',$queryParams)) {
            $findParams['startTimeLocal']['$gte'] = $queryParams['startDate'];
        }
        if(array_key_exists('endDate',$queryParams)){
            $findParams['startTimeLocal']['$lt'] = $queryParams['endDate'];
        }

        if(array_key_exists('minDistance',$queryParams)){
            $findParams['distance']['$gte'] = (int) $queryParams['minDistance'];
        }

        if(array_key_exists('maxDistance',$queryParams)){
            $findParams['distance']['$lte'] = (int) $queryParams['maxDistance'];
        }

        if(array_key_exists('workout',$queryParams)){
            $findParams['workoutId'] = (int) $queryParams['workout'];
        }

        if(array_key_exists('offset',$queryParams)){
            $options['skip'] = (int) $queryParams['offset'];
        }
        if(array_key_exists('sortBy',$queryParams) && array_key_exists('sortOrder',$queryParams)){
            $options['sort'] = [$queryParams['sortBy'] => (int)$queryParams['sortOrder']];
        }else{
            $options['sort'] = ['startTimeLocal'=>-1];
        }
        if(array_key_exists('parentType',$queryParams)){
            $findParams['activityType.parentTypeId'] = (int) $queryParams['parentType'];
        }

        if(array_key_exists('splits',$queryParams)){
            $splitParams['lapDTOs.distance']['$eq'] = (int) $queryParams['splits'];
        }
        if(!empty($splitParams)) {
            $splits = $splitCollection->find($splitParams, []);
            if (!empty($splits)) {

                foreach ($splits as $split) {
                    $findParams['activityId']['$in'][] = $split['activityId'];
                }
            }
        }
        Logger::log()->debug('Findparams: ');
        Logger::log()->debug(json_encode($findParams));
        Logger::log()->debug(json_encode($splitParams));
        $entries = $ActivityCollection->find($findParams,$options);

        $response->getBody()->write(json_encode($entries->toArray()));
        return $response;
    }

    public function handleActivityRequest(ServerRequest  $request, Response $response, array $args) : Response{
        if(array_key_exists('id',$args) && !empty($args['id'])){
            switch($args['id']) {
                case 'count':
                    return $this->getActivityCount($request, $response, $args);
                case 'types':
                    return $this->getActivityTypes($request,$response,$args);
                default:
                    if(substr($request->getUri()->getPath(),-6) == 'splits'){
                        return $this->getActivitySplits($request,$response,$args);
                    }

                    if(substr($request->getUri()->getPath(),-7) == 'details'){
                        return $this->getActivityDetails($request,$response,$args);
                    }
                    return $this->getActivitySummary($request,$response,$args);
            }
        }
        return $this->getActivityList($request,$response,$args);
    }

    public function handleSummaryRequest(ServerRequest $request, Response $response, array $args): Response{
        $collection = $this->database->selectCollection('WorkoutSummary');
        $summary = $collection->findOne([]);
        $response->getBody()->write(json_encode($summary));
        return $response;
    }

    private function getActivitySummary(ServerRequest $request, Response $response, array $args): Response{
        $collection = $this->database->selectCollection('activitySummary');
        $activity = $collection->findOne(array('activityId'=> (int) $args['id']));
        $response->getBody()->write(json_encode($activity));
        return $response;
    }

    private function getActivitySplits(ServerRequest $request, Response $response,array $args) : Response{
        $splits = $this->getSplits($args['id']);
        $response->getBody()->write(json_encode($splits));
        return $response;
    }

    private function getActivityTypes(ServerRequest $request, Response $response, array $args) : Response{
        $collection = $this->database->selectCollection('activities');
        $types = $collection->distinct('activityType');
        $response->getBody()->write(json_encode($types));
        return $response;
    }

    private function getSplits(int $activityId) : object{
        $collection = $this->database->selectCollection('activitySplits');
        $entry = $collection->findOne(array('activityId'=> $activityId));

        if(is_null($entry)){
            Logger::log()->debug(sprintf('Get splits for activity %s',$activityId));
            $this->garminClient->jsonDecode(true);
            $garminData = $this->garminClient->getActivitySplits($activityId);
            $this->garminClient->jsonDecode(false);
            $collection->insertOne($garminData);
        }
        return $collection->findOne(array('activityId'=>(int) $activityId));

    }

    public function handleCompareRequest(ServerRequest $request, Response $response, array $args){
        $activityIds = explode(";",$args['ids']);
        $output = [];
        $collection = $this->database->selectCollection('activities');
        $this->garminClient->jsonDecode(true);
        $maxSplitCount = 0;
        foreach($activityIds as $activity){
            $summary = $collection->findOne(array('activityId'=>(int) $activity));
            $splits = $this->getSplits((int)$activity);
            $output['activities'][] = array(
              'summary'=>$summary,
              'splits'=>$splits
            );

            if(count($splits->lapDTOs) > $maxSplitCount){
                $maxSplitCount = count($splits->lapDTOs);
            }
        }
        $output['maxsplits'] = $maxSplitCount;

        $response->getBody()->write(json_encode($output));
        return $response;
    }

    public function handleRecordRequest(ServerRequest $request, Response $response, array $args)
    {
        $records = $this->garminClient->getPersonalRecords();
        $response->getBody()->write($records);
        return $response;
    }

}