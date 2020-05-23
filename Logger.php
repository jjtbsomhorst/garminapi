<?php


namespace jsomhorst\garmin;
use Apix\Log;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class Logger
{
    //private static Log\Logger\File $log;
    private static array $logs = [];
    //Log\Logger\File
    const defaultPath = 'c:\\xampp\\htdocs\\garmin\\logs\\';

    public static function log($filename = 'garmin.log') : LoggerInterface{
       return self::getLogger($filename);
    }

    public static function setMinLevel($filename = 'garmin.log',$minLevel){
        return self::getLogger($filename)->setMinLevel($minLevel);
    }

    private static function getLogger($filename) : AbstractLogger{
        if(!array_key_exists($filename,self::$logs)){
            $path = self::defaultPath.$filename;
            $l = new Log\Logger\File($path);
            $l->setMinLevel('debug');
            $l->setCascading(false);
            $l->setDeferred(false);      // postpone/accumulate logs processing
            self::$logs[$filename] = $l;
        }
        return self::$logs[$filename];
    }


}