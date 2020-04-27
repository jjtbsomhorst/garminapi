<?php


namespace jsomhorst\garmin;
use Apix\Log;

class Logger
{
    //private static Log\Logger\File $log;
    private static array $logs = [];
    //Log\Logger\File
    const defaultPath = 'c:\\xampp\\htdocs\\garmin\\logs\\';

    public static function log($filename = 'garmin.log') : Log\Logger\File{
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