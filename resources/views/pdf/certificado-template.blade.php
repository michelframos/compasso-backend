<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
            font-family: DejaVu Sans, sans-serif;
            color: #111;
        }
        .pagina {
            position: relative;
            width: 297mm;
            height: 210mm;
            overflow: hidden;
            @if ($fundo_data_uri)
            background-image: url('{{ $fundo_data_uri }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            @endif
        }
        .conteudo {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="pagina">
        <div class="conteudo">
            {!! $conteudo !!}
        </div>
    </div>
</body>
</html>
