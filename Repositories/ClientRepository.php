<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace jsomhorst\garmin\Repositories;

use jsomhorst\garmin\Entities\ClientEntity;
use jsomhorst\garmin\Logger;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;


class ClientRepository implements ClientRepositoryInterface
{
    const CLIENT_NAME = 'My Awesome App';
    const REDIRECT_URI = 'http://foo/bar';

    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier)
    {
        $client = new ClientEntity();

        $client->setIdentifier($clientIdentifier);
        $client->setName(self::CLIENT_NAME);
        $client->setRedirectUri(self::REDIRECT_URI);
        $client->setConfidential();

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        Logger::log('api.log')->debug('Validate if we know the client');
        Logger::log('api.log')->debug($clientIdentifier);
        Logger::log('api.log')->debug($clientSecret);
        $clients = [
            'myawesomeapp' => [
                'secret'          => "123",
                'name'            => self::CLIENT_NAME,
                'redirect_uri'    => self::REDIRECT_URI,
                'is_confidential' => true,
            ],
        ];

        // Check if client is registered
        if (\array_key_exists($clientIdentifier, $clients) === false) {
            return false;
        }

        if (
            $clients[$clientIdentifier]['is_confidential'] === true
            && $clientSecret !== $clients[$clientIdentifier]['secret']
        ) {
            return false;
        }
        Logger::log('api.log')->debug('We seem to have caught a valid client id / secret pair');
        return true;
    }
}
