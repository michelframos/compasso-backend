<?php

namespace App\Modules\Notificacoes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conta;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;
use App\Modules\Notificacoes\Services\MontarNotificacaoContaService;
use App\Modules\Notificacoes\Services\NotificationChannelResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContaNotificacaoController extends Controller
{
    public function __construct(
        private readonly NotificationChannelResolver $canais,
        private readonly MontarNotificacaoContaService $montarNotificacao,
    ) {}

    /**
     * GET /api/notificacoes/contas/{conta}/preview
     */
    public function preview(Request $request, Conta $conta)
    {
        $canal = $request->validate([
            'canal' => ['required', Rule::in($this->canais->canais())],
        ])['canal'];

        $notificacao = $this->montarNotificacao->montar($conta, $canal);

        return response()->json([
            'canal' => $canal,
            'destinatario' => $notificacao->destinatario,
            'destino' => $notificacao->destino,
            'mensagem' => $notificacao->mensagem,
            'conta' => [
                'id' => $conta->id,
                'descricao' => $conta->descricao,
                'valor' => (float) $conta->valor,
                'data_vencimento' => Carbon::parse($conta->data_vencimento)->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * POST /api/notificacoes/contas/{conta}/enviar
     */
    public function enviar(Request $request, Conta $conta)
    {
        $data = $request->validate([
            'canal' => ['required', Rule::in($this->canais->canais())],
            'mensagem' => 'required|string|min:1',
        ]);

        $notificacao = $this->montarNotificacao->montar($conta, $data['canal'])
            ->comMensagem($data['mensagem']);

        $resultado = $this->canais->resolve($data['canal'])
            ->enviar($this->montarNotificacao->paraEnvio($notificacao));

        $status = match ($resultado->status) {
            ResultadoEnvio::ENVIADO => 200,
            ResultadoEnvio::REJEITADO => 422,
            default => 500,
        };

        $body = ['message' => $resultado->mensagem];
        if ($resultado->sucesso()) {
            $body['destino'] = $notificacao->destino;
        }

        return response()->json($body, $status);
    }
}
