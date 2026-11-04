<?php

namespace App\Modules\Bilheteria\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeIngressoService
{
    public function dataUri(string $payload): string
    {
        $qrCode = new QrCode($payload);
        $writer = new PngWriter;
        $png = $writer->write($qrCode)->getString();

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
