<?php

require_once 'lib/_autoload.php';

use \RestService\Utils\Config as Config;
use \VootProxy\PdoVootProxyStorage as PdoVootProxyStorage;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "proxy.ini");

$storage = new PdoVootProxyStorage($config);
$storage->initDatabase();
