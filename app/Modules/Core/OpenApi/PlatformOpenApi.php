<?php

namespace App\Modules\Core\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Documentação OpenAPI do painel Platform (super-admin).
 * Endpoints em /api/platform/* exigem Bearer token e is_super_admin = true.
 * Não enviar o header X-Tenant-Slug nestas rotas.
 */
#[OA\Tag(
    name: 'Platform',
    description: 'Painel super-admin: CRUD de escolas e planos, trial customizado, impersonação de usuários. Requer auth:sanctum + middleware super_admin. Não usar X-Tenant-Slug.'
)]
#[OA\Schema(
    schema: 'PlatformConfigResponse',
    properties: [
        new OA\Property(property: 'default_trial_days', type: 'integer', example: 14),
        new OA\Property(
            property: 'modulos_app',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'key', type: 'string'),
                    new OA\Property(property: 'label', type: 'string'),
                    new OA\Property(property: 'descricao', type: 'string'),
                ],
                type: 'object'
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'ImpersonatePlatformUserRequest',
    required: ['user_id'],
    properties: [
        new OA\Property(property: 'user_id', type: 'integer', description: 'ID do usuário alvo na escola', example: 5),
    ]
)]
#[OA\Schema(
    schema: 'ImpersonatePlatformUserResponse',
    properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'tenant', ref: '#/components/schemas/InstituicaoResource'),
        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
        new OA\Property(
            property: 'impersonation',
            properties: [
                new OA\Property(property: 'log_id', type: 'integer'),
                new OA\Property(
                    property: 'super_admin',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'nome', type: 'string'),
                    ],
                    type: 'object'
                ),
            ],
            type: 'object'
        ),
    ]
)]
#[OA\Schema(
    schema: 'StopImpersonationResponse',
    properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
    ]
)]
class PlatformOpenApi
{
}
