<?php
namespace jsomhorst\garmin\Routes;

use Slim\App;

interface ApiHandlerInterface{
    public static function addRoutes(App $app);
    public static function addProtectedRoutes(App $app);
}