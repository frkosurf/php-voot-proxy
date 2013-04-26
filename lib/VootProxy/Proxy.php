<?php

namespace VootProxy;

use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\HttpResponse as HttpResponse;
use \RestService\Utils\Config as Config;
use \RestService\Utils\Logger as Logger;

use \OAuth\RemoteResourceServer as RemoteResourceServer;

class Proxy
{
    private $_config;
    private $_logger;
    private $_storage;
    private $_resourceServer;

    public function __construct(Config $c, Logger $l = NULL)
    {
        $this->_config = $c;
        $this->_logger = $l;

        $this->_storage = new PdoVootProxyStorage($this->_config);

        $rsConfig = $this->_config->getSectionValues("OAuth");

        $this->_resourceServer = new RemoteResourceServer($rsConfig);
    }

    public function getGroups(HttpRequest $request)
    {
        $response = new HttpResponse();
        $response->setContentType("application/json");

        $introspection = $this->_resourceServer->verifyRequest($request->getHeaders(), $request->getQueryParameters());
        // FIXME: for now we also accept "read" scope, but in the future we SHOULD NOT
        $introspection->requireAnyScope(array("http://openvoot.org/groups", "read"));

        $sortBy = $request->getQueryParameter("sortBy");
        $startIndex = $request->getQueryParameter("startIndex");
        $count = $request->getQueryParameter("count");

        $allEntries = array();

        $providerUserId = $introspection->getAttribute($this->_config->getValue('groupProviderQueryAttributeName'));
        $filterAttributeValues = $introspection->getAttribute($this->_config->getValue('groupProviderFilterAttributeName'));
        $providers = $this->_storage->getProviders();
        foreach ($providers as $p) {
            $provider = Provider::fromArray($p);
            if (!$provider->passFilter($filterAttributeValues)) {
                continue;
            }
            try {
                $remoteProvider = new RemoteProvider($this->_config, $this->_logger);
                $providerEntries = $remoteProvider->getGroups($provider, $providerUserId[0]);
                $scopedProviderEntries = $this->addGroupsScope($provider, $providerEntries);
                $allEntries += $scopedProviderEntries;
            } catch (RemoteProviderException $e) {
                // ignore provider errors, just try next provider
                if (NULL !== $this->_logger) {
                    $this->_logger->logWarn($e->getLogMessage() . PHP_EOL . $request);
                }
                continue;
            }
        }

        $totalResults = count($allEntries);
        $sortedEntries = $this->sortEntries($allEntries, $sortBy);
        $limitedSortedEntries = $this->limitEntries($sortedEntries, $startIndex, $count);

        $responseData = array (
            "entry" => $limitedSortedEntries,
            "itemsPerPage" => count($limitedSortedEntries),
            "totalResults" => $totalResults,
            "startIndex" => $startIndex
        );
        $response->setContent(json_encode($responseData));

        return $response;
    }

    public function getPeople(HttpRequest $request, $groupId)
    {
        $response = new HttpResponse();
        $response->setContentType("application/json");

        $introspection = $this->_resourceServer->verifyRequest($request->getHeaders(), $request->getQueryParameters());
        // FIXME: for now we also accept "read" scope, but in the future we SHOULD NOT
        $introspection->requireAnyScope(array("http://openvoot.org/people", "read"));

        $sortBy = $request->getQueryParameter("sortBy");
        $startIndex = $request->getQueryParameter("startIndex");
        $count = $request->getQueryParameter("count");

        $parsedScope = $this->parseScope($groupId);
        $providerId = $parsedScope[2];
        $providerGroupId = $parsedScope[3];

        $providerArray = $this->_storage->getProvider($providerId);
        if (FALSE === $providerArray) {
            throw new ProxyException("not_found", "provider does not exist");
        }
        $provider = Provider::fromArray($providerArray);

        $providerUserId = $introspection->getAttribute($this->_config->getValue('groupProviderQueryAttributeName'));
        $filterAttributeValues = $introspection->getAttribute($this->_config->getValue('groupProviderFilterAttributeName'));

        $entries = array();
        if ($provider->passFilter($filterAttributeValues)) {
            try {
                $remoteProvider = new RemoteProvider($this->_config, $this->_logger);
                $providerEntries = $remoteProvider->getPeople($provider, $providerUserId[0], $providerGroupId);
                $scopedProviderEntries = $this->addPeopleScope($provider, $providerEntries);
                $entries = $scopedProviderEntries;
            } catch (RemoteProviderException $e) {
                // provider fails to get data, just ignore this
            }
        }
        $totalResults = count($entries);
        $sortedEntries = $this->sortEntries($entries, $sortBy);
        $limitedSortedEntries = $this->limitEntries($sortedEntries, $startIndex, $count);

        $responseData = array (
            "entry" => $limitedSortedEntries,
            "itemsPerPage" => count($limitedSortedEntries),
            "totalResults" => $totalResults,
            "startIndex" => $startIndex
        );
        $response->setContent(json_encode($responseData));

        return $response;
    }

    public function sortEntries(array $entries, $sortBy)
    {
        if (NULL !== $sortBy) {
            if ("title" === $sortBy || "displayName" === $sortBy) {
                usort($entries, function($a, $b) use ($sortBy) {
                    if (array_key_exists($sortBy, $a) && array_key_exists($sortBy, $b)) {
                        return strcasecmp($a[$sortBy], $b[$sortBy]);
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
            $entries[$k]['id'] = "urn:x-groups:" . $provider->getId() . ":" . $entries[$k]['id'];
        }

        return $entries;
    }

    public function addPeopleScope(Provider $provider, array $entries)
    {
        foreach ($entries as $k => $v) {
            $entries[$k]['id'] = "urn:x-people:" . $provider->getId() . ":" . $entries[$k]['id'];
        }

        return $entries;
    }

    public function parseScope($entry)
    {
        $data = explode(":", $entry);
        if (4 !== count($data)) {
            throw new ProxyException("invalid_request", "malformed identifier");
        }
        if ("urn" !== $data[0]) {
            throw new ProxyException("invalid_request", "malformed identifier");
        }
        if ("x-people" !== $data[1] && "x-groups" !== $data[1]) {
            throw new ProxyException("invalid_request", "malformed identifier");
        }

        return $data;
    }

}
