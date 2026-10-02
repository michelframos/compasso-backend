<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Models\ConfiguracaoEmpresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Carne', description: 'Geração de carnê em PDF com QR Code PIX')]
class CarneController extends Controller
{
    #[OA\Post(
        path: '/api/carne/gerar',
        summary: 'Gerar carnê em PDF',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conta_ids'],
                properties: [
                    new OA\Property(property: 'conta_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'titulo', type: 'string', nullable: true),
                    new OA\Property(property: 'preferencia_dados', type: 'string', enum: ['aluno', 'responsavel'], default: 'aluno'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDF do carnê gerado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação'),
            new OA\Response(response: 400, description: 'Configuração PIX não cadastrada'),
        ]
    )]
    public function gerar(Request $request)
    {
        $data = $request->validate([
            'conta_ids'         => 'required|array|min:1',
            'conta_ids.*'       => ['integer', InstituicaoContext::existsRule('contas')],
            'titulo'            => 'nullable|string|max:100',
            'preferencia_dados' => 'nullable|string|in:aluno,responsavel',
        ]);

        // Verificar se a configuração PIX está cadastrada
        $configPix = ConfiguracaoPix::first();
        if (!$configPix) {
            return response()->json([
                'message' => 'Configuração PIX não cadastrada. Acesse as configurações do sistema para cadastrar a chave PIX da escola.',
            ], 400);
        }

        // Buscar dados da empresa para o cabeçalho
        $configEmpresa = ConfiguracaoEmpresa::first();
        $nomeEmpresa = $configEmpresa?->nome_fantasia ?? $configPix->nome_beneficiario;

        // Buscar contas apenas do tipo receita, com relacionamentos
        $contas = Conta::with(['aluno.usuario', 'aluno.responsaveis.usuario', 'matricula.turma.curso', 'categoria'])
            ->whereIn('id', $data['conta_ids'])
            ->where('tipo', 'receita')
            ->orderBy('data_vencimento', 'asc')
            ->get();

        if ($contas->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma conta do tipo receita foi encontrada para os IDs informados.',
            ], 422);
        }

        // Gerar QR Code para cada conta
        $parcelas = [];
        foreach ($contas as $conta) {
            $qrCodeBase64 = null;
            $pixPayload = null;

            if ($configPix->exibir_qrcode) {
                $pixPayload = $this->gerarPixPayload($configPix, $conta);
                $qrCodeBase64 = $this->gerarQrCodeBase64($pixPayload);
            }

            $parcelas[] = [
                'conta'        => $conta,
                'qr_code'      => $qrCodeBase64,
                'pix_payload'  => $pixPayload,
                'chave_pix'    => $configPix->chave_pix,
            ];
        }

        // Determinar título do carnê
        $primeiraContaAluno = $contas->first()->aluno?->usuario?->nome ?? 'Aluno';
        $titulo = $data['titulo'] ?? 'Carnê de Pagamento - ' . $primeiraContaAluno;

        // Gerar PDF via DomPDF
        $pdf = Pdf::loadView('pdf.carne', [
            'parcelas'          => $parcelas,
            'titulo'            => $titulo,
            'nome_beneficiario' => $nomeEmpresa,
            'exibir_qrcode'     => $configPix->exibir_qrcode,
            'preferencia_dados' => $data['preferencia_dados'] ?? 'aluno',
            'gerado_em'         => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $nomeArquivo = 'carne_' . now()->format('YmdHis') . '.pdf';

        return $pdf->download($nomeArquivo);
    }

    /**
     * Gera o payload BR Code (EMV) do PIX para uma parcela.
     * Segue a especificação do Banco Central do Brasil.
     */
    private function gerarPixPayload(ConfiguracaoPix $config, Conta $conta): string
    {
        $chavePix     = $this->sanitizarString($config->chave_pix);
        $nome         = $this->sanitizarString($config->nome_beneficiario, 25);
        $cidade       = $this->sanitizarString($config->cidade, 15);
        $valor        = number_format($conta->saldo_devedor > 0 ? $conta->saldo_devedor : $conta->valor, 2, '.', '');
        $txid         = 'CARNE' . str_pad($conta->id, 10, '0', STR_PAD_LEFT);
        $descricao    = $this->sanitizarString($conta->descricao ?? 'Parcela', 72);

        // Monta os campos do payload EMV
        $merchantAccountInfo = $this->tlv('00', 'BR.GOV.BCB.PIX') . $this->tlv('01', $chavePix);
        if ($descricao) {
            $merchantAccountInfo .= $this->tlv('02', $descricao);
        }

        $payload = '';
        $payload .= $this->tlv('00', '01');                                          // Payload Format Indicator
        $payload .= $this->tlv('26', $merchantAccountInfo);                           // Merchant Account Information
        $payload .= $this->tlv('52', '0000');                                         // Merchant Category Code
        $payload .= $this->tlv('53', '986');                                          // Transaction Currency (BRL)
        $payload .= $this->tlv('54', $valor);                                         // Transaction Amount
        $payload .= $this->tlv('58', 'BR');                                           // Country Code
        $payload .= $this->tlv('59', $nome);                                          // Merchant Name
        $payload .= $this->tlv('60', $cidade);                                        // Merchant City
        $payload .= $this->tlv('62', $this->tlv('05', $txid));                       // Additional Data Field (TXID)
        $payload .= '6304';                                                            // CRC16 placeholder

        // Calcular e acrescentar o CRC16
        $crc = $this->calcularCRC16($payload);
        $payload .= $crc;

        return $payload;
    }

    /**
     * Formata um campo no padrão TLV (Tag-Length-Value) do BR Code.
     */
    private function tlv(string $tag, string $value): string
    {
        $length = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $tag . $length . $value;
    }

    /**
     * Calcula o CRC16-CCITT (XMODEM) conforme especificação EMV.
     */
    private function calcularCRC16(string $payload): string
    {
        $crc = 0xFFFF;
        $polynomial = 0x1021;

        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ $polynomial;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(dechex($crc));
    }

    /**
     * Gera o QR Code do PIX como base64 PNG.
     */
    private function gerarQrCodeBase64(string $pixPayload): string
    {
        $qrCode = new QrCode($pixPayload);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return base64_encode($result->getString());
    }

    /**
     * Remove caracteres especiais e limita o tamanho de uma string para uso no payload PIX.
     */
    private function sanitizarString(string $value, int $maxLength = 0): string
    {
        // Remove acentos e caracteres especiais não permitidos no BR Code
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^a-zA-Z0-9 @._\-\/]/', '', $value);
        $value = trim($value);

        if ($maxLength > 0 && strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }
}
