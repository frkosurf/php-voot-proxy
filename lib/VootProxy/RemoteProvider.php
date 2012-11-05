<?php

namespace VootProxy;

use \RestService\Utils\Config as Config;
use \OAuth\RemoteResourceServer as RemoteResourceServer;

class RemoteProvider
{
    private $_config;
    private $_provider;
    private $_remoteResourceServer;

    public function __construct(Config $c, ProviderRegistration $p, RemoteResourceServer $r)
    {
        $this->_config = $c;
        $this->_provider = $p;
        $this->_remoteResourceServer = $r;
    }

    public function getGroups($providerUserId)
    {
        $requestUri = $provider->getEndpoint() . "/groups/" . $providerUserId;

        return $this->_makeVootRequest($requestUri);
    }

    public function getPeople($providerUserId, $providerGroupId)
    {
        $requestUri = $provider->getEndpoint() . "/people/" . $providerUserIdentifier . "/" . $providerGroupId;

        return $this->_makeVootRequest($provider, $requestUri);
    }

    private function _makeVootRequest($requestUri)
    {
        // check to see if authenticated user is allowed to use this provider
        $providerFilter = $this->_provider->getFilter();
        if (!empty($providerFilter)) {
            $filterAttributeName = $this->_config->getValue('groupProviderFilterAttributeName', FALSE);
            if (NULL === $filterAttributeName) {
                return FALSE;
            }
            $filterAttributeValue = $this->_remoteResourceServer->getAttribute($filterAttributeName);
            if (NULL === $filterAttributeValue || !in_array($filterAttributeValue[0], $providerFilter)) {
                return FALSE;
            }
        }

        $request = new HttpRequest($requestUri);
        $request->setHeader("Authorization", "Basic " . base64_encode($this->_provider->getBasicUser() . ":" . $this->_provider->getBasicPass()));

        // FIXME: implement logging
        //if (NULL !== $this->_logger) {
        //    $this->_logger->logDebug($request);
        //}

        $response = NULL;
        try {
            $response = OutgoingHttpRequest::makeRequest($request);
        } catch (OutgoingHttpRequestException $e) {
            throw new RemoteProviderException("provider_error", $e->getMessage(), $this->_provider, $request, $response);
        }

        // FIXME: logging
        //if (NULL !== $this->_logger) {
        //    $this->_logger->logDebug($response);
        //}

        // validate HTTP response code
        if (200 !== $response->getStatusCode()) {
            throw new RemoteProviderException("provider_error", "unexpected response code", $this->_provider, $request, $response);
        }

        // validate we got JSON back
        $jsonResponse = json_decode($response->getContent(), TRUE);
        if (NULL === $jsonResponse) {
            throw new RemoteProviderException("provider_error", "no JSON response", $this->_provider, $request, $response);
        }

        // validate JSON structure
        $requiredFields = array ('entry', 'startIndex', 'itemsPerPage', 'totalResults');
        foreach ($requiredFields as $f) {
            if (!array_key_exists($f, $jsonResponse)) {
                throw new RemoteProviderException("provider_error", "missing required JSON fields", $this->_provider, $request, $response);
            }
        }

        if ($jsonResponse['itemsPerPage'] !== count($jsonResponse['entry'])) {
            throw new RemoteProviderException("provider_error", "unexpected itemsPerPage value", $this->_provider, $request, $response);
        }
        if ($jsonResponse['totalResults'] < $jsonResponse['itemsPerPage']) {
            throw new RemoteProviderException("provider_error", "unexpected totalResults value", $this->_provider, $request, $response);
        }
        // FIXME: add more validation checks
        return $jsonResponse['entry'];
    }

}
