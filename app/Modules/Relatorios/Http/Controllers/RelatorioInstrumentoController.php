<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Instrumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioInstrumentoController extends Controller
{
    /**
     * Retorna os dados para o Relatório de Inventário de Instrumentos
     */
    public function index(Request $request)
    {
        $tipo = $request->input('tipo');
        $status = $request->input('status');
        $nomeAluno = $request->input('aluno');

        $query = Instrumento::with(['aluno.usuario'])
            ->select('instrumentos.*');

        // Filtro por tipo
        if ($tipo && $tipo !== 'all') {
            $query->where('tipo', $tipo);
        }

        // Filtro por status
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Filtro por nome do aluno (via relacionamento)
        if ($nomeAluno) {
            $query->whereHas('aluno.usuario', function ($q) use ($nomeAluno) {
                $q->where('nome', 'like', '%' . $nomeAluno . '%');
            });
        }

        $instrumentos = $query->orderBy('nome')->get();

        // Estatísticas para o Dashboard do Relatório
        $stats = [
            'total' => Instrumento::count(),
            'disponiveis' => Instrumento::where('status', 'disponivel')->count(),
            'emprestados' => Instrumento::where('status', 'emprestado')->count(),
            'manutencao' => Instrumento::where('status', 'manutencao')->count(),
        ];

        return response()->json([
            'data' => $instrumentos,
            'summary' => $stats,
            'filters' => [
                'tipo' => $tipo,
                'status' => $status,
                'aluno' => $nomeAluno
            ]
        ]);
    }
}
