<?php

namespace VootProxy;

class RemoteProviderException extends \Exception
{
    private $_description;
    private $_provider;
    private $_request;
    private $_response;

    public function __construct($message, $description, Provider $provider = NULL, HttpRequest $request = NULL, HttpResponse $response = NULL, $code = 0, Exception $previous = null)
    {
        $this->_description = $description;
        parent::__construct($message, $code, $previous);
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getResponseCode()
    {
        switch ($this->message) {
            case "provider_error":
                return 500;
            case "not_found":
                return 404;
            case "invalid_request":
                return 400;
            case "forbidden":
                return 403;
            default:
                return 400;
        }
    }

    public function getLogMessage($includeTrace = FALSE)
    {
        $msg = 'Message    : ' . $this->getMessage() . PHP_EOL .
               'Description: ' . $this->getDescription() . PHP_EOL .
               'Provider   : ' . $this->_provider->getName() . " (" . $this->_provider->getId() . ")" . PHP_EOL
               'Request    : ' . PHP_EOL . $this->_request . PHP_EOL .
               'Response   : ' . PHP_EOL . $this->_response . PHP_EOL;
        if ($includeTrace) {
            $msg .= 'Trace      : ' . PHP_EOL . $this->getTraceAsString() . PHP_EOL;
        }

        return $msg;
    }

}
