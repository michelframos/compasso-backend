<?php

namespace App\Modules\Financeiro\Services;

use App\Models\ConfiguracaoEmpresa;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Modules\Financeiro\Models\Conta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class GerarCarneService
{
    public function __construct(private readonly PixPayloadService $pix) {}

    /**
     * @param  Collection<int, Conta>  $contas  já autorizadas, só receitas
     */
    public function download(Collection $contas, ?string $titulo, string $preferenciaDados): Response
    {
        $configPix = ConfiguracaoPix::query()->first();
        if ($configPix === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Configuração PIX não cadastrada. Acesse as configurações do sistema para cadastrar a chave PIX da escola.',
            ], 400));
        }

        $contas->loadMissing(['aluno.usuario', 'aluno.responsaveis.usuario', 'matricula.turma.curso', 'categoria', 'pagamentos']);

        $configEmpresa = ConfiguracaoEmpresa::query()->first();
        $nomeEmpresa = $configEmpresa?->nome_fantasia ?? $configPix->nome_beneficiario;

        $parcelas = $contas
            ->sortBy(fn (Conta $conta) => $conta->data_vencimento?->toDateString().' '.$conta->id)
            ->values()
            ->map(function (Conta $conta) use ($configPix) {
                $pix = $configPix->exibir_qrcode ? $this->pix->paraConta($configPix, $conta) : null;

                return [
                    'conta' => $conta,
                    'qr_code' => $pix['qr_code'] ?? null,
                    'pix_payload' => $pix['payload'] ?? null,
                    'chave_pix' => $configPix->chave_pix,
                ];
            })
            ->all();

        $primeiraContaAluno = $contas->first()?->aluno?->usuario?->nome ?? 'Aluno';
        $titulo ??= 'Carnê de Pagamento - '.$primeiraContaAluno;

        $pdf = Pdf::loadView('pdf.carne', [
            'parcelas' => $parcelas,
            'titulo' => $titulo,
            'nome_beneficiario' => $nomeEmpresa,
            'exibir_qrcode' => $configPix->exibir_qrcode,
            'preferencia_dados' => $preferenciaDados,
            'gerado_em' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('carne_'.now()->format('YmdHis').'.pdf');
    }
}
