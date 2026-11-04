<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoJustificativasTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private User $usuarioPaula;

    private User $usuarioCarlos;

    private Aluno $ana;

    private Aluno $bruno;

    private Professor $paula;

    private AulaTurma $futura;

    private AulaTurma $falta;

    private AulaTurma $outraFalta;

    private AulaTurma $presente;

    private AulaTurma $cancelada;

    private AulaTurma $doBruno;

    private AulaTurma $individual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $this->usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $this->usuarioCarlos = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Carlos Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $this->paula = Professor::create(['id_usuario' => $this->usuarioPaula->id]);
            $carlos = Professor::create(['id_usuario' => $this->usuarioCarlos->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $this->paula, 'Turma Ana');
            $turmaBruno = $this->turma($curso, $nivel, $carlos, 'Turma Bruno');
            $this->matricular($this->ana, $turmaAna);
            $this->matricular($this->bruno, $turmaBruno);

            $this->futura = $this->aula($turmaAna, $this->paula, '2026-10-27', 'agendada');
            $this->falta = $this->aula($turmaAna, $this->paula, '2026-10-20', 'concluida', 'segredo da chamada');
            $this->outraFalta = $this->aula($turmaAna, $this->paula, '2026-10-21', 'concluida');
            $this->presente = $this->aula($turmaAna, $this->paula, '2026-10-22', 'concluida');
            $this->cancelada = $this->aula($turmaAna, $this->paula, '2026-10-28', 'cancelada');
            $this->individual = $this->aula($turmaAna, $this->paula, '2026-10-29', 'agendada', null, $this->bruno->id);
            $this->doBruno = $this->aula($turmaBruno, $carlos, '2026-10-27', 'agendada');

            AulaPresenca::create(['id_aula_turma' => $this->falta->id, 'id_aluno' => $this->ana->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $this->outraFalta->id, 'id_aluno' => $this->ana->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $this->presente->id, 'id_aluno' => $this->ana->id, 'status' => 'presente']);
            $faltaBruno = $this->aula($turmaBruno, $carlos, '2026-10-15', 'concluida');
            AulaPresenca::create(['id_aula_turma' => $faltaBruno->id, 'id_aluno' => $this->bruno->id, 'status' => 'ausente']);
            JustificativaFalta::create([
                'id_aluno' => $this->bruno->id,
                'id_aula_turma' => $faltaBruno->id,
                'tipo' => 'justificativa',
                'motivo' => 'Pedido do Bruno',
                'status' => 'pendente',
            ]);
        });
    }

    public function test_aluno_avisa_ausencia_e_justifica_falta_sem_ver_a_dos_outros(): void
    {
        Storage::fake('public');

        $aviso = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->post('/api/aluno/me/justificativas', [
                'id_aula_turma' => $this->futura->id,
                'tipo' => 'aviso_previo',
                'motivo' => 'Consulta médica',
                'anexo' => UploadedFile::fake()->image('atestado.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('pendente', $aviso['status']);
        $this->assertNotNull($aviso['anexo_url']);
        Storage::disk('public')->assertExists(
            JustificativaFalta::query()->findOrFail($aviso['id'])->anexo
        );

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->futura->id,
            'tipo' => 'aviso_previo',
            'motivo' => 'De novo',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma');

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->falta->id,
            'tipo' => 'justificativa',
            'motivo' => 'Estava doente',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->presente->id,
            'tipo' => 'justificativa',
            'motivo' => 'Não faltei',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('tipo');

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->falta->id,
            'tipo' => 'aviso_previo',
            'motivo' => 'Aula que já passou',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('tipo');

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->cancelada->id,
            'tipo' => 'aviso_previo',
            'motivo' => 'Cancelada',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma');

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->doBruno->id,
            'tipo' => 'aviso_previo',
            'motivo' => 'Turma do Bruno',
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->post('/api/aluno/me/justificativas', [
            'id_aula_turma' => $this->individual->id,
            'tipo' => 'aviso_previo',
            'motivo' => 'Aula individual do Bruno',
        ], ['Accept' => 'application/json'])->assertForbidden();

        $lista = $this->getJson('/api/aluno/me/justificativas')->assertOk()->json('data');
        $corpo = json_encode($lista);
        $this->assertStringNotContainsString('Pedido do Bruno', $corpo);
        $this->assertStringNotContainsString('segredo da chamada', $corpo);
        $this->assertStringNotContainsString('Bruno Melo', $corpo);

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->postJson('/api/aluno/me/justificativas', [
                'id_aula_turma' => $this->outraFalta->id,
                'tipo' => 'justificativa',
                'motivo' => 'Sem o aluno',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->postJson('/api/aluno/me/justificativas?id_aluno='.$this->ana->id, [
            'id_aula_turma' => $this->outraFalta->id,
            'tipo' => 'justificativa',
            'motivo' => 'A Rita avisou',
        ])->assertCreated();

        $this->postJson('/api/aluno/me/justificativas?id_aluno='.$this->bruno->id, [
            'id_aula_turma' => $this->doBruno->id,
            'tipo' => 'aviso_previo',
            'motivo' => 'Sem vínculo',
        ])->assertForbidden();
    }

    public function test_secretaria_e_professor_da_turma_decidem_e_a_falta_fica_justificada(): void
    {
        $aviso = $this->pedido($this->futura, 'aviso_previo', 'Consulta médica');
        $falta = $this->pedido($this->falta, 'justificativa', 'Estava doente');
        $segunda = $this->pedido($this->outraFalta, 'justificativa', 'Compromisso');

        $daPaula = $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson('/api/justificativas-faltas')
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(
            ['Consulta médica', 'Estava doente', 'Compromisso'],
            collect($daPaula)->pluck('motivo')->all()
        );
        $this->assertStringNotContainsString('Pedido do Bruno', json_encode($daPaula));

        $this->comoUsuario($this->usuarioCarlos, $this->instituicao)
            ->postJson('/api/justificativas-faltas/'.$falta->id.'/decisao', ['decisao' => 'aceita'])
            ->assertForbidden();

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->postJson('/api/justificativas-faltas/'.$falta->id.'/decisao', ['decisao' => 'aceita'])
            ->assertOk()
            ->assertJsonPath('data.status', 'aceita');

        $this->assertSame('justificado', AulaPresenca::query()->where('id_aula_turma', $this->falta->id)->where('id_aluno', $this->ana->id)->value('status'));
        $this->assertSame('Estava doente', AulaPresenca::query()->where('id_aula_turma', $this->falta->id)->value('observacao'));

        $this->postJson('/api/justificativas-faltas/'.$falta->id.'/decisao', ['decisao' => 'recusada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/justificativas-faltas/'.$segunda->id.'/decisao', ['decisao' => 'recusada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'recusada');

        $this->assertSame('ausente', AulaPresenca::query()->where('id_aula_turma', $this->outraFalta->id)->value('status'));

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/justificativas-faltas/'.$aviso->id.'/decisao', ['decisao' => 'aceita'])
            ->assertOk();

        $this->assertSame(
            'justificado',
            AulaPresenca::query()->where('id_aula_turma', $this->futura->id)->where('id_aluno', $this->ana->id)->value('status')
        );
    }

    public function test_demais_papeis_e_sessao_recebem_401_ou_403(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/justificativas')
            ->assertUnauthorized();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/justificativas')
                ->assertForbidden();
        }

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/justificativas-faltas')
            ->assertForbidden();

        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/justificativas')
            ->assertForbidden();
    }

    private function pedido(AulaTurma $aula, string $tipo, string $motivo): JustificativaFalta
    {
        return $this->naInstituicao($this->instituicao, fn () => JustificativaFalta::create([
            'id_aluno' => $this->ana->id,
            'id_aula_turma' => $aula->id,
            'tipo' => $tipo,
            'motivo' => $motivo,
            'status' => 'pendente',
        ]));
    }

    private function turma(Curso $curso, Nivel $nivel, Professor $professor, string $descricao): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'descricao' => $descricao,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 350,
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma): void
    {
        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => 'ativa',
        ]);
    }

    private function aula(Turma $turma, Professor $professor, string $data, string $status, ?string $conteudo = null, ?int $idAluno = null): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_curso' => $turma->id_curso,
            'id_professor' => $professor->id,
            'data' => $data,
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
            'status' => $status,
            'tipo' => $idAluno ? 'reposicao' : 'regular',
            'id_aluno_especifico' => $idAluno,
            'conteudo_dado' => $conteudo,
        ]);
    }
}
