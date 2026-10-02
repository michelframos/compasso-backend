<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'ConfiguracaoPix', description: 'Configuração de chave PIX para geração de carnês')]
class ConfiguracaoPixController extends Controller
{
    #[OA\Get(
        path: '/api/configuracao-pix',
        summary: 'Obter configuração PIX',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        responses: [
            new OA\Response(response: 200, description: 'Configuração PIX')
        ]
    )]
    public function show()
    {
        $config = ConfiguracaoPix::first();
        return response()->json($config);
    }

    #[OA\Put(
        path: '/api/configuracao-pix',
        summary: 'Salvar configuração PIX',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        responses: [
            new OA\Response(response: 200, description: 'Configuração salva com sucesso')
        ]
    )]
    public function update(Request $request)
    {
        $data = $request->validate([
            'chave_pix'         => 'required_if:exibir_qrcode,true|nullable|string|max:140',
            'tipo_chave'        => 'required_if:exibir_qrcode,true|nullable|in:cpf,cnpj,email,telefone,evp',
            'nome_beneficiario' => 'required_if:exibir_qrcode,true|nullable|string|max:25',
            'cidade'            => 'required_if:exibir_qrcode,true|nullable|string|max:15',
            'exibir_qrcode'     => 'nullable|boolean',
        ]);

        $config = ConfiguracaoPix::first();

        if ($config) {
            $config->update($data);
        } else {
            $config = ConfiguracaoPix::create($data);
        }

        return response()->json($config);
    }
}
