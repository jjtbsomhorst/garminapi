<?php

use dawguk\GarminConnect;
use jsomhorst\garmin\Repositories\AccessTokenRepository;
use jsomhorst\garmin\Repositories\ClientRepository;
use jsomhorst\garmin\Repositories\RefreshTokenRepository;
use jsomhorst\garmin\Repositories\ScopeRepository;
use jsomhorst\garmin\Repositories\UserRepository;
use jsomhorst\garmin\Middleware\OAuth2MiddleWare;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\ResourceServer;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;

return [
    Configuration::class => function () {
        return new Configuration(require __DIR__ . '/settings.php');
    },
    GarminConnect::class => function(ContainerInterface $container){
        $settings = $container->get(Configuration::class)->getArray('garmin');
        return (new dawguk\GarminConnect($settings))->jsonDecode(false);
    },
    Database::class  => function(ContainerInterface $container) {
        $settings = $container->get(Configuration::class)->getArray('mongodb');
        $dbCient = new Client("mongodb://".$settings['hostname'].":".$settings['port']);

        if(!empty($settings['username'] && !empty($settings['password']))) {
            $dbCient = new Client(sprintf("mongodb://%s:%s@%s:%s/?authSource=admin",
                $settings['username'],$settings['password'],$settings['hostname'],$settings['port']));
        }


        return $dbCient->selectDatabase($settings['database']);
    },
    AuthorizationServer::class => function(ContainerInterface $container){
        $clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface
        $settings = $container->get(Configuration::class)->getArray('users');


        $userRepository = new UserRepository($settings); // instance of UserRepositoryInterface
        $refreshTokenRepository = new RefreshTokenRepository(); // instance of RefreshTokenRepositoryInterface

// Path to public and private keys
        $privateKey = __DIR__.'/private.key';
//$privateKey = new CryptKey('file://path/to/private.key', 'passphrase'); // if private key has a pass phrase
        $encryptionKey = 'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'; // generate using base64_encode(random_bytes(32))

// Setup the authorization server
        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKey,
            $encryptionKey
        );

        $grant = new PasswordGrant(
            $userRepository,
            $refreshTokenRepository
        );

        $grant->setRefreshTokenTTL(new DateInterval('P1M')); // refresh tokens will expire after 1 month

// Enable the password grant on the server
        $server->enableGrantType(
            $grant,
            new DateInterval('PT1H') // access tokens will expire after 1 hour
        );
        return $server;
    },

    ResourceServer::class => function(ContainerInterface $container){
        $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface

// Path to authorization server's public key
        $publicKeyPath = __DIR__.'/public.key';

// Setup the authorization server
        return new ResourceServer(
            $accessTokenRepository,
            $publicKeyPath
        );
    },

    OAuth2MiddleWare::class => function(ContainerInterface $container){
        return new OAuth2MiddleWare($container->get(ResourceServer::class));
    }
];