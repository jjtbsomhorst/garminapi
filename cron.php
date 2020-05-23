<?php
namespace jsomhorst\garmin;
require 'vendor/autoload.php';

use dawguk\GarminConnect;
use DI\ContainerBuilder;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;


class cron
{


    private GarminConnect $garminClient;
    private Database $database;
    private \DI\Container $container;

    private function __construct(){

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions('config/container.php');
        $this->container = $containerBuilder->build();
        $this->garminClient = $this->container->get(GarminConnect::class);
        $this->garminClient->jsonDecode(true);
        $this->database = $this->container->get(Database::class);
    }

    private function importActivities(){
        $activityCollection = $this->database->selectCollection('activities');
        $activityDetailsCollection = $this->database->selectCollection('activityDetails');
        $activitySummaryCollection = $this->database->selectCollection('activitySummary');
        $splitCollection = $this->database->selectCollection('activitySplits');

        if(is_null($activityCollection)){
            Logger::log('cron.log')->warning('Unable to load activities collection');
            return;
        }

        if($activityCollection->countDocuments() > 0){
            $this->updateActivities($activityCollection,$activityDetailsCollection,$splitCollection,$activitySummaryCollection);
        }else{
            $this->initialLoadActivities();
        }
    }

    private function cron(){
        Logger::log('cron.log')->debug('Start running the cron');

        try {

            $userData = $this->garminClient->getUser();
            $this->importActivities();
            $this->importWorkouts();


        } catch (GarminConnect\exceptions\UnexpectedResponseCodeException $e) {
            Logger::log('cron.log')->error($e->getMessage());
            return;
        }
        Logger::log('cron.log')->info('Done importing');

    }

    private function importWorkouts(){
        Logger::log('cron.log')->debug('Start importing workouts');
        $intStart = 0;
        $workoutCollection = $this->database->selectCollection('workouts');

        while(($workouts = $this->garminClient->getWorkouts($intStart))){
            foreach($workouts as $entry){
                $filter = ['workoutId'=>$entry->workoutId];
                $record = $workoutCollection->findOne($filter);
                if(is_null($record)){
                    Logger::log('cron.log')->debug(sprintf('Insert new workout %s',$entry->workoutId));
                    $workoutCollection->insertOne($entry);
                }else{
                    Logger::log('cron.log')->debug(sprintf('Update workout %s',$entry->workoutId));
                    $workoutCollection->replaceOne($filter,$entry);
                }
            }
            $intStart += 20;
        }

    }

    private function updateActivities(Collection $activityCollection,Collection $activityDetailsCollection, Collection $splitCollection, Collection $activitySummaryCollection){
        Logger::log('cron.log')->debug('Check if we have updates');
        $count = $this->garminClient->getActivityCount()->totalCount;
        $importCount = 0;
        $intStart = 0;
        while($importCount < $count){
            $activities = $this->garminClient->getActivityList($intStart,$importCount);
            foreach($activities as $activity){
                Logger::log('cron.log')->debug(sprintf('Searching for activity %s',$activity->activityId));
                $findQuery = ['activityId' => $activity->activityId];
                $ActivityEntry = $activityCollection->findOne($findQuery);
                if(empty($ActivityEntry)){
                    Logger::log('cron.log')->debug(sprintf('new activity %s',$activity->activityId));
                    $activityCollection->insertOne($activity);

                    $splits = $this->garminClient->getActivitySplits($activity->activityId);
                    $splitCollection->insertOne($splits);

                    $summaryEntry = $this->garminClient->getActivitySummary($activity->activityId);
                    $activitySummaryCollection->insertOne($summaryEntry);

                    $detailEntry = $this->garminClient->getActivityDetails($activity->activityId);
                    $activityDetailsCollection->insertOne($detailEntry);
                }
                else{
                    Logger::log('cron.log')->debug('Check if activity has been updated on garmin.');
                    $garminSummaryEntry = $this->garminClient->getActivitySummary($activity->activityId);
                    $summaryEntry = $activitySummaryCollection->findOne($findQuery);
                    if($garminSummaryEntry->metadataDTO->lastUpdateDate !== $summaryEntry->metadataDTO->lastUpdateDate){

                        $activitySummaryCollection->replaceOne($findQuery,$summaryEntry);

                        Logger::log('cron.log')->debug('Data has been changed on garmin');
                        $splits = $this->garminClient->getActivitySplits($activity->activityId);
                        $splitCollection->replaceOne($findQuery,$splits);

                        $detailsEntry = $this->garminClient->getActivityDetails($activity->activityId);
                        $activityDetailsCollection->replaceOne($findQuery,$detailsEntry);
                    }
                }
            }
            $intStart = $importCount;
            $importCount = $importCount+100;
        }


    }

    private function initialLoadActivities(){
        Logger::log('cron.log')->debug('Start initial load of activities');
        $activityCollection = $this->database->selectCollection('activities');
        $activityDetailCollection = $this->database->selectCollection('activityDetails');
        $activitySummaryCollection = $this->database->selectCollection('activitySummary');
        $splitCollection = $this->database->selectCollection('activitySplits');
        $this->updateActivities($activityCollection,$activityDetailCollection,$splitCollection,$activitySummaryCollection);

    }

    public static function run(){
        $me = new cron();
        $me->cron();
    }
}


cron::run();