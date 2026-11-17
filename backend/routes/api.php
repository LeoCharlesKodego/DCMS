<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentConfigurationController;
use App\Http\Controllers\UserAdministrationController;
use App\Http\Controllers\InstitutionConfigurationController;
use App\Http\Controllers\DocumentRegistryController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\DocumentLifecycleController;
use App\Http\Controllers\WorkflowTemplateConfigurationController;
use App\Http\Controllers\WorkflowDecisionController;
use App\Http\Controllers\DocumentDistributionController;
use App\Http\Controllers\DocumentMigrationController;
use App\Http\Controllers\ExternalReferenceController;
use App\Http\Controllers\OperationalRecordsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Phase4BSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureSystemAdministrator;
use App\Http\Middleware\EnsureRolePermission;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::middleware('web')->group(function () {
    Route::get('/auth/csrf-cookie', [AuthController::class, 'csrfCookie']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:web', 'auth.session', EnsureActiveAccount::class])->group(function () {
        Route::get('/auth/user', [AuthController::class, 'user']);
        Route::get('/dashboard', [DashboardController::class, 'show']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/password', [AuthController::class, 'changePassword']);
        Route::middleware(EnsureSystemAdministrator::class)->group(function () {
            Route::post('/admin/users/{user}/password', [AuthController::class, 'resetUserPassword']);
            Route::get('/admin/access', [UserAdministrationController::class, 'index']);
            Route::get('/admin/institution', [InstitutionConfigurationController::class, 'showInstitution']);
            Route::put('/admin/institution', [InstitutionConfigurationController::class, 'updateInstitution']);
            Route::get('/admin/institution/logo', [InstitutionConfigurationController::class, 'institutionLogo']);
            Route::get('/admin/organizational-units', [InstitutionConfigurationController::class, 'indexUnits']);
            Route::post('/admin/organizational-units', [InstitutionConfigurationController::class, 'storeUnit']);
            Route::patch('/admin/organizational-units/{organizationalUnit}', [InstitutionConfigurationController::class, 'updateUnit']);
            Route::post('/admin/users', [UserAdministrationController::class, 'storeUser']);
            Route::patch('/admin/users/{user}', [UserAdministrationController::class, 'updateUser']);
            Route::post('/admin/users/{user}/role-assignments', [UserAdministrationController::class, 'storeAssignment']);
            Route::patch('/admin/role-assignments/{assignment}', [UserAdministrationController::class, 'endAssignment']);

            Route::post('/configuration/document-types', [DocumentConfigurationController::class, 'storeType']);
            Route::patch('/configuration/document-types/{documentType}', [DocumentConfigurationController::class, 'updateType']);
            Route::post('/configuration/document-categories', [DocumentConfigurationController::class, 'storeCategory']);
            Route::patch('/configuration/document-categories/{documentCategory}', [DocumentConfigurationController::class, 'updateCategory']);
            Route::get('/admin/workflow-templates', [WorkflowTemplateConfigurationController::class, 'index']);
            Route::post('/admin/workflow-templates', [WorkflowTemplateConfigurationController::class, 'store']);
            Route::post('/admin/workflow-templates/{workflowTemplate}/versions', [WorkflowTemplateConfigurationController::class, 'storeVersion']);
            Route::post('/admin/workflow-template-versions/{workflowTemplateVersion}/publish', [WorkflowTemplateConfigurationController::class, 'publish']);
            Route::patch('/admin/workflow-templates/{workflowTemplate}', [WorkflowTemplateConfigurationController::class, 'updateTemplate']);
            Route::put('/admin/settings/same-kmt-verify-release', [Phase4BSettingsController::class, 'updateSameAccount']);
        });

        Route::get('/configuration/document-types', [DocumentConfigurationController::class, 'indexTypes']);
        Route::get('/configuration/phase4b-settings', [Phase4BSettingsController::class, 'show']);
        Route::put('/configuration/phase4b-title-size', [Phase4BSettingsController::class, 'updateTitleSize']);
        Route::get('/configuration/document-categories', [DocumentConfigurationController::class, 'indexCategories']);

        Route::get('/documents', [DocumentRegistryController::class, 'index']);
        Route::get('/master-code-register', [DocumentRegistryController::class, 'masterCodeRegister'])
            ->middleware(EnsureRolePermission::class.':document.register');
        Route::post('/documents', [DocumentRegistryController::class, 'store']);
        Route::post('/documents/{document}/versions', [DocumentVersionController::class, 'store']);
        Route::post('/documents/{document}/versions/{documentVersion}/files', [DocumentVersionController::class, 'replaceFiles']);
        Route::post('/documents/{document}/versions/{documentVersion}/actions', [DocumentLifecycleController::class, 'act']);
        Route::post('/workflow-tasks/{workflowTask}/decisions', [WorkflowDecisionController::class, 'store']);
        Route::get('/distribution/target-options', [DocumentDistributionController::class, 'options']);
        Route::get('/distribution/inbox', [DocumentDistributionController::class, 'inbox']);
        Route::post('/documents/{document}/versions/{documentVersion}/distributions', [DocumentDistributionController::class, 'store']);
        Route::post('/distribution-recipients/{distributionRecipient}/acknowledge', [DocumentDistributionController::class, 'acknowledge']);
        Route::post('/distribution-recipients/{distributionRecipient}/exempt', [DocumentDistributionController::class, 'exempt']);
        Route::get('/document-migrations', [DocumentMigrationController::class, 'index']);
        Route::get('/document-migrations/options', [DocumentMigrationController::class, 'options']);
        Route::post('/document-migrations', [DocumentMigrationController::class, 'store']);
        Route::get('/document-migrations/{documentMigrationBatch}', [DocumentMigrationController::class, 'show']);
        Route::post('/document-migrations/{documentMigrationBatch}/files', [DocumentMigrationController::class, 'upload']);
        Route::put('/document-migration-items/{documentMigrationItem}/mapping', [DocumentMigrationController::class, 'mapping']);
        Route::post('/document-migration-items/{documentMigrationItem}/verify', [DocumentMigrationController::class, 'verify']);
        Route::post('/document-migration-items/{documentMigrationItem}/register', [DocumentMigrationController::class, 'register']);
        Route::get('/document-migration-items/{documentMigrationItem}/source', [DocumentMigrationController::class, 'sourceFile']);
        Route::get('/document-migrations/{documentMigrationBatch}/report', [DocumentMigrationController::class, 'report']);
        Route::get('/external-references', [ExternalReferenceController::class, 'index']);
        Route::post('/external-references', [ExternalReferenceController::class, 'store']);
        Route::patch('/external-references/{externalReference}', [ExternalReferenceController::class, 'update']);
        Route::post('/documents/{document}/external-references/{externalReference}', [ExternalReferenceController::class, 'link']);
        Route::get('/operational-records', [OperationalRecordsController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{userNotification}/read', [NotificationController::class, 'read']);
        Route::get('/reports/summary', [ReportsController::class, 'summary']);
        Route::get('/reports/audit', [ReportsController::class, 'audit']);
        Route::post('/retention-schedules', [OperationalRecordsController::class, 'storeSchedule']);
        Route::post('/operational-records', [OperationalRecordsController::class, 'storeRecord']);
        Route::post('/operational-records/{operationalRecord}/holds', [OperationalRecordsController::class, 'hold']);
        Route::post('/operational-records/{operationalRecord}/holds/{hold}/release', [OperationalRecordsController::class, 'releaseHold']);
        Route::post('/operational-records/{operationalRecord}/disposition-requests', [OperationalRecordsController::class, 'requestDisposition']);
        Route::post('/disposition-requests/{dispositionRequest}/decision', [OperationalRecordsController::class, 'decideDisposition']);
        Route::post('/disposition-requests/{dispositionRequest}/execute', [OperationalRecordsController::class, 'executeDisposition']);
        Route::get('/document-files/{documentFile}/download', [DocumentVersionController::class, 'download']);
        Route::get('/documents/code-components', [DocumentRegistryController::class, 'codeComponents'])
            ->middleware(EnsureRolePermission::class.':document.code.assign');
        Route::post('/documents/{document}/assign-code', [DocumentRegistryController::class, 'assignCode'])
            ->middleware(EnsureRolePermission::class.':document.code.assign');
    });
});
