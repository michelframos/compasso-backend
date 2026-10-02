<?php

namespace App\Modules\Financeiro\Adapters;

use App\Models\ApresentacaoAluno;
use App\Modules\Core\Contracts\CriarCobrancasFigurinoPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cobranças de figurino (consumido por Espetáculos via port).
 */
class CriarCobrancasFigurinoAdapter implements CriarCobrancasFigurinoPort
{
    public function execute(array $params): array
    {
        $apresentacaoId = (int) $params['apresentacao_id'];
        $participantesIds = $params['participantes_ids'];
        $dataVencimento = $params['data_vencimento'];

        return DB::transaction(function () use ($apresentacaoId, $participantesIds, $dataVencimento) {
            $categoria = CategoriaConta::firstOrCreate(
                ['nome' => 'Figurino'],
                [
                    'tipo' => 'receita',
                    'descricao' => 'Cobrança referente a figurinos de apresentações/espetáculos',
                ]
            );

            $participantes = ApresentacaoAluno::with(['apresentacao.espetaculo', 'aluno.usuario'])
                ->where('id_apresentacao', $apresentacaoId)
                ->whereIn('id', $participantesIds)
                ->where('valor_figurino', '>', 0)
                ->get();

            if ($participantes->isEmpty()) {
                throw new InvalidArgumentException(
                    'Nenhum participante válido encontrado para gerar cobrança (verifique se pertencem a esta apresentação e se possuem valor de figurino > 0).'
                );
            }

            $contasGeradas = 0;

            foreach ($participantes as $participante) {
                $nomeApresentacao = $participante->apresentacao->espetaculo
                    ? $participante->apresentacao->espetaculo->titulo
                    : 'Apresentação '.$participante->apresentacao->id;
                $nomeAluno = $participante->aluno->usuario->nome ?? 'Aluno Desconhecido';
                $descricao = "Figurino - {$nomeApresentacao} - {$nomeAluno}";

                Conta::create([
                    'id_instituicao' => InstituicaoContext::id(),
                    'id_categoria' => $categoria->id,
                    'descricao' => $descricao,
                    'valor' => $participante->valor_figurino,
                    'data_vencimento' => $dataVencimento,
                    'status' => 'pendente',
                    'id_aluno' => $participante->id_aluno,
                    'tipo' => 'receita',
                    'observacoes' => 'Gerada automaticamente para o figurino tamanho: '.($participante->tamanho_figurino ?? 'N/A'),
                ]);

                $contasGeradas++;
                $participante->update(['fatura_gerada' => true]);
            }

            return [
                'message' => "Foram geradas {$contasGeradas} faturas com sucesso.",
                'contas_geradas' => $contasGeradas,
            ];
        });
    }
}
