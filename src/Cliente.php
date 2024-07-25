<?php

namespace PedroQuezado\Code\Correios;

class Cliente
{
    private $usuario;
    private $senha;
    private $numeroCartaoPostagem;
    private $token;
    private $tokenExpiration;
    private $baseUrl;
    private $produtos = [];

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

    public function inserirProduto($coProduto, array $arrProduto) 
    {
        $arrProduto['coProduto'] = $coProduto;
        $this->produtos[] = $arrProduto;
    }

    public function consultarPreco()
    {
        $this->verificarToken();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/preco/v1/nacional");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "idLote" => "1",
            "parametrosProduto" => $this->produtos
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

    public function consultarPrazo($dataPostagem, $cepOrigem, $cepDestino, $dtEvento = null)
    {
        $this->verificarToken();

        $dtEvento = $dtEvento ? $dtEvento : date("d-m-Y", strtotime($dataPostagem));

        $parametrosPrazo = array_map(function ($produto) use ($dataPostagem, $cepOrigem, $cepDestino, $dtEvento) {
            return [
                "coProduto" => $produto['coProduto'],
                "nuRequisicao" => $produto['nuRequisicao'],
                "dtEvento" => $dtEvento,
                "cepOrigem" => $cepOrigem,
                "cepDestino" => $cepDestino,
                "dataPostagem" => $dataPostagem
            ];
        }, $this->produtos);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/prazo/v1/nacional");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "idLote" => "1",
            "parametrosPrazo" => $parametrosPrazo
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ClienteException('Erro ao consultar prazo: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200) {
            throw new ClienteException('Erro ao consultar prazo', $httpCode, $response);
        }

        return json_decode($response, true);
    }
}
