<?php

namespace App\Modules\Financeiro\Http\Resources;

use App\Modules\Financeiro\Models\Conta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ContaDoAlunoResource',
    title: 'Conta do aluno',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_matricula', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'descricao', type: 'string', example: 'Mensalidade de outubro'),
        new OA\Property(property: 'valor', type: 'number', format: 'float', example: 100),
        new OA\Property(property: 'saldo_devedor', type: 'number', format: 'float', example: 100),
        new OA\Property(property: 'data_vencimento', type: 'string', format: 'date', example: '2026-10-01'),
        new OA\Property(property: 'status', type: 'string', example: 'pendente'),
        new OA\Property(property: 'situacao', type: 'string', enum: ['atrasada', 'vence_hoje', 'a_vencer', 'paga']),
        new OA\Property(property: 'aluno', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'pagamentos', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'valor_pago', type: 'number', format: 'float'),
            new OA\Property(property: 'data_pagamento', type: 'string', format: 'date'),
            new OA\Property(property: 'forma_pagamento', type: 'string', example: 'pix'),
        ])),
    ]
)]
class ContaDoAlunoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Conta $conta */
        $conta = $this->resource;
        $pago = round((float) $conta->pagamentos->sum('valor_pago'), 2);

        return [
            'id' => $conta->id,
            'id_matricula' => $conta->id_matricula,
            'descricao' => $conta->descricao,
            'valor' => (float) $conta->valor,
            'saldo_devedor' => round((float) $conta->valor - $pago, 2),
            'data_vencimento' => $conta->data_vencimento?->toDateString(),
            'status' => $conta->status,
            'situacao' => $this->situacao($conta),
            'aluno' => $this->whenLoaded('aluno', fn () => [
                'id' => $conta->aluno?->id,
                'nome' => $conta->aluno?->usuario?->nome,
            ]),
            'pagamentos' => $conta->pagamentos
                ->sortByDesc(fn ($pagamento) => $pagamento->data_pagamento.' '.$pagamento->id)
                ->values()
                ->map(fn ($pagamento) => [
                    'id' => $pagamento->id,
                    'valor_pago' => (float) $pagamento->valor_pago,
                    'data_pagamento' => substr((string) $pagamento->data_pagamento, 0, 10),
                    'forma_pagamento' => $pagamento->forma_pagamento,
                ])
                ->all(),
        ];
    }

    private function situacao(Conta $conta): string
    {
        if ($conta->status === 'pago') {
            return 'paga';
        }

        $data = $conta->data_vencimento?->toDateString();
        $hoje = now()->toDateString();

        if ($conta->status === 'vencido' || ($data !== null && $data < $hoje)) {
            return 'atrasada';
        }

        if ($data === $hoje) {
            return 'vence_hoje';
        }

        return 'a_vencer';
    }
}
