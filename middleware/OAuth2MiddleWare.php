<?php


namespace jsomhorst\garmin\Middleware;

use jsomhorst\garmin\Logger;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;


class OAuth2MiddleWare implements MiddlewareInterface
{
    private $server;

    public function __construct(ResourceServer $server){
        $this->server = $server;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        Logger::log('api.log')->debug($request->getRequestTarget());
        try {
            $request = $this->server->validateAuthenticatedRequest($request);
            return $handler->handle($request);;
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new Response());
            // @codeCoverageIgnoreStart
        } catch (\Exception $exception) {
            return (new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500))
                ->generateHttpResponse(new Response());
        }
    }
}