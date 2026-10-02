<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\Responsavel;
use App\Models\User;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\AvisoTurmaDestinatario;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Events\NotificacaoProcessada;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Mail\NotificacaoMail;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

/**
 * Hoje é quinta, 15/10/2026. Paula leciona a Turma A: Ana (com e-mail e WhatsApp, responsável Rita só com e-mail),
 * Bruno (sem contato, responsável Rui com e-mail e WhatsApp) e Caio (matrícula cancelada). Duda tem aulas
 * individuais com Paula. Otto leciona a Turma Otto, onde estuda Eva.
 */
class AreaProfessorAvisosTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const ROTA = '/api/professor/me/avisos';

    private const ROTA_EQUIPE = '/api/avisos';

    private Instituicao $instituicao;

    private User $usuarioPaula;

    private User $usuarioOtto;

    private User $secretaria;

    private User $admin;

    private User $usuarioAna;

    private Professor $paula;

    private Professor $otto;

    private Turma $turmaA;

    private Turma $turmaOtto;

    private Aluno $ana;

    private Aluno $duda;

    private Aluno $eva;

    private AulaTurma $aulaTurma;

    /** @var list<array{numero: string, texto: string}> */
    private array $whatsappsEnviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');
        Mail::fake();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $this->usuarioOtto = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Otto Prof']);
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $this->usuarioAna = $this->pessoa('aluno', 'Ana Lima', 'ana@exemplo.com', '(11) 98888-7777');
        $usuarioBruno = $this->pessoa('aluno', 'Bruno Reis', null, null);
        $usuarioCaio = $this->pessoa('aluno', 'Caio Melo', 'caio@exemplo.com', null);
        $usuarioDuda = $this->pessoa('aluno', 'Duda Alves', 'duda@exemplo.com', null);
        $usuarioEva = $this->pessoa('aluno', 'Eva Souza', 'eva@exemplo.com', null);
        $usuarioRita = $this->pessoa('responsavel', 'Rita Lima', 'rita@exemplo.com', null);
        $usuarioRui = $this->pessoa('responsavel', 'Rui Reis', 'rui@exemplo.com', '11977776666');

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioCaio, $usuarioDuda, $usuarioEva, $usuarioRita, $usuarioRui): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $this->paula = Professor::create(['id_usuario' => $this->usuarioPaula->id]);
            $this->otto = Professor::create(['id_usuario' => $this->usuarioOtto->id]);
            $this->turmaA = $this->criarTurma($curso, $nivel, $this->paula, 'Turma A');
            $this->turmaOtto = $this->criarTurma($curso, $nivel, $this->otto, 'Turma Otto');

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $caio = Aluno::create(['id_usuario' => $usuarioCaio->id]);
            $this->duda = Aluno::create(['id_usuario' => $usuarioDuda->id]);
            $this->eva = Aluno::create(['id_usuario' => $usuarioEva->id]);

            $this->ana->responsaveis()->attach(Responsavel::create(['id_usuario' => $usuarioRita->id])->id, ['parentesco' => 'Mãe']);
            $bruno->responsaveis()->attach(Responsavel::create(['id_usuario' => $usuarioRui->id])->id, ['parentesco' => 'Pai']);

            $this->matricular($this->ana, ['id_turma' => $this->turmaA->id, 'tipo' => 'turma']);
            $this->matricular($bruno, ['id_turma' => $this->turmaA->id, 'tipo' => 'turma']);
            $this->matricular($caio, ['id_turma' => $this->turmaA->id, 'tipo' => 'turma'], 'cancelada');
            $this->matricular($this->duda, ['tipo' => 'curso', 'id_curso' => $curso->id, 'id_nivel' => $nivel->id, 'id_professor' => $this->paula->id]);
            $this->matricular($this->eva, ['id_turma' => $this->turmaOtto->id, 'tipo' => 'turma']);

            $this->aulaTurma = AulaTurma::create([
                'id_turma' => $this->turmaA->id,
                'id_professor' => $this->paula->id,
                'data' => '2026-10-20',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
                'status' => 'agendada',
                'tipo' => 'regular',
            ]);
        });

        $enviados = &$this->whatsappsEnviados;
        $this->app->instance(WhatsappGatewayInterface::class, new class($enviados) implements WhatsappGatewayInterface
        {
            public function __construct(private array &$enviados) {}

            public function status(string $instance): string
            {
                return self::STATUS_CONNECTED;
            }

            public function connect(string $instance): ?string
            {
                return null;
            }

            public function reconnect(string $instance): ?string
            {
                return null;
            }

            public function disconnect(string $instance): void {}

            public function sendText(string $instance, string $numero, string $texto): void
            {
                $this->enviados[] = ['numero' => $numero, 'texto' => $texto];
            }
        });
    }

    private function pessoa(string $role, string $nome, ?string $email, ?string $whatsapp): User
    {
        return $this->criarUsuarioNaInstituicao($this->instituicao, $role, [
            'nome' => $nome,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'telefone' => null,
        ]);
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor, string $descricao): Turma
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
        ]);
    }

    private function matricular(Aluno $aluno, array $dados, string $status = 'ativa'): void
    {
        Matricula::create(['id_aluno' => $aluno->id, 'data' => '2026-09-01', 'status' => $status, ...$dados]);
    }

    private function conectarWhatsapp(): void
    {
        $this->naInstituicao($this->instituicao, fn () => ConfiguracaoWhatsapp::create([
            'instance_name' => 'escola',
            'status' => WhatsappGatewayInterface::STATUS_CONNECTED,
        ]));
    }

    private function aviso(array $dados = []): array
    {
        return $dados + [
            'id_turma' => $this->turmaA->id,
            'publico' => 'alunos',
            'canais' => ['email'],
            'titulo' => 'Ensaio extra',
            'mensagem' => "Sábado tem ensaio às 10h.\nTragam o violão.",
        ];
    }

    private function enviar(array $dados = [], ?User $usuario = null, string $rota = self::ROTA)
    {
        return $this->comoUsuario($usuario ?? $this->usuarioPaula, $this->instituicao)->postJson($rota, $this->aviso($dados));
    }

    /** @return array<string, string> "nome|canal" => status */
    private function statusDosDestinatarios(int $idAviso): array
    {
        return AvisoTurmaDestinatario::withoutGlobalScopes()
            ->where('id_aviso_turma', $idAviso)
            ->get()
            ->mapWithKeys(fn (AvisoTurmaDestinatario $d) => ["{$d->nome}|{$d->canal}" => $d->status])
            ->sortKeys()
            ->all();
    }

    public function test_aviso_por_email_vai_ao_aluno_e_ao_responsavel_de_quem_nao_tem_contato(): void
    {
        $resposta = $this->enviar()
            ->assertCreated()
            ->assertJsonPath('data.origem', 'manual')
            ->assertJsonPath('data.turma.nome', 'Turma A')
            ->assertJsonPath('data.professor.id', $this->paula->id)
            ->assertJsonPath('data.totais.alunos', 2)
            ->assertJsonPath('data.totais.destinatarios', 2)
            ->assertJsonPath('data.totais.enviados', 2);

        $this->assertSame(
            ['Ana Lima|email' => 'enviado', 'Rui Reis|email' => 'enviado'],
            $this->statusDosDestinatarios($resposta->json('data.id'))
        );
        $this->assertSame('Bruno Reis', collect($resposta->json('data.destinatarios'))->firstWhere('nome', 'Rui Reis')['aluno']);

        Mail::assertSent(NotificacaoMail::class, 2);
        Mail::assertSent(NotificacaoMail::class, fn (NotificacaoMail $mail) => $mail->hasTo('ana@exemplo.com')
            && $mail->assunto === 'Ensaio extra'
            && str_contains($mail->mensagem, 'Tragam o violão.')
            && str_contains($mail->mensagem, '— Paula Prof · Turma A'));
        Mail::assertNotSent(NotificacaoMail::class, fn (NotificacaoMail $mail) => $mail->hasTo('caio@exemplo.com'));

        $this->assertSame(2, NotificacaoDisparada::withoutInstituicaoScope()->where('canal', 'email')->where('status', 'enviado')->count());
    }

    public function test_publico_ambos_por_whatsapp_registra_quem_esta_sem_contato(): void
    {
        $this->conectarWhatsapp();

        $id = $this->enviar(['publico' => 'ambos', 'canais' => ['whatsapp']])->assertCreated()->json('data.id');

        $this->assertSame([
            'Ana Lima|whatsapp' => 'enviado',
            'Bruno Reis|whatsapp' => 'sem_contato',
            'Rita Lima|whatsapp' => 'sem_contato',
            'Rui Reis|whatsapp' => 'enviado',
        ], $this->statusDosDestinatarios($id));

        $this->assertEqualsCanonicalizing(['5511988887777', '5511977776666'], array_column($this->whatsappsEnviados, 'numero'));
        $this->assertStringStartsWith('*Ensaio extra*', $this->whatsappsEnviados[0]['texto']);
        Mail::assertNothingSent();
    }

    public function test_whatsapp_desconectado_impede_o_envio_por_whatsapp(): void
    {
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson(self::ROTA.'/opcoes')
            ->assertOk()
            ->assertJsonPath('data.canais', ['email' => true, 'whatsapp' => false]);

        $this->enviar(['canais' => ['email', 'whatsapp']])->assertUnprocessable()->assertJsonValidationErrors('canais');
        $this->assertSame(0, AvisoTurma::withoutGlobalScopes()->count());
    }

    public function test_previa_conta_mensagens_e_lista_quem_esta_sem_contato(): void
    {
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson(self::ROTA.'/previa?'.http_build_query(['id_turma' => $this->turmaA->id, 'publico' => 'ambos', 'canais' => ['email']]))
            ->assertOk()
            ->assertJsonPath('data.alunos', 2)
            ->assertJsonPath('data.mensagens', 3)
            ->assertJsonPath('data.por_canal.email', 3)
            ->assertJsonPath('data.sem_contato', [['nome' => 'Bruno Reis', 'canal' => 'email']]);

        $this->assertSame(0, AvisoTurma::withoutGlobalScopes()->count());
    }

    public function test_alunos_escolhidos_incluem_aulas_individuais_e_nao_aceitam_alunos_alheios(): void
    {
        $this->enviar(['id_turma' => null, 'ids_alunos' => [$this->duda->id]])
            ->assertCreated()
            ->assertJsonPath('data.turma', null)
            ->assertJsonPath('data.totais.alunos', 1);
        Mail::assertSent(NotificacaoMail::class, fn (NotificacaoMail $mail) => $mail->hasTo('duda@exemplo.com'));

        $this->enviar(['id_turma' => null, 'ids_alunos' => [$this->eva->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids_alunos');

        $this->enviar(['ids_alunos' => [$this->duda->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids_alunos');

        $this->enviar(['id_turma' => null])->assertUnprocessable()->assertJsonValidationErrors(['id_turma', 'ids_alunos']);
    }

    public function test_professor_so_envia_para_as_proprias_turmas(): void
    {
        $this->enviar(['id_turma' => $this->turmaOtto->id])->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson(self::ROTA.'/previa?'.http_build_query(['id_turma' => $this->turmaOtto->id, 'publico' => 'alunos', 'canais' => ['email']]))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_limite_diario_configurado_pela_escola(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/instituicao/comunicacao-professor')
            ->assertOk()
            ->assertJsonPath('data.limite_avisos_dia', 10);
        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/instituicao/comunicacao-professor', ['limite_avisos_dia' => 0])
            ->assertUnprocessable();
        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/instituicao/comunicacao-professor', ['limite_avisos_dia' => 2])
            ->assertOk()
            ->assertJsonPath('data.limite_avisos_dia', 2);
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson('/api/instituicao/comunicacao-professor', ['limite_avisos_dia' => 50])
            ->assertForbidden();

        $this->enviar()->assertCreated();
        $this->enviar()->assertCreated();
        $this->enviar()->assertStatus(429)->assertJsonPath('message', 'Você atingiu o limite de 2 avisos por dia definido pela escola. Tente novamente amanhã.');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson(self::ROTA.'/opcoes')
            ->assertJsonPath('data.limite', ['por_dia' => 2, 'enviados_hoje' => 2, 'restantes' => 0]);

        $this->enviar([], $this->secretaria, self::ROTA_EQUIPE)->assertCreated();
        $this->comoUsuario($this->secretaria, $this->instituicao)->getJson(self::ROTA_EQUIPE.'/opcoes')->assertJsonPath('data.limite', null);

        $this->travelTo('2026-10-16 08:00:00');
        $this->enviar()->assertCreated();
    }

    public function test_historico_do_professor_e_da_secretaria(): void
    {
        $idPaula = $this->enviar()->json('data.id');
        $idSecretaria = $this->enviar(['id_turma' => $this->turmaOtto->id, 'titulo' => 'Recesso'], $this->secretaria, self::ROTA_EQUIPE)
            ->assertCreated()
            ->assertJsonPath('data.professor.id', $this->otto->id)
            ->assertJsonPath('data.autor.role', 'secretaria')
            ->json('data.id');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $idPaula)
            ->assertJsonPath('data.0.totais.enviados', 2);
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA."/{$idSecretaria}")->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA."/{$idPaula}")
            ->assertOk()
            ->assertJsonCount(2, 'data.destinatarios');

        $this->comoUsuario($this->usuarioOtto, $this->instituicao)->getJson(self::ROTA)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $idSecretaria);

        $this->comoUsuario($this->secretaria, $this->instituicao)->getJson(self::ROTA_EQUIPE)->assertOk()->assertJsonCount(2, 'data');
        $this->comoUsuario($this->secretaria, $this->instituicao)->getJson(self::ROTA_EQUIPE.'?search=Recesso')->assertJsonCount(1, 'data');
        $this->comoUsuario($this->admin, $this->instituicao)->getJson(self::ROTA_EQUIPE."/{$idPaula}")->assertOk();
    }

    public function test_falha_no_envio_aparece_no_destinatario(): void
    {
        $id = $this->enviar()->json('data.id');
        $destinatario = AvisoTurmaDestinatario::withoutGlobalScopes()->where('id_aviso_turma', $id)->where('nome', 'Ana Lima')->first();

        NotificacaoProcessada::dispatch(AvisoTurmaDestinatario::class, $destinatario->id, NotificacaoProcessada::ERRO, 'Caixa cheia');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA."/{$id}")
            ->assertJsonPath('data.totais.erros', 1)
            ->assertJsonPath('data.destinatarios.0.status', 'erro')
            ->assertJsonPath('data.destinatarios.0.erro', 'Caixa cheia');
    }

    public function test_cancelar_aula_avisa_alunos_e_responsaveis_quando_marcado(): void
    {
        $this->instituicao->update(['permissoes_professor_aulas' => ['criar' => 'livre', 'editar' => 'livre', 'excluir' => 'livre']]);

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->postJson('/api/professor/me/solicitacoes', ['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Viagem'])
            ->assertCreated()
            ->assertJsonPath('data.avisar_alunos', true);

        $aviso = AvisoTurma::withoutGlobalScopes()->sole();
        $this->assertSame('aula_alterada', $aviso->origem);
        $this->assertSame($this->aulaTurma->id, $aviso->id_aula_turma);
        $this->assertSame('Aula cancelada', $aviso->titulo);
        $this->assertSame('A aula de Turma A de terça-feira, 20/10, das 14:00 às 15:00 foi cancelada.', $aviso->mensagem);
        $this->assertSame([
            'Ana Lima|email' => 'enviado',
            'Bruno Reis|email' => 'sem_contato',
            'Rita Lima|email' => 'enviado',
            'Rui Reis|email' => 'enviado',
        ], $this->statusDosDestinatarios($aviso->id));

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson(self::ROTA.'/opcoes')
            ->assertJsonPath('data.limite.enviados_hoje', 0);
    }

    public function test_aula_alterada_sem_aviso_quando_desmarcado(): void
    {
        $this->instituicao->update(['permissoes_professor_aulas' => ['criar' => 'livre', 'editar' => 'livre', 'excluir' => 'livre']]);

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->postJson('/api/professor/me/solicitacoes', [
                'tipo' => 'cancelamento',
                'id_aula_turma' => $this->aulaTurma->id,
                'motivo' => 'Viagem',
                'avisar_alunos' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.avisar_alunos', false);

        $this->assertSame(0, AvisoTurma::withoutGlobalScopes()->count());
        Mail::assertNothingSent();
    }

    public function test_secretaria_decide_se_avisa_ao_aprovar(): void
    {
        $this->instituicao->update(['permissoes_professor_aulas' => ['criar' => 'livre', 'editar' => 'aprovacao', 'excluir' => 'livre']]);
        $solicitar = fn (array $dados) => $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->postJson('/api/professor/me/solicitacoes', $dados + ['id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Consulta'])
            ->assertCreated()
            ->json('data.id');

        $substituicao = $solicitar(['tipo' => 'substituicao']);
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson("/api/solicitacoes-aulas/{$substituicao}/decisao", ['decisao' => 'aprovada', 'id_professor_substituto' => $this->otto->id])
            ->assertOk();

        $aviso = AvisoTurma::withoutGlobalScopes()->sole();
        $this->assertSame('Troca de professor', $aviso->titulo);
        $this->assertSame('A aula de Turma A de terça-feira, 20/10, das 14:00 às 15:00 será dada pelo professor Otto Prof.', $aviso->mensagem);
        $this->assertSame($this->otto->id, $aviso->id_professor);
        $this->assertSame($this->secretaria->id, $aviso->id_usuario_autor);

        $reposicao = $solicitar([
            'tipo' => 'reposicao',
            'data_sugerida' => '2026-10-22',
            'hora_inicio_sugerida' => '16:00',
            'hora_termino_sugerida' => '17:00',
        ]);
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson("/api/solicitacoes-aulas/{$reposicao}/decisao", ['decisao' => 'aprovada', 'avisar_alunos' => false])
            ->assertOk()
            ->assertJsonPath('data.avisar_alunos', false);

        $this->assertSame(1, AvisoTurma::withoutGlobalScopes()->count());
    }

    public function test_acessos_por_papel(): void
    {
        $this->getJson(self::ROTA)->assertUnauthorized();
        $this->postJson(self::ROTA_EQUIPE, $this->aviso())->assertUnauthorized();
        $this->getJson('/api/instituicao/comunicacao-professor')->assertUnauthorized();

        $this->comoUsuario($this->usuarioAna, $this->instituicao)->getJson(self::ROTA)->assertForbidden();
        $this->comoUsuario($this->usuarioAna, $this->instituicao)->getJson(self::ROTA_EQUIPE)->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA_EQUIPE)->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->postJson(self::ROTA_EQUIPE, $this->aviso())->assertForbidden();
        $this->comoUsuario($this->secretaria, $this->instituicao)->getJson(self::ROTA)->assertForbidden();
        $this->comoUsuario($this->secretaria, $this->instituicao)->getJson('/api/instituicao/comunicacao-professor')->assertForbidden();

        $this->assertSame(0, AvisoTurma::withoutGlobalScopes()->count());
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $id = $this->enviar()->json('data.id');

        $outra = Instituicao::create(['slug' => 'outra-escola', 'nome_fantasia' => 'Outra Escola', 'status' => Instituicao::STATUS_ATIVO]);
        $secretariaFora = $this->criarUsuarioNaInstituicao($outra, 'secretaria');
        $professorFora = $this->criarUsuarioNaInstituicao($outra, 'professor');
        $this->naInstituicao($outra, fn () => Professor::create(['id_usuario' => $professorFora->id]));

        $this->comoUsuario($secretariaFora, $outra)->getJson(self::ROTA_EQUIPE)->assertOk()->assertJsonCount(0, 'data');
        $this->comoUsuario($secretariaFora, $outra)->getJson(self::ROTA_EQUIPE."/{$id}")->assertNotFound();
        $this->comoUsuario($secretariaFora, $outra)
            ->postJson(self::ROTA_EQUIPE, $this->aviso())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_turma');
        $this->comoUsuario($secretariaFora, $outra)
            ->postJson(self::ROTA_EQUIPE, $this->aviso(['id_turma' => null, 'ids_alunos' => [$this->ana->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids_alunos');

        $this->comoUsuario($professorFora, $outra)->getJson(self::ROTA."/{$id}")->assertNotFound();
        $this->comoUsuario($professorFora, $outra)
            ->postJson(self::ROTA, $this->aviso(['id_turma' => null, 'ids_alunos' => [$this->ana->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids_alunos');

        $this->assertSame(1, AvisoTurma::withoutGlobalScopes()->count());
        Mail::assertSent(NotificacaoMail::class, 2);
    }
}
