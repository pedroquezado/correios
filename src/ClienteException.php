<?php

namespace PedroQuezado\Code\Correios;

use Exception;

/**
 * Class ClienteException
 * 
 * Esta classe representa exceções específicas para a interação com a API dos Correios.
 * Extende a classe base Exception do PHP.
 */
class ClienteException extends Exception
{
    /**
     * @var int Código HTTP retornado pela API dos Correios.
     */
    protected $httpCode;

    /**
     * @var mixed Resposta completa retornada pela API dos Correios.
     */
    protected $response;

    /**
     * ClienteException constructor.
     *
     * @param string $message Mensagem da exceção.
     * @param int $httpCode Código HTTP retornado pela API.
     * @param mixed $response Resposta completa da API.
     * @param int $code Código da exceção.
     * @param Exception|null $previous Exceção anterior para encadeamento.
     */
    public function __construct($message = "", $httpCode = 0, $response = null, $code = 0, Exception $previous = null)
    {
        $this->httpCode = $httpCode;
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Obtém o código HTTP retornado pela API.
     *
     * @return int Código HTTP.
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Obtém a resposta completa retornada pela API.
     *
     * @return mixed Resposta da API.
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Representação da exceção como string.
     *
     * @return string Representação da exceção.
     */
    public function __toString()
    {
        $responseDetails = is_array($this->response) ? json_encode($this->response) : $this->response;
        return __CLASS__ . ": [{$this->code}]: {$this->message}\nHTTP Code: {$this->httpCode}\nResponse: {$responseDetails}\n";
    }
}
