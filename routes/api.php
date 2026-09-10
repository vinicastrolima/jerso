<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\PlayerDirectoryController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TablePlayerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AlaPoker API Routes
|--------------------------------------------------------------------------
*/

// Mesas
Route::prefix('tables')->group(function () {
    Route::get('/', [TableController::class, 'index']);
    Route::post('/', [TableController::class, 'store']);
    Route::get('/{code}', [TableController::class, 'show']);
    Route::post('/{code}/verify-pin', [TableController::class, 'verifyPin']);
    Route::post('/{code}/close', [TableController::class, 'close']);
    Route::get('/{code}/share-summary', [TableController::class, 'shareSummary']);

    // Gerenciamento de participantes na mesa
    Route::post('/{code}/players', [TablePlayerController::class, 'addPlayer']);
    Route::post('/{code}/players/{tablePlayerId}/buyins', [TablePlayerController::class, 'addBuyin']);
    Route::put('/{code}/players/{tablePlayerId}/buyins/{buyinId}', [TablePlayerController::class, 'updateBuyin']);
    Route::delete('/{code}/players/{tablePlayerId}/buyins/{buyinId}', [TablePlayerController::class, 'deleteBuyin']);
    Route::post('/{code}/players/{tablePlayerId}/cashout', [TablePlayerController::class, 'cashout']);
    Route::post('/{code}/players/{tablePlayerId}/reopen', [TablePlayerController::class, 'reopenPlayer']);
});

// Diretório de Jogadores Frequentes
Route::prefix('players')->group(function () {
    Route::get('/', [PlayerDirectoryController::class, 'index']);
    Route::post('/', [PlayerDirectoryController::class, 'store']);
    Route::put('/{id}', [PlayerDirectoryController::class, 'update']);
    Route::delete('/{id}', [PlayerDirectoryController::class, 'destroy']);
});

// Ranking Geral & Histórico
Route::get('/ranking', [LeaderboardController::class, 'hallOfFame']);
Route::get('/history', [LeaderboardController::class, 'history']);
Route::get('/history/{id}', [LeaderboardController::class, 'historyDetail']);

// Painel Admin & Visão Geral
Route::prefix('admin')->group(function () {
    Route::get('/overview', [AdminController::class, 'overview']);
    Route::post('/verify-pin', [AdminController::class, 'verifyPin']);
    Route::post('/reset-data', [AdminController::class, 'resetData']);
});
