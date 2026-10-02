<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Core\Contracts\GerarMensalidadesPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;
use Carbon\Carbon;

class GerarMensalidadesAdapter implements GerarMensalidadesPort
{
    public function execute(array $params): array
    {
        $valor = $params['valor'];
        $qtdParcelas = (int) $params['quantidade_parcelas'];
        $diaVencimento = (int) $params['dia_vencimento'];
        $dataInicio = Carbon::parse($params['data_inicio']);
        $idCategoria = $params['id_categoria'] ?? 1;
        $observacoes = $params['observacoes'] ?? "Geração automática via matrícula #{$params['id_matricula']}";
        $nomeAluno = $params['nome_aluno'];
        $idAluno = (int) $params['id_aluno'];
        $idMatricula = (int) $params['id_matricula'];

        $contasGeradas = [];

        for ($i = 0; $i < $qtdParcelas; $i++) {
            $dataReferencia = $dataInicio->copy()->addMonths($i);
            $vencimento = $dataReferencia->copy()->day($diaVencimento);

            if ($vencimento->month !== $dataReferencia->month) {
                $vencimento = $dataReferencia->copy()->endOfMonth();
            }

            $contasGeradas[] = Conta::create([
                'id_instituicao' => $params['id_instituicao'] ?? InstituicaoContext::id(),
                'id_categoria' => $idCategoria,
                'descricao' => 'Mensalidade '.($i + 1)."/{$qtdParcelas} - ".$nomeAluno,
                'valor' => $valor,
                'data_vencimento' => $vencimento->format('Y-m-d'),
                'status' => 'pendente',
                'observacoes' => $observacoes,
                'numero_parcela' => $i + 1,
                'quantidade_parcelas' => $qtdParcelas,
                'mes_referencia' => $dataReferencia->month,
                'ano_referencia' => $dataReferencia->year,
                'id_aluno' => $idAluno,
                'id_matricula' => $idMatricula,
                'tipo' => 'receita',
            ]);
        }

        return $contasGeradas;
    }
}
