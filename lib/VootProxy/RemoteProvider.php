<?php

namespace VootProxy;

use \RestService\Utils\Config as Config;
use \RestService\Utils\Logger as Logger;
use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\OutgoingHttpRequest as OutgoingHttpRequest;

class RemoteProvider
{
    private $_config;
    private $_logger;

    public function __construct(Config $c, Logger $l)
    {
        $this->_config = $c;
        $this->_logger = $l;
    }

    public function getGroups(Provider $p, $providerUserId)
    {
        $requestUri = $p->getEndpoint() . "/groups/" . $providerUserId;

        return $this->_makeVootRequest($p, $requestUri);
    }

    public function getPeople(Provider $p, $providerUserId, $providerGroupId)
    {
        $requestUri = $p->getEndpoint() . "/people/" . $providerUserId . "/" . $providerGroupId;

        return $this->_makeVootRequest($p, $requestUri);
    }

    private function _makeVootRequest(Provider $p, $requestUri)
    {
        $request = new HttpRequest($requestUri);
        $request->setHeader("Authorization", "Basic " . base64_encode($p->getBasicUser() . ":" . $p->getBasicPass()));
        if (NULL !== $this->_logger) {
            $this->_logger->logDebug($request);
        }

        $response = NULL;
        try {
            $response = OutgoingHttpRequest::makeRequest($request);
        } catch (OutgoingHttpRequestException $e) {
            throw new RemoteProviderException("provider_error", $e->getMessage(), $p, $request, $response);
        }
        if (NULL !== $this->_logger) {
            $this->_logger->logDebug($response);
        }

        // validate HTTP response code
        if (200 !== $response->getStatusCode()) {
            throw new RemoteProviderException("provider_error", "unexpected response code", $p, $request, $response);
        }

        // validate we got JSON back
        $jsonResponse = json_decode($response->getContent(), TRUE);
        if (NULL === $jsonResponse) {
            throw new RemoteProviderException("provider_error", "no JSON response", $p, $request, $response);
        }

        if (!is_array($jsonResponse)) {
            throw new RemoteProviderException("provider_error", "unexpected JSON response", $p, $request, $response);
        }

        // validate JSON structure
        $requiredFields = array ('entry', 'startIndex', 'itemsPerPage', 'totalResults');
        foreach ($requiredFields as $f) {
            if (!array_key_exists($f, $jsonResponse)) {
                throw new RemoteProviderException("provider_error", "missing required JSON fields", $p, $request, $response);
            }
        }

        if ($jsonResponse['itemsPerPage'] !== count($jsonResponse['entry'])) {
            throw new RemoteProviderException("provider_error", "unexpected itemsPerPage value", $p, $request, $response);
        }
        if ($jsonResponse['totalResults'] < $jsonResponse['itemsPerPage']) {
            throw new RemoteProviderException("provider_error", "unexpected totalResults value", $p, $request, $response);
        }

        foreach ($jsonResponse['entry'] as $e) {
            if (!array_key_exists("id", $e) || empty($e['id'])) {
                throw new RemoteProviderException("provider_error", "required id parameter is missing from entry or empty", $p, $request, $response);
            }
        }

        // FIXME: add more validation checks
        return $jsonResponse['entry'];
    }

}
