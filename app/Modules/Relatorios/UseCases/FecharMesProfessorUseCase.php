<?php

namespace App\Modules\Relatorios\UseCases;

use App\Modules\Core\Contracts\GerarPagamentoProfessorPort;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Professor;
use App\Modules\Relatorios\Models\FechamentoProfessor;
use App\Modules\Relatorios\Services\ExtratoProfessorService;
use App\Modules\Relatorios\Services\Remuneracao\Competencia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Congela o extrato de um mês encerrado e, se a escola tiver o módulo financeiro,
 * lança a despesa do pagamento do professor.
 */
class FecharMesProfessorUseCase
{
    private const DIA_VENCIMENTO_PADRAO = 5;

    public function __construct(
        private readonly ExtratoProfessorService $extratos,
        private readonly GerarPagamentoProfessorPort $pagamentos,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {
    }

    public function execute(Professor $professor, Competencia $competencia, User $usuario, ?string $dataVencimento = null): FechamentoProfessor
    {
        if ($competencia->emAndamento()) {
            throw ValidationException::withMessages(['mes' => 'Só é possível fechar meses já encerrados.']);
        }

        return DB::transaction(function () use ($professor, $competencia, $usuario, $dataVencimento): FechamentoProfessor {
            Professor::query()->whereKey($professor->id)->lockForUpdate()->first();

            if ($this->extratos->fechamento($professor, $competencia)) {
                throw ValidationException::withMessages(['mes' => 'Este mês já foi fechado para este professor.']);
            }

            $calculo = $this->extratos->calcular($professor, $competencia);
            $resumo = $calculo['resumo'];

            return FechamentoProfessor::create([
                'id_professor' => $professor->id,
                'ano' => $competencia->ano,
                'mes' => $competencia->mes,
                'salario_fixo' => $resumo['salario_fixo'],
                'total_horas' => $resumo['total_horas'],
                'valor_hora_aula' => $resumo['valor_hora_aula'],
                'valor_comissao' => $resumo['valor_comissao'],
                'valor_total' => $resumo['valor_total'],
                'detalhes' => $calculo,
                'id_conta' => $this->gerarDespesa($professor, $competencia, $resumo['valor_total'], $dataVencimento),
                'id_usuario' => $usuario->id,
                'fechado_em' => now(),
            ]);
        });
    }

    private function gerarDespesa(Professor $professor, Competencia $competencia, float $valor, ?string $dataVencimento): ?int
    {
        $instituicao = InstituicaoContext::instituicao();

        if ($valor <= 0 || ! $instituicao || ! $this->entitlements->permiteModulo($instituicao, 'financeiro')) {
            return null;
        }

        $professor->loadMissing('usuario');

        return $this->pagamentos->gerar([
            'id_professor' => $professor->id,
            'descricao' => sprintf('Pagamento %s - %02d/%04d', $professor->usuario?->nome ?? 'professor', $competencia->mes, $competencia->ano),
            'valor' => $valor,
            'data_vencimento' => $dataVencimento ?? $this->vencimentoPadrao($competencia),
            'mes_referencia' => $competencia->mes,
            'ano_referencia' => $competencia->ano,
            'observacoes' => 'Gerada pelo fechamento do extrato do professor.',
        ]);
    }

    private function vencimentoPadrao(Competencia $competencia): string
    {
        $padrao = $competencia->inicio->addMonthNoOverflow()->day(self::DIA_VENCIMENTO_PADRAO);

        return CarbonImmutable::today()->max($padrao)->toDateString();
    }
}
