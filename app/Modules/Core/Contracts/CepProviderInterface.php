<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\DTOs\EnderecoCepDTO;
use App\Modules\Core\Exceptions\CepProviderException;

interface CepProviderInterface
{
    /**
     * @param  string  $cep  Somente os 8 dígitos.
     * @return EnderecoCepDTO|null null quando o CEP não existe na base do provedor.
     *
     * @throws CepProviderException quando o provedor está indisponível ou responde com erro.
     */
    public function buscar(string $cep): ?EnderecoCepDTO;
}
