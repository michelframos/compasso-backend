<?php

namespace App\Modules\Pessoas\UseCases\Aluno;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\UseCases\Responsavel\ResolveOrCreateResponsavelUseCase;
use Illuminate\Support\Facades\DB;

class UpdateAlunoUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ResolveOrCreateResponsavelUseCase $resolveResponsavel,
    ) {}

    public function execute(Aluno $aluno, array $data): Aluno
    {
        return DB::transaction(function () use ($aluno, $data) {
            $userData = array_filter([
                'nome' => $data['nome'] ?? null,
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'telefone' => $data['telefone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'cep' => $data['cep'] ?? null,
                'rua' => $data['rua'] ?? null,
                'numero' => $data['numero'] ?? null,
                'complemento' => $data['complemento'] ?? null,
                'bairro' => $data['bairro'] ?? null,
                'id_estado' => $data['id_estado'] ?? null,
                'id_cidade' => $data['id_cidade'] ?? null,
            ], fn ($v) => $v !== null);

            if (array_key_exists('data_nascimento', $data) && filled($data['data_nascimento'])) {
                $userData['data_aniversario'] = $data['data_nascimento'];
            }

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            $this->users->update($aluno->usuario, $userData);

            $aluno->update(array_filter([
                'observacoes' => $data['observacoes'] ?? null,
                'id_lead' => $data['id_lead'] ?? null,
            ], fn ($v) => $v !== null || array_key_exists('observacoes', $data)));

            if (! empty($data['responsavel_nome']) || ! empty($data['id_responsavel'])) {
                $responsavel = $this->resolveResponsavel->execute($data, replaceCurrentOnAluno: $aluno);

                $aluno->responsaveis()->syncWithoutDetaching([$responsavel->id => [
                    'parentesco' => 'Responsável',
                    'observacoes' => 'Vinculado via edição de aluno',
                ]]);
            }

            return $aluno->fresh()->load('usuario', 'responsaveis.usuario');
        });
    }
}
