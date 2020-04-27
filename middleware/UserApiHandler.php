<?php


namespace jsomhorst\garmin\middleware;


use Slim\App;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class UserApiHandler extends ActivityApiHandler
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function addRoutes(App $app)
    {
        $app->get('/user',UserApiHandler::class.':getUser')->setName('getUser');
        $app->get('/user/devices',UserApiHandler::class.':getDevices');
        $app->get('/user/hrzones',UserApiHandler::class.':getHeartRateZones');
    }

    public function getUser(ServerRequest $request, Response $response, array $args) :Response{
        $user = $this->garminClient->getUser();
        $response->getBody()->write($user);
        return $response;
    }

    public function getDevices(ServerRequest $request, Response $response, array $args): Response{
        return $response;
    }

    public function getHeartRateZones(ServerRequest $request, Response $response, array $args): Response{
        $hrZones = $this->garminClient->getHrZones();
        $response->getBody()->write($hrZones);
        return $response;
    }


}