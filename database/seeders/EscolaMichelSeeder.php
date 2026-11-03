<?php

namespace Database\Seeders;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\AulaPresenca;
use App\Models\AulaTurma;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Database\Seeder;

/**
 * Escola de desenvolvimento com dados acadêmicos determinísticos.
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

        InstituicaoContext::runWith($instituicao->id, $instituicao->slug, function () use ($instituicao): void {
            $force = filter_var(env('DEMO_SEED_FORCE', false), FILTER_VALIDATE_BOOL);

            if (Curso::query()->exists() && ! $force) {
                $this->command?->warn("[{$instituicao->slug}] já possui dados — pulando domínio. Use DEMO_SEED_FORCE=1 para recriar.");

                return;
            }

            if ($force && Curso::query()->exists()) {
                $this->wipeDomainData();
                $this->command?->warn("[{$instituicao->slug}] domínio limpo (FORCE).");
            }

            $this->seedDominioAcademico($instituicao);
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

    private function seedDominioAcademico(Instituicao $instituicao): void
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

        $violao = Curso::query()->create([
            'nome' => 'Violão',
            'descricao' => 'Curso de Violão — seed Escola Michel',
        ]);
        $ballet = Curso::query()->create([
            'nome' => 'Ballet',
            'descricao' => 'Curso de Ballet — seed Escola Michel',
        ]);

        $niveisViolao = $this->seedNiveis($violao);
        $niveisBallet = $this->seedNiveis($ballet);

        $nivelIntermediario = $niveisViolao->firstWhere('nome', 'Intermediário');
        $nivelIniciante = $niveisBallet->firstWhere('nome', 'Iniciante');

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

        foreach ($alunosViolao as $aluno) {
            Matricula::query()->create([
                'id_aluno' => $aluno->id,
                'id_turma' => $turmaViolao->id,
                'tipo' => 'turma',
                'data' => now()->subDays(30)->toDateString(),
                'status' => 'ativa',
                'observacoes' => 'Matrícula seed — Violão Intermediário',
            ]);
        }

        Matricula::query()->create([
            'id_aluno' => $alunoBallet->id,
            'id_turma' => $turmaBallet->id,
            'tipo' => 'turma',
            'data' => now()->subDays(20)->toDateString(),
            'status' => 'ativa',
            'observacoes' => 'Matrícula seed — Ballet Iniciante',
        ]);

        $this->seedAulas($turmaViolao, $professor1, $alunosViolao);
        $this->seedAulas($turmaBallet, $professor2, collect([$alunoBallet]));
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
     */
    private function seedAulas(Turma $turma, Professor $professor, $alunos): void
    {
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

            foreach ($alunos as $aluno) {
                AulaPresenca::query()->create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $aluno->id,
                    'status' => 'presente',
                ]);
            }
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
        AulaPresenca::query()->forceDelete();
        AulaTurma::query()->forceDelete();
        Matricula::query()->forceDelete();
        TurmaHorario::query()->forceDelete();
        Turma::query()->forceDelete();
        Nivel::query()->forceDelete();
        Curso::query()->forceDelete();
        Aluno::query()->forceDelete();
        Professor::query()->forceDelete();
    }
}
