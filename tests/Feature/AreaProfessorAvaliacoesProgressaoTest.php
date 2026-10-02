<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\MatriculaHistorico;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorAvaliacoesProgressaoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private User $usuarioOutroProfessor;

    private User $usuarioSecretaria;

    private Professor $professor;

    private Professor $outroProfessor;

    private Curso $curso;

    private Nivel $basico;

    private Nivel $intermediario;

    private Nivel $nivelDeOutroCurso;

    private Turma $turma;

    private Turma $turmaAlheia;

    /** Turma do nível intermediário (destino da progressão). */
    private Turma $turmaDestino;

    /** Matriculado na turma do professor e também na turma alheia. */
    private Aluno $aluno;

    private Matricula $matricula;

    /** Matriculado somente na turma alheia. */
    private Aluno $alunoAlheio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->usuarioSecretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Souza']);
        $usuarioAlunoAlheio = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        $this->naInstituicao($this->instituicao, function () use ($usuarioAluno, $usuarioAlunoAlheio): void {
            $this->curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $outroCurso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $this->basico = Nivel::create(['nome' => 'Básico', 'curso_id' => $this->curso->id, 'ordem' => 1]);
            $this->intermediario = Nivel::create(['nome' => 'Intermediário', 'curso_id' => $this->curso->id, 'ordem' => 2]);
            $this->nivelDeOutroCurso = Nivel::create(['nome' => 'Violão 2', 'curso_id' => $outroCurso->id, 'ordem' => 2]);

            $this->professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);
            $this->outroProfessor = Professor::create(['id_usuario' => $this->usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($this->basico, $this->professor);
            $this->turmaAlheia = $this->criarTurma($this->basico, $this->outroProfessor);
            $this->turmaDestino = $this->criarTurma($this->intermediario, $this->outroProfessor);

            $this->aluno = Aluno::create(['id_usuario' => $usuarioAluno->id]);
            $this->alunoAlheio = Aluno::create(['id_usuario' => $usuarioAlunoAlheio->id]);

            $this->matricula = $this->matricular($this->aluno, $this->turma);
            $this->matricular($this->aluno, $this->turmaAlheia);
            $this->matricular($this->alunoAlheio, $this->turmaAlheia);
        });
    }

    private function criarTurma(Nivel $nivel, Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $nivel->curso_id,
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

    private function matricularPorCurso(Aluno $aluno, Professor $professor): Matricula
    {
        return Matricula::create([
            'id_aluno' => $aluno->id,
            'tipo' => 'curso',
            'id_curso' => $this->curso->id,
            'id_nivel' => $this->basico->id,
            'id_professor' => $professor->id,
            'data' => '2026-09-01',
            'status' => 'ativa',
        ]);
    }

    private function novoAluno(): Aluno
    {
        $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        return $this->naInstituicao($this->instituicao, fn () => Aluno::create(['id_usuario' => $usuario->id]));
    }

    private function comoProfessor(): static
    {
        return $this->comoUsuario($this->usuarioProfessor, $this->instituicao);
    }

    private function comoSecretaria(): static
    {
        return $this->comoUsuario($this->usuarioSecretaria, $this->instituicao);
    }

    private function sugerir(?Matricula $matricula = null, ?Nivel $nivel = null): SugestaoProgressao
    {
        $id = $this->comoProfessor()
            ->postJson('/api/professor/me/progressoes', [
                'id_matricula' => ($matricula ?? $this->matricula)->id,
                'id_nivel_sugerido' => ($nivel ?? $this->intermediario)->id,
                'justificativa' => 'Domina as escalas maiores.',
            ])
            ->assertCreated()
            ->json('data.id');

        return $this->naInstituicao($this->instituicao, fn () => SugestaoProgressao::findOrFail($id));
    }

    public function test_professor_registra_lista_edita_e_exclui_avaliacoes(): void
    {
        $id = $this->comoProfessor()
            ->postJson('/api/professor/me/avaliacoes', [
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'data' => '2026-09-29',
                'tipo' => 'pratica',
                'nota' => 8.5,
                'comentario' => 'Boa execução da peça.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nota', 8.5)
            ->assertJsonPath('data.conceito', null)
            ->assertJsonPath('data.turma.id', $this->turma->id)
            ->assertJsonPath('data.aluno.nome', 'Bruno Souza')
            ->assertJsonPath('data.pode_editar', true)
            ->json('data.id');

        $deColega = $this->naInstituicao($this->instituicao, fn () => AvaliacaoAluno::create([
            'id_aluno' => $this->aluno->id,
            'id_turma' => $this->turmaAlheia->id,
            'id_professor' => $this->outroProfessor->id,
            'data' => '2026-09-28',
            'tipo' => 'teorica',
            'conceito' => 'bom',
        ]));

        $this->getJson("/api/professor/me/avaliacoes?id_turma={$this->turma->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->getJson("/api/professor/me/avaliacoes?id_aluno={$this->aluno->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.1.id', $deColega->id)
            ->assertJsonPath('data.1.pode_editar', false);

        $this->putJson("/api/professor/me/avaliacoes/{$id}", ['nota' => null, 'conceito' => 'excelente', 'tipo' => 'apresentacao'])
            ->assertOk()
            ->assertJsonPath('data.nota', null)
            ->assertJsonPath('data.conceito', 'excelente')
            ->assertJsonPath('data.tipo', 'apresentacao');

        $this->putJson("/api/professor/me/avaliacoes/{$id}", ['conceito' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nota');

        $this->putJson("/api/professor/me/avaliacoes/{$deColega->id}", ['nota' => 10])->assertForbidden();
        $this->deleteJson("/api/professor/me/avaliacoes/{$deColega->id}")->assertForbidden();

        $this->deleteJson("/api/professor/me/avaliacoes/{$id}")->assertNoContent();
        $this->assertSoftDeleted('avaliacoes_alunos', ['id' => $id]);

        $this->getJson("/api/professor/me/alunos/{$this->aluno->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.avaliacoes')
            ->assertJsonPath('data.avaliacoes.0.id', $deColega->id)
            ->assertJsonPath('data.matriculas.0.id_curso', $this->curso->id)
            ->assertJsonPath('data.matriculas.0.id_nivel', $this->basico->id);
    }

    public function test_regras_ao_registrar_avaliacao(): void
    {
        $base = ['id_aluno' => $this->aluno->id, 'data' => '2026-09-29', 'tipo' => 'pratica', 'nota' => 7];

        $this->comoProfessor();
        $this->postJson('/api/professor/me/avaliacoes', ['nota' => null] + $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nota', 'conceito']);
        $this->postJson('/api/professor/me/avaliacoes', ['nota' => 11] + $base)->assertJsonValidationErrors('nota');
        $this->postJson('/api/professor/me/avaliacoes', ['data' => '2026-10-01'] + $base)->assertJsonValidationErrors('data');
        $this->postJson('/api/professor/me/avaliacoes', ['tipo' => 'prova'] + $base)->assertJsonValidationErrors('tipo');
        $this->postJson('/api/professor/me/avaliacoes', ['conceito' => 'otimo'] + $base)->assertJsonValidationErrors('conceito');

        $this->postJson('/api/professor/me/avaliacoes', ['id_aluno' => $this->alunoAlheio->id] + $base)->assertForbidden();
        $this->postJson('/api/professor/me/avaliacoes', ['id_turma' => $this->turmaAlheia->id] + $base)->assertForbidden();

        $this->getJson('/api/professor/me/avaliacoes')->assertUnprocessable();
        $this->getJson("/api/professor/me/avaliacoes?id_turma={$this->turmaAlheia->id}")->assertForbidden();
        $this->getJson("/api/professor/me/avaliacoes?id_aluno={$this->alunoAlheio->id}")->assertForbidden();

        $alunoIndividual = $this->novoAluno();
        $this->naInstituicao($this->instituicao, fn () => $this->matricularPorCurso($alunoIndividual, $this->professor));

        $this->postJson('/api/professor/me/avaliacoes', ['id_aluno' => $alunoIndividual->id, 'conceito' => 'regular'] + $base)
            ->assertCreated()
            ->assertJsonPath('data.id_turma', null)
            ->assertJsonPath('data.conceito', 'regular');
    }

    public function test_professor_sugere_e_cancela_progressao(): void
    {
        $this->comoProfessor()
            ->getJson('/api/professor/me/niveis')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $sugestao = $this->sugerir();

        $this->getJson("/api/professor/me/alunos/{$this->aluno->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.progressoes')
            ->assertJsonPath('data.progressoes.0.status', 'pendente')
            ->assertJsonPath('data.progressoes.0.nivel_atual.nome', 'Básico')
            ->assertJsonPath('data.progressoes.0.nivel_sugerido.nome', 'Intermediário')
            ->assertJsonPath('data.progressoes.0.pode_cancelar', true);

        $this->postJson('/api/professor/me/progressoes', [
            'id_matricula' => $this->matricula->id,
            'id_nivel_sugerido' => $this->intermediario->id,
            'justificativa' => 'De novo.',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_matricula');

        $this->comoUsuario($this->usuarioOutroProfessor, $this->instituicao)
            ->deleteJson("/api/professor/me/progressoes/{$sugestao->id}")
            ->assertForbidden();

        $this->comoProfessor()
            ->deleteJson("/api/professor/me/progressoes/{$sugestao->id}")
            ->assertNoContent();
        $this->assertSoftDeleted('sugestoes_progressao', ['id' => $sugestao->id]);
    }

    public function test_regras_ao_sugerir_progressao(): void
    {
        $payload = fn (Matricula $m, Nivel $n) => ['id_matricula' => $m->id, 'id_nivel_sugerido' => $n->id, 'justificativa' => 'x'];

        $this->comoProfessor();
        $this->postJson('/api/professor/me/progressoes', $payload($this->matricula, $this->basico))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_nivel_sugerido');
        $this->postJson('/api/professor/me/progressoes', $payload($this->matricula, $this->nivelDeOutroCurso))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_nivel_sugerido');
        $this->postJson('/api/professor/me/progressoes', ['justificativa' => ''] + $payload($this->matricula, $this->intermediario))
            ->assertJsonValidationErrors('justificativa');

        $matriculaAlheia = $this->naInstituicao($this->instituicao, fn () => Matricula::where('id_aluno', $this->alunoAlheio->id)->firstOrFail());
        $this->postJson('/api/professor/me/progressoes', $payload($matriculaAlheia, $this->intermediario))->assertForbidden();

        $alunoCancelado = $this->novoAluno();
        $cancelada = $this->naInstituicao($this->instituicao, fn () => $this->matricular($alunoCancelado, $this->turma, 'cancelada'));
        $this->postJson('/api/professor/me/progressoes', $payload($cancelada, $this->intermediario))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_matricula');

        $sugestao = $this->sugerir();
        $this->naInstituicao($this->instituicao, fn () => $sugestao->update(['status' => SugestaoProgressao::STATUS_REJEITADA]));
        $this->comoProfessor()
            ->deleteJson("/api/professor/me/progressoes/{$sugestao->id}")
            ->assertUnprocessable();
    }

    public function test_secretaria_aprova_progressao_transferindo_o_aluno_de_turma(): void
    {
        $sugestao = $this->sugerir();

        $this->comoSecretaria()
            ->getJson('/api/progressoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sugestao->id)
            ->assertJsonPath('data.0.aluno.nome', 'Bruno Souza')
            ->assertJsonPath('data.0.pode_cancelar', false)
            ->assertJsonCount(1, 'data.0.turmas_destino')
            ->assertJsonPath('data.0.turmas_destino.0.id', $this->turmaDestino->id)
            ->assertJsonPath('data.0.turmas_destino.0.alunos_vigentes', 0);

        $rota = "/api/progressoes/{$sugestao->id}/decisao";
        $this->postJson($rota, ['decisao' => 'aprovada'])->assertJsonValidationErrors('id_turma_destino');
        $this->postJson($rota, ['decisao' => 'aprovada', 'id_turma_destino' => $this->turmaAlheia->id])
            ->assertJsonValidationErrors('id_turma_destino');

        $this->naInstituicao($this->instituicao, fn () => $this->turmaDestino->update(['maximo_alunos' => 0]));
        $this->postJson($rota, ['decisao' => 'aprovada', 'id_turma_destino' => $this->turmaDestino->id])
            ->assertJsonValidationErrors('id_turma_destino');
        $this->naInstituicao($this->instituicao, fn () => $this->turmaDestino->update(['maximo_alunos' => 10]));

        $this->postJson($rota, ['decisao' => 'aprovada', 'id_turma_destino' => $this->turmaDestino->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'aprovada')
            ->assertJsonPath('data.turma_destino.id', $this->turmaDestino->id)
            ->assertJsonPath('data.decisor.nome', $this->usuarioSecretaria->nome);

        $this->naInstituicao($this->instituicao, function (): void {
            $this->assertSame($this->turmaDestino->id, $this->matricula->fresh()->id_turma);
            $historico = MatriculaHistorico::where('id_matricula', $this->matricula->id)->latest('id')->firstOrFail();
            $this->assertSame($this->turma->id, $historico->id_turma_origem);
            $this->assertSame($this->turmaDestino->id, $historico->id_turma_destino);
            $this->assertStringContainsString('Progressão de nível para Intermediário', $historico->motivo);
        });

        $this->postJson($rota, ['decisao' => 'rejeitada', 'motivo_decisao' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson('/api/progressoes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/progressoes?status=aprovada')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_secretaria_rejeita_e_aprova_progressao_de_matricula_por_curso(): void
    {
        $sugestao = $this->sugerir();

        $this->comoSecretaria();
        $this->postJson("/api/progressoes/{$sugestao->id}/decisao", ['decisao' => 'rejeitada'])
            ->assertJsonValidationErrors('motivo_decisao');
        $this->postJson("/api/progressoes/{$sugestao->id}/decisao", ['decisao' => 'rejeitada', 'motivo_decisao' => 'Aguardar o recital.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejeitada')
            ->assertJsonPath('data.motivo_decisao', 'Aguardar o recital.');
        $this->naInstituicao($this->instituicao, fn () => $this->assertSame($this->turma->id, $this->matricula->fresh()->id_turma));

        $alunoIndividual = $this->novoAluno();
        $matriculaCurso = $this->naInstituicao($this->instituicao, fn () => $this->matricularPorCurso($alunoIndividual, $this->professor));
        $sugestaoCurso = $this->sugerir($matriculaCurso);

        $this->comoSecretaria()
            ->getJson('/api/progressoes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sugestaoCurso->id)
            ->assertJsonCount(0, 'data.0.turmas_destino');

        $this->postJson("/api/progressoes/{$sugestaoCurso->id}/decisao", ['decisao' => 'aprovada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'aprovada')
            ->assertJsonPath('data.turma_destino', null);

        $this->naInstituicao($this->instituicao, fn () => $this->assertSame($this->intermediario->id, $matriculaCurso->fresh()->id_nivel));
    }

    public function test_acesso_exige_credenciais_e_papel(): void
    {
        $sugestao = $this->naInstituicao($this->instituicao, fn () => SugestaoProgressao::create([
            'id_matricula' => $this->matricula->id,
            'id_nivel_atual' => $this->basico->id,
            'id_nivel_sugerido' => $this->intermediario->id,
            'id_professor' => $this->professor->id,
            'justificativa' => 'x',
        ]));

        $this->getJson('/api/professor/me/avaliacoes?id_turma='.$this->turma->id)->assertUnauthorized();
        $this->postJson('/api/professor/me/progressoes', [])->assertUnauthorized();
        $this->getJson('/api/progressoes')->assertUnauthorized();
        $this->postJson("/api/progressoes/{$sugestao->id}/decisao", ['decisao' => 'aprovada'])->assertUnauthorized();

        foreach (['secretaria', 'aluno', 'responsavel'] as $papel) {
            $this->comoUsuario($this->criarUsuarioNaInstituicao($this->instituicao, $papel), $this->instituicao);
            $this->getJson('/api/professor/me/avaliacoes?id_turma='.$this->turma->id)->assertForbidden();
            $this->getJson('/api/professor/me/niveis')->assertForbidden();
            $this->postJson('/api/professor/me/progressoes', [])->assertForbidden();
        }

        foreach (['professor', 'aluno', 'responsavel'] as $papel) {
            $usuario = $papel === 'professor' ? $this->usuarioProfessor : $this->criarUsuarioNaInstituicao($this->instituicao, $papel);
            $this->comoUsuario($usuario, $this->instituicao);
            $this->getJson('/api/progressoes')->assertForbidden();
            $this->postJson("/api/progressoes/{$sugestao->id}/decisao", ['decisao' => 'rejeitada', 'motivo_decisao' => 'x'])->assertForbidden();
        }

        $this->comoUsuario($this->criarUsuarioNaInstituicao($this->instituicao, 'admin'), $this->instituicao)
            ->getJson('/api/progressoes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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

        [$avaliacaoDeFora, $sugestaoDeFora, $matriculaDeFora] = $this->naInstituicao($outra, function () use ($usuarioOutro, $usuarioAlunoFora): array {
            $professor = Professor::create(['id_usuario' => $usuarioOutro->id]);
            $curso = Curso::create(['nome' => 'Bateria', 'descricao' => 'Percussão']);
            $n1 = Nivel::create(['nome' => 'N1', 'curso_id' => $curso->id]);
            $n2 = Nivel::create(['nome' => 'N2', 'curso_id' => $curso->id]);
            $turma = Turma::create([
                'id_curso' => $curso->id,
                'id_nivel' => $n1->id,
                'id_professor' => $professor->id,
                'maximo_alunos' => 10,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => '2026-09-01',
                'valor_mensalidade' => 300,
            ]);
            $aluno = Aluno::create(['id_usuario' => $usuarioAlunoFora->id]);
            $matricula = $this->matricular($aluno, $turma);

            return [
                AvaliacaoAluno::create([
                    'id_aluno' => $aluno->id,
                    'id_turma' => $turma->id,
                    'id_professor' => $professor->id,
                    'data' => '2026-09-20',
                    'tipo' => 'pratica',
                    'nota' => 5,
                ]),
                SugestaoProgressao::create([
                    'id_matricula' => $matricula->id,
                    'id_nivel_atual' => $n1->id,
                    'id_nivel_sugerido' => $n2->id,
                    'id_professor' => $professor->id,
                    'justificativa' => 'x',
                ]),
                $matricula,
            ];
        });

        $this->comoProfessor();
        $this->putJson("/api/professor/me/avaliacoes/{$avaliacaoDeFora->id}", ['nota' => 1])->assertNotFound();
        $this->deleteJson("/api/professor/me/progressoes/{$sugestaoDeFora->id}")->assertNotFound();
        $this->postJson('/api/professor/me/progressoes', [
            'id_matricula' => $matriculaDeFora->id,
            'id_nivel_sugerido' => $this->intermediario->id,
            'justificativa' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_matricula');
        $this->getJson('/api/professor/me/niveis')->assertOk()->assertJsonCount(3, 'data');

        $this->comoSecretaria();
        $this->getJson('/api/progressoes?status=todas')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/progressoes/{$sugestaoDeFora->id}/decisao", ['decisao' => 'rejeitada', 'motivo_decisao' => 'x'])
            ->assertNotFound();
    }
}
