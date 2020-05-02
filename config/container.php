<?php

use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use dawguk\GarminConnect;
use MongoDB\Database;
use MongoDB\Client;

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
        return $dbCient->selectDatabase($settings['database']);
    }

];