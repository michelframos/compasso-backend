<?php

namespace App\Modules\Financeiro\Services;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\BillingPayloadBuilder;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Support\Collection;

/**
 * Monta subscriber_id / customer_id / customer das cobranças online de mensalidades.
 *
 * Pagador: responsável financeiro (primeiro responsável com CPF) e, na ausência, o próprio aluno.
 */
class CobrancaOnlineCustomerBuilder
{
    public static function customerId(int $idAluno): string
    {
        return 'aluno:'.$idAluno;
    }

    public function subscriberId(Instituicao $instituicao): string
    {
        return BillingPayloadBuilder::subscriberId($instituicao);
    }

    /**
     * @return array{name: string, email: string, cpf_cnpj: string}|null  null quando faltam dados do pagador
     */
    public function customer(Conta $conta): ?array
    {
        $aluno = $conta->aluno ?? Aluno::query()->with('usuario')->find($conta->id_aluno);

        if ($aluno === null) {
            return null;
        }

        /** @var Collection<int, User> $candidatos */
        $candidatos = $aluno->responsaveis()
            ->with('usuario')
            ->get()
            ->map(fn ($responsavel) => $responsavel->usuario)
            ->push($aluno->usuario)
            ->filter();

        $pagador = $candidatos->first(fn (User $u) => filled($this->digits($u->cpf)) && filled($u->email))
            ?? $candidatos->first(fn (User $u) => filled($this->digits($u->cpf)));

        if ($pagador === null) {
            return null;
        }

        $email = filled($pagador->email)
            ? $pagador->email
            : $candidatos->first(fn (User $u) => filled($u->email))?->email;

        if (blank($email) || blank($pagador->nome)) {
            return null;
        }

        return [
            'name' => (string) $pagador->nome,
            'email' => (string) $email,
            'cpf_cnpj' => (string) $this->digits($pagador->cpf),
        ];
    }

    private function digits(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $valor);

        return $digits === '' ? null : $digits;
    }
}
