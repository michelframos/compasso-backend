<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Support\PermissoesProfessorAulas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProfessorAutenticadoResource',
    title: 'ProfessorAutenticadoResource',
    description: 'Dados do professor autenticado na instituição ativa',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'ID do cadastro de professor', example: 1),
        new OA\Property(property: 'id_usuario', type: 'integer', example: 10),
        new OA\Property(property: 'nome', type: 'string', example: 'Ana Lima'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana@escola.com'),
        new OA\Property(property: 'foto', type: 'string', nullable: true),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'whatsapp', type: 'string', nullable: true),
        new OA\Property(property: 'permissoes_aulas', ref: '#/components/schemas/UpdatePermissoesProfessorRequest', description: 'Por ação: livre (faz direto), aprovacao (vira solicitação) ou bloqueado'),
    ]
)]
class ProfessorAutenticadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_usuario' => $this->id_usuario,
            'nome' => $this->usuario?->nome,
            'email' => $this->usuario?->email,
            'foto' => $this->usuario?->foto,
            'telefone' => $this->usuario?->telefone,
            'whatsapp' => $this->usuario?->whatsapp,
            'permissoes_aulas' => PermissoesProfessorAulas::da(InstituicaoContext::instituicao()),
        ];
    }
}
