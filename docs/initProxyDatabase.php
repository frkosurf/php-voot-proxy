<?php

require_once "lib/SplClassLoader.php";
$c =  new SplClassLoader("Tuxed", dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib");
$c->register();

use \Tuxed\Config as Config;
use \Tuxed\VootProxy\PdoVootProxyStorage as PdoVootProxyStorage;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "proxy.ini");

$storage = new PdoVootProxyStorage($config);
$storage->initDatabase();
