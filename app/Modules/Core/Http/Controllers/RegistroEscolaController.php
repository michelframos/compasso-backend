<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Auth\ReenviarCodigoAtivacaoRequest;
use App\Modules\Core\Http\Requests\Auth\RegistrarEscolaRequest;
use App\Modules\Core\UseCases\Auth\ReenviarCodigoAtivacaoUseCase;
use App\Modules\Core\UseCases\Auth\RegistrarEscolaUseCase;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RegistroEscolaController extends Controller
{
    public function __construct(
        private readonly RegistrarEscolaUseCase $registrarEscola,
        private readonly ReenviarCodigoAtivacaoUseCase $reenviarCodigo,
    ) {}

    #[OA\Post(
        path: '/api/registrar-escola',
        summary: 'Cadastro de nova escola pela tela de login (exige ativação no primeiro acesso)',
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegistrarEscolaRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Conta criada; código de ativação enviado por e-mail'),
            new OA\Response(response: 422, description: 'Erro de validação'),
            new OA\Response(response: 429, description: 'Rate limit'),
        ]
    )]
    public function store(RegistrarEscolaRequest $request): JsonResponse
    {
        $result = $this->registrarEscola->execute($request->validated());

        return response()->json([
            'message' => $result['email_enviado']
                ? 'Conta criada! Enviamos um e-mail com o código de ativação.'
                : 'Conta criada, mas não foi possível enviar o e-mail agora. Use "Reenviar código" no login.',
            'email_enviado' => $result['email_enviado'],
            'tenant' => [
                'id' => $result['instituicao']->id,
                'slug' => $result['instituicao']->slug,
                'nome_fantasia' => $result['instituicao']->nome_fantasia,
                'cnpj' => $result['instituicao']->cnpj,
                'trial_ends_at' => $result['instituicao']->trial_ends_at?->toIso8601String(),
            ],
            'user' => [
                'id' => $result['user']->id,
                'nome' => $result['user']->nome,
                'email' => $result['user']->email,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/ativacao/reenviar',
        summary: 'Reenviar o código de ativação da escola',
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Código reenviado'),
            new OA\Response(response: 401, description: 'Credenciais inválidas'),
            new OA\Response(response: 409, description: 'Escola já ativada'),
            new OA\Response(response: 503, description: 'Falha ao enviar o e-mail'),
        ]
    )]
    public function reenviarCodigo(ReenviarCodigoAtivacaoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->reenviarCodigo->execute($data['email'], $data['password'], $data['tenant_cnpj']);

        return match ($result) {
            ReenviarCodigoAtivacaoUseCase::RESULT_ENVIADO => response()->json([
                'message' => 'Enviamos um novo código de ativação para o seu e-mail.',
            ]),
            ReenviarCodigoAtivacaoUseCase::RESULT_JA_ATIVADA => response()->json([
                'message' => 'Esta escola já está ativada. Faça login normalmente.',
                'code' => 'already_activated',
            ], 409),
            ReenviarCodigoAtivacaoUseCase::RESULT_FALHA_ENVIO => response()->json([
                'message' => 'Não foi possível enviar o e-mail agora. Tente novamente em instantes.',
            ], 503),
            default => response()->json(['message' => 'Credenciais inválidas'], 401),
        };
    }
}
