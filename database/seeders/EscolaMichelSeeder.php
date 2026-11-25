<?php

namespace Database\Seeders;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Apresentacao;
use App\Models\ApresentacaoAluno;
use App\Models\AulaPresenca;
use App\Models\AulaTurma;
use App\Models\CategoriaConta;
use App\Models\Conta;
use App\Models\ContaPagamento;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Espetaculo;
use App\Models\Instrumento;
use App\Models\InstrumentoHistorico;
use App\Models\Lead;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\AvisoTurmaDestinatario;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Bilheteria\Models\CupomIngresso;
use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Escola de desenvolvimento com dados determinísticos de todos os domínios.
 *
 * Credenciais (senha: Redo@2018):
 * - michelframos@hotmail.com  → admin (login com CNPJ 26.366.000/0001-01)
 * - professor1@email.com / professor2@email.com
 * - aluno1@email.com … aluno4@email.com
 */
class EscolaMichelSeeder extends Seeder
{
    private const SENHA = 'Redo@2018';

    private const CNPJ = '26366000000101';

    private const SLUG = 'escola-michel';

    /** @var list<array{nome: string, idade_minima: int, idade_maxima: int, ordem: int}> */
    private const NIVEIS = [
        ['nome' => 'Infantil', 'idade_minima' => 4, 'idade_maxima' => 11, 'ordem' => 1],
        ['nome' => 'Iniciante', 'idade_minima' => 12, 'idade_maxima' => 15, 'ordem' => 2],
        ['nome' => 'Básico', 'idade_minima' => 16, 'idade_maxima' => 18, 'ordem' => 3],
        ['nome' => 'Intermediário', 'idade_minima' => 19, 'idade_maxima' => 21, 'ordem' => 4],
        ['nome' => 'Avançado', 'idade_minima' => 22, 'idade_maxima' => 30, 'ordem' => 5],
    ];

    public function run(): void
    {
        $plano = PlanoAssinatura::query()->where('slug', 'enterprise')->first();

        if (! $plano) {
            $this->command?->error('Rode PlanoAssinaturaSeeder antes do EscolaMichelSeeder.');

            return;
        }

        $instituicao = Instituicao::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'nome_fantasia' => 'Escola Michel',
                'razao_social' => 'Escola Michel LTDA',
                'cnpj' => self::CNPJ,
                'rua' => 'Rua das Melodias',
                'numero' => '200',
                'bairro' => 'Centro',
                'cep' => '01310100',
                'status' => Instituicao::STATUS_ATIVO,
                'id_plano_assinatura' => $plano->id,
                'trial_ends_at' => null,
                'trial_usa_padrao' => false,
                'assinatura_inicia_em' => now()->subMonth()->toDateString(),
                'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            ],
        );

        $admin = $this->upsertUser(
            email: 'michelframos@hotmail.com',
            nome: 'Michel Ramos',
            role: 'admin',
            cpf: '55555555555',
        );

        $this->vincular($instituicao, $admin, 'admin');

        InstituicaoContext::runWith($instituicao->id, $instituicao->slug, function () use ($instituicao, $admin): void {
            $force = filter_var(env('DEMO_SEED_FORCE', false), FILTER_VALIDATE_BOOL);

            if (Curso::query()->exists() && ! $force) {
                $this->command?->warn("[{$instituicao->slug}] já possui dados — pulando domínio. Use DEMO_SEED_FORCE=1 para recriar.");

                return;
            }

            if ($force && Curso::query()->exists()) {
                $this->wipeDomainData();
                $this->command?->warn("[{$instituicao->slug}] domínio limpo (FORCE).");
            }

            $this->seedDominioCompleto($instituicao, $admin);
        });

        $this->command?->newLine();
        $this->command?->info('=== Escola Michel (senha: Redo@2018) ===');
        $this->command?->table(
            ['Email', 'Role', 'Tenant slug', 'CNPJ (login)'],
            [
                ['michelframos@hotmail.com', 'admin', self::SLUG, '26.366.000/0001-01'],
                ['professor1@email.com', 'professor', self::SLUG, '26.366.000/0001-01'],
                ['professor2@email.com', 'professor', self::SLUG, '26.366.000/0001-01'],
                ['aluno1@email.com … aluno4@email.com', 'aluno', self::SLUG, '26.366.000/0001-01'],
            ],
        );
    }

    private function seedDominioCompleto(Instituicao $instituicao, User $admin): void
    {
        $prof1User = $this->upsertUser(
            email: 'professor1@email.com',
            nome: 'Professor 1',
            role: 'professor',
            cpf: '55555555501',
        );
        $prof2User = $this->upsertUser(
            email: 'professor2@email.com',
            nome: 'Professor 2',
            role: 'professor',
            cpf: '55555555502',
        );

        $this->vincular($instituicao, $prof1User, 'professor');
        $this->vincular($instituicao, $prof2User, 'professor');

        $professor1 = Professor::query()->firstOrCreate(
            ['id_usuario' => $prof1User->id],
            [
                'comissao' => 10,
                'valor_hora_aula' => 80,
                'observacoes' => 'Professor 1 — seed Escola Michel',
                'criado_em' => now(),
            ],
        );
        $professor2 = Professor::query()->firstOrCreate(
            ['id_usuario' => $prof2User->id],
            [
                'comissao' => 10,
                'valor_hora_aula' => 80,
                'observacoes' => 'Professor 2 — seed Escola Michel',
                'criado_em' => now(),
            ],
        );

        $contrato = Contrato::query()->create([
            'nome' => 'Contrato de Matrícula Padrão',
            'conteudo' => '<p>Eu <strong>{{nome_aluno}}</strong> declaro estar ciente das regras da escola, valores e horários da turma {{nome_turma}}.</p>',
        ]);

        $templateCertificado = TemplateCertificado::query()->create([
            'nome' => 'Certificado Conclusão Padrão',
            'conteudo' => '<p>Certificamos que <strong>{{nome_aluno}}</strong> concluiu o curso <strong>{{nome_curso}}</strong> com carga horária de {{carga_horaria}} horas.</p>',
        ]);

        $violao = Curso::query()->create([
            'nome' => 'Violão',
            'descricao' => 'Curso de Violão — seed Escola Michel',
            'contrato_id' => $contrato->id,
            'id_template_certificado' => $templateCertificado->id,
        ]);
        $ballet = Curso::query()->create([
            'nome' => 'Ballet',
            'descricao' => 'Curso de Ballet — seed Escola Michel',
            'contrato_id' => $contrato->id,
            'id_template_certificado' => $templateCertificado->id,
        ]);

        $niveisViolao = $this->seedNiveis($violao);
        $niveisBallet = $this->seedNiveis($ballet);

        $nivelIntermediario = $niveisViolao->firstWhere('nome', 'Intermediário');
        $nivelIniciante = $niveisBallet->firstWhere('nome', 'Iniciante');
        $nivelAvancadoViolao = $niveisViolao->firstWhere('nome', 'Avançado');

        $turmaViolao = Turma::query()->create([
            'id_curso' => $violao->id,
            'id_nivel' => $nivelIntermediario->id,
            'id_professor' => $professor1->id,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'maximo_alunos' => 15,
            'valor_mensalidade' => 280,
            'descricao' => 'Turma Violão Intermediário',
            'tipo_agendamento' => 'datas',
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => now()->addMonths(4)->toDateString(),
            'contrato_id' => $contrato->id,
        ]);

        TurmaHorario::query()->create([
            'id_turma' => $turmaViolao->id,
            'dia_semana' => 'terca',
            'hora_inicio' => '18:00:00',
            'hora_termino' => '19:00:00',
        ]);

        $turmaBallet = Turma::query()->create([
            'id_curso' => $ballet->id,
            'id_nivel' => $nivelIniciante->id,
            'id_professor' => $professor2->id,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'maximo_alunos' => 15,
            'valor_mensalidade' => 250,
            'descricao' => 'Turma Ballet Iniciante',
            'tipo_agendamento' => 'datas',
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => now()->addMonths(4)->toDateString(),
            'contrato_id' => $contrato->id,
        ]);

        TurmaHorario::query()->create([
            'id_turma' => $turmaBallet->id,
            'dia_semana' => 'quinta',
            'hora_inicio' => '17:00:00',
            'hora_termino' => '18:00:00',
        ]);

        $alunosViolao = collect();
        for ($i = 1; $i <= 3; $i++) {
            $alunosViolao->push($this->criarAluno(
                instituicao: $instituicao,
                email: "aluno{$i}@email.com",
                nome: "Aluno {$i}",
                cpf: '5555555551'.(string) $i,
                dataAniversario: now()->subYears(20)->subMonths($i)->toDateString(),
            ));
        }

        $alunoBallet = $this->criarAluno(
            instituicao: $instituicao,
            email: 'aluno4@email.com',
            nome: 'Aluno 4',
            cpf: '55555555514',
            dataAniversario: now()->subYears(13)->subMonths(2)->toDateString(),
        );

        $todosAlunos = $alunosViolao->concat([$alunoBallet])->values();

        $this->seedLeads($todosAlunos);

        $matriculas = collect();
        foreach ($alunosViolao as $aluno) {
            $matriculas->push(Matricula::query()->create([
                'id_aluno' => $aluno->id,
                'id_turma' => $turmaViolao->id,
                'tipo' => 'turma',
                'data' => now()->subDays(30)->toDateString(),
                'status' => 'ativa',
                'observacoes' => 'Matrícula seed — Violão Intermediário',
                'contrato_id' => $contrato->id,
            ]));
        }

        $matriculas->push(Matricula::query()->create([
            'id_aluno' => $alunoBallet->id,
            'id_turma' => $turmaBallet->id,
            'tipo' => 'turma',
            'data' => now()->subDays(20)->toDateString(),
            'status' => 'ativa',
            'observacoes' => 'Matrícula seed — Ballet Iniciante',
            'contrato_id' => $contrato->id,
        ]));

        $matriculaCompletada = Matricula::query()->create([
            'id_aluno' => $alunosViolao->first()->id,
            'id_curso' => $violao->id,
            'id_nivel' => $nivelAvancadoViolao->id,
            'id_professor' => $professor1->id,
            'tipo' => 'curso',
            'data' => now()->subYear()->toDateString(),
            'status' => 'completada',
            'observacoes' => 'Matrícula concluída — seed certificado',
            'contrato_id' => $contrato->id,
        ]);
        $matriculas->push($matriculaCompletada);

        $aulasViolao = $this->seedAulas($turmaViolao, $professor1, $alunosViolao);
        $this->seedAulas($turmaBallet, $professor2, collect([$alunoBallet]));

        $this->seedMensalidades($matriculas->where('status', 'ativa')->values());
        $this->seedInstrumentos($todosAlunos, $contrato);
        $this->seedCertificados($templateCertificado, $matriculaCompletada);
        $this->seedJustificativasEReposicoes(
            aluno: $alunosViolao->first(),
            aulas: $aulasViolao,
            admin: $admin,
        );
        $this->seedSolicitacoesAula(
            professor1: $professor1,
            professor2: $professor2,
            turma: $turmaViolao,
            curso: $violao,
            aulas: $aulasViolao,
        );
        $this->seedAvisos(
            admin: $admin,
            professor: $professor1,
            turma: $turmaViolao,
            alunos: $alunosViolao,
        );
        $this->seedEspetaculosEBilheteria(
            turmas: collect([$turmaViolao, $turmaBallet]),
            alunos: $todosAlunos,
            contrato: $contrato,
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Nivel>
     */
    private function seedNiveis(Curso $curso)
    {
        $niveis = collect();

        foreach (self::NIVEIS as $cfg) {
            $niveis->push(Nivel::query()->create([
                'nome' => $cfg['nome'],
                'curso_id' => $curso->id,
                'ordem' => $cfg['ordem'],
                'idade_minima' => $cfg['idade_minima'],
                'idade_maxima' => $cfg['idade_maxima'],
            ]));
        }

        return $niveis;
    }

    private function criarAluno(
        Instituicao $instituicao,
        string $email,
        string $nome,
        string $cpf,
        string $dataAniversario,
    ): Aluno {
        $user = $this->upsertUser(
            email: $email,
            nome: $nome,
            role: 'aluno',
            cpf: $cpf,
            dataAniversario: $dataAniversario,
        );

        $this->vincular($instituicao, $user, 'aluno');

        return Aluno::query()->firstOrCreate(
            ['id_usuario' => $user->id],
            [
                'observacoes' => 'Aluno seed — Escola Michel',
                'criado_em' => now(),
            ],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     * @return \Illuminate\Support\Collection<int, AulaTurma>
     */
    private function seedAulas(Turma $turma, Professor $professor, $alunos)
    {
        $aulas = collect();

        for ($i = 1; $i <= 3; $i++) {
            $aula = AulaTurma::query()->create([
                'id_turma' => $turma->id,
                'id_curso' => $turma->id_curso,
                'id_nivel' => $turma->id_nivel,
                'id_professor' => $professor->id,
                'data' => now()->subDays($i * 7)->toDateString(),
                'hora_inicio' => '18:00:00',
                'hora_termino' => '19:00:00',
                'status' => 'concluida',
                'tipo' => 'regular',
                'conteudo_dado' => "Aula seed {$i}",
            ]);
            $aulas->push($aula);

            foreach ($alunos as $idx => $aluno) {
                // Primeira aula: 1º aluno ausente (para justificativa/reposição)
                $status = ($i === 1 && $idx === 0) ? 'ausente' : 'presente';

                AulaPresenca::query()->create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $aluno->id,
                    'status' => $status,
                ]);
            }
        }

        return $aulas;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedLeads($alunos): void
    {
        $statuses = ['novo', 'contatado', 'matriculado', 'perdido'];

        for ($i = 1; $i <= 8; $i++) {
            $status = $statuses[($i - 1) % count($statuses)];
            Lead::query()->create([
                'nome' => "Lead Michel {$i}",
                'email' => "lead{$i}@escolamichel.local",
                'telefone' => '119'.str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT),
                'status' => $status,
                'observacoes' => 'Lead seed — Escola Michel',
            ]);
        }

        $leadsMatriculados = Lead::query()->where('status', 'matriculado')->take(2)->get();
        foreach ($leadsMatriculados as $idx => $lead) {
            $aluno = $alunos->get($idx);
            if ($aluno) {
                $aluno->update(['id_lead' => $lead->id]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Matricula>  $matriculas
     */
    private function seedMensalidades($matriculas): void
    {
        $catMensalidade = CategoriaConta::query()->create([
            'nome' => 'Mensalidades',
            'tipo' => 'receita',
            'descricao' => 'Mensalidades de alunos',
        ]);
        CategoriaConta::query()->create([
            'nome' => 'Materiais',
            'tipo' => 'receita',
            'descricao' => 'Apostilas e materiais',
        ]);
        CategoriaConta::query()->create([
            'nome' => 'Despesas Gerais',
            'tipo' => 'despesa',
            'descricao' => 'Custos operacionais',
        ]);

        $hoje = now()->startOfDay();

        foreach ($matriculas as $idx => $matricula) {
            $status = match ($idx % 4) {
                0 => 'pago',
                1 => 'pendente',
                2 => 'vencido',
                default => 'pago_parcialmente',
            };

            Conta::query()->create([
                'id_categoria' => $catMensalidade->id,
                'descricao' => 'Mensalidade '.$hoje->format('m/Y').' — matrícula #'.$matricula->id,
                'valor' => 280,
                'data_vencimento' => $hoje->copy()->subDays($status === 'vencido' ? 15 : -10)->toDateString(),
                'data_pagamento' => $status === 'pago' ? $hoje->copy()->subDays(2)->toDateString() : null,
                'status' => $status,
                'tipo' => 'receita',
                'id_aluno' => $matricula->id_aluno,
                'id_matricula' => $matricula->id,
                'mes_referencia' => (int) $hoje->format('n'),
                'ano_referencia' => (int) $hoje->format('Y'),
                'notificar' => true,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedInstrumentos($alunos, Contrato $contrato): void
    {
        $tipos = ['violao', 'teclado', 'flauta', 'ukulele'];

        foreach ($tipos as $i => $tipo) {
            $emprestado = $i < 2;
            $aluno = $emprestado ? $alunos->get($i) : null;

            $instrumento = Instrumento::query()->create([
                'nome' => ucfirst($tipo).' Michel '.($i + 1),
                'tipo' => $tipo,
                'numero_serie' => 'SN-MICH-'.strtoupper($tipo).'-'.(1000 + $i),
                'status' => $emprestado ? 'emprestado' : 'disponivel',
                'id_aluno' => $aluno?->id,
                'data_emprestimo' => $emprestado ? now()->subDays(10)->toDateString() : null,
                'observacoes' => 'Instrumento seed — Escola Michel',
            ]);

            if ($emprestado && $aluno) {
                InstrumentoHistorico::query()->create([
                    'id_instrumento' => $instrumento->id,
                    'id_aluno' => $aluno->id,
                    'acao' => 'emprestimo',
                    'data' => now()->subDays(10)->toDateString(),
                    'observacoes' => 'Empréstimo seed — Escola Michel',
                    'contrato_id' => $contrato->id,
                    'contrato_gerado' => '<p>Termo de responsabilidade — {{nome_aluno}}</p>',
                ]);
            }
        }
    }

    private function seedCertificados(TemplateCertificado $template, Matricula $matriculaCompletada): void
    {
        Certificado::query()->create([
            'id_matricula' => $matriculaCompletada->id,
            'codigo_verificacao' => Certificado::gerarCodigo(),
            'emitido_em' => now()->subDays(5),
            'carga_horaria' => 40,
            'conteudo_gerado' => $template->conteudo,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AulaTurma>  $aulas
     */
    private function seedJustificativasEReposicoes(Aluno $aluno, $aulas, User $admin): void
    {
        $aulaPerdida = $aulas->first();
        $aulaDestino = $aulas->get(1);

        if (! $aulaPerdida) {
            return;
        }

        JustificativaFalta::query()->create([
            'id_aluno' => $aluno->id,
            'id_aula_turma' => $aulaPerdida->id,
            'tipo' => JustificativaFalta::TIPO_JUSTIFICATIVA,
            'motivo' => 'Atestado médico — seed Escola Michel',
            'status' => JustificativaFalta::STATUS_ACEITA,
            'id_usuario_decisor' => $admin->id,
            'decidido_em' => now()->subDays(5),
        ]);

        JustificativaFalta::query()->create([
            'id_aluno' => $aluno->id,
            'id_aula_turma' => $aulaDestino?->id ?? $aulaPerdida->id,
            'tipo' => JustificativaFalta::TIPO_AVISO,
            'motivo' => 'Viagem familiar — aviso prévio seed',
            'status' => JustificativaFalta::STATUS_PENDENTE,
        ]);

        SolicitacaoReposicao::query()->create([
            'id_aluno' => $aluno->id,
            'id_aula_turma_perdida' => $aulaPerdida->id,
            'id_aula_turma_destino' => $aulaDestino?->id,
            'motivo' => 'Reposição da aula com falta justificada',
            'status' => SolicitacaoReposicao::STATUS_PENDENTE,
        ]);

        SolicitacaoReposicao::query()->create([
            'id_aluno' => $aluno->id,
            'id_aula_turma_perdida' => $aulaPerdida->id,
            'motivo' => 'Reposição aprovada — seed',
            'status' => SolicitacaoReposicao::STATUS_APROVADA,
            'id_usuario_decisor' => $admin->id,
            'decidido_em' => now()->subDays(3),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AulaTurma>  $aulas
     */
    private function seedSolicitacoesAula(
        Professor $professor1,
        Professor $professor2,
        Turma $turma,
        Curso $curso,
        $aulas,
    ): void {
        $aula = $aulas->first();

        SolicitacaoAula::query()->create([
            'id_professor' => $professor1->id,
            'tipo' => SolicitacaoAula::TIPO_CRIACAO,
            'data_sugerida' => now()->addDays(7)->toDateString(),
            'hora_inicio_sugerida' => '18:00:00',
            'hora_termino_sugerida' => '19:00:00',
            'id_turma' => $turma->id,
            'id_curso' => $curso->id,
            'tipo_aula' => 'extra',
            'motivo' => 'Aula extra de revisão — seed',
            'status' => SolicitacaoAula::STATUS_PENDENTE,
            'avisar_alunos' => true,
        ]);

        if ($aula) {
            SolicitacaoAula::query()->create([
                'id_aula_turma' => $aula->id,
                'id_professor' => $professor1->id,
                'tipo' => SolicitacaoAula::TIPO_CANCELAMENTO,
                'motivo' => 'Imprevisto pessoal — seed',
                'destino_cobranca' => SolicitacaoAula::COBRANCA_PROXIMA_AULA,
                'avisar_alunos' => true,
                'status' => SolicitacaoAula::STATUS_PENDENTE,
            ]);

            SolicitacaoAula::query()->create([
                'id_aula_turma' => $aula->id,
                'id_professor' => $professor1->id,
                'tipo' => SolicitacaoAula::TIPO_SUBSTITUICAO,
                'id_professor_substituto' => $professor2->id,
                'motivo' => 'Substituição pontual — seed',
                'status' => SolicitacaoAula::STATUS_PENDENTE,
                'avisar_alunos' => true,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedAvisos(User $admin, Professor $professor, Turma $turma, $alunos): void
    {
        $aviso = AvisoTurma::query()->create([
            'id_turma' => $turma->id,
            'id_professor' => $professor->id,
            'id_usuario_autor' => $admin->id,
            'origem' => AvisoTurma::ORIGEM_MANUAL,
            'titulo' => 'Ensaio geral na próxima semana',
            'mensagem' => 'Lembrete: ensaio geral na terça-feira. Trazer material completo.',
            'publico' => AvisoTurma::PUBLICO_ALUNOS,
            'canais' => ['email'],
            'enviado_em' => now()->subDay(),
        ]);

        foreach ($alunos as $aluno) {
            $usuario = User::query()->find($aluno->id_usuario);
            AvisoTurmaDestinatario::query()->create([
                'id_aviso_turma' => $aviso->id,
                'id_aluno' => $aluno->id,
                'id_usuario' => $aluno->id_usuario,
                'tipo' => AvisoTurmaDestinatario::TIPO_ALUNO,
                'nome' => $usuario?->nome ?? 'Aluno',
                'canal' => 'email',
                'destino' => $usuario?->email,
                'status' => AvisoTurmaDestinatario::STATUS_ENVIADO,
                'enviado_em' => now()->subDay(),
            ]);
        }

        AvisoTurma::query()->create([
            'id_turma' => $turma->id,
            'id_professor' => $professor->id,
            'id_usuario_autor' => $admin->id,
            'origem' => AvisoTurma::ORIGEM_MANUAL,
            'titulo' => 'Recesso de carnaval',
            'mensagem' => 'Não haverá aulas na semana de carnaval.',
            'publico' => AvisoTurma::PUBLICO_AMBOS,
            'canais' => ['email', 'whatsapp'],
            'enviado_em' => null,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Turma>  $turmas
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedEspetaculosEBilheteria($turmas, $alunos, Contrato $contrato): void
    {
        $templateIngresso = TemplateIngresso::query()->create([
            'nome' => 'Ingresso padrão Escola Michel',
            'largura' => 15.0,
            'altura' => 8.0,
            'unidade' => 'cm',
            'conteudo' => json_encode([
                'elementos' => [
                    ['tipo' => 'texto', 'conteudo' => '{{titulo_espetaculo}}'],
                    ['tipo' => 'texto', 'conteudo' => '{{numero}}'],
                    ['tipo' => 'qrcode', 'campo' => 'codigo_qr'],
                ],
            ]),
        ]);

        $espetaculo = Espetaculo::query()->create([
            'titulo' => 'Recital de Fim de Ano — Escola Michel',
            'data_evento' => now()->addMonths(2)->toDateString(),
            'hora_evento' => '19:30:00',
            'local' => 'Teatro Municipal',
            'status' => 'ensaios',
            'contrato_id' => $contrato->id,
            'id_template_ingresso' => $templateIngresso->id,
            'valor_ingresso_padrao' => 50.00,
            'observacoes' => 'Espetáculo seed — Escola Michel',
        ]);

        $apresentacaoViolao = Apresentacao::query()->create([
            'id_espetaculo' => $espetaculo->id,
            'id_turma' => $turmas->first()?->id,
            'titulo_musica' => 'Asturias (Leyenda)',
            'ordem_entrada' => 1,
            'duracao_estimada' => '00:05:00',
        ]);

        $apresentacaoBallet = Apresentacao::query()->create([
            'id_espetaculo' => $espetaculo->id,
            'id_turma' => $turmas->get(1)?->id,
            'titulo_musica' => 'Vals das Flores',
            'ordem_entrada' => 2,
            'duracao_estimada' => '00:08:00',
        ]);

        foreach ($alunos->take(3) as $aluno) {
            ApresentacaoAluno::query()->create([
                'id_apresentacao' => $apresentacaoViolao->id,
                'id_aluno' => $aluno->id,
                'tamanho_figurino' => 'M',
                'pago_figurino' => false,
                'presenca_ensaio_geral' => false,
            ]);
        }

        $alunoBallet = $alunos->last();
        if ($alunoBallet) {
            ApresentacaoAluno::query()->create([
                'id_apresentacao' => $apresentacaoBallet->id,
                'id_aluno' => $alunoBallet->id,
                'tamanho_figurino' => 'P',
                'pago_figurino' => true,
                'presenca_ensaio_geral' => true,
            ]);
        }

        $cupom = CupomIngresso::query()->create([
            'codigo' => 'MICHEL10',
            'tipo' => 'percentual',
            'valor' => 10,
            'ativo' => true,
            'valido_de' => now()->toDateString(),
            'valido_ate' => now()->addMonths(3)->toDateString(),
            'uso_maximo' => 50,
            'usos' => 1,
        ]);
        $cupom->espetaculos()->sync([$espetaculo->id]);

        CupomIngresso::query()->create([
            'codigo' => 'FAMILIA20',
            'tipo' => 'valor_fixo',
            'valor' => 20,
            'ativo' => true,
            'valido_de' => now()->toDateString(),
            'valido_ate' => now()->addMonths(2)->toDateString(),
            'uso_maximo' => 30,
            'usos' => 0,
        ])->espetaculos()->sync([$espetaculo->id]);

        $ingressos = [
            ['numero' => '1', 'status' => Ingresso::STATUS_DISPONIVEL, 'cortesia' => false, 'pagamento' => null, 'cupom' => null, 'comprador' => null],
            ['numero' => '2', 'status' => Ingresso::STATUS_DISPONIVEL, 'cortesia' => false, 'pagamento' => null, 'cupom' => null, 'comprador' => null],
            ['numero' => '3', 'status' => Ingresso::STATUS_VENDIDO, 'cortesia' => false, 'pagamento' => Ingresso::PAGAMENTO_PAGO, 'cupom' => $cupom->id, 'comprador' => ['nome' => 'Maria Silva', 'documento' => '12345678901']],
            ['numero' => '4', 'status' => Ingresso::STATUS_VENDIDO, 'cortesia' => true, 'pagamento' => Ingresso::PAGAMENTO_PAGO, 'cupom' => null, 'comprador' => ['nome' => 'Cortesia Escola', 'documento' => null]],
            ['numero' => '5', 'status' => Ingresso::STATUS_UTILIZADO, 'cortesia' => false, 'pagamento' => Ingresso::PAGAMENTO_PAGO, 'cupom' => null, 'comprador' => ['nome' => 'João Santos', 'documento' => '98765432100']],
        ];

        foreach ($ingressos as $cfg) {
            Ingresso::query()->create([
                'id_espetaculo' => $espetaculo->id,
                'id_template_ingresso' => $templateIngresso->id,
                'numero' => $cfg['numero'],
                'codigo_qr' => Str::lower(Str::ulid()->toString()),
                'valor' => $cfg['cupom'] ? $cupom->aplicarDesconto(50.00) : 50.00,
                'status' => $cfg['status'],
                'cortesia' => $cfg['cortesia'],
                'status_pagamento' => $cfg['pagamento'],
                'id_cupom' => $cfg['cupom'],
                'comprador_nome' => $cfg['comprador']['nome'] ?? null,
                'comprador_documento' => $cfg['comprador']['documento'] ?? null,
                'utilizado_em' => $cfg['status'] === Ingresso::STATUS_UTILIZADO ? now()->subDay() : null,
            ]);
        }
    }

    private function upsertUser(
        string $email,
        string $nome,
        string $role,
        string $cpf,
        ?string $dataAniversario = null,
    ): User {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'nome' => $nome,
                'senha' => self::SENHA,
                'role' => $role,
                'cpf' => $cpf,
                'data_aniversario' => $dataAniversario,
                'email_verified_at' => now(),
                'deve_trocar_senha' => false,
            ],
        );
    }

    private function vincular(Instituicao $instituicao, User $user, string $role): void
    {
        InstituicaoUsuario::query()->firstOrCreate(
            [
                'id_instituicao' => $instituicao->id,
                'id_usuario' => $user->id,
            ],
            [
                'role' => $role,
                'status' => InstituicaoUsuario::STATUS_ATIVO,
            ],
        );
    }

    private function wipeDomainData(): void
    {
        AvisoTurmaDestinatario::query()->forceDelete();
        AvisoTurma::query()->forceDelete();
        JustificativaFalta::query()->forceDelete();
        SolicitacaoReposicao::query()->forceDelete();
        SolicitacaoAula::query()->forceDelete();
        Certificado::query()->forceDelete();

        Ingresso::query()->forceDelete();
        DB::table('cupons_ingresso_espetaculos')
            ->whereIn('id_cupom_ingresso', CupomIngresso::query()->pluck('id'))
            ->delete();
        CupomIngresso::query()->delete();

        AulaPresenca::query()->forceDelete();
        AulaTurma::query()->forceDelete();
        ApresentacaoAluno::query()->forceDelete();
        Apresentacao::query()->forceDelete();

        Espetaculo::query()->update(['id_template_ingresso' => null]);
        Espetaculo::query()->forceDelete();
        TemplateIngresso::query()->forceDelete();

        InstrumentoHistorico::query()->forceDelete();
        Instrumento::query()->forceDelete();
        ContaPagamento::query()->forceDelete();
        Conta::query()->forceDelete();
        Matricula::query()->forceDelete();
        TurmaHorario::query()->forceDelete();
        Turma::query()->forceDelete();
        Lead::query()->forceDelete();

        Curso::query()->update(['id_template_certificado' => null, 'contrato_id' => null]);
        TemplateCertificado::query()->forceDelete();
        Contrato::query()->forceDelete();
        CategoriaConta::query()->forceDelete();
        Nivel::query()->forceDelete();
        Curso::query()->forceDelete();
        Aluno::query()->forceDelete();
        Professor::query()->forceDelete();
    }
}
