<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Matricula;
use App\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RelatorioComercialController extends Controller
{
    /**
     * Retorna os dados para o Funil de Vendas e Conversão de Leads
     */
    public function funilVendas(Request $request)
    {
        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->toDateString());

        // 1. Novos Leads no período
        $novosLeads = Lead::whereBetween('created_at', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->count();

        // 2. Distribuição por Status (Leads criados no período)
        $statusDistribution = Lead::whereBetween('created_at', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();

        // Garantir que todos os status apareçam
        $allStatus = ['novo', 'contatado', 'matriculado', 'perdido'];
        $distData = [];
        foreach ($allStatus as $st) {
            $distData[] = [
                'status' => $st,
                'label' => ucfirst($st),
                'value' => $statusDistribution[$st] ?? 0
            ];
        }

        // 3. Matrículas Realizadas no período
        $matriculasRealizadas = Matricula::whereBetween('data', [$dataInicio, $dataFim])
            ->count();

        // 4. Taxa de Conversão Geral
        $taxaConversao = $novosLeads > 0 ? ($matriculasRealizadas / $novosLeads) * 100 : 0;

        // 5. Dados para Gráfico de Evolução (Leads criados por dia no período)
        $evolucaoLeads = Lead::whereBetween('created_at', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->select(DB::raw('DATE(created_at) as data'), DB::raw('count(*) as total'))
            ->groupBy('data')
            ->orderBy('data')
            ->get();

        return response()->json([
            'summary' => [
                'novos_leads' => $novosLeads,
                'matriculas_realizadas' => $matriculasRealizadas,
                'taxa_conversao' => round($taxaConversao, 2),
                'leads_por_status' => $distData
            ],
            'charts' => [
                'evolucao_leads' => $evolucaoLeads
            ],
            'filters' => [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]
        ]);
    }
    
    /**
     * Retorna os aniversariantes do mês (Alunos e Professores)
     */
    public function aniversariantes(Request $request)
    {
        $mes = $request->input('mes', Carbon::now()->month);

        $query = User::whereMonth('data_aniversario', $mes)
            ->whereIn('role', ['aluno', 'professor', 'admin', 'secretaria']);

        if (InstituicaoContext::has()) {
            $idInstituicao = InstituicaoContext::id();
            $query->whereHas('instituicoes', fn ($q) => $q->where('instituicoes.id', $idInstituicao));
        }

        $aniversariantes = $query
            ->select('id', 'nome', 'data_aniversario', 'telefone', 'whatsapp', 'role')
            ->orderByRaw('DAY(data_aniversario) ASC')
            ->get()
            ->map(function ($user) {
                $birthday = Carbon::parse($user->data_aniversario);
                return [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'dia' => $birthday->day,
                    'data_aniversario' => $user->data_aniversario,
                    'telefone' => $user->telefone,
                    'whatsapp' => $user->whatsapp,
                    'role' => $user->role,
                    'tipo' => $user->role === 'aluno' ? 'Aluno' : ($user->role === 'professor' ? 'Professor' : 'Equipe')
                ];
            });

        return response()->json($aniversariantes);
    }
}
