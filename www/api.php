<?php

require_once "../lib/ApiException.php";
require_once "../lib/RemoteResourceServer.php";
require_once "../lib/SplClassLoader.php";

$c =  new SplClassLoader("Tuxed", dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib");
$c->register();

use \Tuxed\Config as Config;
use \Tuxed\Http\HttpRequest as HttpRequest;
use \Tuxed\Http\HttpResponse as HttpResponse;
use \Tuxed\Http\IncomingHttpRequest as IncomingHttpRequest;
use \Tuxed\Http\OutgoingHttpRequest as OutgoingHttpRequest;
use \Tuxed\Logger as Logger;
use \Tuxed\VootProxy\PdoVootProxyStorage as PdoVootProxyStorage;

$logger = NULL;
$request = NULL;
$response = NULL;

try {
    $config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "proxy.ini");
    $logger = new Logger($config->getSectionValue('Log', 'logLevel'), $config->getValue('serviceName'), $config->getSectionValue('Log', 'logFile'), $config->getSectionValue('Log', 'logMail', FALSE));

    $storage = new PdoVootProxyStorage($config);

    $rs = new RemoteResourceServer($config->getSectionValues("OAuth"));
    $rs->verifyRequest();

    $request = HttpRequest::fromIncomingHttpRequest(new IncomingHttpRequest());

    $response = new HttpResponse();
    $response->setHeader("Content-Type", "application/json");

    // get my groups
    $request->matchRest("GET", "/groups/@me", function() use ($rs, $response, $storage, $logger) {
        $rs->requireScope("read");
        $uid = $rs->getAttribute("uid");
        // we always need to request the uid field...
        
        $providers = $storage->getProviders();

        $itemsPerPage = 0;
        $totalResults = 0;
        $startIndex = 0;

        $allEntries = array();

        foreach($providers as $p) {
            $requestUri = $p['endpoint'] . "/groups/" . $uid[0];

            $o = new HttpRequest($requestUri);
            $o->setHeader("Authorization", "Basic " . base64_encode($p['username'] . ":" . $p['password']));
            if(NULL !== $logger) {
                $logger->logDebug($o);
            }
            $r = OutgoingHttpRequest::makeRequest($o);
            if(NULL !== $logger) {
                $logger->logDebug($r);
            }
            $jr = json_decode($r->getContent(), TRUE);

            $itemsPerPage += $jr['itemsPerPage'];
            $totalResults += $jr['totalResults'];

            foreach($jr['entry'] as $k => $e) {
                $jr['entry'][$k]['id'] = "urn:" . $p['id'] . ":" . $jr['entry'][$k]['id'];
                array_push($allEntries, $jr['entry'][$k]);
            }
        }

        $x = array ( 
            "entry" => $allEntries, 
            "itemsPerPage" => $itemsPerPage, 
            "totalResults" => $totalResults,
            "startIndex" => 0
        );

        $response->setContent(json_encode($x));
    });

    $request->matchRestDefault(function($methodMatch, $patternMatch) use ($request, $response) {
        if(in_array($request->getRequestMethod(), $methodMatch)) {
            if(!$patternMatch) {
                throw new ApiException("not_found", "resource not found");
            }
        } else {
            throw new ApiException("method_not_allowed", "request method not allowed");
        }
    });

} catch (ApiException $e) {
    $response = new HttpResponse();
    $response->setStatusCode($e->getResponseCode());
    $response->setContent(json_encode(array("error" => $e->getMessage(), "error_description" => $e->getDescription())));
    if(NULL !== $logger) {
        $logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
    }
} catch (Exception $e) {
    $response = new HttpResponse();
    $response->setStatusCode(500);
    $response->setContent(json_encode(array("error" => "internal_server_error", "error_description" => $e->getMessage())));
    if(NULL !== $logger) {
        $logger->logFatal($e->getMessage() . PHP_EOL . $request . PHP_EOL . $response);
    }
}

if (NULL !== $logger) {
    $logger->logDebug($request);
}
if (NULL !== $logger) {
    $logger->logDebug($response);
}
if (NULL !== $response) {
    $response->sendResponse();
}
