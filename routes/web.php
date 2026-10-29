<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Bioimpedance\AdminDashboardController;
use App\Http\Controllers\Bioimpedance\AiAnalysisController;
use App\Http\Controllers\Bioimpedance\AssessmentController;
use App\Http\Controllers\Bioimpedance\AssessmentImportController;
use App\Http\Controllers\Bioimpedance\AssessmentReportController;
use App\Http\Controllers\Bioimpedance\AssessmentShareController;
use App\Http\Controllers\Bioimpedance\ClientController;
use App\Http\Controllers\Bioimpedance\ClinicSettingsController;
use App\Http\Controllers\Bioimpedance\KnowledgeDocumentController;
use App\Http\Controllers\Bioimpedance\UserController;
use App\Http\Controllers\Bioimpedance\WorkspaceController;
use App\Http\Controllers\Screening\PublicScreeningController;
use App\Http\Controllers\Screening\PublicScreeningResultPdfController;
use App\Http\Controllers\Screening\ScreeningInvitationController;
use App\Http\Controllers\Screening\ScreeningProfessionalReviewController;
use App\Http\Controllers\Screening\ScreeningQuestionnaireApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/r/{token}', [AssessmentShareController::class, 'publicReport'])->name('bioimpedance.public-report');
Route::get('/triagem/{token}', [PublicScreeningController::class, 'show'])
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('screening.public.show');
Route::post('/triagem/{token}/identidade', [PublicScreeningController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('screening.public.verify');
Route::post('/triagem/{token}/finalizar', [PublicScreeningController::class, 'complete'])
    ->middleware('throttle:6,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('screening.public.complete');
Route::get('/triagem/{token}/resultado.pdf', PublicScreeningResultPdfController::class)
    ->middleware('throttle:10,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('screening.public.result-pdf');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/app', 'operational-app')->name('operational-app');
    Route::get('/bioimpedance', [WorkspaceController::class, 'index'])->name('bioimpedance.index');
    Route::get('/bioimpedance/operational', [WorkspaceController::class, 'operational'])->name('bioimpedance.operational.index');
    Route::get('/bioimpedance/admin-dashboard', AdminDashboardController::class)->name('bioimpedance.admin-dashboard');
    Route::get('/bioimpedance/knowledge-documents', [KnowledgeDocumentController::class, 'index'])->name('bioimpedance.knowledge-documents.index');
    Route::post('/bioimpedance/knowledge-documents', [KnowledgeDocumentController::class, 'store'])->name('bioimpedance.knowledge-documents.store');
    Route::patch('/bioimpedance/knowledge-documents/{document}/transition', [KnowledgeDocumentController::class, 'transition'])->name('bioimpedance.knowledge-documents.transition');
    Route::patch('/bioimpedance/clinic', [ClinicSettingsController::class, 'update'])->name('bioimpedance.clinic.update');
    Route::post('/bioimpedance/users', [UserController::class, 'store'])->name('bioimpedance.users.store');
    Route::put('/bioimpedance/users/{user}', [UserController::class, 'update'])->name('bioimpedance.users.update');
    Route::patch('/bioimpedance/users/{user}/inactivate', [UserController::class, 'inactivate'])->name('bioimpedance.users.inactivate');
    Route::post('/bioimpedance/clients', [ClientController::class, 'store'])->name('bioimpedance.clients.store');
    Route::put('/bioimpedance/clients/{client}', [ClientController::class, 'update'])->name('bioimpedance.clients.update');
    Route::patch('/bioimpedance/clients/{client}/inactivate', [ClientController::class, 'inactivate'])->name('bioimpedance.clients.inactivate');
    Route::get('/bioimpedance/clients/{client}/privacy-export', [ClientController::class, 'exportPrivacyData'])->name('bioimpedance.clients.privacy-export');
    Route::patch('/bioimpedance/clients/{client}/anonymize', [ClientController::class, 'anonymize'])->name('bioimpedance.clients.anonymize');
    Route::get('/screening/clients/{client}/invitations', [ScreeningInvitationController::class, 'index'])->name('screening.invitations.index');
    Route::post('/screening/invitations', [ScreeningInvitationController::class, 'store'])->name('screening.invitations.store');
    Route::post('/screening/invitations/{session}/resend', [ScreeningInvitationController::class, 'resend'])->name('screening.invitations.resend');
    Route::patch('/screening/invitations/{session}/revoke', [ScreeningInvitationController::class, 'revoke'])->name('screening.invitations.revoke');
    Route::get('/screening/sessions/{session}', [ScreeningProfessionalReviewController::class, 'show'])->name('screening.sessions.show');
    Route::patch('/screening/sessions/{session}/professional-review', [ScreeningProfessionalReviewController::class, 'update'])->name('screening.sessions.professional-review.update');
    Route::patch('/screening/questionnaires/{questionnaire}/approve', ScreeningQuestionnaireApprovalController::class)->name('screening.questionnaires.approve');
    Route::post('/bioimpedance/assessments', [AssessmentController::class, 'store'])->name('bioimpedance.assessments.store');
    Route::post('/bioimpedance/relaxmedic/process-image', [AssessmentImportController::class, 'processRelaxmedicImage'])
        ->middleware('throttle:5,1')
        ->name('bioimpedance.relaxmedic.process-image');
    Route::post('/bioimpedance/bodyscan-pro/process-image', [AssessmentImportController::class, 'processBodyscanProImage'])
        ->middleware('throttle:5,1')
        ->name('bioimpedance.bodyscan-pro.process-image');
    Route::get('/bioimpedance/assessments/{assessment}/pdf', [AssessmentReportController::class, 'download'])->name('bioimpedance.assessments.pdf');
    Route::get('/bioimpedance/assessments/{assessment}/observation-suggestion', [AiAnalysisController::class, 'suggestObservation'])
        ->middleware('throttle:5,1')
        ->name('bioimpedance.assessments.observation-suggestion');
    Route::patch('/bioimpedance/assessments/{assessment}/observation', [AiAnalysisController::class, 'approveObservation'])->name('bioimpedance.assessments.observation.approve');
    Route::get('/bioimpedance/assessments/{assessment}/care-guidance-suggestion', [AiAnalysisController::class, 'suggestCareGuidance'])
        ->middleware('throttle:5,1')
        ->name('bioimpedance.assessments.care-guidance.suggestion');
    Route::patch('/bioimpedance/assessments/{assessment}/care-guidance', [AiAnalysisController::class, 'approveCareGuidance'])->name('bioimpedance.assessments.care-guidance.approve');
    Route::post('/bioimpedance/assessments/{assessment}/shares', [AssessmentShareController::class, 'store'])->name('bioimpedance.assessments.shares.store');
    Route::patch('/bioimpedance/report-shares/{share}/revoke', [AssessmentShareController::class, 'revoke'])->name('bioimpedance.report-shares.revoke');
    Route::put('/bioimpedance/assessments/{assessment}', [AssessmentController::class, 'update'])->name('bioimpedance.assessments.update');
    Route::patch('/bioimpedance/assessments/{assessment}/cancel', [AssessmentController::class, 'cancel'])->name('bioimpedance.assessments.cancel');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
