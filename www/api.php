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
    $request->matchRest("GET", "/groups/@me", function() use ($config, $rs, $request, $response, $storage, $logger) {
        $rs->requireScope("read");

        $queryAttributeValue = $rs->getAttribute($config->getValue('groupProviderQueryAttributeName'));
        $filterAttributeValue = $rs->getAttribute($config->getValue('groupProviderFilterAttributeName', FALSE));

        $allEntries = array();

        $providers = $storage->getProviders();
        foreach($providers as $p) {
            // check if the provider has a filter and ignore the provider if 
            // it has a filter and does not match the expected value
            if(NULL !== $filterAttributeValue && $filterAttributeValue !== $p['filter']) {
                continue;
            }

            $requestUri = $p['endpoint'] . "/groups/" . $queryAttributeValue[0];

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
            foreach($jr['entry'] as $k => $e) {
                // update the group identifier to make it unique among the 
                // possibly various group providers

                // FIXME: maybe only do this after the paging stuff is dealt with
                $jr['entry'][$k]['id'] = "urn:" . $p['id'] . ":" . $jr['entry'][$k]['id'];
                array_push($allEntries, $jr['entry'][$k]);
            }
        }

        $totalResults = count($allEntries);

        // sort by group title from allEntries, this is a bit expensive but we 
        // don't expect people to be in 100s of groups, so should be fine
        usort($allEntries, function($a, $b) { 
            return strcasecmp($a['title'], $b['title']);
        });

        $startIndex = $request->getQueryParameter("startIndex");
        if(NULL === $startIndex) {
            $startIndex = 0;
        }
        if(!is_numeric($startIndex) || 0 > $startIndex) {
            $startIndex = 0;
        }
        $count = $request->getQueryParameter("count");
        if(NULL === $count) {
            $count = $totalResults;
        }
        if(!is_numeric($count) || 0 > $count) {
            $count = $totalResults;
        }
        
        $slicedArray = array_slice($allEntries, $startIndex, $count);

        // FIXME: should this be the request number of items, or the actual
        // number of items returned?
        $itemsPerPage = count($slicedArray);

        $x = array ( 
            "entry" => $slicedArray, 
            "itemsPerPage" => $itemsPerPage, 
            "totalResults" => $totalResults,
            "startIndex" => $startIndex
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
