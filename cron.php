<?php
namespace jsomhorst\garmin;
require 'vendor/autoload.php';

use dawguk\GarminConnect;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;


class cron
{

    private Client $mongoClient;
    private GarminConnect $garminClient;
    private Database $database;

    private function __construct(){
        include_once('config\settings.php');
        $this->mongoClient = new Client("mongodb://".$settings['mongodb']['hostname'].":".$settings['mongodb']['port']);
        $this->garminClient = (new GarminConnect(array("username"=>$settings['garmin']['username'],"password"=>$settings['garmin']['password'])));

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

            $database = $this->mongoClient->selectDatabase('garmin');
            if(empty($database)){
                Logger::log('cron.log')->warning('Unable to retrieve \'garmin\' database');
                return;
            }
            $this->database = $database;
            $this->importActivities();
            $this->importRecords();
            $this->importWorkouts();

            Logger::log('cron.log')->info('Done importing');

        } catch (GarminConnect\exceptions\UnexpectedResponseCodeException $e) {
            Logger::log('cron.log')->error($e->getMessage());
            return;
        }



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

                    $summaryEntry = $this->garminClient->getActivityDetails($activity->activityId);
                    $activityDetailsCollection->insertOne($summaryEntry);
                }else{
                    Logger::log('cron.log')->debug('Check if activity has been updated on garmin.');
                    $garminSummaryEntry = $this->garminClient->getActivitySummary($activity->activityId);
                    $summaryEntry = $activitySummaryCollection->findOne($findQuery);
                    if($garminSummaryEntry->metadataDTO->lastUpdateDate !== $summaryEntry->metadataDTO->lastUpdateDate){
                        Logger::log('cron.log')->debug('Data has been changed on garmin');
                        $splits = $this->garminClient->getActivitySplits($activity->activityId);
                        $splitCollection->replaceOne($findQuery,$splits);

                        $summaryEntry = $this->garminClient->getActivitySummary($activity->activityId);
                        $activitySummaryCollection->replaceOne($findQuery,$summaryEntry);

                        $summaryEntry = $this->garminClient->getActivityDetails($activity->activityId);
                        $activityDetailsCollection->replaceOne($findQuery,$summaryEntry);
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