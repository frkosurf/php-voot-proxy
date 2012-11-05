<?php

namespace VootProxy;

use \RestService\Utils\Config as Config;
use \RestService\Utils\Logger as Logger;
use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\HttpResponse as HttpResponse;

use \OAuth\RemoteResourceServer as RemoteResourceServer;
use \OAuth\RemoteResourceServerException as RemoteResourceServerException;

class Proxy
{
    private $_config;
    private $_logger;
    private $_storage;
    private $_rs;

    public function __construct(Config $c, Logger $l = NULL)
    {
        $this->_config = $c;
        $this->_logger = $l;

        $this->_storage = new PdoVootProxyStorage($this->_config);

        $rsConfig = $this->_config->getSectionValues("OAuth");
        $rsConfig += array("throwException" => TRUE);

        $this->_rs = new RemoteResourceServer($rsConfig);
    }

    public function sortEntries(array $entries, $sortBy, $sortOrder)
    {
        if (NULL !== $sortBy) {
            if ("title" === $sortBy || "displayName" === $sortBy) {
                if (NULL === $sortOrder && "ascending" !== $sortOrder && "descending" !== $sortOrder) {
                    $sortOrder = "ascending";
                }
                usort($entries, function($a, $b) use ($sortBy, $sortOrder) {
                    if (array_key_exists($sortBy, $a) && array_key_exists($sortBy, $b)) {
                        if ("ascending" === $sortOrder) {
                            return strcasecmp($a[$sortBy], $b[$sortBy]);
                        } else {
                            // must be descending
                            return strcasecmp($b[$sortBy], $a[$sortBy]);
                        }
                    }
                });
            }
        }

        return $entries;
    }

    public function limitEntries(array $entries, $startIndex, $count)
    {
        if (NULL === $startIndex || !is_numeric($startIndex) || 0 > $startIndex) {
            $startIndex = 0;
        }
        if (NULL === $count || !is_numeric($count) || 0 > $count) {
            $count = count($entries);
        }

        return array_slice($entries, $startIndex, $count);
    }

    public function addGroupsScope(Provider $provider, array $entries)
    {
        foreach ($entries as $k => $v) {
            $entries[$k]['id'] = "urn:groups:" . $provider->getId() . ":" . $entries[$k]['id'];
        }

        return $entries;
    }

    public function addPeopleScope(Provider $provider, array $entries)
    {
        foreach ($entries as $k => $v) {
            $entries[$k]['id'] = "urn:people:" . $provider->getId() . ":" . $entries[$k]['id'];
        }

        return $entries;
    }

    public function parseScope($entry)
    {
        $data = explode(":", $entry);
        if (4 !== count($data)) {
            return FALSE;
        }
        if ("urn" !== $data[0]) {
            return FALSE;
        }
        if ("people" !== $data[1] && "groups" !== $data[1]) {
            return FALSE;
        }

        return $data;
    }

    public function handleRequest(HttpRequest $request)
    {
        $response = new HttpResponse();
        $response->setContentType("application/json");

        try {
            $this->_rs->verifyAuthorizationHeader($request->getHeader("Authorization"));

            $storage = $this->_storage; // FIXME: can this be avoided? stupid PHP 5.3!
            $rs = $this->_rs; // FIXME: can this be avoided? stupid PHP 5.3!
            $x = &$this;
            $config = $this->_config;
            $logger = $this->_logger;

            $request->matchRest("GET", "/groups/@me", function() use ($x, $rs, $config, $logger, $request, $response, $storage) {
                $rs->requireScope("read");

                $sortBy = $request->getQueryParameter("sortBy");
                $sortOrder = $request->getQueryParameter("sortOrder");
                $startIndex = $request->getQueryParameter("startIndex");
                $count = $request->getQueryParameter("count");

                $allEntries = array();

                $providers = $storage->getProviders();
                foreach ($providers as $p) {
                    $provider = Provider::fromArray($p);
                    try {
                        $remoteProvider = new RemoteProvider($config, $logger, $provider, $rs);
                        $allEntries += $remoteProvider->getGroups("UID");
                    } catch (RemoteProviderException $e) {
                        // ignore provider errors, just try next provider
                        continue;
                    }
                }

                $totalResults = count($allEntries);

                $sortedEntries = $x->sortEntries($allEntries, $sortBy, $sortOrder);
                $limitedSortedEntries = $x->limitEntries($sortedEntries, $startIndex, $count);
                $scopedLimitedSortedEntries = $x->addGroupsScope($provider, $limitedSortedEntries);

                $responseData = array (
                    "entry" => $scopedLimitedSortedEntries,
                    "itemsPerPage" => count($scopedLimitedSortedEntries),
                    "totalResults" => $totalResults,
                    "startIndex" => $startIndex
                );
                $response->setContent(json_encode($responseData));
            });

            $request->matchRest("GET", "/people/@me/:groupId", function($groupId) use ($x, $rs, $config, $logger, $request, $response, $storage) {
                $rs->requireScope("read");

                $sortBy = $request->getQueryParameter("sortBy");
                $sortOrder = $request->getQueryParameter("sortOrder");
                $startIndex = $request->getQueryParameter("startIndex");
                $count = $request->getQueryParameter("count");

                $parsedScope = $x->parseScope($groupId);
                if (FALSE === $parsedScope) {
                    throw new ProxyException("not_found", "invalid groupId");
                }

                $providerId = $parsedScope[2];
                $providerGroupId = $parsedScope[3];

                $providerArray = $storage->getProvider($providerId);
                if (FALSE === $providerArray) {
                    throw new ProxyException("not_found", "provider does not exist");
                }
                $provider = Provider::fromArray($providerArray);

                $entries = NULL;
                try {
                    $remoteProvider = new RemoteProvider($config, $logger, $provider, $rs);
                    $entries = $remoteProvider->getPeople($providerUserId, $providerGroupId);
                } catch (RemoteProviderException $e) {
                    // FIXME: should we just let this go?!
                    $entries = array();
                }
                $totalResults = count($entries);

                $sortedEntries = $x->sortEntries($entries, $sortBy, $sortOrder);
                $limitedSortedEntries = $x->limitEntries($sortedEntries, $startIndex, $count);
                $scopedLimitedSortedEntries = $x->addPeopleScope($provider, $limitedSortedEntries);

                $responseData = array (
                    "entry" => $scopedLimitedSortedEntries,
                    "itemsPerPage" => count($scopedLimitedSortedEntries),
                    "totalResults" => $totalResults,
                    "startIndex" => $startIndex
                );
                $response->setContent(json_encode($responseData));
            });

            $request->matchRestDefault(function($methodMatch, $patternMatch) use ($request, $response) {
                if (in_array($request->getRequestMethod(), $methodMatch)) {
                    if (!$patternMatch) {
                        throw new ProxyException("not_found", "resource not found");
                    }
                } else {
                    throw new ProxyException("method_not_allowed", "request method not allowed");
                }
            });

        } catch (RemoteResourceServerException $e) {
            $response->setStatusCode($e->getResponseCode());
            $response->addHeader("WWW-Authenticate", $e->getAuthenticateHeader());
            $response->setContent($e->getContent());
            // FIXME: add logging here?
        } catch (ProxyException $e) {
            // FIXME: add handling here
        }

        return $response;

    }

}
