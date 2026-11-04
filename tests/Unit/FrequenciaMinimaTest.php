<?php

namespace Tests\Unit;

use App\Enums\TurmaStatus;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Support\FrequenciaMinima;
use App\Modules\Core\Models\Instituicao;
use App\Models\Professor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class FrequenciaMinimaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    public function test_resolve_turma_instituicao_e_fallback(): void
    {
        $instituicao = $this->instituicaoDefault();
        $instituicao->update(['frequencia_minima' => 82]);

        $this->naInstituicao($instituicao, function () use ($instituicao): void {
            $curso = Curso::create(['nome' => 'Piano']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $usuario = User::factory()->create(['role' => 'professor']);
            $professor = Professor::create(['id_usuario' => $usuario->id]);

            $turma = Turma::create([
                'id_curso' => $curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $professor->id,
                'maximo_alunos' => 10,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 8,
                'data_inicio' => '2026-09-01',
                'frequencia_minima' => null,
            ]);

            $this->assertSame(82.0, FrequenciaMinima::resolver($turma, $instituicao));

            $turma->update(['frequencia_minima' => 91]);
            $this->assertSame(91.0, FrequenciaMinima::resolver($turma->fresh(), $instituicao));
        });

        $this->assertSame(
            Certificado::FREQUENCIA_MINIMA,
            FrequenciaMinima::resolver(null, null)
        );
    }
}
