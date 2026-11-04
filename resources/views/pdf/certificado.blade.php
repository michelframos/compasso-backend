<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; margin: 40px; }
        .borda { border: 3px solid #312e81; padding: 36px 48px; min-height: 480px; }
        .escola { font-size: 14px; color: #4338ca; letter-spacing: 2px; text-transform: uppercase; }
        h1 { font-size: 34px; margin: 28px 0 8px; color: #18181b; }
        .subtitulo { font-size: 14px; color: #52525b; margin-bottom: 36px; }
        .aluno { font-size: 26px; font-weight: bold; margin: 12px 0; }
        .texto { font-size: 14px; line-height: 1.6; color: #3f3f46; }
        .meta { margin-top: 40px; font-size: 12px; color: #71717a; }
        .codigo { margin-top: 24px; font-size: 11px; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="borda">
        <p class="escola">{{ $escola }}</p>
        <h1>Certificado de conclusão</h1>
        <p class="subtitulo">Este documento certifica que</p>

        <p class="aluno">{{ $aluno }}</p>

        <p class="texto">
            concluiu
            @if ($curso)
                o curso <strong>{{ $curso }}</strong>
            @else
                o programa
            @endif
            @if ($turma)
                na turma <strong>{{ $turma }}</strong>
            @endif
            @if ($nivel)
                · nível <strong>{{ $nivel }}</strong>
            @endif
            , com carga horária de <strong>{{ $carga_horaria }} horas</strong>.
        </p>

        <p class="meta">Emitido em {{ $emitido_em }}.</p>
        <p class="codigo">Código de verificação: {{ $codigo }}</p>
    </div>
</body>
</html>
