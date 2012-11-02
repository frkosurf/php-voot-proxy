<?php

require_once "lib/SplClassLoader.php";

$c1 = new SplClassLoader("RestService", "../extlib/php-rest-service/lib");
$c1->register();

$c2 =  new SplClassLoader("VootProxy", "../lib");
$c2->register();

use \RestService\Utils\Config as Config;
use \VootProxy\PdoVootProxyStorage as PdoVootProxyStorage;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "proxy.ini");

$storage = new PdoVootProxyStorage($config);
$storage->initDatabase();
