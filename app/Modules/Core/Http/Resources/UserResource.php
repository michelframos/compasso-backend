<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "UserResource",
    description: "User resource representation",
    xml: new OA\Xml(name: "UserResource"),
    properties: [
        new OA\Property(property: "id", type: "integer", description: "ID do usuÃ¡rio", example: 1),
        new OA\Property(property: "nome", type: "string", description: "Nome do usuÃ¡rio", example: "JoÃ£o Silva"),
        new OA\Property(property: "email", type: "string", format: "email", description: "Email do usuÃ¡rio", example: "joao@email.com"),
        new OA\Property(property: "role", type: "string", description: "FunÃ§Ã£o do usuÃ¡rio", example: "admin"),
        new OA\Property(property: "is_super_admin", type: "boolean", description: "Super-admin da plataforma", example: false),
        new OA\Property(property: "deve_trocar_senha", type: "boolean", description: "Exige troca de senha antes de acessar a área do usuário", example: false),
        new OA\Property(property: "foto", type: "string", description: "URL da foto do usuÃ¡rio", example: "http://example.com/foto.jpg"),
        new OA\Property(property: "whatsapp", type: "string", description: "Whatsapp", example: "(11) 99999-9999"),
        new OA\Property(property: "data_aniversario", type: "string", format: "date", description: "Data de nascimento/aniversÃ¡rio", example: "2010-01-01"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Data de criaÃ§Ã£o"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Data de atualizaÃ§Ã£o")
    ]
)]
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'role' => $this->role,
            'is_super_admin' => (bool) $this->is_super_admin,
            'deve_trocar_senha' => (bool) $this->deve_trocar_senha,
            'foto' => $this->foto,
            'telefone' => $this->telefone,
            'whatsapp' => $this->whatsapp,
            'data_aniversario' => $this->data_aniversario,
            'cep' => $this->cep,
            'rua' => $this->rua,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'id_estado' => $this->id_estado,
            'id_cidade' => $this->id_cidade,
            'responsavel_observacoes' => $this->responsavel_observacoes ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
