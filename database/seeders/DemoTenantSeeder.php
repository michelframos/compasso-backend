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
use App\Models\Curso;
use App\Models\Espetaculo;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Professor;
use App\Models\Responsavel;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Modules\Comercial\Models\Lead;
use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Modules\Financeiro\Models\Contrato;
use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Instrumentos\Models\InstrumentoHistorico;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Escolas demo + dados ricos para testar entitlements e o app.
 *
 * Credenciais (senha de todos: password):
 * - super@compasso.local          → painel platform
 * - admin@trial.demo              → escola-trial (trial, todos os módulos)
 * - admin@basico.demo             → escola-basico (active, só leads)
 * - admin@pro.demo                → escola-pro (active, leads+financeiro+instrumentos)
 * - admin@enterprise.demo         → escola-enterprise (active, todos)
 */
class DemoTenantSeeder extends Seeder
{
    private const SENHA = 'password';

    public function run(): void
    {
        $planos = PlanoAssinatura::query()->get()->keyBy('slug');

        if ($planos->isEmpty()) {
            $this->command?->error('Rode PlanoAssinaturaSeeder antes do DemoTenantSeeder.');

            return;
        }

        $this->seedEscola([
            'slug' => 'escola-trial',
            'nome_fantasia' => 'Escola Trial Compasso',
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'trial_ends_at' => now()->addDays(14),
            'id_plano_assinatura' => $planos['enterprise']->id,
            'admin_email' => 'admin@trial.demo',
            'admin_nome' => 'Admin Trial',
            'admin_cpf' => '11111111111',
            'perfil' => 'completo',
        ]);

        $this->seedEscola([
            'slug' => 'escola-basico',
            'nome_fantasia' => 'Escola Básico (só Leads)',
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'trial_ends_at' => null,
            'assinatura_inicia_em' => now()->subMonth()->toDateString(),
            'id_plano_assinatura' => $planos['basico']->id,
            'admin_email' => 'admin@basico.demo',
            'admin_nome' => 'Admin Básico',
            'admin_cpf' => '22222222222',
            'perfil' => 'basico',
        ]);

        $this->seedEscola([
            'slug' => 'escola-pro',
            'nome_fantasia' => 'Escola Profissional',
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'trial_ends_at' => null,
            'assinatura_inicia_em' => now()->subMonths(2)->toDateString(),
            'id_plano_assinatura' => $planos['profissional']->id,
            'admin_email' => 'admin@pro.demo',
            'admin_nome' => 'Admin Pro',
            'admin_cpf' => '33333333333',
            'perfil' => 'profissional',
        ]);

        $this->seedEscola([
            'slug' => 'escola-enterprise',
            'nome_fantasia' => 'Escola Enterprise',
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'trial_ends_at' => null,
            'assinatura_inicia_em' => now()->subMonths(6)->toDateString(),
            'id_plano_assinatura' => $planos['enterprise']->id,
            'admin_email' => 'admin@enterprise.demo',
            'admin_nome' => 'Admin Enterprise',
            'admin_cpf' => '44444444444',
            'perfil' => 'completo',
        ]);

        $this->command?->newLine();
        $this->command?->info('=== Credenciais demo (senha: password) ===');
        $this->command?->table(
            ['Email', 'Tenant slug', 'CNPJ (login)', 'Plano / status'],
            [
                ['super@compasso.local', '— (platform)', '— (/platform/login)', 'super admin'],
                ['admin@trial.demo', 'escola-trial', $this->fakeCnpj('escola-trial'), 'Enterprise / trial'],
                ['admin@basico.demo', 'escola-basico', $this->fakeCnpj('escola-basico'), 'Básico / active (só leads)'],
                ['admin@pro.demo', 'escola-pro', $this->fakeCnpj('escola-pro'), 'Profissional / active'],
                ['admin@enterprise.demo', 'escola-enterprise', $this->fakeCnpj('escola-enterprise'), 'Enterprise / active'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function seedEscola(array $cfg): void
    {
        $instituicao = Instituicao::query()->updateOrCreate(
            ['slug' => $cfg['slug']],
            [
                'nome_fantasia' => $cfg['nome_fantasia'],
                'razao_social' => $cfg['nome_fantasia'].' LTDA',
                'cnpj' => $this->fakeCnpj($cfg['slug']),
                'rua' => 'Rua das Melodias',
                'numero' => '100',
                'bairro' => 'Centro',
                'cep' => '01310100',
                'status' => Instituicao::STATUS_ATIVO,
                'id_plano_assinatura' => $cfg['id_plano_assinatura'],
                'trial_ends_at' => $cfg['trial_ends_at'] ?? null,
                'trial_usa_padrao' => false,
                'assinatura_inicia_em' => $cfg['assinatura_inicia_em'] ?? now()->toDateString(),
                'assinatura_status' => $cfg['assinatura_status'],
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => $cfg['admin_email']],
            [
                'nome' => $cfg['admin_nome'],
                'senha' => self::SENHA,
                'role' => 'admin',
                'cpf' => $cfg['admin_cpf'],
                'email_verified_at' => now(),
                'is_super_admin' => false,
            ],
        );

        InstituicaoUsuario::query()->firstOrCreate(
            [
                'id_instituicao' => $instituicao->id,
                'id_usuario' => $admin->id,
            ],
            [
                'role' => 'admin',
                'status' => InstituicaoUsuario::STATUS_ATIVO,
            ],
        );

        $secretaria = User::query()->updateOrCreate(
            ['email' => str_replace('admin@', 'secretaria@', $cfg['admin_email'])],
            [
                'nome' => 'Secretaria '.$cfg['nome_fantasia'],
                'senha' => self::SENHA,
                'role' => 'secretaria',
                'cpf' => substr($cfg['admin_cpf'], 0, 10).'9',
                'email_verified_at' => now(),
            ],
        );

        InstituicaoUsuario::query()->firstOrCreate(
            [
                'id_instituicao' => $instituicao->id,
                'id_usuario' => $secretaria->id,
            ],
            [
                'role' => 'secretaria',
                'status' => InstituicaoUsuario::STATUS_ATIVO,
            ],
        );

        InstituicaoContext::runWith($instituicao->id, $instituicao->slug, function () use ($cfg, $instituicao): void {
            $force = filter_var(env('DEMO_SEED_FORCE', false), FILTER_VALIDATE_BOOL);

            if (Curso::query()->exists() && ! $force) {
                $this->command?->warn("[{$instituicao->slug}] já possui dados — pulando domínio. Use DEMO_SEED_FORCE=1 para recriar.");

                return;
            }

            if ($force && Curso::query()->exists()) {
                $this->wipeDomainData();
                $this->command?->warn("[{$instituicao->slug}] domínio limpo (FORCE).");
            }

            match ($cfg['perfil']) {
                'basico' => $this->seedPerfilBasico(),
                'profissional' => $this->seedPerfilProfissional(),
                default => $this->seedPerfilCompleto($instituicao->slug),
            };
        });

        $this->command?->info("Escola pronta: {$instituicao->slug} ({$cfg['admin_email']})");
    }

    private function wipeDomainData(): void
    {
        // Ordem respeitando FKs mais comuns
        AulaPresenca::query()->forceDelete();
        AulaTurma::query()->forceDelete();
        ApresentacaoAluno::query()->forceDelete();
        Apresentacao::query()->forceDelete();
        Espetaculo::query()->forceDelete();
        InstrumentoHistorico::query()->forceDelete();
        Instrumento::query()->forceDelete();
        Conta::query()->forceDelete();
        Matricula::query()->forceDelete();
        TurmaHorario::query()->forceDelete();
        Turma::query()->forceDelete();
        Lead::query()->forceDelete();
        Contrato::query()->forceDelete();
        CategoriaConta::query()->forceDelete();
        ConfiguracaoPix::query()->delete();
        ConfiguracaoNotificacao::query()->delete();
        ConfiguracaoWhatsapp::query()->delete();
        Nivel::query()->forceDelete();
        Curso::query()->forceDelete();

        // Pessoas: remove vínculos e registros da instituição
        $alunoIds = Aluno::query()->pluck('id');
        if ($alunoIds->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::table('responsaveis_alunos')
                ->whereIn('id_aluno', $alunoIds)
                ->delete();
        }

        Aluno::query()->forceDelete();
        Professor::query()->forceDelete();
        Responsavel::query()->forceDelete();
    }

    private function seedPerfilBasico(): void
    {
        [$cursos, $niveis, $professores, $alunos] = $this->seedCoreAcademico(
            cursos: 2,
            niveisPorCurso: 2,
            professores: 2,
            alunos: 8,
            turmas: 2,
            matriculasPorTurma: 3,
        );

        $this->seedLeads(12, $alunos);
        $this->seedConfigs(withFinanceiro: false, instanceSuffix: 'basico');
        unset($cursos, $niveis, $professores);
    }

    private function seedPerfilProfissional(): void
    {
        [$cursos, $niveis, $professores, $alunos, $turmas, $matriculas] = $this->seedCoreAcademico(
            cursos: 3,
            niveisPorCurso: 2,
            professores: 3,
            alunos: 15,
            turmas: 4,
            matriculasPorTurma: 3,
        );

        $this->seedLeads(20, $alunos);
        $contrato = $this->seedFinanceiro($alunos, $matriculas);
        $this->seedInstrumentos($alunos, $contrato);
        $this->seedConfigs(withFinanceiro: true, instanceSuffix: 'pro');
        unset($cursos, $niveis, $professores, $turmas);
    }

    private function seedPerfilCompleto(string $slug): void
    {
        [$cursos, $niveis, $professores, $alunos, $turmas, $matriculas] = $this->seedCoreAcademico(
            cursos: 4,
            niveisPorCurso: 3,
            professores: 5,
            alunos: 25,
            turmas: 6,
            matriculasPorTurma: 4,
        );

        $this->seedLeads(30, $alunos);
        $contrato = $this->seedFinanceiro($alunos, $matriculas);
        $this->seedInstrumentos($alunos, $contrato);
        $this->seedEspetaculos($turmas, $alunos, $contrato);
        $this->seedConfigs(withFinanceiro: true, instanceSuffix: $slug);
        unset($cursos, $niveis, $professores);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection, 4: \Illuminate\Support\Collection, 5: \Illuminate\Support\Collection}
     */
    private function seedCoreAcademico(
        int $cursos,
        int $niveisPorCurso,
        int $professores,
        int $alunos,
        int $turmas,
        int $matriculasPorTurma,
    ): array {
        $cursosCol = collect();
        $niveisCol = collect();

        for ($i = 1; $i <= $cursos; $i++) {
            $curso = Curso::factory()->create([
                'nome' => match ($i) {
                    1 => 'Violão',
                    2 => 'Teclado',
                    3 => 'Canto',
                    default => 'Bateria',
                },
                'descricao' => 'Curso seed para testes',
            ]);
            $cursosCol->push($curso);

            for ($n = 1; $n <= $niveisPorCurso; $n++) {
                $niveisCol->push(Nivel::factory()->create([
                    'nome' => "Nível {$n}",
                    'curso_id' => $curso->id,
                ]));
            }
        }

        $professoresCol = collect();
        for ($i = 1; $i <= $professores; $i++) {
            $user = User::factory()->create([
                'role' => 'professor',
                'nome' => "Professor Demo {$i}",
            ]);
            $prof = Professor::factory()->create([
                'id_usuario' => $user->id,
                'comissao' => 10,
                'valor_hora_aula' => 80,
            ]);
            $professoresCol->push($prof);

            InstituicaoUsuario::query()->firstOrCreate(
                [
                    'id_instituicao' => InstituicaoContext::id(),
                    'id_usuario' => $user->id,
                ],
                [
                    'role' => 'professor',
                    'status' => InstituicaoUsuario::STATUS_ATIVO,
                ],
            );
        }

        $alunosCol = collect();
        for ($i = 1; $i <= $alunos; $i++) {
            $user = User::factory()->create([
                'role' => 'aluno',
                'nome' => "Aluno Demo {$i}",
            ]);
            $aluno = Aluno::factory()->create([
                'id_usuario' => $user->id,
                'observacoes' => 'Aluno gerado pelo DemoTenantSeeder',
            ]);
            $alunosCol->push($aluno);

            InstituicaoUsuario::query()->firstOrCreate(
                [
                    'id_instituicao' => InstituicaoContext::id(),
                    'id_usuario' => $user->id,
                ],
                [
                    'role' => 'aluno',
                    'status' => InstituicaoUsuario::STATUS_ATIVO,
                ],
            );

            if ($i <= 5) {
                $respUser = User::factory()->create([
                    'role' => 'responsavel',
                    'nome' => "Responsável do Aluno {$i}",
                ]);
                $responsavel = Responsavel::factory()->create([
                    'id_usuario' => $respUser->id,
                ]);
                $aluno->responsaveis()->attach($responsavel->id, [
                    'id_instituicao' => InstituicaoContext::id(),
                    'parentesco' => 'mãe',
                    'observacoes' => null,
                ]);
            }
        }

        $turmasCol = collect();
        $matriculasCol = collect();

        for ($i = 0; $i < $turmas; $i++) {
            $curso = $cursosCol[$i % $cursosCol->count()];
            $nivel = $niveisCol->firstWhere('curso_id', $curso->id) ?? $niveisCol->first();
            $professor = $professoresCol[$i % $professoresCol->count()];

            $turma = Turma::factory()->create([
                'id_curso' => $curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $professor->id,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'maximo_alunos' => 15,
                'valor_mensalidade' => 250 + ($i * 20),
                'descricao' => "Turma {$curso->nome} ".($i + 1),
            ]);
            $turmasCol->push($turma);

            TurmaHorario::factory()->create([
                'id_turma' => $turma->id,
                'dia_semana' => ['segunda', 'terca', 'quarta', 'quinta', 'sexta'][$i % 5],
                'hora_inicio' => '18:00:00',
                'hora_termino' => '19:00:00',
            ]);

            $alunosDaTurma = $alunosCol->shuffle()->take(min($matriculasPorTurma, $alunosCol->count()));
            foreach ($alunosDaTurma as $aluno) {
                $exists = Matricula::query()
                    ->where('id_aluno', $aluno->id)
                    ->where('id_turma', $turma->id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $matriculasCol->push(Matricula::factory()->create([
                    'id_aluno' => $aluno->id,
                    'id_turma' => $turma->id,
                    'tipo' => 'turma',
                    'data' => now()->subDays(rand(5, 90))->toDateString(),
                    'status' => 'ativa',
                    'observacoes' => 'Matrícula seed',
                ]));
            }

            $aulas = AulaTurma::factory(4)->create([
                'id_turma' => $turma->id,
                'id_professor' => $professor->id,
                'status' => 'concluida',
                'data' => now()->subDays(rand(1, 30))->toDateString(),
            ]);

            foreach ($aulas as $aula) {
                foreach ($alunosDaTurma->take(3) as $aluno) {
                    AulaPresenca::factory()->create([
                        'id_aula_turma' => $aula->id,
                        'id_aluno' => $aluno->id,
                    ]);
                }
            }
        }

        return [$cursosCol, $niveisCol, $professoresCol, $alunosCol, $turmasCol, $matriculasCol];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedLeads(int $qtd, $alunos): void
    {
        $statuses = ['novo', 'contatado', 'matriculado', 'perdido'];

        for ($i = 1; $i <= $qtd; $i++) {
            $status = $statuses[$i % count($statuses)];
            Lead::query()->create([
                'nome' => "Lead Demo {$i}",
                'email' => "lead{$i}@demo.local",
                'telefone' => '119'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'status' => $status,
                'observacoes' => 'Lead gerado pelo DemoTenantSeeder',
            ]);
        }

        // Vincula alguns alunos a leads matriculados
        $leadsMatriculados = Lead::query()->where('status', 'matriculado')->take(3)->get();
        foreach ($leadsMatriculados as $idx => $lead) {
            $aluno = $alunos->get($idx);
            if ($aluno) {
                $aluno->update(['id_lead' => $lead->id]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     * @param  \Illuminate\Support\Collection<int, Matricula>  $matriculas
     */
    private function seedFinanceiro($alunos, $matriculas): Contrato
    {
        $contrato = Contrato::query()->create([
            'nome' => 'Contrato de Matrícula Padrão',
            'conteudo' => '<p>Eu <strong>{{nome_aluno}}</strong> declaro estar ciente das regras da escola, valores e horários da turma {{nome_turma}}.</p>',
        ]);

        $catMensalidade = CategoriaConta::factory()->create([
            'nome' => 'Mensalidades',
            'tipo' => 'receita',
            'descricao' => 'Mensalidades de alunos',
        ]);
        $catMaterial = CategoriaConta::factory()->create([
            'nome' => 'Materiais',
            'tipo' => 'receita',
            'descricao' => 'Apostilas e materiais',
        ]);
        CategoriaConta::factory()->create([
            'nome' => 'Despesas Gerais',
            'tipo' => 'despesa',
            'descricao' => 'Custos operacionais',
        ]);

        $hoje = Carbon::today();

        foreach ($matriculas->take(20) as $idx => $matricula) {
            $status = match ($idx % 4) {
                0 => 'pago',
                1 => 'pendente',
                2 => 'vencido',
                default => 'pago_parcialmente',
            };

            Conta::factory()->create([
                'id_categoria' => $catMensalidade->id,
                'descricao' => 'Mensalidade '.now()->format('m/Y').' — matrícula #'.$matricula->id,
                'valor' => 250,
                'data_vencimento' => $hoje->copy()->subDays($status === 'vencido' ? 15 : -10)->toDateString(),
                'data_pagamento' => $status === 'pago' ? $hoje->copy()->subDays(2)->toDateString() : null,
                'status' => $status,
                'tipo' => 'receita',
                'id_aluno' => $matricula->id_aluno,
                'id_matricula' => $matricula->id,
                'mes_referencia' => (int) now()->format('n'),
                'ano_referencia' => (int) now()->format('Y'),
                'notificar' => true,
            ]);
        }

        // Contas avulsas / materiais
        foreach ($alunos->take(5) as $aluno) {
            Conta::factory()->create([
                'id_categoria' => $catMaterial->id,
                'descricao' => 'Apostila — '.$aluno->id,
                'valor' => 45,
                'data_vencimento' => $hoje->copy()->addDays(7)->toDateString(),
                'status' => 'pendente',
                'tipo' => 'receita',
                'id_aluno' => $aluno->id,
            ]);
        }

        ConfiguracaoPix::query()->create([
            'chave_pix' => '11999999999',
            'tipo_chave' => 'telefone',
            'nome_beneficiario' => 'Escola Demo Compasso',
            'cidade' => 'SAO PAULO',
            'exibir_qrcode' => true,
        ]);

        return $contrato;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedInstrumentos($alunos, Contrato $contrato): void
    {
        $tipos = ['violao', 'teclado', 'flauta', 'ukulele', 'baixo'];

        foreach ($tipos as $i => $tipo) {
            $emprestado = $i < 2;
            $aluno = $emprestado ? $alunos->get($i) : null;

            $instrumento = Instrumento::query()->create([
                'nome' => ucfirst($tipo).' Demo '.($i + 1),
                'tipo' => $tipo,
                'numero_serie' => 'SN-'.strtoupper($tipo).'-'.(1000 + $i),
                'status' => $emprestado ? 'emprestado' : 'disponivel',
                'id_aluno' => $aluno?->id,
                'data_emprestimo' => $emprestado ? now()->subDays(10)->toDateString() : null,
                'observacoes' => 'Instrumento seed',
            ]);

            if ($emprestado && $aluno) {
                InstrumentoHistorico::query()->create([
                    'id_instrumento' => $instrumento->id,
                    'id_aluno' => $aluno->id,
                    'acao' => 'emprestimo',
                    'data' => now()->subDays(10)->toDateString(),
                    'observacoes' => 'Empréstimo seed',
                    'contrato_id' => $contrato->id,
                    'contrato_gerado' => '<p>Termo de responsabilidade — {{nome_aluno}}</p>',
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Turma>  $turmas
     * @param  \Illuminate\Support\Collection<int, Aluno>  $alunos
     */
    private function seedEspetaculos($turmas, $alunos, Contrato $contrato): void
    {
        $espetaculo = Espetaculo::factory()->create([
            'titulo' => 'Recital de Fim de Ano',
            'data_evento' => now()->addMonths(2)->toDateString(),
            'local' => 'Teatro Municipal',
            'status' => 'ensaios',
            'contrato_id' => $contrato->id,
            'observacoes' => 'Espetáculo seed',
        ]);

        $apresentacao = Apresentacao::factory()->create([
            'id_espetaculo' => $espetaculo->id,
            'id_turma' => $turmas->first()?->id,
        ]);

        foreach ($alunos->take(8) as $aluno) {
            ApresentacaoAluno::factory()->create([
                'id_apresentacao' => $apresentacao->id,
                'id_aluno' => $aluno->id,
            ]);
        }
    }

    private function seedConfigs(bool $withFinanceiro, ?string $instanceSuffix = null): void
    {
        $suffix = $instanceSuffix ?? (string) InstituicaoContext::id();

        ConfiguracaoWhatsapp::query()->updateOrCreate(
            ['id_instituicao' => InstituicaoContext::id()],
            [
                'instance_name' => 'demo-'.$suffix,
                'status' => 'disconnected',
            ],
        );

        ConfiguracaoNotificacao::query()->updateOrCreate(
            [
                'id_instituicao' => InstituicaoContext::id(),
                'modulo' => 'agendamentos',
                'tipo' => null,
            ],
            [
                'ativo' => true,
                'dias_antecedencia' => 1,
                'intervalo_repeticao' => 1,
                'max_repeticoes' => 2,
                'template_mensagem' => 'Olá {nome}! Lembrete da aula de {curso} em {data} às {horario}.',
                'horario_envio' => '08:00',
            ],
        );

        if ($withFinanceiro) {
            ConfiguracaoNotificacao::query()->updateOrCreate(
                [
                    'id_instituicao' => InstituicaoContext::id(),
                    'modulo' => 'contas_a_receber',
                    'tipo' => 'vencimento',
                ],
                [
                    'ativo' => true,
                    'dias_antecedencia' => 3,
                    'intervalo_repeticao' => 1,
                    'max_repeticoes' => null,
                    'template_mensagem' => 'Olá {nome}. Sua mensalidade de {valor} vence em {vencimento}.',
                    'horario_envio' => '09:00',
                ],
            );

            ConfiguracaoNotificacao::query()->updateOrCreate(
                [
                    'id_instituicao' => InstituicaoContext::id(),
                    'modulo' => 'contas_a_receber',
                    'tipo' => 'atraso',
                ],
                [
                    'ativo' => true,
                    'dias_antecedencia' => 1,
                    'intervalo_repeticao' => 3,
                    'max_repeticoes' => 5,
                    'template_mensagem' => 'Olá {nome}. Identificamos atraso na parcela de {valor}.',
                    'horario_envio' => '10:00',
                ],
            );
        }
    }

    private function fakeCnpj(string $slug): string
    {
        $base = str_pad((string) (abs(crc32($slug)) % 100000000), 8, '0', STR_PAD_LEFT).'0001';

        return $base.Cnpj::calcularDigitosVerificadores($base);
    }
}
