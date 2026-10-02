<?php

namespace App\Modules\Pessoas\UseCases\Aluno;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\UseCases\Responsavel\ResolveOrCreateResponsavelUseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAlunoUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ResolveOrCreateResponsavelUseCase $resolveResponsavel,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    public function execute(array $data): Aluno
    {
        $this->assertDentroDoLimite();

        return DB::transaction(function () use ($data) {
            $user = $this->users->create([
                'nome' => $data['nome'],
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'role' => 'aluno',
                'telefone' => $data['telefone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'data_aniversario' => $data['data_nascimento'] ?? null,
                'cep' => $data['cep'] ?? null,
                'rua' => $data['rua'] ?? null,
                'numero' => $data['numero'] ?? null,
                'complemento' => $data['complemento'] ?? null,
                'bairro' => $data['bairro'] ?? null,
                'id_estado' => $data['id_estado'] ?? null,
                'id_cidade' => $data['id_cidade'] ?? null,
                'password' => $data['password'] ?? 'camerata123',
            ]);

            $aluno = Aluno::create([
                'id_usuario' => $user->id,
                'id_lead' => $data['id_lead'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
            ]);

            if (! empty($data['responsavel_nome']) || ! empty($data['id_responsavel'])) {
                $responsavel = $this->resolveResponsavel->execute($data, replaceCurrentOnAluno: null);

                $aluno->responsaveis()->syncWithoutDetaching([
                    $responsavel->id => InstituicaoContext::pivotAttributes([
                        'parentesco' => 'Responsável',
                        'observacoes' => 'Criado/Vinculado via cadastro de aluno',
                    ]),
                ]);
            }

            return $aluno->load('usuario', 'responsaveis.usuario');
        });
    }

    private function assertDentroDoLimite(): void
    {
        $instituicao = InstituicaoContext::instituicao();
        if ($instituicao === null) {
            return;
        }

        $limite = $this->entitlements->limiteAlunos($instituicao);
        if ($limite === null) {
            return;
        }

        $total = Aluno::query()->count();
        if ($total >= $limite) {
            throw ValidationException::withMessages([
                'limite_alunos' => [
                    "Limite de {$limite} alunos do plano atingido. Faça upgrade para cadastrar mais alunos.",
                ],
            ]);
        }
    }
}
