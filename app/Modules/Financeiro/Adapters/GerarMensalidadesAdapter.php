<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Core\Contracts\GerarMensalidadesPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
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
        $idCategoria = $params['id_categoria']
            ?? CategoriaConta::firstOrCreate(
                ['nome' => 'Mensalidades'],
                ['tipo' => 'receita']
            )->id;
        $observacoes = $params['observacoes'] ?? "Geração automática via matrícula #{$params['id_matricula']}";
        $nomeAluno = $params['nome_aluno'];
        $idAluno = (int) $params['id_aluno'];
        $idMatricula = (int) $params['id_matricula'];
        $idInstituicao = $params['id_instituicao'] ?? InstituicaoContext::id();

        $existentes = Conta::query()
            ->where('id_matricula', $idMatricula)
            ->whereNotNull('mes_referencia')
            ->whereNotNull('ano_referencia')
            ->get(['mes_referencia', 'ano_referencia'])
            ->mapWithKeys(fn (Conta $c) => [((int) $c->ano_referencia).'-'.((int) $c->mes_referencia) => true])
            ->all();

        $contasGeradas = [];
        $numeroParcela = Conta::query()->where('id_matricula', $idMatricula)->max('numero_parcela') ?? 0;

        for ($i = 0; $i < $qtdParcelas; $i++) {
            $dataReferencia = $dataInicio->copy()->addMonths($i);
            $chave = $dataReferencia->year.'-'.$dataReferencia->month;

            if (isset($existentes[$chave])) {
                continue;
            }

            $vencimento = $dataReferencia->copy()->day($diaVencimento);

            if ($vencimento->month !== $dataReferencia->month) {
                $vencimento = $dataReferencia->copy()->endOfMonth();
            }

            $numeroParcela++;

            $conta = Conta::create([
                'id_instituicao' => $idInstituicao,
                'id_categoria' => $idCategoria,
                'descricao' => 'Mensalidade '.$numeroParcela.' - '.$nomeAluno,
                'valor' => $valor,
                'data_vencimento' => $vencimento->format('Y-m-d'),
                'status' => 'pendente',
                'observacoes' => $observacoes,
                'numero_parcela' => $numeroParcela,
                'quantidade_parcelas' => $qtdParcelas,
                'mes_referencia' => $dataReferencia->month,
                'ano_referencia' => $dataReferencia->year,
                'id_aluno' => $idAluno,
                'id_matricula' => $idMatricula,
                'tipo' => 'receita',
            ]);

            $contasGeradas[] = $conta;
            $existentes[$chave] = true;
        }

        return $contasGeradas;
    }
}
