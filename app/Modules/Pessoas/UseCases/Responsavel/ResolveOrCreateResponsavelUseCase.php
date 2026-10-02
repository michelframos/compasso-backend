<?php

namespace App\Modules\Pessoas\UseCases\Responsavel;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\Models\Responsavel;

class ResolveOrCreateResponsavelUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Aluno $replaceCurrentOnAluno = null): Responsavel
    {
        $responsavel = null;

        if (! empty($data['id_responsavel'])) {
            $responsavel = Responsavel::query()->find($data['id_responsavel']);
        } else {
            $responsavelUser = $this->users->findByCpfOrEmail(
                $data['responsavel_cpf'] ?? null,
                $data['responsavel_email'] ?? null,
            );

            if ($responsavelUser) {
                $responsavel = Responsavel::query()->where('id_usuario', $responsavelUser->id)->first();
                if (! $responsavel) {
                    $responsavel = Responsavel::create([
                        'id_usuario' => $responsavelUser->id,
                    ]);
                }
            }
        }

        if ($responsavel) {
            if ($replaceCurrentOnAluno) {
                $atual = $replaceCurrentOnAluno->responsaveis()->first();
                if ($atual && $atual->id !== $responsavel->id) {
                    $replaceCurrentOnAluno->responsaveis()->detach($atual->id);
                }
            }

            $this->users->update($responsavel->usuario, array_filter([
                'nome' => $data['responsavel_nome'] ?? null,
                'email' => $data['responsavel_email'] ?? null,
                'cpf' => $data['responsavel_cpf'] ?? null,
                'telefone' => $data['responsavel_telefone'] ?? null,
                'whatsapp' => $data['responsavel_whatsapp'] ?? null,
                'cep' => $data['responsavel_cep'] ?? null,
                'rua' => $data['responsavel_rua'] ?? null,
                'numero' => $data['responsavel_numero'] ?? null,
                'complemento' => $data['responsavel_complemento'] ?? null,
                'bairro' => $data['responsavel_bairro'] ?? null,
                'id_estado' => $data['responsavel_id_estado'] ?? null,
                'id_cidade' => $data['responsavel_id_cidade'] ?? null,
            ], fn ($v) => $v !== null));

            $responsavel->update([
                'observacoes' => $data['responsavel_observacoes'] ?? $responsavel->observacoes,
            ]);

            return $responsavel->fresh(['usuario']);
        }

        $responsavelUser = $this->users->create([
            'nome' => $data['responsavel_nome'] ?? null,
            'email' => $data['responsavel_email'] ?? null,
            'cpf' => $data['responsavel_cpf'] ?? null,
            'telefone' => $data['responsavel_telefone'] ?? null,
            'whatsapp' => $data['responsavel_whatsapp'] ?? null,
            'role' => 'responsavel',
            'senha' => null,
            'cep' => $data['responsavel_cep'] ?? null,
            'rua' => $data['responsavel_rua'] ?? null,
            'numero' => $data['responsavel_numero'] ?? null,
            'complemento' => $data['responsavel_complemento'] ?? null,
            'bairro' => $data['responsavel_bairro'] ?? null,
            'id_estado' => $data['responsavel_id_estado'] ?? null,
            'id_cidade' => $data['responsavel_id_cidade'] ?? null,
        ]);

        $responsavel = Responsavel::create([
            'id_usuario' => $responsavelUser->id,
            'observacoes' => $data['responsavel_observacoes'] ?? null,
        ]);

        if ($replaceCurrentOnAluno) {
            $atual = $replaceCurrentOnAluno->responsaveis()->first();
            if ($atual) {
                $replaceCurrentOnAluno->responsaveis()->detach($atual->id);
            }
            $replaceCurrentOnAluno->responsaveis()->attach($responsavel->id, InstituicaoContext::pivotAttributes([
                'parentesco' => 'Responsável',
                'observacoes' => 'Criado via edição de aluno',
            ]));
        }

        return $responsavel->load('usuario');
    }
}
