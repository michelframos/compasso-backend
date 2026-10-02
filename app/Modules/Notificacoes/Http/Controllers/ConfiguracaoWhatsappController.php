<?php

namespace App\Modules\Notificacoes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Exceptions\WhatsappGatewayException;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use Illuminate\Http\Request;

class ConfiguracaoWhatsappController extends Controller
{
    public function __construct(
        private readonly WhatsappGatewayInterface $whatsapp,
    ) {}

    private function configuracaoAtual(): ?ConfiguracaoWhatsapp
    {
        return ConfiguracaoWhatsapp::first();
    }

    private function salvarConfiguracao(string $instanceName, string $status): ConfiguracaoWhatsapp
    {
        $config = $this->configuracaoAtual();

        if ($config) {
            $config->update(['instance_name' => $instanceName, 'status' => $status]);

            return $config;
        }

        return ConfiguracaoWhatsapp::create([
            'instance_name' => $instanceName,
            'status' => $status,
        ]);
    }

    /**
     * GET /api/whatsapp/config
     * Retorna configuração atual e status da instância.
     */
    public function show()
    {
        $config = $this->configuracaoAtual();

        if (!$config) {
            return response()->json([
                'instance_name' => null,
                'status' => WhatsappGatewayInterface::STATUS_DISCONNECTED,
                'qr_code' => null,
            ]);
        }

        try {
            $status = $this->whatsapp->status($config->instance_name);

            if ($config->status !== $status) {
                $config->update(['status' => $status]);
            }
        } catch (WhatsappGatewayException) {
            // API WhatsApp offline: mantém o status salvo
        }

        return response()->json([
            'instance_name' => $config->instance_name,
            'status' => $config->status,
            'qr_code' => null,
        ]);
    }

    /**
     * POST /api/whatsapp/connect
     * Cria/conecta instância e retorna QR Code.
     */
    public function connect(Request $request)
    {
        $instanceName = $request->validate([
            'instance_name' => 'required|string|max:100',
        ])['instance_name'];

        try {
            $qrCode = $this->whatsapp->connect($instanceName);
        } catch (WhatsappGatewayException $e) {
            return response()->json([
                'message' => 'Erro ao conectar com a API do WhatsApp.',
                'error' => $e->getMessage(),
                'details' => $e->details,
            ], 422);
        }

        $this->salvarConfiguracao($instanceName, WhatsappGatewayInterface::STATUS_PENDING);

        return response()->json([
            'instance_name' => $instanceName,
            'status' => WhatsappGatewayInterface::STATUS_PENDING,
            'qr_code' => $qrCode,
        ]);
    }

    /**
     * PUT /api/whatsapp/reconnect
     * Reconecta instância desconectada e retorna novo QR Code.
     */
    public function reconnect()
    {
        $config = $this->configuracaoAtual();

        if (!$config) {
            return response()->json(['message' => 'Nenhuma instância configurada.'], 404);
        }

        try {
            $qrCode = $this->whatsapp->reconnect($config->instance_name);
        } catch (WhatsappGatewayException $e) {
            return response()->json([
                'message' => 'Erro ao reconectar com a API do WhatsApp.',
                'details' => $e->details,
            ], 422);
        }

        $config->update(['status' => WhatsappGatewayInterface::STATUS_PENDING]);

        return response()->json([
            'instance_name' => $config->instance_name,
            'status' => WhatsappGatewayInterface::STATUS_PENDING,
            'qr_code' => $qrCode,
        ]);
    }

    /**
     * DELETE /api/whatsapp/disconnect
     * Desconecta/logout da instância.
     */
    public function disconnect()
    {
        $config = $this->configuracaoAtual();

        if (!$config) {
            return response()->json(['message' => 'Nenhuma instância configurada.'], 404);
        }

        try {
            $this->whatsapp->disconnect($config->instance_name);
        } catch (WhatsappGatewayException) {
            // Mesmo que a API falhe, atualizamos o status local
        }

        $config->update(['status' => WhatsappGatewayInterface::STATUS_DISCONNECTED]);

        return response()->json([
            'instance_name' => $config->instance_name,
            'status' => WhatsappGatewayInterface::STATUS_DISCONNECTED,
        ]);
    }
}
