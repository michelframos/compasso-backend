<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorFichaAlunoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private Professor $professor;

    private Professor $outroProfessor;

    private Turma $turma;

    private Turma $turmaAlheia;

    /** Matriculado na turma do professor e também na turma alheia. */
    private Aluno $aluno;

    /** Matriculado somente na turma alheia. */
    private Aluno $alunoAlheio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Souza', 'data_aniversario' => '2012-04-10']);
        $usuarioAlunoAlheio = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor, $usuarioAluno, $usuarioAlunoAlheio): void {
            $curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $this->professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);
            $this->outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($curso, $nivel, $this->professor);
            $this->turmaAlheia = $this->criarTurma($curso, $nivel, $this->outroProfessor);

            $this->aluno = Aluno::create(['id_usuario' => $usuarioAluno->id]);
            $this->alunoAlheio = Aluno::create(['id_usuario' => $usuarioAlunoAlheio->id]);

            $this->matricular($this->aluno, $this->turma);
            $this->matricular($this->aluno, $this->turmaAlheia);
            $this->matricular($this->alunoAlheio, $this->turmaAlheia);
        });
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 300,
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma, string $status = 'ativa'): Matricula
    {
        return Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => $status,
        ]);
    }

    private function lancarPresenca(Turma $turma, string $data, string $status, string $statusAula = 'concluida'): void
    {
        $aula = AulaTurma::create([
            'id_turma' => $turma->id,
            'id_professor' => $turma->id_professor,
            'data' => $data,
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => $statusAula,
        ]);

        AulaPresenca::create(['id_aula_turma' => $aula->id, 'id_aluno' => $this->aluno->id, 'status' => $status]);
    }

    private function observacao(Professor $autor, Aluno $aluno, array $atributos = []): ObservacaoAluno
    {
        return $this->naInstituicao($this->instituicao, fn () => ObservacaoAluno::create([
            'id_aluno' => $aluno->id,
            'id_professor' => $autor->id,
            'tipo' => 'geral',
            'texto' => 'Observação de teste',
        ] + $atributos));
    }

    private function comoProfessor(): static
    {
        return $this->comoUsuario($this->usuarioProfessor, $this->instituicao);
    }

    public function test_ficha_traz_dados_matriculas_frequencia_e_observacoes_do_escopo_do_professor(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->lancarPresenca($this->turma, '2026-09-22', 'presente');
            $this->lancarPresenca($this->turma, '2026-09-29', 'ausente');
            $this->lancarPresenca($this->turma, '2026-09-15', 'justificado');
            $this->lancarPresenca($this->turma, '2026-09-08', 'presente', 'cancelada');
            $this->lancarPresenca($this->turmaAlheia, '2026-09-23', 'presente');
        });
        $propria = $this->observacao($this->professor, $this->aluno, ['tipo' => 'evolucao', 'id_turma' => $this->turma->id]);
        $this->travelTo('2026-09-30 16:00:00');
        $deColega = $this->observacao($this->outroProfessor, $this->aluno, ['tipo' => 'alerta']);
        $this->observacao($this->outroProfessor, $this->alunoAlheio);

        $this->comoProfessor()
            ->getJson("/api/professor/me/alunos/{$this->aluno->id}")
            ->assertOk()
            ->assertJsonPath('data.aluno.nome', 'Bruno Souza')
            ->assertJsonPath('data.aluno.data_nascimento', '2012-04-10')
            ->assertJsonPath('data.aluno.idade', 14)
            ->assertJsonCount(1, 'data.matriculas')
            ->assertJsonPath('data.matriculas.0.id_turma', $this->turma->id)
            ->assertJsonPath('data.matriculas.0.turma.curso.nome', 'Piano')
            ->assertJsonPath('data.frequencia.total', 3)
            ->assertJsonPath('data.frequencia.presentes', 1)
            ->assertJsonPath('data.frequencia.ausentes', 1)
            ->assertJsonPath('data.frequencia.justificados', 1)
            ->assertJsonPath('data.frequencia.percentual_presenca', 33.3)
            ->assertJsonPath('data.frequencia.por_turma.0.id_turma', $this->turma->id)
            ->assertJsonPath('data.frequencia.por_turma.0.total', 3)
            ->assertJsonPath('data.frequencia.ultimas.0.data', '2026-09-29')
            ->assertJsonPath('data.frequencia.ultimas.0.status', 'ausente')
            ->assertJsonCount(2, 'data.observacoes')
            ->assertJsonPath('data.observacoes.0.id', $deColega->id)
            ->assertJsonPath('data.observacoes.0.pode_editar', false)
            ->assertJsonPath('data.observacoes.1.id', $propria->id)
            ->assertJsonPath('data.observacoes.1.pode_editar', true)
            ->assertJsonPath('data.observacoes.1.turma.id', $this->turma->id);
    }

    public function test_professor_nao_ve_ficha_de_aluno_fora_de_suas_turmas(): void
    {
        $this->comoProfessor()
            ->getJson("/api/professor/me/alunos/{$this->alunoAlheio->id}")
            ->assertForbidden();

        $this->naInstituicao($this->instituicao, fn () => Matricula::where('id_aluno', $this->aluno->id)
            ->where('id_turma', $this->turma->id)
            ->update(['status' => 'transferida']));

        $this->getJson("/api/professor/me/alunos/{$this->aluno->id}")->assertForbidden();
    }

    public function test_aluno_matriculado_por_curso_com_o_professor_aparece_na_ficha_e_no_painel(): void
    {
        $usuarioIndividual = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Clara Dias']);
        $usuarioDeColega = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        [$alunoIndividual, $alunoDoColega] = $this->naInstituicao($this->instituicao, function () use ($usuarioIndividual, $usuarioDeColega): array {
            $curso = Curso::create(['nome' => 'Canto', 'descricao' => 'Voz']);
            $nivel = Nivel::create(['nome' => 'Intermediário']);
            $individual = Aluno::create(['id_usuario' => $usuarioIndividual->id]);
            $doColega = Aluno::create(['id_usuario' => $usuarioDeColega->id]);

            foreach ([[$individual, $this->professor], [$doColega, $this->outroProfessor]] as [$aluno, $professor]) {
                Matricula::create([
                    'id_aluno' => $aluno->id,
                    'tipo' => 'curso',
                    'id_curso' => $curso->id,
                    'id_nivel' => $nivel->id,
                    'id_professor' => $professor->id,
                    'data' => '2026-09-01',
                    'status' => 'ativa',
                ]);
            }

            $aula = AulaTurma::create([
                'id_professor' => $this->professor->id,
                'id_aluno_especifico' => $individual->id,
                'data' => '2026-09-29',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
                'status' => 'concluida',
            ]);
            AulaPresenca::create(['id_aula_turma' => $aula->id, 'id_aluno' => $individual->id, 'status' => 'presente']);

            return [$individual, $doColega];
        });

        $this->comoProfessor()
            ->getJson("/api/professor/me/alunos/{$alunoIndividual->id}")
            ->assertOk()
            ->assertJsonPath('data.aluno.nome', 'Clara Dias')
            ->assertJsonCount(1, 'data.matriculas')
            ->assertJsonPath('data.matriculas.0.tipo', 'curso')
            ->assertJsonPath('data.matriculas.0.id_turma', null)
            ->assertJsonPath('data.matriculas.0.curso.nome', 'Canto')
            ->assertJsonPath('data.matriculas.0.nivel.nome', 'Intermediário')
            ->assertJsonPath('data.frequencia.total', 1)
            ->assertJsonPath('data.frequencia.por_turma.0.id_turma', null)
            ->assertJsonPath('data.frequencia.por_turma.0.presentes', 1);

        $this->postJson('/api/professor/me/observacoes', ['id_aluno' => $alunoIndividual->id, 'tipo' => 'evolucao', 'texto' => 'Afinação melhorou.'])
            ->assertCreated();
        $this->postJson('/api/professor/me/observacoes', [
            'id_aluno' => $alunoIndividual->id,
            'id_turma' => $this->turma->id,
            'tipo' => 'geral',
            'texto' => 'x',
        ])->assertForbidden();

        $this->getJson("/api/professor/me/alunos/{$alunoDoColega->id}")->assertForbidden();

        $this->getJson('/api/professor/me/painel')
            ->assertOk()
            ->assertJsonPath('data.contadores.alunos_ativos', 2);
    }

    public function test_lista_alunos_do_professor_em_turmas_e_por_curso(): void
    {
        $usuarioIndividual = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioDeColega = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $usuarioCancelado = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        $alunoIndividual = $this->naInstituicao($this->instituicao, function () use ($usuarioIndividual, $usuarioDeColega, $usuarioCancelado): Aluno {
            $curso = Curso::create(['nome' => 'Canto', 'descricao' => 'Voz']);
            $nivel = Nivel::create(['nome' => 'Intermediário']);
            $individual = Aluno::create(['id_usuario' => $usuarioIndividual->id]);
            $doColega = Aluno::create(['id_usuario' => $usuarioDeColega->id]);

            foreach ([[$individual, $this->professor], [$doColega, $this->outroProfessor]] as [$aluno, $professor]) {
                Matricula::create([
                    'id_aluno' => $aluno->id,
                    'tipo' => 'curso',
                    'id_curso' => $curso->id,
                    'id_nivel' => $nivel->id,
                    'id_professor' => $professor->id,
                    'data' => '2026-09-01',
                    'status' => 'ativa',
                ]);
            }

            $this->matricular(Aluno::create(['id_usuario' => $usuarioCancelado->id]), $this->turma, 'cancelada');

            return $individual;
        });

        $this->comoProfessor()
            ->getJson('/api/professor/me/alunos')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $alunoIndividual->id)
            ->assertJsonPath('data.0.nome', 'Ana Lima')
            ->assertJsonCount(1, 'data.0.matriculas')
            ->assertJsonPath('data.0.matriculas.0.tipo', 'curso')
            ->assertJsonPath('data.0.matriculas.0.turma', null)
            ->assertJsonPath('data.0.matriculas.0.curso.nome', 'Canto')
            ->assertJsonPath('data.0.matriculas.0.nivel.nome', 'Intermediário')
            ->assertJsonPath('data.1.id', $this->aluno->id)
            ->assertJsonCount(1, 'data.1.matriculas')
            ->assertJsonPath('data.1.matriculas.0.id_turma', $this->turma->id)
            ->assertJsonPath('data.1.matriculas.0.curso.nome', 'Piano');

        $this->getJson('/api/professor/me/alunos?tipo=curso')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alunoIndividual->id);

        $this->getJson('/api/professor/me/alunos?search=bruno')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->aluno->id);

        $this->getJson('/api/professor/me/alunos?tipo=outro')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    public function test_professor_registra_observacao_para_aluno_de_sua_turma(): void
    {
        $this->comoProfessor()
            ->postJson('/api/professor/me/observacoes', [
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'comportamento',
                'texto' => 'Muito participativo nas atividades em grupo.',
                'visivel_responsavel' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'comportamento')
            ->assertJsonPath('data.id_professor', $this->professor->id)
            ->assertJsonPath('data.visivel_responsavel', true)
            ->assertJsonPath('data.pode_editar', true);

        $this->postJson('/api/professor/me/observacoes', [
            'id_aluno' => $this->aluno->id,
            'tipo' => 'geral',
            'texto' => 'Sem turma vinculada.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id_turma', null)
            ->assertJsonPath('data.visivel_responsavel', false);

        $this->assertSame(2, ObservacaoAluno::withoutGlobalScopes()->where('id_professor', $this->professor->id)->count());
    }

    public function test_regras_ao_registrar_observacao(): void
    {
        $this->comoProfessor();

        $this->postJson('/api/professor/me/observacoes', ['id_aluno' => $this->alunoAlheio->id, 'tipo' => 'geral', 'texto' => 'x'])
            ->assertForbidden();
        $this->postJson('/api/professor/me/observacoes', [
            'id_aluno' => $this->aluno->id,
            'id_turma' => $this->turmaAlheia->id,
            'tipo' => 'geral',
            'texto' => 'x',
        ])->assertForbidden();

        $this->postJson('/api/professor/me/observacoes', ['id_aluno' => $this->aluno->id, 'tipo' => 'elogio', 'texto' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo', 'texto']);
        $this->postJson('/api/professor/me/observacoes', ['id_aluno' => 999999, 'tipo' => 'geral', 'texto' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_aluno');

        $this->assertSame(0, ObservacaoAluno::withoutGlobalScopes()->count());
    }

    public function test_professor_edita_e_exclui_somente_as_proprias_observacoes(): void
    {
        $propria = $this->observacao($this->professor, $this->aluno);
        $deColega = $this->observacao($this->outroProfessor, $this->aluno);

        $this->comoProfessor()
            ->putJson("/api/professor/me/observacoes/{$propria->id}", ['tipo' => 'alerta', 'texto' => 'Texto revisado'])
            ->assertOk()
            ->assertJsonPath('data.tipo', 'alerta')
            ->assertJsonPath('data.texto', 'Texto revisado');

        $this->putJson("/api/professor/me/observacoes/{$deColega->id}", ['texto' => 'Tentativa'])->assertForbidden();
        $this->deleteJson("/api/professor/me/observacoes/{$deColega->id}")->assertForbidden();
        $this->putJson("/api/professor/me/observacoes/{$propria->id}", ['tipo' => 'elogio'])->assertUnprocessable();

        $this->deleteJson("/api/professor/me/observacoes/{$propria->id}")->assertNoContent();

        $this->assertSoftDeleted('observacoes_alunos', ['id' => $propria->id]);
        $this->assertSame('Observação de teste', ObservacaoAluno::withoutGlobalScopes()->find($deColega->id)->texto);
    }

    public function test_area_exige_credenciais_do_professor(): void
    {
        $observacao = $this->observacao($this->professor, $this->aluno);
        $rotaFicha = "/api/professor/me/alunos/{$this->aluno->id}";
        $payload = ['id_aluno' => $this->aluno->id, 'tipo' => 'geral', 'texto' => 'x'];

        $this->getJson($rotaFicha)->assertUnauthorized();
        $this->getJson('/api/professor/me/alunos')->assertUnauthorized();
        $this->postJson('/api/professor/me/observacoes', $payload)->assertUnauthorized();

        foreach (['secretaria', 'aluno', 'responsavel', 'admin'] as $papel) {
            $this->comoUsuario($this->criarUsuarioNaInstituicao($this->instituicao, $papel), $this->instituicao);

            $this->getJson($rotaFicha)->assertForbidden();
            $this->getJson('/api/professor/me/alunos')->assertForbidden();
            $this->postJson('/api/professor/me/observacoes', $payload)->assertForbidden();
            $this->deleteJson("/api/professor/me/observacoes/{$observacao->id}")->assertForbidden();
        }

        $professorSemTroca = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['deve_trocar_senha' => true]);
        $this->naInstituicao($this->instituicao, fn () => $this->turma->update([
            'id_professor' => Professor::create(['id_usuario' => $professorSemTroca->id])->id,
        ]));

        $this->comoUsuario($professorSemTroca, $this->instituicao)
            ->getJson($rotaFicha)
            ->assertForbidden()
            ->assertJsonPath('code', 'troca_senha_obrigatoria');
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'professor');
        $usuarioAlunoFora = $this->criarUsuarioNaInstituicao($outra, 'aluno');

        [$alunoDeFora, $observacaoDeFora] = $this->naInstituicao($outra, function () use ($usuarioOutro, $usuarioAlunoFora): array {
            $professor = Professor::create(['id_usuario' => $usuarioOutro->id]);
            $turma = $this->criarTurma(
                Curso::create(['nome' => 'Bateria', 'descricao' => 'Percussão']),
                Nivel::create(['nome' => 'Avançado']),
                $professor
            );
            $aluno = Aluno::create(['id_usuario' => $usuarioAlunoFora->id]);
            $this->matricular($aluno, $turma);

            return [$aluno, ObservacaoAluno::create([
                'id_aluno' => $aluno->id,
                'id_professor' => $professor->id,
                'tipo' => 'geral',
                'texto' => 'Outra escola',
            ])];
        });

        $this->comoProfessor();
        $this->getJson("/api/professor/me/alunos/{$alunoDeFora->id}")->assertNotFound();
        $this->getJson('/api/professor/me/alunos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->aluno->id);
        $this->putJson("/api/professor/me/observacoes/{$observacaoDeFora->id}", ['texto' => 'x'])->assertNotFound();
        $this->deleteJson("/api/professor/me/observacoes/{$observacaoDeFora->id}")->assertNotFound();
        $this->postJson('/api/professor/me/observacoes', ['id_aluno' => $alunoDeFora->id, 'tipo' => 'geral', 'texto' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_aluno');

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson("/api/professor/me/alunos/{$this->aluno->id}")
            ->assertForbidden();
    }
}
