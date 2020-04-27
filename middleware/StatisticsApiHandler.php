<?php


namespace jsomhorst\garmin\middleware;


use DateTime;
use Slim\App;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class StatisticsApiHandler extends ActivityApiHandler
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function addRoutes(App $app)
    {
        $app->get('/summary/calories',self::class.":getTotalCalories");
        $app->get('/summary/distance',self::class.":getTotalDistance");
        $app->get('/summary/duration',self::class.":getTotalDuration");
        $app->get('/summary/avgHeartRate',self::class.":getAvgSpeed");
        $app->get('/summary/avgSpeed',self::class.":getAvgHeartRate");
        $app->get('/summary/avgPace',self::class.":getAvgHeartRate");
        $app->get('/summary/avgMovingSpeed',self::class.":getAvgMovingSpeed");
        $app->get('/summary/sph',__CLASS__.":getSph");
    }

    public function getTotals(ServerRequest $request, Response $response, array $args,$property) : Response
    {
        $queryParams = $request->getQueryParams();
        $collection = $this->database->selectCollection('activities');
        $findParams = [];

        if (array_key_exists('type', $queryParams)) {
            $findParams['activityType.typeId'] = $args['type'];
        }

        if (!array_key_exists('periods', $queryParams)) {
            $period = 4;
        } else {
            $period = (int)$queryParams['periods'];
        }

        $interval = $this->getInterval($queryParams, $period);
        $enddate = new \DateTime('NOW');
        $startDate = new \DateTime('NOW');
        $startDate = $startDate->sub($interval);

        $findParams['startTimeLocal']['$gte'] = $startDate->format('Y-m-d');
        $findParams['startTimeLocal']['$lt'] = $enddate->format('Y-m-d');

        $results = $collection->find($findParams, array('sort' => array('startTimeLocal' => -1)))->toArray();
        $groupedResults = array();
        foreach ($results as $row) {
            $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $row['startTimeGMT']);
            $key = self::getKeyFromDate($startTime, $queryParams['groupby']);
            if (!array_key_exists($key, $groupedResults)) {
                $groupedResults[$key] = 0;
            }
            switch($property){
                case 'distance':
                    $groupedResults[$key] += $row[$property]/1000 ;
                    break;
                case 'duration':
                    $groupedResults[$key] += $row[$property] / 60;
                    break;
                default:
                    $groupedResults[$key] += $row[$property];
                    break;
            }

        }

        $response->getBody()->write(json_encode($groupedResults));
        return $response;
    }
    public function getAverage(ServerRequest $request,Response $response, array $args, $property) : Response{
        $queryParams = $request->getQueryParams();
        $collection = $this->database->selectCollection('activities');
        $findParams = [];

        if (array_key_exists('type', $queryParams)) {
            $findParams['activityType.typeId'] = $args['type'];
        }

        if (!array_key_exists('periods', $queryParams)) {
            $period = 4;
        } else {
            $period = (int)$queryParams['periods'];
        }

        $interval = $this->getInterval($queryParams, $period);
        $enddate = new \DateTime('NOW');
        $startDate = new \DateTime('NOW');
        $startDate = $startDate->sub($interval);

        $findParams['startTimeLocal']['$gte'] = $startDate->format('Y-m-d');
        $findParams['startTimeLocal']['$lt'] = $enddate->format('Y-m-d');

        $results = $collection->find($findParams, array('sort' => array('startTimeLocal' => -1)))->toArray();
        $groupedResults = array();
        $groupedEntries = array();
        foreach ($results as $row) {
            $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $row['startTimeGMT']);
            $key = self::getKeyFromDate($startTime, $queryParams['groupby']);
            if (!array_key_exists($key, $groupedResults) ) {
                $groupedResults[$key] = 0;
                $groupedEntries[$key] = 0;
            }
            $groupedResults[$key] = $groupedResults[$key] + $row[$property];
            $groupedEntries[$key] = $groupedEntries[$key] +=1;
        }

        foreach($groupedResults as $key => $value){
            $entryCount = $groupedEntries[$key];
            $groupedResults[$key] = $value / $entryCount;
        }

        $response->getBody()->write(json_encode($groupedResults));
        return $response;
    }

    /**
     * Returns the total amount of calories. It can be grouped by week, month, year
     * @param ServerRequest $request
     * @param Response $response
     * @param array $args => ['groupby'] => week,month,year, ['periods'] => how many periods to return (number) ['type']= > activity type
     * @return Response
     * @throws \Exception
     */
    public function getTotalCalories(ServerRequest $request, Response $response, array $args) : Response{
        return $this->getTotals($request,$response,$args,'calories');
    }

    private static function getKeyFromDate(DateTime $time, string $groupby) : string{
        switch($groupby){
            case 'month':
                return $time->format('Y-m');
                break;
            case 'year':
                return $time->format('Y');
                break;
            case 'week':
                return $time->format('Y-W');
                break;
        }
    }

    public function getTotalDuration(ServerRequest $request, Response $response, array $args) : Response{
        return $this->getTotals($request,$response,$args,'duration');
    }
    public function getTotalDistance(ServerRequest $request, Response $response, array $args) : Response{
        return $this->getTotals($request,$response,$args,'distance');
    }
    public function getAvgHeartRate(ServerRequest $request, Response $response, array $args) : Response{
        return $this->getAverage($request,$response,$args,'averageHR');
    }
    public function getAvgSpeed(ServerRequest $request, Response $response, array $args) : Response{
        return $this->getAverage($request,$response,$args,'averageSpeed');
    }

    public function getAvgMovingSpeed(ServerRequest $request, Response $response, array $args){
        $queryParams = $request->getQueryParams();
        $findParams = [];
        if(array_key_exists('startDate',$queryParams)) {
            $findParams['summaryDTO.startTimeLocal']['$gte'] = $queryParams['startDate'];
        }
        if(array_key_exists('endDate',$queryParams)){
            $findParams['summaryDTO.startTimeLocal']['$lt'] = $queryParams['endDate'];
        }

        $collection = $this->database->selectCollection('activitySummary');

        $results = $collection->find($findParams, ['projection'=>['activityId'=>1,'summaryDTO.averageMovingSpeed'=>1,'summaryDTO.startTimeGMT'=>1],'sort'=>['activityId'=>-1]])->toArray();
        $output = array();
        foreach($results as $row){
                $summary = $row['summaryDTO'];
                if(array_key_exists('averageMovingSpeed',$summary) && $row['summaryDTO']['averageMovingSpeed'] > 0){
                    $output[$row['summaryDTO']['startTimeGMT']] = $row['summaryDTO']['averageMovingSpeed']*3.6;
                }
        }
        $response->getBody()->write(json_encode($output));
        return $response;
    }


    public function getPersonalBests(ServerRequest $request, Response $response, array $args) : Response{}

    /**
     * @param array $queryParams
     * @param int $period
     * @return \DateInterval
     * @throws \Exception
     */
    public function getInterval(array $queryParams, int $period): \DateInterval
    {
        $intervalSpec = "P";
        switch ($queryParams['groupby']) {
            case 'month':
                $intervalSpec .= $period . "M";
                break;
            case 'year':
                $intervalSpec .= $period . "Y";
                break;
            default:
                $intervalSpec .= $period . "W";
                break;
        }

        $interval = new \DateInterval($intervalSpec);
        return $interval;
    }

    public function getSph(ServerRequest $request, Response $response, array $args) : Response{
        $queryParams = $request->getQueryParams();
        $findParams = [];
        if(array_key_exists('startDate',$queryParams)) {
            $findParams['summaryDTO.startTimeLocal']['$gte'] = $queryParams['startDate'];
        }
        if(array_key_exists('endDate',$queryParams)){
            $findParams['summaryDTO.startTimeLocal']['$lt'] = $queryParams['endDate'];
        }
        $findParams['summaryDTO.averageMovingSpeed']['$exists'] = true;
        $findParams['summaryDTO.movingDuration']['$exists'] = true;

        $collection = $this->database->selectCollection('activitySummary');
        $results = $collection->find($findParams, ['projection'=>['summaryDTO.startTimeLocal'=> 1,'summaryDTO.averageMovingSpeed'=>1,'summaryDTO.movingDuration'=>1],'sort'=>['summaryDTO.startTimeLocal'=>-1]])->toArray();
        $output = [];

        foreach($results as $row){

            $startTime = DateTime::createFromFormat('Y-m-d H:i:s.v', str_replace('T',' ',$row['summaryDTO']['startTimeLocal']));

            $key = StatisticsApiHandler::getKeyFromDate($startTime,'month');
            $output[$key][] = ($row['summaryDTO']['averageMovingSpeed'] * 3.6) / ($row['summaryDTO']['movingDuration'] / 1000);
        }

        foreach($output as $key => $value){
            $output[$key] = array_sum($value) / count($value);
        }

        $response->getBody()->write(json_encode($output));
        return $response;
    }


}