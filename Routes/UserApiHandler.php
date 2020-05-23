<?php


namespace jsomhorst\garmin\Routes;


use dawguk\GarminConnect;
use Slim\App;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Routing\RouteCollectorProxy;
use jsomhorst\garmin\Middleware\OAuth2MiddleWare;

class UserApiHandler implements ApiHandlerInterface
{

    /**
     * @var GarminConnect
     */
    private GarminConnect $garminClient;

    public function __construct(GarminConnect $garmin)
    {
        $this->garminClient = $garmin;

    }

    public static function addRoutes(App $app)
    {
        UserApiHandler::addProtectedRoutes($app);
    }

    public static function addProtectedRoutes(App $app)
    {
        $app->group('/user',function (RouteCollectorProxy $group){
            $group->get('',UserApiHandler::class.':getUser');
            $group->get('/devices',UserApiHandler::class.':getDevices');
            $group->get('/hrzones',UserApiHandler::class.':getHeartRateZones');
        })->addMiddleware($app->getContainer()->get(OAuth2MiddleWare::class));
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