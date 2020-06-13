<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace jsomhorst\garmin\Repositories;

use jsomhorst\garmin\Entities\UserEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use MongoDB\Database;

class UserRepository implements UserRepositoryInterface
{
    private $respository = array();

    public function __construct($userrepository)
    {
        $this->respository = $userrepository;
    }


    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {

        if(array_key_exists($username,$this->respository) && $this->respository[$username] === $password){
            return new UserEntity();
        }

        return;
    }
}
