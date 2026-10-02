<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AulaPresenca;
use App\Models\Turma;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Relatorios\Services\DiarioClasseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RelatorioPedagogicoController extends Controller
{
    public function __construct(private readonly DiarioClasseService $diarioClasse) {}

    /**
     * Retorna os dados para o Relatório de Frequência e Absenteísmo
     */
    public function absenteismo(Request $request)
    {
        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->toDateString());
        $limiteFaltas = $request->input('limite_faltas', 3);
        $idTurma = $request->input('id_turma');

        // Query principal para consolidar faltas e presenças por aluno no período
        $query = DB::table('aulas_presencas')
            ->join('aulas_turmas', 'aulas_presencas.id_aula_turma', '=', 'aulas_turmas.id')
            ->join('alunos', 'aulas_presencas.id_aluno', '=', 'alunos.id')
            ->join('usuarios', 'alunos.id_usuario', '=', 'usuarios.id')
            ->select(
                'alunos.id',
                'usuarios.nome as nome_aluno',
                DB::raw("SUM(CASE WHEN aulas_presencas.status = 'presente' THEN 1 ELSE 0 END) as total_presencas"),
                DB::raw("SUM(CASE WHEN aulas_presencas.status IN ('ausente', 'justificado') THEN 1 ELSE 0 END) as total_faltas"),
                DB::raw("SUM(CASE WHEN aulas_presencas.status = 'justificado' THEN 1 ELSE 0 END) as total_justificadas"),
                DB::raw("COUNT(aulas_presencas.id) as total_aulas")
            )
            ->whereNull('aulas_presencas.deleted_at')
            ->whereNull('aulas_turmas.deleted_at')
            ->whereBetween('aulas_turmas.data', [$dataInicio, $dataFim]);

        InstituicaoContext::applyToQuery($query, 'aulas_turmas');

        if ($idTurma && $idTurma !== 'all') {
            $query->where('aulas_turmas.id_turma', $idTurma);
        }

        $absenteismo = $query->groupBy('alunos.id', 'usuarios.nome')
            ->get()
            ->map(function ($item) use ($limiteFaltas) {
                $item->taxa_absenteismo = $item->total_aulas > 0 
                    ? round(($item->total_faltas / $item->total_aulas) * 100, 2) 
                    : 0;
                
                // Lógica de faltas consecutivas (dinâmica baseada no limite)
                // Buscamos as últimas aulas do aluno (até 2x o limite para segurança na sequência)
                $ultimasPresencas = AulaPresenca::where('id_aluno', $item->id)
                    ->join('aulas_turmas', 'aulas_presencas.id_aula_turma', '=', 'aulas_turmas.id')
                    ->where('aulas_turmas.data', '<=', Carbon::now()->toDateString())
                    ->whereNull('aulas_presencas.deleted_at')
                    ->whereNull('aulas_turmas.deleted_at')
                    ->orderBy('aulas_turmas.data', 'desc')
                    ->limit($limiteFaltas + 2)
                    ->pluck('aulas_presencas.status');

                $consecutivas = 0;
                foreach ($ultimasPresencas as $status) {
                    if ($status === 'ausente') {
                        $consecutivas++;
                    } else {
                        break;
                    }
                }
                
                $item->faltas_consecutivas = $consecutivas;
                $item->em_risco = $consecutivas >= $limiteFaltas;

                return $item;
            });

        // Totais Gerais para o Dashboard
        $totalAlunos = $absenteismo->count();
        $mediaAbsenteismo = $absenteismo->avg('taxa_absenteismo') ?: 0;
        $alunosEmRisco = $absenteismo->where('em_risco', true)->count();

        return response()->json([
            'data' => $absenteismo,
            'summary' => [
                'total_alunos' => $totalAlunos,
                'media_absenteismo' => round($mediaAbsenteismo, 2),
                'alunos_em_risco' => $alunosEmRisco,
            ],
            'filters' => [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'id_turma' => $idTurma
            ]
        ]);
    }

    /**
     * Retorna os dados detalhados para o Diário de Classe de uma turma, 
     * incluindo histórico de presenças e conteúdos.
     */
    public function diarioClasse(Request $request)
    {
        $idTurma = $request->input('id_turma');
        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->toDateString());

        if (!$idTurma) {
            return response()->json(['message' => 'ID da turma é obrigatório'], 400);
        }

        $turma = Turma::find($idTurma);

        if (!$turma) {
            return response()->json(['message' => 'Turma não encontrada'], 404);
        }

        return response()->json($this->diarioClasse->montar($turma, $dataInicio, $dataFim));
    }
}
