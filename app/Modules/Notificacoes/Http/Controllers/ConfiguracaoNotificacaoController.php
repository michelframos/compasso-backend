<?php

namespace App\Modules\Notificacoes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesAgendamentos;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesContasReceber;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Http\Request;

class ConfiguracaoNotificacaoController extends Controller
{
    /**
     * GET /api/notificacoes/config
     * Lista todas as configurações de notificações.
     */
    public function index()
    {
        $configs = ConfiguracaoNotificacao::all();
        return response()->json($configs);
    }

    /**
     * PUT /api/notificacoes/config
     * Upsert de todas as configurações de notificação.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'configs' => 'required|array',
            'configs.*.modulo' => 'required|in:agendamentos,contas_a_receber',
            'configs.*.tipo' => 'nullable|in:vencimento,atraso',
            'configs.*.ativo' => 'required|boolean',
            'configs.*.dias_antecedencia' => 'required|integer|min:0',
            'configs.*.intervalo_repeticao' => 'required|integer|min:1',
            'configs.*.max_repeticoes' => 'nullable|integer|min:0',
            'configs.*.template_mensagem' => 'nullable|string',
            'configs.*.horario_envio' => 'nullable|date_format:H:i',
        ]);

        $saved = collect($data['configs'])->map(function ($item) {
            return ConfiguracaoNotificacao::updateOrCreate(
                [
                    'modulo' => $item['modulo'],
                    'tipo'   => $item['tipo'] ?? null,
                ],
                [
                    'ativo'               => $item['ativo'],
                    'dias_antecedencia'   => $item['dias_antecedencia'],
                    'intervalo_repeticao' => $item['intervalo_repeticao'],
                    'max_repeticoes'      => $item['max_repeticoes'] ?? null,
                    'template_mensagem'   => $item['template_mensagem'] ?? null,
                    'horario_envio'       => $item['horario_envio'] ?? '08:00',
                ]
            );
        });

        return response()->json($saved);
    }

    /**
     * POST /api/notificacoes/disparar-agora
     * Dispara manualmente as rotinas de notificação (ignorando o horário agendado).
     */
    public function dispararAgora()
    {
        $idInstituicao = InstituicaoContext::id();

        ProcessarNotificacoesAgendamentos::dispatch(true, $idInstituicao);
        ProcessarNotificacoesContasReceber::dispatch(true, $idInstituicao);

        return response()->json([
            'message' => 'Processamento de notificações iniciado com sucesso.'
        ]);
    }
}
