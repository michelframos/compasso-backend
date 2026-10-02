<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Camerata API',
    description: 'API monólito modular multi-tenant (SaaS). Header X-Tenant-Slug obrigatório nas rotas de domínio. Tags = módulos: Core, Pessoas, Academico, Financeiro, Comercial, Espetaculos, Instrumentos, Notificacoes, Relatorios.',
    contact: new OA\Contact(email: 'contato@camerata.local')
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(name: 'Core', description: 'Auth, usuários, localidades, configuração da empresa')]
#[OA\Tag(name: 'Pessoas', description: 'Alunos, professores, responsáveis e medidas')]
#[OA\Tag(name: 'Academico', description: 'Cursos, níveis, turmas, matrículas, aulas e materiais')]
#[OA\Tag(name: 'Financeiro', description: 'Contas, pagamentos, categorias, contratos e PIX')]
#[OA\Tag(name: 'Comercial', description: 'Leads e funil comercial')]
#[OA\Tag(name: 'Espetaculos', description: 'Espetáculos e apresentações')]
#[OA\Tag(name: 'Instrumentos', description: 'Inventário e empréstimos de instrumentos')]
#[OA\Tag(name: 'Notificacoes', description: 'WhatsApp e regras de disparo')]
#[OA\Tag(name: 'Relatorios', description: 'Dashboard e relatórios de leitura')]
class SwaggerDocs
{
}
