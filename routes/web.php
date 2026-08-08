<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Bioimpedance\BioimpedanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/bioimpedance', [BioimpedanceController::class, 'index'])->name('bioimpedance.index');
    Route::post('/bioimpedance/clients', [BioimpedanceController::class, 'storeClient'])->name('bioimpedance.clients.store');
    Route::put('/bioimpedance/clients/{client}', [BioimpedanceController::class, 'updateClient'])->name('bioimpedance.clients.update');
    Route::patch('/bioimpedance/clients/{client}/inactivate', [BioimpedanceController::class, 'inactivateClient'])->name('bioimpedance.clients.inactivate');
    Route::post('/bioimpedance/assessments', [BioimpedanceController::class, 'storeAssessment'])->name('bioimpedance.assessments.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
