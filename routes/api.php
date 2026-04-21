<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizacaoController;
use App\Http\Controllers\Api\V1\TenantAbrigoController;
use App\Http\Controllers\Api\V1\TenantUsuarioController;
use App\Http\Controllers\Api\V1\TenantUsuarioPermissaoController;
use App\Http\Controllers\Api\V1\UsuarioController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login',   [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Endpoint público — verifica se organização existe e está ativa (sem autenticação)
    Route::get('organizacoes/{slug}/check', [OrganizacaoController::class, 'check']);

    Route::middleware(['auth:api', 'user.active'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('me',               [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });

        // ── Organizações ───────────────────────────────────────────────────────
        Route::apiResource('organizacoes', OrganizacaoController::class)
            ->parameters(['organizacoes' => 'organizacao']);

        // Lookup por slug (para a página de detalhe do frontend)
        Route::get('organizacoes/{slug}/info',          [OrganizacaoController::class, 'showBySlug']);
        Route::post('organizacoes/{slug}/reprovision',  [OrganizacaoController::class, 'reprovision']);

        Route::get('organizacoes/{organizacao_slug}/permissoes', [TenantUsuarioPermissaoController::class, 'indexCatalog']);
        Route::get('organizacoes/{organizacao_slug}/perfis-permissoes-nivel/{nivel}', [TenantUsuarioPermissaoController::class, 'slugsPorNivel'])
            ->whereNumber('nivel');

        // ── Usuários do tenant gerenciados pela plataforma ─────────────────────
        Route::prefix('organizacoes/{organizacao_slug}/usuarios')->group(function () {
            Route::get('/{usuario_id}/permissoes-resumo', [TenantUsuarioPermissaoController::class, 'permissoesResumo']);
            Route::put('/{usuario_id}/gestao-permissoes', [TenantUsuarioPermissaoController::class, 'syncGestao']);
            Route::get('/{usuario_id}/permissoes-overrides', [TenantUsuarioPermissaoController::class, 'showOverrides']);
            Route::put('/{usuario_id}/permissoes-overrides', [TenantUsuarioPermissaoController::class, 'syncOverrides']);
            Route::get('/',                             [TenantUsuarioController::class, 'index']);
            Route::post('/',                            [TenantUsuarioController::class, 'store']);
            Route::put('/{usuario_id}',                 [TenantUsuarioController::class, 'update']);
            Route::delete('/{usuario_id}',              [TenantUsuarioController::class, 'destroy']);
            Route::post('/{usuario_id}/reset-senha',    [TenantUsuarioController::class, 'resetSenha']);
            Route::patch('/{usuario_id}/toggle-ativo',  [TenantUsuarioController::class, 'toggleAtivo']);
        });

        // ── Abrigos do tenant gerenciados pela plataforma ──────────────────────
        Route::prefix('organizacoes/{organizacao_slug}/abrigos')->group(function () {
            Route::get('/',                             [TenantAbrigoController::class, 'index']);
            Route::post('/',                            [TenantAbrigoController::class, 'store']);
            Route::put('/{abrigo_id}',                  [TenantAbrigoController::class, 'update']);
            Route::delete('/{abrigo_id}',               [TenantAbrigoController::class, 'destroy']);
            Route::patch('/{abrigo_id}/toggle-status',  [TenantAbrigoController::class, 'toggleStatus']);
        });

        // ── Usuários da plataforma (platform_admin) ────────────────────────────
        Route::apiResource('usuarios', UsuarioController::class)
            ->parameters(['usuarios' => 'usuario']);
        Route::post('usuarios/{usuario}/reset-senha', [UsuarioController::class, 'resetSenha']);
    });
});
