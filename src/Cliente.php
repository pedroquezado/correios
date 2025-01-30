<?php

namespace PedroQuezado\Code\Correios;

use \CURLFile;

/**
 * Classe Cliente para integração com os serviços dos Correios.
 */
class Cliente
{
	/**
	 * @var string $usuario Usuário de autenticação.
	 */
	private $usuario;

	/**
	 * @var string $senha Senha de autenticação.
	 */
	private $senha;

	/**
	 * @var string $numeroCartaoPostagem Número do cartão de postagem.
	 */
	private $numeroCartaoPostagem;

	/**
	 * @var string $token Token de autenticação.
	 */
	private $token;

	/**
	 * @var int $tokenExpiration Timestamp de expiração do token.
	 */
	private $tokenExpiration;

	/**
	 * @var string $baseUrl URL base da API dos Correios.
	 */
	private $baseUrl;

	/**
	 * @var array $produtos Lista de produtos a serem consultados.
	 */
	private $produtos = [];

	/**
	 * @var array $respostaPreco Resposta da consulta de preços.
	 */
	private $respostaPreco;

	/**
	 * @var array $respostaPrazo Resposta da consulta de prazos.
	 */
	private $respostaPrazo;

	/**
	 * Construtor da classe Cliente.
	 *
	 * @param string $usuario Usuário de autenticação.
	 * @param string $senha Senha de autenticação.
	 * @param string $numeroCartaoPostagem Número do cartão de postagem.
	 * @param bool $producao Indica se é ambiente de produção (true) ou homologação (false).
	 */
	public function __construct($usuario, $senha, $numeroCartaoPostagem, $producao = true)
	{
		$this->usuario = $usuario;
		$this->senha = $senha;
		$this->numeroCartaoPostagem = $numeroCartaoPostagem;
		$this->baseUrl = $producao ? "https://api.correios.com.br" : "https://apihom.correios.com.br";
		$this->token = $this->obterToken();
	}

	/**
	 * Obtém o token de autenticação.
	 *
	 * @return string Token de autenticação.
	 * @throws ClienteException Em caso de erro na obtenção do token.
	 */
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

		if (is_string($response)) {
			$responseDecoded = json_decode($response, true);
			$this->tokenExpiration = time() + 3600; // Supondo que o token expira em 1 hora
		} else {
			throw new ClienteException('Erro ao obter token: resposta inválida recebida.');
		}

		return $responseDecoded['token'];
	}

	/**
	 * Verifica a validade do token e renova se necessário.
	 */
	private function verificarToken()
	{
		if (time() >= $this->tokenExpiration) {
			$this->token = $this->obterToken();
		}
	}

	/**
	 * Insere um produto na lista de produtos a serem consultados.
	 *
	 * @param string $coProduto Código do produto.
	 * @param array $arrProduto Dados do produto.
	 */
	public function inserirProduto($coProduto, array $arrProduto) 
	{
		$arrProduto['coProduto'] = $coProduto;
		$this->produtos[] = $arrProduto;
	}

	/**
	 * Consulta o preço dos produtos inseridos dividindo-os em lotes.
	 * 
	 * @return array A resposta da consulta de preço.
	 * @throws ClienteException Se ocorrer um erro durante a consulta.
	 */
	public function consultarPreco()
	{
		if (empty($this->produtos)) {
			throw new ClienteException('Nenhum produto inserido para consulta de preço.');
		}

		$this->verificarToken();
		$chunks = $this->criarChunksDeProdutosPreco($this->produtos);
		$this->respostaPreco = $this->consultarPrecoEmChunks($chunks);
		return $this->respostaPreco;
	}

	/**
	 * Cria lotes de produtos divididos em grupos de no máximo 5 itens.
	 * 
	 * @param array $produtos Os produtos a serem divididos em lotes.
	 * @return array Os produtos divididos em lotes de no máximo 5 itens.
	 */
	private function criarChunksDeProdutosPreco(array $produtos)
	{
		return array_chunk($produtos, 5);
	}

	/**
	 * Consulta o preço dos produtos em lotes e retorna as respostas.
	 * 
	 * @param array $chunks Os lotes de produtos a serem consultados.
	 * @return array As respostas da consulta de preço.
	 * @throws ClienteException Se ocorrer um erro durante a consulta.
	 */
	private function consultarPrecoEmChunks(array $chunks)
	{
		$responses = [];

		foreach ($chunks as $chunk) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/preco/v1/nacional");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				"idLote" => "1",
				"parametrosProduto" => $chunk
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

			if (is_string($response)) {
				$responses[] = json_decode($response, true);
			} else {
				throw new ClienteException('Erro ao consultar preço: resposta inválida recebida.');
			}
		}

		return array_merge(...$responses);
	}

	/**
	 * Consulta o preço total de um produto específico.
	 *
	 * @param string $coProduto Código do produto.
	 * @return float Preço total do produto.
	 * @throws ClienteException Em caso de erro na consulta de preços ou se preços não foram consultados ainda.
	 */
	public function consultarPrecoTotal($coProduto)
	{
		if (empty($this->respostaPreco)) {
			throw new ClienteException('Preço não foi consultado ainda.');
		}

		$total = 0;
		foreach ($this->respostaPreco as $produto) {
			if ($produto['coProduto'] === $coProduto) {
				$total += (float)str_replace(',', '.', $produto['pcFinal']);
			}
		}

		return $total;
	}

	/**
	 * Consulta os prazos de entrega dos produtos inseridos.
	 *
	 * @param string $dataPostagem Data de postagem no formato YYYY-MM-DD.
	 * @param string $cepOrigem CEP de origem.
	 * @param string $cepDestino CEP de destino.
	 * @param string|null $dtEvento Data do evento no formato DD-MM-YYYY (opcional).
	 * @return array Resposta da consulta de prazos.
	 * @throws ClienteException Em caso de erro na consulta de prazos.
	 */
	public function consultarPrazo($dataPostagem, $cepOrigem, $cepDestino, $dtEvento = null)
	{
		if (empty($this->produtos)) {
			throw new ClienteException('Nenhum produto inserido para consulta de prazo.');
		}

		$this->verificarToken(); // Verifica se o token de autenticação é válido e, se necessário, renova o token.
		$dtEvento = $dtEvento ? $dtEvento : date("d-m-Y", strtotime($dataPostagem)); // Define a data do evento. Se $dtEvento não for fornecido, usa a data de postagem convertida para o formato DD-MM-YYYY.

		$chunks = $this->criarChunksDeProdutosPrazo($dataPostagem, $cepOrigem, $cepDestino, $dtEvento);

		$responses = $this->consultarPrazoEmChunks($chunks);

		$this->respostaPrazo = array_merge(...$responses);
		return $this->respostaPrazo;
	}

	/**
	 * Cria lotes de produtos divididos em grupos de no máximo 5 itens.
	 * 
	 * @param string $dataPostagem A data de postagem no formato YYYY-MM-DD.
	 * @param string $cepOrigem O CEP de origem do envio.
	 * @param string $cepDestino O CEP de destino do envio.
	 * @param string $dtEvento A data do evento no formato DD-MM-YYYY.
	 * @return array Os produtos divididos em lotes de no máximo 5 itens.
	 */
	private function criarChunksDeProdutosPrazo($dataPostagem, $cepOrigem, $cepDestino, $dtEvento)
	{
		return array_chunk(array_map(function ($produto) use ($dataPostagem, $cepOrigem, $cepDestino, $dtEvento) {
			return [
				"coProduto" => $produto['coProduto'],
				"nuRequisicao" => $produto['nuRequisicao'],
				"dtEvento" => $dtEvento,
				"cepOrigem" => $cepOrigem,
				"cepDestino" => $cepDestino,
				"dataPostagem" => $dataPostagem
			];
		}, $this->produtos), 5);
	}

	/**
	 * Consulta o prazo de entrega dos produtos em lotes e retorna as respostas.
	 * 
	 * @param array $chunks Os lotes de produtos a serem consultados.
	 * @return array As respostas da consulta de prazo de entrega.
	 * @throws ClienteException Se ocorrer um erro durante a consulta.
	 */
	private function consultarPrazoEmChunks($chunks)
	{
		$responses = [];

		foreach ($chunks as $chunk) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/prazo/v1/nacional");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				"idLote" => "1",
				"parametrosPrazo" => $chunk
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

			if (is_string($response)) {
				$responses[] = json_decode($response, true);
			} else {
				throw new ClienteException('Erro ao consultar prazo: resposta inválida recebida.');
			}
		}

		return $responses;
	}

	/**
	 * Consulta o prazo total de entrega de um produto específico.
	 *
	 * @param string $coProduto Código do produto.
	 * @return array Prazo total de entrega do produto.
	 * @throws ClienteException Em caso de erro na consulta de prazos ou se prazos não foram consultados ainda.
	 */
	public function consultarPrazoTotal($coProduto)
	{
		if (empty($this->respostaPrazo)) {
			throw new ClienteException('Prazo não foi consultado ainda.');
		}

		foreach ($this->respostaPrazo as $produto) {
			if ($produto['coProduto'] === $coProduto) {
				return $produto;
			}
		}

		throw new ClienteException('Produto não encontrado na resposta do prazo.');
	}

	/**
	 * Realiza a pré-postagem a faturar de objetos registrados.
	 *
	 * @param array $dadosPrePostagem Dados da pré-postagem.
	 * @return array Resposta da API dos Correios.
	 * @throws ClienteException Em caso de erro na solicitação.
	 */
	public function realizarPrePostagem(array $dadosPrePostagem)
	{
		$this->verificarToken();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/prepostagem/v1/prepostagens");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosPrePostagem));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"Authorization: Bearer {$this->token}"
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new ClienteException('Erro ao realizar pré-postagem: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode != 200) {
			throw new ClienteException('Erro ao realizar pré-postagem', $httpCode, $response);
		}

		return json_decode($response, true);
	}

	/**
	 * Cancela uma pré-postagem pelo identificador.
	 *
	 * @param string $idPrePostagem Identificador da pré-postagem.
	 * @param string|null $idSolicitante ID Correios do solicitante do cancelamento (opcional).
	 * @return array Resposta da API dos Correios.
	 * @throws ClienteException Se ocorrer um erro na solicitação.
	 */
	public function cancelarPrePostagem(string $idPrePostagem, string $idSolicitante = null)
	{
	    $this->verificarToken(); // Garante que o token esteja válido

	    // Monta a URL com os parâmetros
	    $url = "{$this->baseUrl}/prepostagem/v1/prepostagens/{$idPrePostagem}";

	    if (!empty($idSolicitante)) {
	        $url .= "?idCorreiosSolicitanteCancelamento={$idSolicitante}";
	    }

	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	    curl_setopt($ch, CURLOPT_HTTPHEADER, [
	        "Content-Type: application/json",
	        "Authorization: Bearer {$this->token}"
	    ]);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    
	    $response = curl_exec($ch);

	    if (curl_errno($ch)) {
	        throw new ClienteException('Erro ao cancelar pré-postagem: ' . curl_error($ch));
	    }

	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    curl_close($ch);

	    if ($httpCode != 200) {
	        throw new ClienteException("Erro ao cancelar pré-postagem", $httpCode, $response);
	    }

	    return json_decode($response, true);
	}

	/**
	 * Realiza a pré-postagem de múltiplos objetos registrados em um único envio.
	 *
	 * @param array $listaObjetos Lista de objetos para pré-postagem.
	 * @return array Resposta da API dos Correios.
	 * @throws ClienteException Em caso de erro na solicitação.
	 */
	public function realizarPrePostagemEmLote(array $listaObjetos)
	{
		$this->verificarToken();

		$caminhoArquivo = sys_get_temp_dir() . "/prepostagem.json";

		// Salvar o JSON no arquivo
		file_put_contents($caminhoArquivo, json_encode([$listaObjetos]));

		// Criar um objeto CURLFile para o upload do arquivo
    	$arquivo = new CURLFile($caminhoArquivo, 'application/json', 'prepostagem.json');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/prepostagem/v1/prepostagens/lista/objetosregistrados");
		curl_setopt($ch, CURLOPT_POST, 1);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($listaObjetos));
		curl_setopt($ch, CURLOPT_POSTFIELDS, ["arquivo" => $arquivo]);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: multipart/form-data",
			"Authorization: Bearer {$this->token}"
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new ClienteException('Erro ao realizar pré-postagem em lote: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode != 200) {
			throw new ClienteException('Erro ao realizar pré-postagem em lote', $httpCode, $response);
		}

		return json_decode($response, true);
	}


	/**
	 * Gera o rótulo para o objeto previamente cadastrado na pré-postagem.
	 *
	 * @param string $idCorreios ID retornado na pré-postagem.
	 * @return string URL para download do rótulo.
	 * @throws ClienteException Em caso de erro na solicitação.
	 */
	public function gerarRotulo($idCorreios)
	{
		$this->verificarToken();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/prepostagem/v1/prepostagens/rotulo");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["idCorreios" => $idCorreios]));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"Authorization: Bearer {$this->token}"
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new ClienteException('Erro ao gerar rótulo: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode != 200) {
			throw new ClienteException('Erro ao gerar rótulo', $httpCode, $response);
		}

		$responseDecoded = json_decode($response, true);

		return $responseDecoded['urlEtiqueta'] ?? '';
	}
}
