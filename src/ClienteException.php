<?php

namespace PedroQuezado\Code\Correios;

use Exception;

class ClienteException extends Exception
{
    protected $httpCode;
    protected $response;

    public function __construct($message = "", $httpCode = 0, $response = null, $code = 0, Exception $previous = null)
    {
        $this->httpCode = $httpCode;
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function __toString()
    {
        $responseDetails = is_array($this->response) ? json_encode($this->response) : $this->response;
        return __CLASS__ . ": [{$this->code}]: {$this->message}\nHTTP Code: {$this->httpCode}\nResponse: {$responseDetails}\n";
    }
}
