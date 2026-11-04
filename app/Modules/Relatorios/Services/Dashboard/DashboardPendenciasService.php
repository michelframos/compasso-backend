<?php

namespace App\Modules\Relatorios\Services\Dashboard;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Queries\ListarMatriculasElegiveisCertificado;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Support\InstituicaoContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardPendenciasService
{
    public function __construct(
        private readonly ListarMatriculasElegiveisCertificado $elegiveisCertificado,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    /**
     * @return array{items: list<array{key: string, label: string, count: int, href: string, variant?: string}>}
     */
    public function build(?Carbon $agora = null): array
    {
        $agora ??= Carbon::now();
        $items = [
            $this->item(
                'certificados_elegiveis',
                'Certificados elegíveis',
                $this->elegiveisCertificado->execute()->count(),
                '/certificados',
            ),
            $this->item(
                'solicitacoes_aula',
                'Solicitações de aula',
                SolicitacaoAula::query()->where('status', SolicitacaoAula::STATUS_PENDENTE)->count(),
                '/solicitacoes-aulas',
            ),
            $this->item(
                'reposicoes',
                'Reposições pendentes',
                SolicitacaoReposicao::query()->where('status', SolicitacaoReposicao::STATUS_PENDENTE)->count(),
                '/solicitacoes-reposicoes',
            ),
            $this->item(
                'chamadas_pendentes',
                'Chamadas pendentes',
                $this->countChamadasPendentes($agora),
                '/agenda',
                'warning',
            ),
        ];

        if ($this->permiteModulo('financeiro')) {
            $items[] = $this->item(
                'contas_vencidas',
                'Contas vencidas',
                $this->countContasVencidas($agora),
                '/relatorios/financeiro/inadimplencia',
                'danger',
            );
        }

        return ['items' => $items];
    }

    private function countChamadasPendentes(Carbon $agora): int
    {
        $hoje = $agora->toDateString();
        $hora = $agora->format('H:i:s');

        return (int) AulaTurma::query()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelada'))
            ->where(fn ($q) => $q
                ->where('data', '<', $hoje)
                ->orWhere(fn ($h) => $h->where('data', $hoje)->where('hora_termino', '<=', $hora)))
            ->where(fn ($q) => $q
                ->whereNull('status')
                ->orWhere('status', 'agendada')
                ->orWhereDoesntHave('presencas'))
            ->count();
    }

    private function countContasVencidas(Carbon $agora): int
    {
        return (int) DashboardContaQuery::openReceitaBase()
            ->where('contas.data_vencimento', '<', $agora->toDateString())
            ->count(DB::raw('contas.id'));
    }

    private function permiteModulo(string $modulo): bool
    {
        $instituicao = InstituicaoContext::instituicao();
        if ($instituicao === null) {
            return false;
        }

        return $this->entitlements->permiteModulo($instituicao, $modulo);
    }

    /**
     * @return array{key: string, label: string, count: int, href: string, variant?: string}
     */
    private function item(string $key, string $label, int $count, string $href, ?string $variant = null): array
    {
        $item = [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'href' => $href,
        ];

        if ($variant !== null) {
            $item['variant'] = $variant;
        }

        return $item;
    }
}
