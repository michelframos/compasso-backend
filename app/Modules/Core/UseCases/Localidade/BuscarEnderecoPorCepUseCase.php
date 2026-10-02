<?php

namespace App\Modules\Core\UseCases\Localidade;

use App\Modules\Core\Contracts\CepProviderInterface;
use App\Modules\Core\DTOs\EnderecoCepDTO;
use App\Modules\Core\Exceptions\CepProviderException;
use App\Modules\Core\Models\Cidade;
use App\Modules\Core\Models\Estado;
use Illuminate\Support\Facades\Cache;

class BuscarEnderecoPorCepUseCase
{
    private const CACHE_TTL_DIAS = 30;

    public function __construct(
        private readonly CepProviderInterface $provider,
    ) {}

    /**
     * @return array{endereco: EnderecoCepDTO, id_estado: int|null, id_cidade: int|null}|null
     *
     * @throws CepProviderException
     */
    public function execute(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return null;
        }

        $dados = Cache::remember(
            "cep:{$cep}",
            now()->addDays(self::CACHE_TTL_DIAS),
            fn () => $this->provider->buscar($cep)?->toArray(),
        );

        if ($dados === null) {
            return null;
        }

        $endereco = EnderecoCepDTO::fromArray($dados);
        $cidade = $this->resolverCidade($endereco);

        return [
            'endereco' => $endereco,
            'id_estado' => $cidade?->id_estado ?? $this->resolverEstado($endereco),
            'id_cidade' => $cidade?->id,
        ];
    }

    private function resolverEstado(EnderecoCepDTO $endereco): ?int
    {
        if ($endereco->uf === null) {
            return null;
        }

        return Estado::where('sigla', $endereco->uf)->value('id');
    }

    private function resolverCidade(EnderecoCepDTO $endereco): ?Cidade
    {
        if ($endereco->codigoIbge !== null) {
            $cidade = Cidade::where('codigo_ibge', $endereco->codigoIbge)->first();

            if ($cidade) {
                return $cidade;
            }
        }

        if ($endereco->cidade === null || $endereco->uf === null) {
            return null;
        }

        return Cidade::query()
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($endereco->cidade)])
            ->whereHas('estado', fn ($q) => $q->where('sigla', $endereco->uf))
            ->first();
    }
}
