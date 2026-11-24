<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            width: 150mm;
            height: 70mm;
            font-family: DejaVu Sans, sans-serif;
            color: #18181b;
        }
        .pagina {
            box-sizing: border-box;
            width: 150mm;
            height: 70mm;
            padding: 6mm;
            border: 1px solid #e4e4e7;
            display: table;
        }
        .col {
            display: table-cell;
            vertical-align: middle;
        }
        .meta { width: 70%; padding-right: 4mm; }
        .qr { width: 30%; text-align: center; }
        .escola { font-size: 10px; color: #71717a; text-transform: uppercase; letter-spacing: 0.04em; }
        .titulo { font-size: 16px; font-weight: bold; margin-top: 2mm; }
        .linha { font-size: 11px; margin-top: 1.5mm; color: #3f3f46; }
        .numero { font-size: 13px; font-weight: bold; margin-top: 3mm; font-family: DejaVu Sans Mono, monospace; }
        .qr img { width: 28mm; height: 28mm; }
    </style>
</head>
<body>
    <div class="pagina">
        <div class="col meta">
            <div class="escola">{{ $escola }}</div>
            <div class="titulo">{{ $espetaculo ?? 'Ingresso' }}</div>
            @if ($data || $hora || $local)
                <div class="linha">
                    {{ collect([$data, $hora, $local])->filter()->implode(' · ') }}
                </div>
            @endif
            @if ($comprador)
                <div class="linha">Comprador: {{ $comprador }}</div>
            @endif
            <div class="linha">Status: {{ $status }} · R$ {{ $valor }}</div>
            <div class="numero">Nº {{ $numero }}</div>
        </div>
        <div class="col qr">
            <img src="{{ $qr_data_uri }}" alt="QR">
        </div>
    </div>
</body>
</html>
