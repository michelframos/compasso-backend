<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 8mm 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            background: #fff;
        }

        /* === WRAPPER DE CADA VIA === */
        .via-wrapper {
            width: 100%;
            margin-bottom: 4mm;
            page-break-inside: avoid;
        }

        /* === LINHA DE CORTE === */
        .corte {
            display: flex;
            align-items: center;
            margin-bottom: 2mm;
            font-size: 8px;
            color: #555;
        }

        .corte-linha {
            flex: 1;
            border-top: 1px dashed #999;
        }

        .corte-icone {
            padding: 0 4px;
            font-size: 10px;
        }

        /* === CARNE (duas vias lado a lado) === */
        .carne-row {
            display: table;
            width: 100%;
            border: 1px solid #000;
        }

        .via-aluno,
        .via-escola {
            display: table-cell;
            vertical-align: top;
            padding: 0;
        }

        .via-aluno {
            width: 42%;
            border-right: 1px dashed #666;
        }

        .via-escola {
            width: 58%;
        }

        /* === CABEÇALHO DE VIA === */
        .via-header {
            background: #f3f4f6;
            color: #000;
            padding: 3px 6px;
            border-bottom: 1px solid #ccc;
            font-size: 8px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .via-header-escola {
            background: #f3f4f6;
            color: #000;
            padding: 0;
            border-bottom: 1px solid #ccc;
            display: table;
            width: 100%;
        }

        .via-header-escola-nome {
            display: table-cell;
            padding: 3px 6px;
            font-size: 12px;
            font-weight: bold;
            vertical-align: middle;
        }

        .via-header-escola-parcela {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            padding: 2px 5px;
            white-space: nowrap;
        }

        .parcela-box {
            display: inline-block;
            background: #fff;
            color: #000;
            border: 1px solid #ccc;
            padding: 2px 8px;
            font-size: 8px;
            text-align: center;
            min-width: 65px;
            margin-top: 10px;
            margin-bottom: -5px;
        }

        .parcela-box .label {
            font-size: 7px;
            color: #333;
            display: block;
        }

        .parcela-box .valor-parcela {
            font-size: 11px;
            font-weight: bold;
        }

        /* === CAMPOS (tabela) === */
        .campos {
            width: 100%;
            border-collapse: collapse;
        }

        .campos td {
            border: 1px solid #ccc;
            padding: 2px 4px;
            vertical-align: top;
        }

        .campos td.label {
            font-size: 7px;
            color: #444;
            display: block;
            margin-bottom: 1px;
        }

        .campo-label {
            font-size: 7px;
            color: #444;
            display: block;
            line-height: 1.2;
        }

        .campo-valor {
            font-size: 9px;
            font-weight: bold;
            color: #000;
            display: block;
            line-height: 1.4;
        }

        .campo-valor-lg {
            font-size: 11px;
            font-weight: bold;
            color: #000;
        }

        /* === LINHA DE CAMPOS === */
        .row-campos {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .col {
            display: table-cell;
            border: 1px solid #ccc;
            padding: 2px 4px;
            vertical-align: top;
        }

        /* === SEÇÃO DE INSTRUÇÕES === */
        .instrucoes {
            padding: 3px 5px;
            border-top: 1px solid #ccc;
        }

        .instrucoes .campo-label {
            font-size: 7px;
            color: #444;
            margin-bottom: 1px;
        }

        .instrucoes p {
            font-size: 8px;
            color: #000;
            font-style: italic;
        }

        /* === RODAPÉ DA VIA === */
        .via-rodape {
            border-top: 1px solid #ccc;
            padding: 2px 5px;
            display: table;
            width: 100%;
        }

        .via-rodape-esq {
            display: table-cell;
            font-size: 7px;
            color: #444;
            vertical-align: bottom;
        }

        .via-rodape-dir {
            display: table-cell;
            font-size: 7px;
            color: #444;
            text-align: right;
            vertical-align: bottom;
        }

        /* === QR CODE === */
        .qr-area {
            display: table;
            width: 100%;
            border-top: 1px solid #ccc;
        }

        .qr-img-cell {
            display: table-cell;
            padding: 4px;
            vertical-align: middle;
            text-align: center;
            width: 80px;
        }

        .qr-img-cell img {
            width: 70px;
            height: 70px;
        }

        .qr-info-cell {
            display: table-cell;
            padding: 4px 5px;
            vertical-align: top;
        }

        .autenticacao {
            border-top: 1px solid #ccc;
            padding: 2px 5px;
            font-size: 7px;
            color: #555;
            text-align: right;
        }
    </style>
</head>
<body>
    @foreach ($parcelas as $parcela)
        @php
            $conta        = $parcela['conta'];
            $aluno        = $conta->aluno?->usuario;
            $turma        = $conta->matricula?->turma;
            $curso        = $turma?->curso;
            $vencimento   = \Carbon\Carbon::parse($conta->data_vencimento)->format('d/m/Y');
            $valor        = number_format($conta->saldo_devedor > 0 ? $conta->saldo_devedor : $conta->valor, 2, ',', '.');
            $nomeAluno    = $aluno?->nome ?? 'Não informado';

            // Se a preferência for usar dados do responsável, tenta buscar o primeiro responsável
            if (isset($preferencia_dados) && $preferencia_dados === 'responsavel') {
                $responsavel = $conta->aluno?->responsaveis?->first();
                if ($responsavel && $responsavel->usuario) {
                    $nomeAluno = $responsavel->usuario->nome;
                }
            }
            $numParcela   = $conta->numero_parcela ? str_pad($conta->numero_parcela, 2, '0', STR_PAD_LEFT) : '--';
            $qtdParcelas  = $conta->quantidade_parcelas ? str_pad($conta->quantidade_parcelas, 2, '0', STR_PAD_LEFT) : '--';
            $numLanc      = str_pad($conta->id, 6, '0', STR_PAD_LEFT);
            $parcelaLabel = $numParcela . '/' . $qtdParcelas;
            $descCurso    = $curso?->nome ?? ($turma?->descricao ?? '');
            $tipoCob      = $parcelaLabel . ' ' . ($conta->descricao ?? 'Mensalidade');
        @endphp

        <div class="via-wrapper">

            {{-- Linha de corte superior --}}
            <div class="corte">
                <div class="corte-linha"></div>
                <div class="corte-icone">✂</div>
                <div class="corte-linha"></div>
            </div>

            {{-- Carnê: duas vias --}}
            <div class="carne-row">

                {{-- ============ VIA DO ALUNO (esquerda) ============ --}}
                <div class="via-aluno">

                    {{-- Cabeçalho preto --}}
                    <div class="via-header">
                        <span>{{ $nome_beneficiario }}</span>
                    </div>

                    {{-- Nome do Aluno --}}
                    <div class="row-campos">
                        <div class="col" style="width:100%">
                            <span class="campo-label">{{ (isset($preferencia_dados) && $preferencia_dados === 'responsavel') ? 'Responsável' : 'Nome Aluno' }}</span>
                            <span class="campo-valor">{{ strtoupper($nomeAluno) }}</span>
                        </div>
                    </div>

                    {{-- Tipo Cobrança + Vencimento --}}
                    <div class="row-campos">
                        <div class="col" style="width:65%">
                            <span class="campo-label">Tipo Cobrança</span>
                            <span class="campo-valor">{{ $tipoCob }}</span>
                        </div>
                        <div class="col" style="width:35%">
                            <span class="campo-label">Vencimento</span>
                            <span class="campo-valor">{{ $vencimento }}</span>
                        </div>
                    </div>

                    {{-- Nº Lanç. + Valor c/ Desconto + Valor s/ Desconto --}}
                    <div class="row-campos">
                        <div class="col" style="width:30%">
                            <span class="campo-label">Nº Lanç.</span>
                            <span class="campo-valor">{{ $numLanc }}</span>
                        </div>
                        <div class="col" style="width:35%">
                            <span class="campo-label">Valor c/ Desconto</span>
                            <span class="campo-valor">R$ {{ $valor }}</span>
                        </div>
                        <div class="col" style="width:35%">
                            <span class="campo-label">Valor s/ Desconto</span>
                            <span class="campo-valor">R$ {{ $valor }}</span>
                        </div>
                    </div>

                    {{-- Juros/Multa + Desconto + V. Desconto --}}
                    <div class="row-campos">
                        <div class="col" style="width:33%">
                            <span class="campo-label">Juros/Multa(+)</span>
                            <span class="campo-valor">&nbsp;</span>
                        </div>
                        <div class="col" style="width:33%">
                            <span class="campo-label">Desconto(-)</span>
                            <span class="campo-valor">&nbsp;</span>
                        </div>
                        <div class="col" style="width:34%">
                            <span class="campo-label">V. Cobrado(=)</span>
                            <span class="campo-valor">&nbsp;</span>
                        </div>
                    </div>

                    {{-- Curso --}}
                    @if($descCurso)
                    <div class="row-campos">
                        <div class="col" style="width:100%">
                            <span class="campo-label">Curso</span>
                            <span class="campo-valor">{{ strtoupper($descCurso) }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- Rodapé da via --}}
                    <div class="via-rodape">
                        <div class="via-rodape-esq">Via do Aluno</div>
                        <div class="via-rodape-dir">&nbsp;</div>
                    </div>

                </div>

                {{-- ============ VIA DA ESCOLA (direita) ============ --}}
                <div class="via-escola">

                    {{-- Cabeçalho preto com nome e parcela --}}
                    <div class="via-header-escola">
                        <div class="via-header-escola-nome">{{ strtoupper($nome_beneficiario) }}</div>
                        <div class="via-header-escola-parcela">
                            <div class="parcela-box" style="margin-right:4px;">
                                <span class="label">Nº Lanç.</span>
                                <span class="valor-parcela">{{ $numLanc }}</span>
                            </div>
                            <div class="parcela-box">
                                <span class="label">Parcela</span>
                                <span class="valor-parcela">{{ $parcelaLabel }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Nome do Aluno --}}
                    <div class="row-campos">
                        <div class="col" style="width:100%">
                            <span class="campo-label">{{ (isset($preferencia_dados) && $preferencia_dados === 'responsavel') ? 'Responsável' : 'Nome Aluno' }}</span>
                            <span class="campo-valor">{{ strtoupper($nomeAluno) }}</span>
                        </div>
                    </div>

                    {{-- Tipo Cobrança + Vencimento + Sem Desconto + Com Desconto --}}
                    <div class="row-campos">
                        <div class="col" style="width:32%">
                            <span class="campo-label">Tipo Cobrança</span>
                            <span class="campo-valor">{{ $tipoCob }}</span>
                        </div>
                        <div class="col" style="width:18%">
                            <span class="campo-label">Vencimento</span>
                            <span class="campo-valor">{{ $vencimento }}</span>
                        </div>
                        <div class="col" style="width:25%">
                            <span class="campo-label">Sem Desconto(=)</span>
                            <span class="campo-valor campo-valor-lg">R$ {{ $valor }}</span>
                        </div>
                        <div class="col" style="width:25%">
                            <span class="campo-label">Com Desconto(=)</span>
                            <span class="campo-valor campo-valor-lg">R$ {{ $valor }}</span>
                        </div>
                    </div>

                    {{-- Curso --}}
                    @if($descCurso)
                    <div class="row-campos">
                        <div class="col" style="width:100%">
                            <span class="campo-label">Curso</span>
                            <span class="campo-valor">{{ strtoupper($descCurso) }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- Instruções --}}
                    @if($exibir_qrcode)
                    <div class="instrucoes">
                        <span class="campo-label">Instruções</span>
                        <p>Pague com PIX escaneando o QR Code abaixo com o app do seu banco.</p>
                    </div>

                    {{-- QR Code PIX --}}
                    <div class="qr-area">
                        <div class="qr-img-cell">
                            <img src="data:image/png;base64,{{ $parcela['qr_code'] }}" alt="QR Code PIX" />
                        </div>
                        <div class="qr-info-cell">
                            <span class="campo-label" style="font-size:8px; font-weight:bold;">Pague com PIX</span>
                            <p style="font-size:7px; color:#333; margin-top:2px; word-break:break-all;">
                                Chave: {{ $parcela['chave_pix'] }}
                            </p>
                            <p style="font-size:7px; color:#555; margin-top:4px; font-style:italic;">
                                *Pagamentos via PIX são confirmados instantaneamente.
                            </p>
                        </div>
                    </div>
                    @else
                    <div class="instrucoes" style="border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                        <span class="campo-label">Instruções</span>
                        <p>Realize o pagamento na secretaria da escola ou conforme orientações recebidas.</p>
                    </div>
                    <div style="height: 60px;"></div>
                    @endif

                    {{-- Rodapé da via --}}
                    <div class="via-rodape">
                        <div class="via-rodape-esq">Via da Escola</div>
                        <div class="via-rodape-dir">Autenticação Mecânica - Via da Escola</div>
                    </div>

                </div>{{-- fim via-escola --}}

            </div>{{-- fim carne-row --}}

        </div>{{-- fim via-wrapper --}}
    @endforeach
</body>
</html>
