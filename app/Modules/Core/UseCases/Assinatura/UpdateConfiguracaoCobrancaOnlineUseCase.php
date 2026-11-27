<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Models\Instituicao;
use Illuminate\Validation\ValidationException;

class UpdateConfiguracaoCobrancaOnlineUseCase
{
    public function __construct(
        private readonly GetConfiguracaoCobrancaOnlineUseCase $get,
    ) {}

    /**
     * @param  array{meios: list<string>}  $data
     * @return array{meios: list<string>, meios_disponiveis: list<string>, billing_disponivel: bool, subconta_status: string, subconta_ativa: bool}
     */
    public function execute(Instituicao $instituicao, array $data): array
    {
        $meios = array_values(array_unique($data['meios'] ?? []));

        if ($meios === []) {
            throw ValidationException::withMessages([
                'meios' => ['Selecione ao menos um meio de cobrança.'],
            ]);
        }

        if (array_diff($meios, Instituicao::MEIOS_COBRANCA_DISPONIVEIS) !== []) {
            throw ValidationException::withMessages([
                'meios' => ['Meio de cobrança inválido. Use: '.implode(', ', Instituicao::MEIOS_COBRANCA_DISPONIVEIS).'.'],
            ]);
        }

        $instituicao->forceFill(['meios_cobranca_online' => $meios])->save();

        return $this->get->execute($instituicao->refresh());
    }
}
