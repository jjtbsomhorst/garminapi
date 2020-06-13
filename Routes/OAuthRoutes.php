<?php


namespace jsomhorst\garmin\Routes;


use jsomhorst\garmin\Logger;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Psr7\Stream;

class OAuthRoutes implements ApiHandlerInterface
{
    private AuthorizationServer $server;

    public function __construct(AuthorizationServer $server){
        $this->server = $server;
    }
    
    public function generateToken(ServerRequest $request, Response $response, array $args) : ResponseInterface{
        Logger::log('api.log')->debug('Generate a new token');
        Logger::log('api.log')->debug($request->getParsedBody());
        Logger::log('api.log')->debug($response);
        try {
            return $this->server->respondToAccessTokenRequest($request, $response);
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        } catch (\Exception $exception) {
            $body = new Stream('php://temp', 'r+');
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);

        }
    }

    public static function addRoutes(App $app)
    {
        $app->post('/token',self::class.":generateToken");
        OAuthRoutes::addProtectedRoutes($app);
    }

    public static function addProtectedRoutes(App $app)
    {

    }
}