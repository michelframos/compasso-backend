<?php

namespace App\Modules\Academico\UseCases\AvisoTurma;

use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Services\AvisoTurmaService;
use App\Modules\Academico\Services\DestinatariosAvisoService;
use App\Modules\Academico\Services\LimiteAvisosService;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnviarAvisoTurmaUseCase
{
    public function __construct(
        private readonly AvisoTurmaService $avisos,
        private readonly DestinatariosAvisoService $destinatarios,
        private readonly LimiteAvisosService $limites,
    ) {}

    /** @param  array<string, mixed>  $dados */
    public function execute(User $autor, array $dados, ?Turma $turma): AvisoTurma
    {
        $this->limites->garantir($autor);

        $indisponiveis = array_diff($dados['canais'], $this->avisos->canaisDisponiveis());
        if ($indisponiveis !== []) {
            throw ValidationException::withMessages([
                'canais' => 'WhatsApp não está conectado. Conecte em Configurações ou envie só por e-mail.',
            ]);
        }

        $alunos = $this->destinatarios->alunos($autor, $turma, array_map('intval', $dados['ids_alunos'] ?? []));
        if ($alunos->isEmpty()) {
            throw ValidationException::withMessages([
                'id_turma' => 'Nenhum aluno com matrícula vigente para receber o aviso.',
            ]);
        }

        $idProfessor = $autor->role === 'professor' ? $autor->professor?->id : $turma?->id_professor;

        return DB::transaction(fn () => $this->avisos->registrar($autor, $dados, $alunos, $turma, $idProfessor));
    }
}
