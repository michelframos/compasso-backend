<?php

use App\Modules\Core\Http\Controllers\AssinaturaController;
use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\ConfiguracaoEmpresaController;
use App\Modules\Core\Http\Controllers\InstituicaoController;
use App\Modules\Core\Http\Controllers\PlatformConfigController;
use App\Modules\Core\Http\Controllers\PlatformConfiguracoesController;
use App\Modules\Core\Http\Controllers\PlatformImpersonationController;
use App\Modules\Core\Http\Controllers\PlatformInstituicaoController;
use App\Modules\Core\Http\Controllers\PlatformDepoimentoController;
use App\Modules\Core\Http\Controllers\PlatformPlanoAssinaturaController;
use App\Modules\Core\Http\Controllers\PlatformSiteModuloController;
use App\Modules\Core\Http\Controllers\PlatformSolicitacaoAssinaturaController;
use App\Modules\Core\Http\Controllers\LocalidadeController;
use App\Modules\Core\Http\Controllers\MeController;
use App\Modules\Core\Http\Controllers\ModuloInstituicaoController;
use App\Modules\Core\Http\Controllers\ComunicacaoProfessorController;
use App\Modules\Core\Http\Controllers\PermissoesProfessorController;
use App\Modules\Core\Http\Controllers\PasswordResetController;
use App\Modules\Core\Http\Controllers\PublicController;
use App\Modules\Core\Http\Controllers\RegistroEscolaController;
use App\Modules\Core\Http\Controllers\UserController;
use App\Modules\Core\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Core (Identity + Configurações).
| Prefixadas com /api pelo CoreServiceProvider.
*/

Route::prefix('public')->group(function () {
    Route::get('planos', [PublicController::class, 'planos']);
    Route::get('depoimentos', [PublicController::class, 'depoimentos']);
    Route::get('modulos', [PublicController::class, 'modulos']);
    Route::get('config', [PublicController::class, 'config']);
    Route::post('signup', [PublicController::class, 'signup'])
        ->middleware('throttle:5,1');
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/registrar-escola', [RegistroEscolaController::class, 'store'])
    ->middleware('throttle:5,1');
Route::post('/ativacao/reenviar', [RegistroEscolaController::class, 'reenviarCodigo'])
    ->middleware('throttle:5,1');
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/platform/login', [AuthController::class, 'platformLogin']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return new UserResource($request->user());
    });
    Route::put('/me/senha', [MeController::class, 'updateSenha']);

    Route::middleware('ensure.senha.atualizada')->prefix('me/perfil')->group(function () {
        Route::get('/', [MeController::class, 'showPerfil']);
        Route::put('/', [MeController::class, 'updatePerfil']);
        Route::post('foto', [MeController::class, 'updateFoto']);
        Route::delete('foto', [MeController::class, 'destroyFoto']);
    });

    Route::get('/instituicoes/mine', [InstituicaoController::class, 'mine']);
    Route::post('/instituicoes/switch', [InstituicaoController::class, 'switch']);

    Route::middleware('ensure.instituicao.membership')->group(function () {
        Route::middleware('role:secretaria,admin')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::get('configuracao-empresa', [ConfiguracaoEmpresaController::class, 'show']);
            Route::put('configuracao-empresa', [ConfiguracaoEmpresaController::class, 'update']);
        });

        Route::middleware('role:admin')->prefix('assinatura')->group(function () {
            Route::get('planos', [AssinaturaController::class, 'planos']);
            Route::get('solicitacao', [AssinaturaController::class, 'solicitacaoAtual']);
            Route::post('solicitar', [AssinaturaController::class, 'solicitar']);
        });

        Route::middleware('role:admin')->prefix('instituicao/modulos')->group(function () {
            Route::get('/', [ModuloInstituicaoController::class, 'index']);
            Route::put('{modulo}', [ModuloInstituicaoController::class, 'update']);
        });

        Route::middleware('role:admin')->prefix('instituicao/comunicacao-professor')->group(function () {
            Route::get('/', [ComunicacaoProfessorController::class, 'show']);
            Route::put('/', [ComunicacaoProfessorController::class, 'update']);
        });

        Route::middleware('role:admin')->prefix('instituicao/permissoes-professor')->group(function () {
            Route::get('/', [PermissoesProfessorController::class, 'show']);
            Route::put('/', [PermissoesProfessorController::class, 'update']);
        });
    });

    Route::get('/estados', [LocalidadeController::class, 'estados']);
    Route::get('/estados/{estadoId}/cidades', [LocalidadeController::class, 'cidades']);
    Route::get('/cep/{cep}', [LocalidadeController::class, 'cep'])
        ->where('cep', '[0-9]{5}-?[0-9]{3}')
        ->middleware('throttle:30,1');

    Route::post('platform/stop-impersonation', [PlatformImpersonationController::class, 'stop']);

    Route::middleware('super_admin')->prefix('platform')->group(function () {
        Route::get('config', [PlatformConfigController::class, 'show']);
        Route::get('configuracoes', [PlatformConfiguracoesController::class, 'show']);
        Route::put('configuracoes', [PlatformConfiguracoesController::class, 'update']);
        Route::get('instituicoes/{instituicao}/usuarios', [PlatformInstituicaoController::class, 'usuarios']);
        Route::post('instituicoes/{instituicao}/impersonate', [PlatformInstituicaoController::class, 'impersonate']);
        Route::apiResource('instituicoes', PlatformInstituicaoController::class);
        Route::apiResource('planos', PlatformPlanoAssinaturaController::class);
        Route::post('depoimentos/{depoimento}/aprovar', [PlatformDepoimentoController::class, 'aprovar']);
        Route::post('depoimentos/{depoimento}/ocultar', [PlatformDepoimentoController::class, 'ocultar']);
        Route::apiResource('depoimentos', PlatformDepoimentoController::class);
        Route::post('modulos/{modulo}/aprovar', [PlatformSiteModuloController::class, 'aprovar']);
        Route::post('modulos/{modulo}/ocultar', [PlatformSiteModuloController::class, 'ocultar']);
        Route::apiResource('modulos', PlatformSiteModuloController::class);

        Route::get('solicitacoes', [PlatformSolicitacaoAssinaturaController::class, 'index']);
        Route::post('solicitacoes/{solicitacao}/aprovar', [PlatformSolicitacaoAssinaturaController::class, 'aprovar']);
        Route::post('solicitacoes/{solicitacao}/rejeitar', [PlatformSolicitacaoAssinaturaController::class, 'rejeitar']);
    });
});
