<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SplClassLoader.php";

$c1 = new SplClassLoader("RestService", "../extlib/php-rest-service/lib");
$c1->register();

$c2 =  new SplClassLoader("VootProxy", "../lib");
$c2->register();

$c =  new SplClassLoader("Tuxed", dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib");
$c->register();

use \RestService\Utils\Config as Config;
use \VootProxy\PdoVootProxyStorage as PdoVootProxyStorage;
use \VootProxy\ProviderRegistration as ProviderRegistration;

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
    $pr = ProviderRegistration::fromArray($r);
    if(FALSE === $storage->getProvider($pr->getId())) {
        // does not exist yet, install
        echo "Adding '" . $pr->getName() . "'..." . PHP_EOL;
        $storage->addProvider($pr->getProviderAsArray());
    }
}
