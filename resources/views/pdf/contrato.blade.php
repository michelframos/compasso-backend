<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 11px; }
        .conteudo { margin-top: 24px; }
        .aceite { margin-top: 32px; font-size: 11px; color: #333; }
    </style>
</head>
<body>
    <p class="muted">{{ $escola }}</p>
    <h1>{{ $nome }}</h1>
    <p class="muted">{{ $aluno }} · emitido em {{ $emitido_em }}</p>

    <div class="conteudo">{!! $conteudo !!}</div>

    @if ($aceito_em)
        <p class="aceite">Aceito digitalmente em {{ $aceito_em }}.</p>
    @endif
</body>
</html>
