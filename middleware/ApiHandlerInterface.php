<?php
namespace jsomhorst\garmin\middleware;

use Slim\App;

interface ApiHandlerInterface{
    public static function addRoutes(App $app);
}