<?php

use Phalcon\Di\FactoryDefault\Cli as CliDI;

require __DIR__ . '/../vendor/autoload.php';

$di = new CliDI();
$di->set('db', new \Phalcon\Db\Adapter\Pdo\Mysql([
    "host"     => "127.0.0.1",
    "username" => "root",
    "password" => "314",
    "dbname"   => "emailparser",
    "charset" => "utf8"
]));

try {
    $parser = new \Lib\Parser("@gmail.com", "passwor");
    $parser->createClient()
        ->auth()
        ->parseAllResume();
} catch (Exception $e) {
    echo $e;
}
