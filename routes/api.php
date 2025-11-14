<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\TransactionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/activate', [AuthController::class, 'activate']);

// Routes marchands - Commentées pour l'instant
// Route::get('/marchands', [App\Http\Controllers\MarchandController::class, 'index']);
// Route::get('/marchands/{id}', [App\Http\Controllers\MarchandController::class, 'show']);

// Routes protégées
Route::middleware('auth:api')->group(function () {
    // Route::post('/logout', [AuthController::class, 'logout']); // Commenté temporairement

    // Routes comptes - Architecture multi-comptes
    Route::get('/comptes', [CompteController::class, 'index']); // Dashboard complet (utilisateur + comptes + transactions)
    Route::get('/soldes', [CompteController::class, 'soldes']); // Soldes rapides
    Route::get('/transactions', [CompteController::class, 'transactions']); // Transactions filtrées (compte_id requis)

    // Routes transactions - Unifiées avec compte_id obligatoire
    Route::post('/transactions', [TransactionController::class, 'store']); // Nouvelle transaction (compte_id requis)
    Route::get('/transactions/{reference}', [TransactionController::class, 'show']); // Détail transaction (compte_id requis)
    Route::patch('/transactions/{reference}/cancel', [TransactionController::class, 'cancel']); // Annuler transaction (compte_id requis)
});
