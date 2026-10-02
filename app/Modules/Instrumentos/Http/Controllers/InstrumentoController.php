<?php

namespace App\Modules\Instrumentos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Modules\Instrumentos\Http\Requests\EmprestarInstrumentoRequest;
use App\Modules\Instrumentos\Http\Requests\StoreInstrumentoRequest;
use App\Modules\Instrumentos\Http\Requests\UpdateInstrumentoRequest;
use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Instrumentos\Models\InstrumentoHistorico;
use App\Modules\Core\Contracts\CriarContaEmprestimoInstrumentoPort;

class InstrumentoController extends Controller
{
    public function __construct(
        private readonly CriarContaEmprestimoInstrumentoPort $criarContaEmprestimoPort,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $instrumentos = Instrumento::with('aluno.usuario')
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->paginate($perPage);

        return response()->json($instrumentos);
    }

    public function store(StoreInstrumentoRequest $request)
    {
        $instrumento = Instrumento::create($request->validated());

        return response()->json([
            'message' => 'Instrumento cadastrado com sucesso!',
            'data' => $instrumento
        ], 201);
    }

    public function show(Instrumento $instrumento)
    {
        $instrumento->load('aluno.usuario');
        return response()->json(['data' => $instrumento]);
    }

    public function update(UpdateInstrumentoRequest $request, Instrumento $instrumento)
    {
        $instrumento->update($request->validated());

        return response()->json([
            'message' => 'Instrumento atualizado com sucesso!',
            'data' => $instrumento
        ]);
    }

    public function destroy(Instrumento $instrumento)
    {
        if ($instrumento->status === 'emprestado') {
            return response()->json([
                'message' => 'Não é possível excluir um instrumento que está emprestado.'
            ], 400);
        }

        $instrumento->delete();

        return response()->json(['message' => 'Instrumento excluído com sucesso!']);
    }

    public function emprestar(EmprestarInstrumentoRequest $request, Instrumento $instrumento)
    {
        $instrumento->update([
            'id_aluno' => $request->id_aluno,
            'data_emprestimo' => $request->data_emprestimo,
            'status' => 'emprestado'
        ]);

        // Registrar no histórico
        InstrumentoHistorico::create([
            'id_instrumento' => $instrumento->id,
            'id_aluno' => $request->id_aluno,
            'acao' => 'emprestimo',
            'data' => now(),
            'observacoes' => 'Empréstimo realizado via sistema.',
            'contrato_id' => $request->contrato_id,
            'contrato_gerado' => $request->contrato_gerado,
        ]);

        // Conta a receber opcional (via port — Financeiro)
        if ($request->boolean('gerar_conta')) {
            $this->criarContaEmprestimoPort->execute([
                'id_aluno' => $request->id_aluno,
                'id_categoria' => $request->id_categoria_conta,
                'descricao' => "Empréstimo de Instrumento ({$instrumento->nome}) - ".\Carbon\Carbon::parse($request->data_emprestimo)->format('d/m/Y'),
                'valor' => $request->valor_conta,
                'data_vencimento' => $request->data_vencimento_conta,
            ]);
        }

        return response()->json([
            'message' => 'Instrumento emprestado com sucesso!',
            'data' => $instrumento->load('aluno.usuario')
        ]);
    }

    public function devolver(Instrumento $instrumento)
    {
        $id_aluno_anterior = $instrumento->id_aluno;

        $instrumento->update([
            'id_aluno' => null,
            'data_emprestimo' => null,
            'status' => 'disponivel'
        ]);

        // Registrar no histórico
        InstrumentoHistorico::create([
            'id_instrumento' => $instrumento->id,
            'id_aluno' => $id_aluno_anterior,
            'acao' => 'devolucao',
            'data' => now(),
            'observacoes' => 'Devolução realizada via sistema.'
        ]);

        return response()->json([
            'message' => 'Instrumento devolvido com sucesso!',
            'data' => $instrumento
        ]);
    }
    public function historico(Instrumento $instrumento)
    {
        $historico = InstrumentoHistorico::with('aluno.usuario')
            ->where('id_instrumento', $instrumento->id)
            ->orderBy('data', 'desc')
            ->get();

        return response()->json(['data' => $historico]);
    }
}
