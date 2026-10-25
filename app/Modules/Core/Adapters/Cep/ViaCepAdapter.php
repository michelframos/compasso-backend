<?php

namespace App\Modules\Core\Adapters\Cep;

use App\Modules\Core\Contracts\CepProviderInterface;
use App\Modules\Core\DTOs\EnderecoCepDTO;
use App\Modules\Core\Exceptions\CepProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ViaCepAdapter implements CepProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
    ) {}

    public function buscar(string $cep): ?EnderecoCepDTO
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/')."/{$cep}/json/");
        } catch (ConnectionException $e) {
            throw new CepProviderException('ViaCEP indisponível: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new CepProviderException("ViaCEP respondeu com HTTP {$response->status()}.");
        }

        $dados = $response->json();

        if (! is_array($dados)) {
            throw new CepProviderException('Resposta inválida do ViaCEP.');
        }

        if (filter_var($dados['erro'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return new EnderecoCepDTO(
            cep: $cep,
            logradouro: $dados['logradouro'] ?? null,
            complemento: $dados['complemento'] ?? null,
            bairro: $dados['bairro'] ?? null,
            cidade: $dados['localidade'] ?? null,
            uf: $dados['uf'] ?? null,
            codigoIbge: $dados['ibge'] ?? null,
        );
    }
}
