<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SplClassLoader.php";

$c1 = new SplClassLoader("RestService", "../extlib/php-rest-service/lib");
$c1->register();
$c2 = new SplClassLoader("OAuth", "../extlib/php-lib-remote-rs/lib");
$c2->register();
$c3 = new SplClassLoader("VootProxy", "../lib");
$c3->register();

use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\HttpResponse as HttpResponse;
use \RestService\Http\IncomingHttpRequest as IncomingHttpRequest;
use \RestService\Utils\Config as Config;
use \RestService\Utils\Logger as Logger;

use \VootProxy\Proxy as Proxy;
use \VootProxy\ProxyException as ProxyException;

use \OAuth\RemoteResourceServerException as RemoteResourceServerException;

$logger = NULL;
$request = NULL;
$response = NULL;

try {
    $config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "proxy.ini");
    $logger = new Logger($config->getSectionValue('Log', 'logLevel'), $config->getValue('serviceName'), $config->getSectionValue('Log', 'logFile'), $config->getSectionValue('Log', 'logMail', FALSE));

    $service = new Proxy($config, $logger);

    $request = HttpRequest::fromIncomingHttpRequest(new IncomingHttpRequest());
    $request->matchRest("GET", "/groups/@me", function() use ($request, &$response, $service) {
        $response = $service->getGroups($request);
    });
    $request->matchRest("GET", "/people/@me/:groupId", function($groupId) use ($request, &$response, $service) {
        $response = $service->getPeople($request, $groupId);
    });
    $request->matchRestDefault(function($methodMatch, $patternMatch) use ($request) {
        if (in_array($request->getRequestMethod(), $methodMatch)) {
            if (!$patternMatch) {
                throw new ProxyException("not_found", "resource not found");
            }
        } else {
            throw new ProxyException("method_not_allowed", "request method not allowed");
        }
    });

} catch (ProxyException $e) {
    $response = new HttpResponse($e->getResponseCode());
    $response->setHeader("Content-Type", "application/json");
    $response->setContent(json_encode(array("error" => $e->getMessage(), "error_description" => $e->getMessage())));
    if (NULL !== $logger) {
        $logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
    }
} catch (RemoteResourceServerException $e) {
    $response = new HttpResponse($e->getResponseCode());
    $response->setHeader("WWW-Authenticate", $e->getAuthenticateHeader());
    $response->setHeader("Content-Type", "application/json");
    $response->setContent($e->getContent());
    if (NULL !== $logger) {
        $logger->logWarn($e->getMessage() . PHP_EOL . $e->getDescription() . PHP_EOL . $request . PHP_EOL . $response);
    }
} catch (Exception $e) {
    $response = new HttpResponse(500);
    $response->setHeader("Content-Type", "application/json");
    $response->setContent(json_encode(array("error" => "internal_server_error", "error_description" => $e->getMessage())));
    if (NULL !== $logger) {
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
