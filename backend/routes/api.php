<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\DunningController;
use App\Http\Controllers\Api\V1\FinancialController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\OwnerStatementController;
use App\Http\Controllers\Api\V1\UtilityController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\MaintenanceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InspectionController;
use App\Http\Controllers\Api\V1\LeaseController;
use App\Http\Controllers\Api\V1\ScreeningController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| APRMS API v1
|--------------------------------------------------------------------------
| All application APIs are versioned under /api/v1.
| Consistent JSON responses; validation errors via FormRequest (422);
| auth errors 401; authorization errors 403.
*/

Route::prefix('v1')->group(function () {
    // Public
    Route::get('health', HealthController::class);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated (JWT)
    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Users & roles
        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');

        // Settings (agency-scoped)
        Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.view');
        Route::put('settings', [SettingController::class, 'update'])->middleware('permission:settings.manage');

        // Audit trail (read-only by design — no write routes)
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

        // Dashboard (real query-backed stats)
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->middleware('permission:dashboard.view');

        // P2 — Property domain
        Route::get('properties', [PropertyController::class, 'index'])->middleware('permission:properties.view');
        Route::post('properties', [PropertyController::class, 'store'])->middleware('permission:properties.manage');
        Route::get('properties/{property}', [PropertyController::class, 'show'])->middleware('permission:properties.view');
        Route::put('properties/{property}', [PropertyController::class, 'update'])->middleware('permission:properties.manage');
        Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->middleware('permission:properties.manage');
        Route::post('properties/{property}/restore', [PropertyController::class, 'restore'])->middleware('permission:properties.manage');

        Route::get('buildings', [BuildingController::class, 'index'])->middleware('permission:buildings.view');
        Route::post('buildings', [BuildingController::class, 'store'])->middleware('permission:buildings.manage');
        Route::get('buildings/{building}', [BuildingController::class, 'show'])->middleware('permission:buildings.view');
        Route::put('buildings/{building}', [BuildingController::class, 'update'])->middleware('permission:buildings.manage');
        Route::delete('buildings/{building}', [BuildingController::class, 'destroy'])->middleware('permission:buildings.manage');
        Route::post('buildings/{building}/restore', [BuildingController::class, 'restore'])->middleware('permission:buildings.manage');

        Route::get('units', [UnitController::class, 'index'])->middleware('permission:units.view');
        Route::post('units', [UnitController::class, 'store'])->middleware('permission:units.manage');
        Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.view');
        Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.manage');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.manage');
        Route::post('units/{unit}/restore', [UnitController::class, 'restore'])->middleware('permission:units.manage');

        Route::get('documents', [DocumentController::class, 'index'])->middleware('permission:documents.view');
        Route::post('documents', [DocumentController::class, 'store'])->middleware('permission:documents.manage');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->middleware('permission:documents.view');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.manage');

        // P3 — Leasing domain
        Route::get('tenants', [TenantController::class, 'index'])->middleware('permission:tenants.view');
        Route::post('tenants', [TenantController::class, 'store'])->middleware('permission:tenants.manage');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->middleware('permission:tenants.view');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.manage');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->middleware('permission:tenants.manage');

        Route::get('applications', [ApplicationController::class, 'index'])->middleware('permission:applications.view');
        Route::post('applications', [ApplicationController::class, 'store'])->middleware('permission:applications.manage');
        Route::get('applications/{application}', [ApplicationController::class, 'show'])->middleware('permission:applications.view');
        Route::post('applications/{application}/transition', [ApplicationController::class, 'transition'])->middleware('permission:applications.manage');
        Route::post('applications/{application}/screening/start', [ScreeningController::class, 'start'])->middleware('permission:screening.manage');
        Route::post('applications/{application}/screening/decide', [ScreeningController::class, 'decide'])->middleware('permission:screening.manage');

        Route::get('leases', [LeaseController::class, 'index'])->middleware('permission:leases.view');
        Route::post('leases', [LeaseController::class, 'store'])->middleware('permission:leases.manage');
        Route::get('leases/{lease}', [LeaseController::class, 'show'])->middleware('permission:leases.view');
        Route::put('leases/{lease}', [LeaseController::class, 'update'])->middleware('permission:leases.manage');
        Route::post('leases/{lease}/activate', [LeaseController::class, 'activate'])->middleware('permission:leases.manage');
        Route::post('leases/{lease}/renew', [LeaseController::class, 'renew'])->middleware('permission:leases.manage');
        Route::post('leases/{lease}/terminate', [LeaseController::class, 'terminate'])->middleware('permission:leases.manage');

        Route::get('inspections', [InspectionController::class, 'index'])->middleware('permission:inspections.view');
        Route::post('inspections', [InspectionController::class, 'store'])->middleware('permission:inspections.manage');
        Route::get('inspections/{inspection}', [InspectionController::class, 'show'])->middleware('permission:inspections.view');
        Route::put('inspections/{inspection}', [InspectionController::class, 'update'])->middleware('permission:inspections.manage');
        Route::post('inspections/{inspection}/review', [InspectionController::class, 'review'])->middleware('permission:inspections.manage');

        // P4 — Billing / money-in domain
        Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices.view');
        Route::post('invoices', [InvoiceController::class, 'store'])->middleware('permission:invoices.generate');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.view');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:invoices.generate');
        Route::post('rent-cycle/generate', [InvoiceController::class, 'generateCycle'])->middleware('permission:invoices.generate');
        Route::post('late-fees/accrue', [InvoiceController::class, 'accrueLateFees'])->middleware('permission:billing.adjust');
        Route::post('late-fees/{lateFee}/waive', [InvoiceController::class, 'waiveLateFee'])->middleware('permission:billing.adjust');

        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::post('payments/preview', [PaymentController::class, 'preview'])->middleware('permission:payments.record');
        Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.record');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments.view');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->middleware('permission:payments.reverse');

        Route::get('tenants/{tenant}/ledger', [LedgerController::class, 'show'])->middleware('permission:ledger.view');
        Route::get('tenants/{tenant}/ledger/balance', [LedgerController::class, 'balance'])->middleware('permission:ledger.view');

        Route::get('dunning', [DunningController::class, 'index'])->middleware('permission:dunning.view');
        Route::post('dunning/process', [DunningController::class, 'process'])->middleware('permission:dunning.manage');
        Route::post('dunning/{reminder}/sent', [DunningController::class, 'markSent'])->middleware('permission:dunning.manage');

        Route::get('payments/{payment}/receipt', [ReceiptController::class, 'show'])->middleware('permission:receipts.view');
        Route::get('payments/{payment}/receipt/pdf', [ReceiptController::class, 'pdf'])->middleware('permission:receipts.view');

        Route::get('financial/dashboard', [FinancialController::class, 'dashboard'])->middleware('permission:billing.view');
        Route::get('financial/arrears', [FinancialController::class, 'arrears'])->middleware('permission:billing.view');
        Route::get('financial/periods', [FinancialController::class, 'periods'])->middleware('permission:billing.view');
        Route::post('financial/periods/lock', [FinancialController::class, 'lockPeriod'])->middleware('permission:periods.manage');
        Route::post('financial/periods/unlock', [FinancialController::class, 'unlockPeriod'])->middleware('permission:periods.manage');

        // P5 — Deposits
        Route::get('deposits', [DepositController::class, 'index'])->middleware('permission:deposits.view');
        Route::post('deposits', [DepositController::class, 'store'])->middleware('permission:deposits.manage');
        Route::get('deposits/{deposit}', [DepositController::class, 'show'])->middleware('permission:deposits.view');
        Route::post('deposits/{deposit}/receive', [DepositController::class, 'receive'])->middleware('permission:deposits.manage');
        Route::post('deposits/{deposit}/adjust', [DepositController::class, 'adjust'])->middleware('permission:deposits.manage');
        Route::post('deposits/{deposit}/deductions', [DepositController::class, 'proposeDeduction'])->middleware('permission:deposits.settle');
        Route::post('deposits/deductions/{deduction}/review', [DepositController::class, 'reviewDeduction'])->middleware('permission:deposits.settle');
        Route::post('deposits/{deposit}/settlement/draft', [DepositController::class, 'draftSettlement'])->middleware('permission:deposits.settle');
        Route::post('deposits/{deposit}/settlement/preview', [DepositController::class, 'previewSettlement'])->middleware('permission:deposits.settle');
        Route::post('deposits/{deposit}/settlement/finalize', [DepositController::class, 'finalizeSettlement'])->middleware('permission:deposits.settle');
        Route::post('deposits/{deposit}/settlement/reverse', [DepositController::class, 'reverseSettlement'])->middleware('permission:deposits.settle');

        // P5 — Maintenance
        Route::get('maintenance/tickets', [MaintenanceController::class, 'index'])->middleware('permission:maintenance.view');
        Route::post('maintenance/tickets', [MaintenanceController::class, 'store'])->middleware('permission:maintenance.report');
        Route::get('maintenance/tickets/{ticket}', [MaintenanceController::class, 'show'])->middleware('permission:maintenance.view');
        Route::post('maintenance/tickets/{ticket}/transition', [MaintenanceController::class, 'transition'])->middleware('permission:maintenance.triage');
        Route::post('maintenance/tickets/{ticket}/assign', [MaintenanceController::class, 'assign'])->middleware('permission:maintenance.triage');
        Route::post('maintenance/tickets/{ticket}/quotes', [MaintenanceController::class, 'createQuote'])->middleware('permission:maintenance.work');
        Route::post('maintenance/quotes/{quote}/decide', [MaintenanceController::class, 'decideQuote'])->middleware('permission:maintenance.approve');
        Route::post('maintenance/tickets/{ticket}/work-logs', [MaintenanceController::class, 'logWork'])->middleware('permission:maintenance.work');
        Route::post('maintenance/tickets/{ticket}/verify', [MaintenanceController::class, 'verify'])->middleware('permission:maintenance.approve');
        Route::get('maintenance/vendors', [MaintenanceController::class, 'vendors'])->middleware('permission:vendors.view');
        Route::post('maintenance/vendors', [MaintenanceController::class, 'createVendor'])->middleware('permission:vendors.manage');
        Route::get('maintenance/dashboard', [MaintenanceController::class, 'dashboard'])->middleware('permission:maintenance.view');

        // P6 — Utilities
        Route::get('utility/meters', [UtilityController::class, 'indexMeters'])->middleware('permission:utilities.view');
        Route::post('utility/meters', [UtilityController::class, 'storeMeter'])->middleware('permission:utilities.manage');
        Route::get('utility/meters/{meter}', [UtilityController::class, 'showMeter'])->middleware('permission:utilities.view');
        Route::get('utility/meters/{meter}/readings', [UtilityController::class, 'indexReadings'])->middleware('permission:utilities.view');
        Route::post('utility/meters/{meter}/readings', [UtilityController::class, 'storeReading'])->middleware('permission:utilities.manage');
        Route::post('utility/meters/{meter}/consumption', [UtilityController::class, 'consumption'])->middleware('permission:utilities.view');
        Route::get('utility/bills', [UtilityController::class, 'indexBills'])->middleware('permission:utilities.view');
        Route::post('utility/meters/{meter}/bills/preview', [UtilityController::class, 'previewBill'])->middleware('permission:utilities.bill');
        Route::post('utility/meters/{meter}/bills', [UtilityController::class, 'generateBill'])->middleware('permission:utilities.bill');
        Route::get('utility/bills/{bill}', [UtilityController::class, 'showBill'])->middleware('permission:utilities.view');
        Route::post('utility/bills/{bill}/allocate', [UtilityController::class, 'allocateBill'])->middleware('permission:utilities.bill');
        Route::post('utility/bills/{bill}/finalize', [UtilityController::class, 'finalizeBill'])->middleware('permission:utilities.bill');
        Route::post('utility/bills/{bill}/reverse', [UtilityController::class, 'reverseBill'])->middleware('permission:utilities.adjust');

        // P6 — Expenses
        Route::get('expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
        Route::get('expenses/summary', [ExpenseController::class, 'summary'])->middleware('permission:expenses.view');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->middleware('permission:expenses.view');
        Route::post('expenses/{expense}/transition', [ExpenseController::class, 'transition'])->middleware('permission:expenses.create');
        Route::post('expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->middleware('permission:expenses.reverse');

        // P7 — Owner statements
        Route::get('statement-periods', [OwnerStatementController::class, 'indexPeriods'])->middleware('permission:statements.view');
        Route::post('statement-periods', [OwnerStatementController::class, 'storePeriod'])->middleware('permission:statements.generate');
        Route::post('statement-periods/{period}/lock', [OwnerStatementController::class, 'lockPeriod'])->middleware('permission:statements.finalize');
        Route::get('owner-statements', [OwnerStatementController::class, 'index'])->middleware('permission:statements.view');
        Route::post('owner-statements/preview', [OwnerStatementController::class, 'preview'])->middleware('permission:statements.generate');
        Route::post('owner-statements', [OwnerStatementController::class, 'generate'])->middleware('permission:statements.generate');
        Route::get('owner-statements/{statement}', [OwnerStatementController::class, 'show'])->middleware('permission:statements.view');
        Route::post('owner-statements/{statement}/transition', [OwnerStatementController::class, 'transition'])->middleware('permission:statements.review');
        Route::post('owner-statements/{statement}/adjust', [OwnerStatementController::class, 'adjust'])->middleware('permission:statements.adjust');
        Route::get('owner-statements/{statement}/pdf', [OwnerStatementController::class, 'pdf'])->middleware('permission:statements.view');
        Route::get('owner-reports/portfolio', [OwnerStatementController::class, 'portfolio'])->middleware('permission:owner-reports.view');
        Route::get('owner-reports/profitability', [OwnerStatementController::class, 'profitability'])->middleware('permission:owner-reports.view');
        Route::get('owner-reports/trend', [OwnerStatementController::class, 'trend'])->middleware('permission:owner-reports.view');
    });
});
