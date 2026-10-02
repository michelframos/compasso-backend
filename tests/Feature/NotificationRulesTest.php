<?php

namespace Tests\Feature;

use App\Models\AulaTurma;
use App\Models\ConfiguracaoNotificacao;
use App\Models\User;
use App\Models\Aluno;
use App\Models\Turma;
use App\Models\Professor;
use App\Models\Curso;
use App\Models\Nivel;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesAgendamentos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use App\Modules\Notificacoes\Jobs\EnviarMensagemWhatsappJob;

class NotificationRulesTest extends TestCase
{
    use RefreshDatabase;

    private $config;
    private $aluno;
    private $professor;
    private $turma;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar usuário e aluno para destinatário
        $user = User::factory()->create(['nome' => 'João', 'whatsapp' => '11999999999']);
        $this->aluno = Aluno::create(['id_usuario' => $user->id, 'nome' => 'João', 'status' => 'ativo']);

        // Configuração básica de aula
        $curso = Curso::create(['nome' => 'Piano', 'status' => 'ativo']);
        $nivel = Nivel::create(['nome' => 'Iniciante', 'status' => 'ativo']);
        $professorUser = User::factory()->create();
        $this->professor = Professor::create(['id_usuario' => $professorUser->id, 'nome' => 'Prof', 'status' => 'ativo']);
        
        $this->turma = Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $this->professor->id,
            'status' => 'em_andamento'
        ]);

        // Configuração de notificação
        $this->config = ConfiguracaoNotificacao::create([
            'modulo' => 'agendamentos',
            'ativo' => true,
            'horario_envio' => '08:00',
            'dias_antecedencia' => 1,
            'template_mensagem' => 'Olá {nome}',
            'intervalo_repeticao' => 1
        ]);
        
        Queue::fake();
    }

    public function test_it_does_not_process_before_scheduled_time()
    {
        Carbon::setTestNow('2026-03-25 07:59:00');

        AulaTurma::create([
            'id_aluno_especifico' => $this->aluno->id,
            'id_professor' => $this->professor->id,
            'data' => '2026-03-26',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'notificar' => true,
            'status' => 'agendada'
        ]);

        (new ProcessarNotificacoesAgendamentos())->handle();

        Queue::assertNothingPushed();
    }

    public function test_it_processes_all_eligible_on_first_daily_run()
    {
        Carbon::setTestNow('2026-03-25 08:00:00');

        AulaTurma::create([
            'id_aluno_especifico' => $this->aluno->id,
            'id_professor' => $this->professor->id,
            'data' => '2026-03-26',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'notificar' => true,
            'status' => 'agendada'
        ]);

        (new ProcessarNotificacoesAgendamentos())->handle();

        Queue::assertPushed(EnviarMensagemWhatsappJob::class, 1);
        
        $this->config->refresh();
        $this->assertNotNull($this->config->ultimo_processamento);
        $this->assertTrue($this->config->ultimo_processamento->isToday());
    }

    public function test_it_processes_only_new_items_after_daily_run()
    {
        // 1. Run at 08:00
        Carbon::setTestNow('2026-03-25 08:00:00');
        
        AulaTurma::create([
            'id_aluno_especifico' => $this->aluno->id,
            'id_professor' => $this->professor->id,
            'data' => '2026-03-26',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'notificar' => true,
            'status' => 'agendada'
        ]);

        (new ProcessarNotificacoesAgendamentos())->handle();
        Queue::assertPushed(EnviarMensagemWhatsappJob::class, 1);
        
        // 2. Create new item at 09:00
        Carbon::setTestNow('2026-03-25 09:00:00');
        
        AulaTurma::create([
            'id_aluno_especifico' => $this->aluno->id,
            'id_professor' => $this->professor->id,
            'data' => '2026-03-26',
            'hora_inicio' => '11:00',
            'hora_termino' => '12:00',
            'notificar' => true,
            'status' => 'agendada'
        ]);

        (new ProcessarNotificacoesAgendamentos())->handle();
        
        // Total should be 2 now (1 from first run, 1 from second)
        Queue::assertPushed(EnviarMensagemWhatsappJob::class, 2);
    }
    
    public function test_it_does_not_duplicate_notifications_on_subsequent_runs()
    {
        Carbon::setTestNow('2026-03-25 08:00:00');
        
        AulaTurma::create([
            'id_aluno_especifico' => $this->aluno->id,
            'id_professor' => $this->professor->id,
            'data' => '2026-03-26',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'notificar' => true,
            'status' => 'agendada'
        ]);

        (new ProcessarNotificacoesAgendamentos())->handle();
        Queue::assertPushed(EnviarMensagemWhatsappJob::class, 1);
        
        // Run again 1 minute later
        Carbon::setTestNow('2026-03-25 08:01:00');
        (new ProcessarNotificacoesAgendamentos())->handle();
        
        // Should still be 1 total
        Queue::assertPushed(EnviarMensagemWhatsappJob::class, 1);
    }
}
