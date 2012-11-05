<?php

namespace VootProxy;

use \RestService\Utils\Config as Config;
use \RestService\Utils\Logger as Logger;
use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\HttpResponse as HttpResponse;

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
        $rsConfig += array("throwException" => TRUE);

        $this->_resourceServer = new RemoteResourceServer($rsConfig);
    }

    public function getGroups(HttpRequest $request)
    {
        $response = new HttpResponse();
        $response->setContentType("application/json");

        $this->_resourceServer->verifyAuthorizationHeader($request->getHeader("Authorization"));
        $this->_resourceServer->requireScope("read");

        $sortBy = $request->getQueryParameter("sortBy");
        $sortOrder = $request->getQueryParameter("sortOrder");
        $startIndex = $request->getQueryParameter("startIndex");
        $count = $request->getQueryParameter("count");

        $allEntries = array();

        $providerUserId = $this->_resourceServer->getAttribute($this->_config->getValue('groupProviderQueryAttributeName'));

        $providers = $this->_storage->getProviders();
        foreach ($providers as $p) {
            $provider = Provider::fromArray($p);
            try {
                $remoteProvider = new RemoteProvider($this->_config, $this->_logger, $provider, $this->_resourceServer);
                $allEntries += $remoteProvider->getGroups($providerUserId[0]);
            } catch (RemoteProviderException $e) {
                // ignore provider errors, just try next provider
                continue;
            }
        }

        $totalResults = count($allEntries);
        $sortedEntries = $this->sortEntries($allEntries, $sortBy, $sortOrder);
        $limitedSortedEntries = $this->limitEntries($sortedEntries, $startIndex, $count);
        $scopedLimitedSortedEntries = $this->addGroupsScope($provider, $limitedSortedEntries);

        $responseData = array (
            "entry" => $scopedLimitedSortedEntries,
            "itemsPerPage" => count($scopedLimitedSortedEntries),
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

        $this->_resourceServer->verifyAuthorizationHeader($request->getHeader("Authorization"));
        $this->_resourceServer->requireScope("read");

        $sortBy = $request->getQueryParameter("sortBy");
        $sortOrder = $request->getQueryParameter("sortOrder");
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

        $providerUserId = $this->_resourceServer->getAttribute($this->_config->getValue('groupProviderQueryAttributeName'));

        $entries = NULL;
        try {
            $remoteProvider = new RemoteProvider($this->_config, $this->_logger, $provider, $this->_resourceServer);
            $entries = $remoteProvider->getPeople($providerUserId[0], $providerGroupId);
        } catch (RemoteProviderException $e) {
            // FIXME: should we just let this go, or error??!
            $entries = array();
        }
        $totalResults = count($entries);

        $sortedEntries = $this->sortEntries($entries, $sortBy, $sortOrder);
        $limitedSortedEntries = $this->limitEntries($sortedEntries, $startIndex, $count);
        $scopedLimitedSortedEntries = $this->addPeopleScope($provider, $limitedSortedEntries);

        $responseData = array (
            "entry" => $scopedLimitedSortedEntries,
            "itemsPerPage" => count($scopedLimitedSortedEntries),
            "totalResults" => $totalResults,
            "startIndex" => $startIndex
        );
        $response->setContent(json_encode($responseData));

        return $response;
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
            throw new ProxyException("invalid_request", "malformed identifier");
        }
        if ("urn" !== $data[0]) {
            throw new ProxyException("invalid_request", "malformed identifier");
        }
        if ("people" !== $data[1] && "groups" !== $data[1]) {
            throw new ProxyException("invalid_request", "malformed identifier");
        }

        return $data;
    }

}
