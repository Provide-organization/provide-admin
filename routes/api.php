<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizacaoController;
use App\Http\Controllers\Api\V1\UsuarioController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:api')->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',     [AuthController::class, 'me']);
        });

        Route::apiResource('organizacoes', OrganizacaoController::class)
            ->parameters(['organizacoes' => 'organizacao']);

        Route::apiResource('usuarios', UsuarioController::class)
            ->parameters(['usuarios' => 'usuario']);
        Route::post('usuarios/{usuario}/reset-senha', [UsuarioController::class, 'resetSenha']);
    });
});
