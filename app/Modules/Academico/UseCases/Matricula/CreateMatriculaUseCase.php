<?php

namespace App\Modules\Academico\UseCases\Matricula;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Support\PoliticaMensalidade;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMatriculaUseCase
{
    private const CAMPOS_MENSALIDADE = [
        'gerar_mensalidades',
        'valor_mensalidade_geracao',
        'quantidade_parcelas',
        'dia_vencimento',
        'data_inicio_mensalidades',
        'inicio_parcelas_mensalidade',
        'id_categoria_mensalidade',
        'observacoes_mensalidade',
    ];

    public function __construct(
        private readonly GerarMensalidadesDaMatriculaUseCase $gerarMensalidades,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    public function execute(array $dados): Matricula
    {
        $opcoesMensalidade = array_intersect_key($dados, array_flip(self::CAMPOS_MENSALIDADE));
        $dadosMatricula = array_diff_key($dados, array_flip(self::CAMPOS_MENSALIDADE));

        return DB::transaction(function () use ($dadosMatricula, $opcoesMensalidade) {
            if (($dadosMatricula['tipo'] ?? null) === 'turma' && ! empty($dadosMatricula['id_turma'])) {
                $excluida = Matricula::onlyTrashed()
                    ->where('id_aluno', $dadosMatricula['id_aluno'])
                    ->where('id_turma', $dadosMatricula['id_turma'])
                    ->latest('deleted_at')
                    ->first();

                if ($excluida) {
                    $excluida->restore();
                    $excluida->update($dadosMatricula);

                    $matricula = $excluida->fresh([
                        'aluno.usuario',
                        'turma.curso',
                        'turma.nivel',
                        'curso',
                        'nivel',
                        'professor.usuario',
                    ]);
                } else {
                    $matricula = Matricula::create($dadosMatricula)->load([
                        'aluno.usuario',
                        'turma.curso',
                        'turma.nivel',
                        'curso',
                        'nivel',
                        'professor.usuario',
                    ]);
                }
            } else {
                $matricula = Matricula::create($dadosMatricula)->load([
                    'aluno.usuario',
                    'turma.curso',
                    'turma.nivel',
                    'curso',
                    'nivel',
                    'professor.usuario',
                ]);
            }

            $this->talvezGerarMensalidades($matricula, $opcoesMensalidade);

            return $matricula;
        });
    }

    /**
     * @param  array<string, mixed>  $opcoes
     */
    private function talvezGerarMensalidades(Matricula $matricula, array $opcoes): void
    {
        $instituicao = InstituicaoContext::instituicao()
            ?? Instituicao::query()->find($matricula->id_instituicao);

        if ($instituicao === null || ! $this->entitlements->permiteModulo($instituicao, 'financeiro')) {
            return;
        }

        $tipo = $matricula->tipo ?? null;
        $gerarPorFormulario = (bool) ($opcoes['gerar_mensalidades'] ?? false);
        $dataMatricula = $matricula->data?->format('Y-m-d') ?? now()->toDateString();

        if ($tipo === 'curso') {
            $this->gerarParaCurso($matricula, $instituicao, $opcoes, $gerarPorFormulario, $dataMatricula);

            return;
        }

        if ($tipo !== 'turma' || empty($matricula->id_turma)) {
            return;
        }

        $turma = $matricula->turma ?? Turma::query()->find($matricula->id_turma);
        if ($turma === null) {
            return;
        }

        $politica = PoliticaMensalidade::resolver($turma, $instituicao, $dataMatricula);

        $deveGerar = match ($politica['modo']) {
            PoliticaMensalidade::MODO_AUTOMATICO,
            PoliticaMensalidade::MODO_DURACAO_TURMA => $politica['valor'] > 0,
            default => $gerarPorFormulario,
        };

        if (! $deveGerar) {
            return;
        }

        $valor = isset($opcoes['valor_mensalidade_geracao']) && $opcoes['valor_mensalidade_geracao'] !== null && $opcoes['valor_mensalidade_geracao'] !== ''
            ? round((float) $opcoes['valor_mensalidade_geracao'], 2)
            : $politica['valor'];

        if ($valor <= 0) {
            return;
        }

        $modoAutomatico = in_array($politica['modo'], [
            PoliticaMensalidade::MODO_AUTOMATICO,
            PoliticaMensalidade::MODO_DURACAO_TURMA,
        ], true);

        // Em modos automáticos, quantidade e início vêm da política (não do payload do form),
        // para um "12" herdado na UI não sobrescrever o cálculo por duração.
        if ($politica['modo'] === PoliticaMensalidade::MODO_DURACAO_TURMA) {
            $quantidade = $politica['quantidade_parcelas'];
            $inicioEfetivo = PoliticaMensalidade::inicioEfetivoDuracao($turma, $dataMatricula) ?? $dataMatricula;
            $dataInicioLote = PoliticaMensalidade::dataInicioLote($inicioEfetivo, $politica['inicio_parcelas']);
        } elseif ($modoAutomatico) {
            $quantidade = $politica['quantidade_parcelas'];
            $dataInicioLote = PoliticaMensalidade::dataInicioLote($dataMatricula, $politica['inicio_parcelas']);
        } else {
            $quantidade = (int) ($opcoes['quantidade_parcelas'] ?? $politica['quantidade_parcelas']);
            $dataInicioLote = $this->resolverDataInicioFormulario($opcoes, $dataMatricula, $politica['inicio_parcelas']);
        }

        $this->gerarMensalidades->execute($matricula, [
            'valor' => $valor,
            'quantidade_parcelas' => $quantidade,
            'dia_vencimento' => (int) ($opcoes['dia_vencimento'] ?? $politica['dia_vencimento']),
            'data_inicio' => $dataInicioLote,
            'id_categoria' => $opcoes['id_categoria_mensalidade'] ?? null,
            'observacoes' => $opcoes['observacoes_mensalidade'] ?? "Geração automática via matrícula #{$matricula->id}",
        ]);
    }

    /**
     * Curso: gera quando há intenção no formulário (flag e/ou valor > 0).
     *
     * @param  array<string, mixed>  $opcoes
     */
    private function gerarParaCurso(
        Matricula $matricula,
        Instituicao $instituicao,
        array $opcoes,
        bool $gerarPorFormulario,
        string $dataMatricula,
    ): void {
        $valor = isset($opcoes['valor_mensalidade_geracao']) && $opcoes['valor_mensalidade_geracao'] !== null && $opcoes['valor_mensalidade_geracao'] !== ''
            ? round((float) $opcoes['valor_mensalidade_geracao'], 2)
            : 0.0;

        // Valor informado no payload já indica intenção de gerar (mesmo se a flag falhar no client).
        $deveGerar = $gerarPorFormulario || $valor > 0;
        if (! $deveGerar) {
            return;
        }

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'valor_mensalidade_geracao' => 'Informe o valor da mensalidade para gerar as parcelas.',
            ]);
        }

        $politica = PoliticaMensalidade::resolver(null, $instituicao, $dataMatricula);
        $qtd = (int) ($opcoes['quantidade_parcelas'] ?? $politica['quantidade_parcelas']);
        if ($qtd < 1) {
            throw ValidationException::withMessages([
                'quantidade_parcelas' => 'Informe a quantidade de parcelas (1 a 24).',
            ]);
        }

        $this->gerarMensalidades->execute($matricula, [
            'valor' => $valor,
            'quantidade_parcelas' => max(1, min(24, $qtd)),
            'dia_vencimento' => (int) ($opcoes['dia_vencimento'] ?? $politica['dia_vencimento']),
            'data_inicio' => $this->resolverDataInicioFormulario($opcoes, $dataMatricula, $politica['inicio_parcelas']),
            'id_categoria' => $opcoes['id_categoria_mensalidade'] ?? null,
            'observacoes' => $opcoes['observacoes_mensalidade'] ?? "Geração via matrícula em curso #{$matricula->id}",
        ]);
    }

    /**
     * No formulário: data_inicio_mensalidades enviada manda; senão deriva da política.
     *
     * @param  array<string, mixed>  $opcoes
     */
    private function resolverDataInicioFormulario(array $opcoes, string $dataMatricula, string $inicioParcelasPolitica): string
    {
        if (! empty($opcoes['data_inicio_mensalidades'])) {
            return (string) $opcoes['data_inicio_mensalidades'];
        }

        $inicio = $inicioParcelasPolitica;
        $inicioRequest = $opcoes['inicio_parcelas_mensalidade'] ?? null;
        if (in_array($inicioRequest, PoliticaMensalidade::INICIOS, true)) {
            $inicio = $inicioRequest;
        }

        return PoliticaMensalidade::dataInicioLote($dataMatricula, $inicio);
    }
}
