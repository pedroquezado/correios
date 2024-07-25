# Correios API Client - PedroQuezado

[![Maintainer](http://img.shields.io/badge/maintainer-@pedroquezado-blue.svg?style=flat-square)](https://github.com/pedroquezado)
[![Source Code](http://img.shields.io/badge/source-pedroquezado/correios-blue.svg?style=flat-square)](https://github.com/pedroquezado/correios)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/pedroquezado/correios.svg?style=flat-square)](https://packagist.org/packages/pedroquezado/correios)
[![Latest Version](https://img.shields.io/github/release/pedroquezado/correios.svg?style=flat-square)](https://github.com/pedroquezado/correios/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://img.shields.io/scrutinizer/build/g/pedroquezado/correios.svg?style=flat-square)](https://scrutinizer-ci.com/g/pedroquezado/correios)
[![Quality Score](https://img.shields.io/scrutinizer/g/pedroquezado/correios.svg?style=flat-square)](https://scrutinizer-ci.com/g/pedroquezado/correios)
[![Total Downloads](https://img.shields.io/packagist/dt/pedroquezado/correios.svg?style=flat-square)](https://packagist.org/packages/pedroquezado/correios)


## Introduction

This package provides an easy-to-use PHP client for interacting with the Correios API. It allows you to fetch pricing and delivery times for different products offered by Correios.

## Installation

First, you need to add the package to your project using Composer. 

```bash
composer require pedroquezado/correios
```

## Usage

### Initialize the Client

To initialize the `Cliente` class, you need to provide your Correios API credentials (username and password), the card number, and an optional fourth parameter to specify the environment (production or homologation). By default, the client uses the production environment.

#### Example

```php
require 'vendor/autoload.php';

use PedroQuezado\Code\Correios\Cliente;

// Initialize for production (default)
$cliente = new Cliente('correios_usuario', 'correios_senha_key', 'numero_cartao_postagem');

// Initialize for homologation
$clienteHomologacao = new Cliente('correios_usuario', 'correios_senha_key', 'numero_cartao_postagem', false);
```

- **correios_usuario**: Your Correios API username.
- **correios_senha_key**: Your Correios API password.
- **numero_cartao_postagem**: Your Correios card number.
- **producao (optional)**: Boolean flag to specify the environment. Use `true` for production (default) and `false` for homologation.

### Adding Products

The `inserirProduto` method allows you to add products to the client for further operations, such as price and delivery time calculations. The method accepts two parameters: `$coProduto` and an array `$arrProduto` containing the product details.

#### Parameters

- **$coProduto**: This is the product code (e.g., '03220' for SEDEX or '03298' for PAC). It is crucial for identifying the product type in subsequent operations.
- **$arrProduto**: An associative array containing the product details, including:
  - **nuRequisicao**: Request number.
  - **cepOrigem**: Origin postal code.
  - **cepDestino**: Destination postal code.
  - **psObjeto**: Object weight in grams.
  - **tpObjeto**: Object type (e.g., '1' for Box/Package).
  - **comprimento**: Object length in centimeters.
  - **largura**: Object width in centimeters.
  - **altura**: Object height in centimeters.
  - **servicosAdicionais**: Array of additional services (e.g., ["001", "019"]).
  - **vlDeclarado**: Declared value.
  - **dtEvento**: Event date (DD/MM/YYYY).

#### Example

```php
$cliente->inserirProduto('03220', [
    "nuRequisicao" => "1",
    "cepOrigem" => "70002900",
    "cepDestino" => "05311900",
    "psObjeto" => "8000",
    "tpObjeto" => "1", // Caixa/Pacote
    "comprimento" => "20",
    "largura" => "20",
    "altura" => "20",
    "servicosAdicionais" => ["001", "019"], // Valor Declarado adicionado
    "vlDeclarado" => "100",
    "dtEvento" => "18/03/2022"
]);

$cliente->inserirProduto('03298', [
    "nuRequisicao" => "2",
    "cepOrigem" => "70002900",
    "cepDestino" => "05311900",
    "psObjeto" => "5000",
    "tpObjeto" => "1", // Caixa/Pacote
    "comprimento" => "30",
    "largura" => "30",
    "altura" => "30",
    "servicosAdicionais" => ["001", "064"], // Valor Declarado adicionado
    "vlDeclarado" => "200",
    "dtEvento" => "18/03/2022"
]);
```

#### Importance in Other Methods

The `$coProduto` parameter is essential for the `consultarPrecoTotal` and `consultarPrazoTotal` methods. These methods use `$coProduto` to filter and calculate the total price or delivery time for the specified product type. By organizing products with `$coProduto`, you can easily manage and retrieve detailed information for specific product types.

### Fetching Prices

You can fetch the prices for the added products using the `consultarPreco` method.

```php
try {
    $precos = $cliente->consultarPreco();
    print_r($precos);
} catch (ClienteException $e) {
    echo 'Erro: ' . $e->getMessage();
}
```

### Fetching Delivery Time

The `consultarPrazo` method allows you to calculate the delivery time for your products. It takes four parameters:

1. **$dataPostagem**: The posting date in the format YYYY-MM-DD.
2. **$cepOrigem**: The origin postal code.
3. **$cepDestino**: The destination postal code.
4. **$dtEvento**: (Optional) The event date in the format DD-MM-YYYY. If not provided, it defaults to the posting date converted to the required format.

#### Example

```php
try {
    $prazo = $cliente->consultarPrazo("2024-07-25", "70002900", "05311900");
    print_r($prazo);
} catch (ClienteException $e) {
    echo 'Erro: ' . $e->getMessage();
}
```

#### Parameters Explanation

- **$dataPostagem**: The date when the package is sent. It is essential for calculating the estimated delivery time.
- **$cepOrigem**: The postal code from where the package is sent.
- **$cepDestino**: The postal code to where the package is being delivered.
- **$dtEvento**: This parameter is optional. If not provided, the method will automatically set it to the value of `$dataPostagem` formatted as DD-MM-YYYY. It represents the date of the event related to the delivery.

By calling `consultarPrazo`, you can obtain the estimated delivery time and other related information for each product you have added. The method returns detailed delivery time information, which can be used to inform customers about their expected delivery dates.

### Fetching Total Price

You can fetch the total price for a specific product code using the `consultarPrecoTotal` method.

```php
try {
    $precoTotal03220 = $cliente->consultarPrecoTotal('03220');
    echo 'Preço total para o produto 03220: ' . $precoTotal03220 . PHP_EOL;

    $precoTotal03298 = $cliente->consultarPrecoTotal('03298');
    echo 'Preço total para o produto 03298: ' . $precoTotal03298 . PHP_EOL;
} catch (ClienteException $e) {
    echo 'Erro: ' . $e->getMessage();
}
```

### Fetching Total Delivery Time

You can fetch the total delivery time for a specific product code using the `consultarPrazoTotal` method.

```php
try {
    $prazoTotal03220 = $cliente->consultarPrazoTotal('03220');
    echo 'Prazo total para o produto 03220: ' . $prazoTotal03220['prazoEntrega'] . PHP_EOL;

    $prazoTotal03298 = $cliente->consultarPrazoTotal('03298');
    echo 'Prazo total para o produto 03298: ' . $prazoTotal03298['prazoEntrega'] . PHP_EOL;
} catch (ClienteException $e) {
    echo 'Erro: ' . $e->getMessage();
}
```

## Exception Handling

All exceptions are handled using the `ClienteException` class. This class provides additional information such as the HTTP code and the response from the API.

```php
try {
    $precos = $cliente->consultarPreco();
    print_r($precos);

    $prazo = $cliente->consultarPrazo("2024-07-25", "70002900", "05311900");
    print_r($prazo);

    $precoTotal03220 = $cliente->consultarPrecoTotal('03220');
    echo 'Preço total para o produto 03220: ' . $precoTotal03220 . PHP_EOL;

    $precoTotal03298 = $cliente->consultarPrecoTotal('03298');
    echo 'Preço total para o produto 03298: ' . $precoTotal03298 . PHP_EOL;

    $prazoTotal03220 = $cliente->consultarPrazoTotal('03220');
    echo 'Prazo total para o produto 03220: ' . $prazoTotal03220['prazoEntrega'] . PHP_EOL;

    $prazoTotal03298 = $cliente->consultarPrazoTotal('03298');
    echo 'Prazo total para o produto 03298: ' . $prazoTotal03298['prazoEntrega'] . PHP_EOL;
} catch (ClienteException $e) {
    echo 'Erro: ' . $e->getMessage();
    echo 'HTTP Code: ' . $e->getHttpCode();
    echo 'Response: ' . $e->getResponse();
}
```

## Documentation

For more detailed information, please refer to the official Correios API documentation:
- [Correios API Documentation](https://cws.correios.com.br/dashboard) (requires login)
- [Manual para Integração Correios API (PDF)](https://www.correios.com.br/atendimento/developers/arquivos/manual-para-integracao-correios-api)

## Conclusion

This package simplifies the process of interacting with the Correios API by providing a structured and easy-to-use client. It supports fetching prices and delivery times for various products, handling authentication, and providing detailed error handling.
