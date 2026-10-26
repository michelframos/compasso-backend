<?php

namespace App\Modules\Financeiro\Services;

use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Modules\Financeiro\Models\Conta;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/** Payload BR Code (EMV) do PIX e o QR em PNG, no formato usado pelo carnê. */
class PixPayloadService
{
    /** @return array{payload: string, qr_code: string, valor: float} */
    public function paraConta(ConfiguracaoPix $config, Conta $conta): array
    {
        $payload = $this->gerarPayload($config, $conta);

        return [
            'payload' => $payload,
            'qr_code' => $this->qrCodeBase64($payload),
            'valor' => (float) $this->valor($conta),
        ];
    }

    public function gerarPayload(ConfiguracaoPix $config, Conta $conta): string
    {
        $chavePix = $this->sanitizarString($config->chave_pix);
        $nome = $this->sanitizarString($config->nome_beneficiario, 25);
        $cidade = $this->sanitizarString($config->cidade, 15);
        $valor = $this->valor($conta);
        $txid = 'CARNE'.str_pad((string) $conta->id, 10, '0', STR_PAD_LEFT);
        $descricao = $this->sanitizarString($conta->descricao ?? 'Parcela', 72);

        $merchantAccountInfo = $this->tlv('00', 'BR.GOV.BCB.PIX').$this->tlv('01', $chavePix);
        if ($descricao) {
            $merchantAccountInfo .= $this->tlv('02', $descricao);
        }

        $payload = '';
        $payload .= $this->tlv('00', '01');
        $payload .= $this->tlv('26', $merchantAccountInfo);
        $payload .= $this->tlv('52', '0000');
        $payload .= $this->tlv('53', '986');
        $payload .= $this->tlv('54', $valor);
        $payload .= $this->tlv('58', 'BR');
        $payload .= $this->tlv('59', $nome);
        $payload .= $this->tlv('60', $cidade);
        $payload .= $this->tlv('62', $this->tlv('05', $txid));
        $payload .= '6304';
        $payload .= $this->calcularCRC16($payload);

        return $payload;
    }

    public function qrCodeBase64(string $pixPayload): string
    {
        $qrCode = new QrCode($pixPayload);
        $writer = new PngWriter();

        return base64_encode($writer->write($qrCode)->getString());
    }

    private function valor(Conta $conta): string
    {
        $pago = $conta->relationLoaded('pagamentos')
            ? (float) $conta->pagamentos->sum('valor_pago')
            : $conta->totalPago();
        $saldo = round((float) $conta->valor - $pago, 2);

        return number_format($saldo > 0 ? $saldo : (float) $conta->valor, 2, '.', '');
    }

    private function tlv(string $tag, string $value): string
    {
        $length = str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);

        return $tag.$length.$value;
    }

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

    private function sanitizarString(string $value, int $maxLength = 0): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^a-zA-Z0-9 @._\-\/]/', '', $value);
        $value = trim($value);

        if ($maxLength > 0 && strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }
}
