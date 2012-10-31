<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SplClassLoader.php";
$c =  new SplClassLoader("Tuxed", dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib");
$c->register();

use \Tuxed\Config as Config;
use \Tuxed\VootProxy\PdoVootProxyStorage as PdoVootProxyStorage;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "proxy.ini");
$storage = new PdoVootProxyStorage($config);

if($argc !== 2) {
        echo "ERROR: please specify file with provider registration information" . PHP_EOL;
        die();
}

$registrationFile = $argv[1];
if(!file_exists($registrationFile) || !is_file($registrationFile) || !is_readable($registrationFile)) {
        echo "ERROR: unable to read provider registration file" . PHP_EOL;
        die();
}

$registration = json_decode(file_get_contents($registrationFile), TRUE);

foreach($registration as $r) {
    if(FALSE === $storage->getProvider($r['id'])) {
        // does not exist yet, install
        echo "Adding '" . $r['name'] . "'..." . PHP_EOL;
        $storage->addProvider($r);
    }
}
