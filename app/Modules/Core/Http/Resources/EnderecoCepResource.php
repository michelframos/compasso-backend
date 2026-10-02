<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\DTOs\EnderecoCepDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EnderecoCepResource',
    title: 'Endereço por CEP',
    description: 'Endereço encontrado para um CEP, com estado e cidade já resolvidos para os IDs locais',
    properties: [
        new OA\Property(property: 'cep', type: 'string', example: '01001-000'),
        new OA\Property(property: 'logradouro', type: 'string', nullable: true, example: 'Praça da Sé'),
        new OA\Property(property: 'complemento', type: 'string', nullable: true, example: 'lado ímpar'),
        new OA\Property(property: 'bairro', type: 'string', nullable: true, example: 'Sé'),
        new OA\Property(property: 'cidade', type: 'string', nullable: true, example: 'São Paulo'),
        new OA\Property(property: 'uf', type: 'string', nullable: true, example: 'SP'),
        new OA\Property(property: 'codigo_ibge', type: 'integer', nullable: true, example: 3550308),
        new OA\Property(property: 'id_estado', type: 'integer', nullable: true, example: 25),
        new OA\Property(property: 'id_cidade', type: 'integer', nullable: true, example: 3830),
    ]
)]
/**
 * @property array{endereco: EnderecoCepDTO, id_estado: int|null, id_cidade: int|null} $resource
 */
class EnderecoCepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EnderecoCepDTO $endereco */
        $endereco = $this->resource['endereco'];

        return [
            'cep' => $endereco->cepFormatado(),
            'logradouro' => $endereco->logradouro,
            'complemento' => $endereco->complemento,
            'bairro' => $endereco->bairro,
            'cidade' => $endereco->cidade,
            'uf' => $endereco->uf,
            'codigo_ibge' => $endereco->codigoIbge,
            'id_estado' => $this->resource['id_estado'],
            'id_cidade' => $this->resource['id_cidade'],
        ];
    }
}
