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

    $request->matchRest("GET", "/people/@me/:groupId", function($groupId) use ($config, $rs, $request, $response, $storage, $logger) {
        $rs->requireScope("read");


    });

    // get groups
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

            if(200 !== $r->getStatusCode()) {
                if(NULL !== $logger) {
                    $logger->logWarn("unexpected HTTP response code from group provider '" . $p['id'] . "', expected 200" . PHP_EOL . $o . PHP_EOL . $r);
                }
                continue;
            }

            $jr = json_decode($r->getContent(), TRUE);
            if(NULL === $jr) {
                if(NULL !== $logger) {
                    $logger->logWarn("unable to decode JSON from group provider '" . $p['id'] . "', no JSON?" . PHP_EOL . $o . PHP_EOL . $r);
                }
                continue;
            }

            if(!array_key_exists('entry', $jr)) {
                if(NULL !== $logger) {
                    $logger->logWarn("malformed JSON from group provider '" . $p['id'] . "', need 'entry' key" . PHP_EOL . $o . PHP_EOL . $r);
                }
                continue;
            }

            foreach($jr['entry'] as $k => $e) {
                // update the group identifier to make it unique among the 
                // possibly various group providers

                // FIXME: some more validation before using keys!
                // FIXME: maybe only do this after the paging stuff is dealt with
                $jr['entry'][$k]['id'] = "urn:" . $p['id'] . ":" . $jr['entry'][$k]['id'];
                array_push($allEntries, $jr['entry'][$k]);
            }
        }

        $totalResults = count($allEntries);

        // deal with sorting, only if requested
        $sortBy = $request->getQueryParameter("sortBy");
        if(NULL !== $sortBy) { 
            if("title" === $sortBy) {
                // we currently only want to support sorting by title
                $sortOrder = $request->getQueryParameter("sortOrder");
                if(NULL === $sortOrder && "ascending" !== $sortOrder && "descending" !== $sortOrder) {
                    $sortOrder = "ascending";
                }
                usort($allEntries, function($a, $b) use ($sortBy, $sortOrder) { 
                    if(array_key_exists($sortBy, $a) && array_key_exists($sortBy, $b)) {
                        if("ascending" === $sortOrder) {
                            return strcasecmp($a[$sortBy], $b[$sortBy]);
                        } else {
                            // must be descending
                            return strcasecmp($b[$sortBy], $a[$sortBy]);
                        }
                    }
                });
            }
        }

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

        $itemsPerPage = count($slicedArray);

        $x = array ( 
            "entry" => $slicedArray, 
            "itemsPerPage" => $itemsPerPage, 
            "totalResults" => $totalResults,
            "startIndex" => $startIndex
        );

        $response->setContent(json_encode($x));
    });

    // get people
    $request->matchRest("GET", "/people/@me/:groupId", function($groupId) use ($config, $rs, $request, $response, $storage, $logger) {
        $rs->requireScope("read");

        $queryAttributeValue = $rs->getAttribute($config->getValue('groupProviderQueryAttributeName'));
        $filterAttributeValue = $rs->getAttribute($config->getValue('groupProviderFilterAttributeName', FALSE));

        $allEntries = array();

        // $groupId is in format "urn:<ID>:local_group_identifier
        // we need to extract <ID>
        if(!is_string($groupId) || 2 > strlen($groupId)) {
            throw new ApiException("not_found", "the group was not found");
        }

        list(, $providerId, $localGroupId ) = explode(":", $groupId);
        
        $p = $storage->getProvider($providerId);
        if(FALSE === $p) {
            throw new ApiException("not_found", "provider not found");
        }
        
        $requestUri = $p['endpoint'] . "/people/" . $queryAttributeValue[0] . "/" . $localGroupId;

        $o = new HttpRequest($requestUri);
        $o->setHeader("Authorization", "Basic " . base64_encode($p['username'] . ":" . $p['password']));
        if(NULL !== $logger) {
            $logger->logDebug($o);
        }
        $r = OutgoingHttpRequest::makeRequest($o);
        if(NULL !== $logger) {
            $logger->logDebug($r);
        }

        if(200 !== $r->getStatusCode()) {
            if(NULL !== $logger) {
                $logger->logWarn("unexpected HTTP response code from group provider '" . $p['id'] . "', expected 200" . PHP_EOL . $o . PHP_EOL . $r);
            }
            continue;
        }

        $jr = json_decode($r->getContent(), TRUE);
        if(NULL === $jr) {
            if(NULL !== $logger) {
                $logger->logWarn("unable to decode JSON from group provider '" . $p['id'] . "', no JSON?" . PHP_EOL . $o . PHP_EOL . $r);
            }
            continue;
        }

        if(!array_key_exists('entry', $jr)) {
            if(NULL !== $logger) {
                $logger->logWarn("malformed JSON from group provider '" . $p['id'] . "', need 'entry' key" . PHP_EOL . $o . PHP_EOL . $r);
            }
            continue;
        }

        foreach($jr['entry'] as $k => $e) {
            // update the people identifier to make it unique among the 
            // possibly various group providers

            // FIXME: maybe only do this after the paging stuff is dealt with
            $jr['entry'][$k]['id'] = "urn:" . $p['id'] . ":" . $jr['entry'][$k]['id'];
            array_push($allEntries, $jr['entry'][$k]);
        }

        $totalResults = count($allEntries);

        // deal with sorting, only if requested
        $sortBy = $request->getQueryParameter("sortBy");
        if(NULL !== $sortBy) { 
            if("id" === $sortBy || "displayName" === $sortBy) {
                // we currently only want to support sorting by id, displayName
                $sortOrder = $request->getQueryParameter("sortOrder");
                if(NULL === $sortOrder && "ascending" !== $sortOrder && "descending" !== $sortOrder) {
                    $sortOrder = "ascending";
                }
                usort($allEntries, function($a, $b) use ($sortBy, $sortOrder) { 
                    if(array_key_exists($sortBy, $a) && array_key_exists($sortBy, $b)) {
                        if("ascending" === $sortOrder) {
                            return strcasecmp($a[$sortBy], $b[$sortBy]);
                        } else {
                            return strcasecmp($b[$sortBy], $a[$sortBy]);
                        }
                    }
                });
            }
        }

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
