<?php

use App\Modules\Academico\Http\Controllers\AlunoAgendaController;
use App\Modules\Academico\Http\Controllers\AlunoBoletimController;
use App\Modules\Academico\Http\Controllers\AlunoCertificadoController;
use App\Modules\Academico\Http\Controllers\AlunoFrequenciaController;
use App\Modules\Academico\Http\Controllers\CertificadoController;
use App\Modules\Academico\Http\Controllers\PublicCertificadoController;
use App\Modules\Academico\Http\Controllers\AlunoJustificativaController;
use App\Modules\Academico\Http\Controllers\AlunoReposicaoController;
use App\Modules\Academico\Http\Controllers\AlunoMaterialController;
use App\Modules\Academico\Http\Controllers\AlunoMatriculaController;
use App\Modules\Academico\Http\Controllers\AlunoPainelController;
use App\Modules\Academico\Http\Controllers\AulaPresencaController;
use App\Modules\Academico\Http\Controllers\AvaliacaoAlunoController;
use App\Modules\Academico\Http\Controllers\AulaTurmaController;
use App\Modules\Academico\Http\Controllers\AvisoTurmaController;
use App\Modules\Academico\Http\Controllers\ConcluirAulaController;
use App\Modules\Academico\Http\Controllers\CursoController;
use App\Modules\Academico\Http\Controllers\DisponibilidadeProfessorController;
use App\Modules\Academico\Http\Controllers\JustificativaFaltaController;
use App\Modules\Academico\Http\Controllers\MaterialTurmaController;
use App\Modules\Academico\Http\Controllers\MatriculaController;
use App\Modules\Academico\Http\Controllers\NivelController;
use App\Modules\Academico\Http\Controllers\ObservacaoAlunoController;
use App\Modules\Academico\Http\Controllers\ProfessorAlunoController;
use App\Modules\Academico\Http\Controllers\ProfessorAulaController;
use App\Modules\Academico\Http\Controllers\ProfessorMeController;
use App\Modules\Academico\Http\Controllers\ProfessorPainelController;
use App\Modules\Academico\Http\Controllers\ProfessorTurmaController;
use App\Modules\Academico\Http\Controllers\SolicitacaoAulaController;
use App\Modules\Academico\Http\Controllers\SolicitacaoReposicaoController;
use App\Modules\Academico\Http\Controllers\SugestaoProgressaoController;
use App\Modules\Academico\Http\Controllers\TurmaController;
use App\Modules\Academico\Http\Controllers\TurmaHorarioController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Acadêmico.
| Prefixadas com /api pelo AcademicoServiceProvider.
*/

Route::prefix('public')->group(function () {
    Route::get('certificados/{codigo}', [PublicCertificadoController::class, 'show'])
        ->middleware('throttle:30,1');
});

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.senha.atualizada'])->group(function () {
    Route::middleware(['role:professor', 'ensure.perfil.professor'])->prefix('professor/me')->group(function () {
        Route::get('/', [ProfessorMeController::class, 'show']);
        Route::get('painel', [ProfessorPainelController::class, 'show']);
        Route::get('turmas', [ProfessorTurmaController::class, 'index']);
        Route::get('turmas/{turma}', [ProfessorTurmaController::class, 'show']);
        Route::get('aulas/{aulaTurma}', [ProfessorAulaController::class, 'show']);
        Route::get('aulas/{aulaTurma}/opcoes-solicitacao', [SolicitacaoAulaController::class, 'opcoes']);
        Route::get('disponibilidades', [DisponibilidadeProfessorController::class, 'minhas']);
        Route::put('disponibilidades', [DisponibilidadeProfessorController::class, 'salvar']);
        Route::get('solicitacoes', [SolicitacaoAulaController::class, 'minhas']);
        Route::post('solicitacoes', [SolicitacaoAulaController::class, 'store']);
        Route::delete('solicitacoes/{solicitacaoAula}', [SolicitacaoAulaController::class, 'destroy']);
        Route::get('avisos', [AvisoTurmaController::class, 'index']);
        Route::get('avisos/opcoes', [AvisoTurmaController::class, 'opcoes']);
        Route::get('avisos/previa', [AvisoTurmaController::class, 'previa']);
        Route::post('avisos', [AvisoTurmaController::class, 'store']);
        Route::get('avisos/{avisoTurma}', [AvisoTurmaController::class, 'show']);
        Route::get('alunos', [ProfessorAlunoController::class, 'index']);
        Route::get('alunos/{aluno}', [ProfessorAlunoController::class, 'show']);
        Route::post('observacoes', [ObservacaoAlunoController::class, 'store']);
        Route::put('observacoes/{observacaoAluno}', [ObservacaoAlunoController::class, 'update']);
        Route::delete('observacoes/{observacaoAluno}', [ObservacaoAlunoController::class, 'destroy']);
        Route::middleware('ensure.plano.modulo:avaliacoes')->group(function () {
            Route::get('avaliacoes', [AvaliacaoAlunoController::class, 'index']);
            Route::post('avaliacoes', [AvaliacaoAlunoController::class, 'store']);
            Route::put('avaliacoes/{avaliacaoAluno}', [AvaliacaoAlunoController::class, 'update']);
            Route::delete('avaliacoes/{avaliacaoAluno}', [AvaliacaoAlunoController::class, 'destroy']);
        });
        Route::middleware('ensure.plano.modulo:progressao')->group(function () {
            Route::get('niveis', [SugestaoProgressaoController::class, 'niveis']);
            Route::post('progressoes', [SugestaoProgressaoController::class, 'store']);
            Route::delete('progressoes/{sugestaoProgressao}', [SugestaoProgressaoController::class, 'destroy']);
        });
    });

    Route::middleware(['role:aluno,responsavel', 'ensure.perfil.aluno'])->prefix('aluno/me')->group(function () {
        Route::get('painel', [AlunoPainelController::class, 'show']);
        Route::get('agenda', [AlunoAgendaController::class, 'index']);
        Route::get('frequencia', [AlunoFrequenciaController::class, 'index']);
        Route::get('boletim', [AlunoBoletimController::class, 'index']);
        Route::get('certificados', [AlunoCertificadoController::class, 'index']);
        Route::get('certificados/{certificado}/pdf', [AlunoCertificadoController::class, 'pdf']);
        Route::get('materiais', [AlunoMaterialController::class, 'index']);
        Route::get('matriculas', [AlunoMatriculaController::class, 'index']);
        Route::get('matriculas/{matricula}/historico', [AlunoMatriculaController::class, 'historico']);
        Route::get('justificativas', [AlunoJustificativaController::class, 'index']);
        Route::post('justificativas', [AlunoJustificativaController::class, 'store']);
        Route::get('reposicoes/opcoes', [AlunoReposicaoController::class, 'opcoes']);
        Route::get('reposicoes', [AlunoReposicaoController::class, 'index']);
        Route::post('reposicoes', [AlunoReposicaoController::class, 'store']);
    });

    Route::middleware('role:secretaria,admin')->group(function () {
        Route::apiResource('cursos', CursoController::class);
        Route::apiResource('niveis', NivelController::class);

        Route::patch('turmas/{turma}/status', [TurmaController::class, 'updateStatus']);
        Route::apiResource('turmas', TurmaController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('turma-horarios', TurmaHorarioController::class)
            ->parameters(['turma-horarios' => 'turmaHorario'])
            ->only(['store', 'update', 'destroy']);
        Route::apiResource('matriculas', MatriculaController::class)->only(['store', 'update', 'destroy']);
        Route::post('matriculas/{matricula}/gerar-mensalidades', [MatriculaController::class, 'gerarMensalidades']);
        Route::get('certificados', [CertificadoController::class, 'index']);
        Route::post('matriculas/{matricula}/certificados', [CertificadoController::class, 'store']);
        Route::get('professores/{professor}/disponibilidades', [DisponibilidadeProfessorController::class, 'doProfessor']);
        Route::get('solicitacoes-aulas', [SolicitacaoAulaController::class, 'index']);
        Route::post('solicitacoes-aulas/{solicitacaoAula}/decisao', [SolicitacaoAulaController::class, 'decidir']);
        Route::get('avisos', [AvisoTurmaController::class, 'index']);
        Route::get('avisos/opcoes', [AvisoTurmaController::class, 'opcoes']);
        Route::get('avisos/previa', [AvisoTurmaController::class, 'previa']);
        Route::post('avisos', [AvisoTurmaController::class, 'store']);
        Route::get('avisos/{avisoTurma}', [AvisoTurmaController::class, 'show']);
        Route::middleware('ensure.plano.modulo:progressao')->group(function () {
            Route::get('progressoes', [SugestaoProgressaoController::class, 'index']);
            Route::post('progressoes/{sugestaoProgressao}/decisao', [SugestaoProgressaoController::class, 'decidir']);
        });
    });

    Route::middleware('role:secretaria,admin,professor')->group(function () {
        Route::get('justificativas-faltas', [JustificativaFaltaController::class, 'index']);
        Route::post('justificativas-faltas/{justificativaFalta}/decisao', [JustificativaFaltaController::class, 'decidir']);
        Route::get('solicitacoes-reposicoes', [SolicitacaoReposicaoController::class, 'index']);
        Route::get('solicitacoes-reposicoes/{solicitacaoReposicao}/opcoes', [SolicitacaoReposicaoController::class, 'opcoes']);
        Route::post('solicitacoes-reposicoes/{solicitacaoReposicao}/decisao', [SolicitacaoReposicaoController::class, 'decidir']);
        Route::get('agenda/professores-livres', [DisponibilidadeProfessorController::class, 'professoresLivres']);
        Route::apiResource('aulas-turmas', AulaTurmaController::class)
            ->parameters(['aulas-turmas' => 'aulaTurma']);
        Route::post('aulas-turmas/{aulaTurma}/presencas/sync', [AulaPresencaController::class, 'bulkStore']);
        Route::post('aulas-turmas/{aulaTurma}/concluir', [ConcluirAulaController::class, 'store']);
        Route::delete('aulas-turmas/{aulaTurma}/presencas', [AulaPresencaController::class, 'destroyAll']);
        Route::delete('presencas/{aulaPresenca}', [AulaPresencaController::class, 'destroy']);

        Route::apiResource('materiais-turmas', MaterialTurmaController::class)
            ->parameters(['materiais-turmas' => 'id'])
            ->only(['store', 'update', 'destroy']);
    });

    Route::apiResource('turmas', TurmaController::class)->only(['index', 'show']);
    Route::apiResource('turma-horarios', TurmaHorarioController::class)
        ->parameters(['turma-horarios' => 'turmaHorario'])
        ->only(['index', 'show']);
    Route::apiResource('matriculas', MatriculaController::class)->only(['index', 'show']);
    Route::get('/alunos/{aluno}/matriculas', [MatriculaController::class, 'getByAluno']);
    Route::get('turmas/{turma}/matriculas', [MatriculaController::class, 'getByTurma']);
    Route::get('matriculas/{matricula}/historico', [MatriculaController::class, 'getHistorico']);
    Route::get('matriculas/{matricula}/mensalidades', [MatriculaController::class, 'getMensalidades']);

    Route::get('aulas-turmas/{aulaTurma}/presencas', [AulaPresencaController::class, 'index']);
    Route::apiResource('materiais-turmas', MaterialTurmaController::class)
        ->parameters(['materiais-turmas' => 'id'])
        ->only(['index', 'show']);
    Route::get('turmas/{turma}/materiais', [MaterialTurmaController::class, 'getByTurma']);
});
