<?php

namespace PedroQuezado\Code\Correios;

use PedroQuezado\Code\Correios\ClienteException;

class Cliente
{
    private $usuario;
    private $senha;
    private $numeroCartaoPostagem;
    private $token;
    private $tokenExpiration;
    private $baseUrl;

    public function __construct($usuario, $senha, $numeroCartaoPostagem, $producao = true)
    {
        $this->usuario = $usuario;
        $this->senha = $senha;
        $this->numeroCartaoPostagem = $numeroCartaoPostagem;
        $this->baseUrl = $producao ? "https://api.correios.com.br" : "https://apihom.correios.com.br";
        $this->token = $this->obterToken();
    }

    private function obterToken()
    {
        $credenciais = base64_encode("{$this->usuario}:{$this->senha}");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/token/v1/autentica/cartaopostagem");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["numero" => $this->numeroCartaoPostagem]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Basic $credenciais"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ClienteException('Erro ao obter token: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 201) {
            throw new ClienteException('Erro ao obter token', $httpCode, $response);
        }

        $responseDecoded = json_decode($response, true);
        $this->tokenExpiration = time() + 3600; // Supondo que o token expira em 1 hora

        return $responseDecoded['token'];
    }

    private function verificarToken()
    {
        if (time() >= $this->tokenExpiration) {
            $this->token = $this->obterToken();
        }
    }

    public function adicionarProduto($parametrosProduto)
    {
        return $parametrosProduto;
    }

    public function consultarPreco(array $parametrosProduto)
    {
        $this->verificarToken();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/preco/v1/nacional");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "idLote" => "1",
            "parametrosProduto" => $parametrosProduto
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ClienteException('Erro ao consultar preço: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200) {
            throw new ClienteException('Erro ao consultar preço', $httpCode, $response);
        }

        return json_decode($response, true);
    }
}
