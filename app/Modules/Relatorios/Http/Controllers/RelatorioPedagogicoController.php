<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Turma;
use App\Modules\Relatorios\Services\AbsenteismoService;
use App\Modules\Relatorios\Services\DiarioClasseService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RelatorioPedagogicoController extends Controller
{
    public function __construct(private readonly DiarioClasseService $diarioClasse) {}

    /**
     * Retorna os dados para o Relatório de Frequência e Absenteísmo
     */
    public function absenteismo(Request $request, AbsenteismoService $absenteismo)
    {
        $idTurma = $request->input('id_turma');

        return response()->json($absenteismo->calcular(
            $request->user(),
            $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString()),
            $request->input('data_fim', Carbon::now()->endOfMonth()->toDateString()),
            (int) $request->input('limite_faltas', 3),
            $idTurma && $idTurma !== 'all' ? (int) $idTurma : null,
        ));
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
