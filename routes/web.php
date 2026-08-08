<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Bioimpedance\BioimpedanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/r/{token}', [BioimpedanceController::class, 'publicReport'])->name('bioimpedance.public-report');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/bioimpedance', [BioimpedanceController::class, 'index'])->name('bioimpedance.index');
    Route::patch('/bioimpedance/clinic', [BioimpedanceController::class, 'updateClinicSettings'])->name('bioimpedance.clinic.update');
    Route::post('/bioimpedance/users', [BioimpedanceController::class, 'storeUser'])->name('bioimpedance.users.store');
    Route::put('/bioimpedance/users/{user}', [BioimpedanceController::class, 'updateUser'])->name('bioimpedance.users.update');
    Route::patch('/bioimpedance/users/{user}/inactivate', [BioimpedanceController::class, 'inactivateUser'])->name('bioimpedance.users.inactivate');
    Route::post('/bioimpedance/clients', [BioimpedanceController::class, 'storeClient'])->name('bioimpedance.clients.store');
    Route::put('/bioimpedance/clients/{client}', [BioimpedanceController::class, 'updateClient'])->name('bioimpedance.clients.update');
    Route::patch('/bioimpedance/clients/{client}/inactivate', [BioimpedanceController::class, 'inactivateClient'])->name('bioimpedance.clients.inactivate');
    Route::get('/bioimpedance/clients/{client}/privacy-export', [BioimpedanceController::class, 'exportClientPrivacyData'])->name('bioimpedance.clients.privacy-export');
    Route::patch('/bioimpedance/clients/{client}/anonymize', [BioimpedanceController::class, 'anonymizeClient'])->name('bioimpedance.clients.anonymize');
    Route::post('/bioimpedance/assessments', [BioimpedanceController::class, 'storeAssessment'])->name('bioimpedance.assessments.store');
    Route::get('/bioimpedance/assessments/{assessment}/pdf', [BioimpedanceController::class, 'downloadAssessmentPdf'])->name('bioimpedance.assessments.pdf');
    Route::post('/bioimpedance/assessments/{assessment}/shares', [BioimpedanceController::class, 'createAssessmentShare'])->name('bioimpedance.assessments.shares.store');
    Route::patch('/bioimpedance/report-shares/{share}/revoke', [BioimpedanceController::class, 'revokeAssessmentShare'])->name('bioimpedance.report-shares.revoke');
    Route::put('/bioimpedance/assessments/{assessment}', [BioimpedanceController::class, 'updateAssessment'])->name('bioimpedance.assessments.update');
    Route::patch('/bioimpedance/assessments/{assessment}/cancel', [BioimpedanceController::class, 'cancelAssessment'])->name('bioimpedance.assessments.cancel');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
