<?php

namespace App\Modules\Financeiro\Queries;

use App\Modules\Core\Models\User;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Database\Eloquent\Builder;

class ListContasDoAlunoQuery
{
    public function __construct(private readonly ContaQuery $contas) {}

    public function build(User $user, int $idAluno, ?string $situacao, ?int $idMatricula): Builder
    {
        $query = Conta::query()
            ->with('pagamentos')
            ->visivelPara($user)
            ->where('id_aluno', $idAluno)
            ->where('tipo', 'receita')
            ->where(fn (Builder $status) => $status->whereNull('status')->orWhere('status', '!=', 'cancelado'));

        $this->contas->aplicar($query, array_filter([
            'situacao' => $situacao,
            'id_matricula' => $idMatricula,
        ]));

        return $query->orderBy('data_vencimento')->orderBy('id');
    }

    /** Receitas de todos os dependentes do responsável, sem observações internas. */
    public function buildDoResponsavel(User $user, ?string $situacao): Builder
    {
        $query = Conta::query()
            ->with(['pagamentos', 'aluno.usuario'])
            ->visivelPara($user)
            ->where('tipo', 'receita')
            ->where(fn (Builder $status) => $status->whereNull('status')->orWhere('status', '!=', 'cancelado'));

        $this->contas->aplicar($query, array_filter(['situacao' => $situacao]));

        return $query->orderBy('data_vencimento')->orderBy('id');
    }
}
