<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid #ddd; }
        th { width: 40%; color: #555; font-weight: normal; }
        .valor { font-size: 20px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <p class="muted">{{ $escola }}</p>
    <h1>Recibo de pagamento</h1>
    <p class="muted">Emitido em {{ $emitido_em }}</p>

    <p class="valor">R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}</p>

    <table>
        <tr>
            <th>Aluno</th>
            <td>{{ $aluno }}</td>
        </tr>
        <tr>
            <th>Referente a</th>
            <td>{{ $conta->descricao }}</td>
        </tr>
        <tr>
            <th>Vencimento</th>
            <td>{{ $conta->data_vencimento?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Pago em</th>
            <td>{{ \Illuminate\Support\Carbon::parse($pagamento->data_pagamento)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Forma</th>
            <td>{{ $pagamento->forma_pagamento }}</td>
        </tr>
    </table>
</body>
</html>
