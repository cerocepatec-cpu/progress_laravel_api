<?php

use App\Http\Controllers\Api\V1\AccountingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\NetworkController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

$registerPublicAuthRoutes = function (): void {
    Route::post('members', [MemberController::class, 'store']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/sign-in', [AuthController::class, 'login']);
    Route::post('/contact-us', [SettingsController::class, 'storeObservation']);
};

$registerSharedAuthenticatedRoutes = function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/password', [AuthController::class, 'changePassword']);
    Route::post('auth/e-wallet-password', [AuthController::class, 'changeEwalletPassword']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
};

$registerMemberApplicationRoutes = function (): void {
    Route::get('network/directs', [NetworkController::class, 'directs']);
    Route::get('network/tree/{identifier?}', [NetworkController::class, 'tree']);
    Route::get('network/downline/latest/{identifier?}', [NetworkController::class, 'latestDownlineMembers']);
    Route::get('network/downline/{identifier?}', [NetworkController::class, 'downline']);
    Route::get('network/downline-count/{identifier?}', [NetworkController::class, 'downlineCount']);
    Route::get('network/downline-paginated/{identifier?}', [NetworkController::class, 'downlinePaginated']);
    Route::get('network/downline-by-date/{identifier?}', [NetworkController::class, 'downlineByDate']);
    Route::get('network/qualified/{level}', [NetworkController::class, 'qualified']);
    Route::get(
        'network/vip-packet-downline-count/{identifier?}',
        [NetworkController::class, 'vipPacketDownlineCount']
    );
    Route::post('accounting/transfers', [AccountingController::class, 'transfer']);
};

$registerSharedBusinessRoutes = function (): void {
    Route::get('accounting/members/{identifier}/ledger', [AccountingController::class, 'memberLedger']);
    Route::get('mlm/dashboard', [AccountingController::class, 'summary']);
    Route::get('mlm/members', [MemberController::class, 'index']);
    Route::get('members/admin-agents', [MemberController::class, 'adminAgents']);
    Route::get('wallet/overview', [AccountingController::class, 'summary']);
    Route::get('wallet/commissions', [AccountingController::class, 'reports']);
    Route::get('accounting/member-monthly-stats', [AccountingController::class, 'memberMonthlyStats']);
    Route::get('accounting/summary', [AccountingController::class, 'summary']);
    Route::get('accounting/reports', [AccountingController::class, 'reports']);
    Route::get('accounting/cash-operations', [AccountingController::class, 'cashOperations']);
    Route::get('accounting/cash-operations/{cashId}', [AccountingController::class, 'showCashOperation']);
    Route::post('accounting/cash-operations', [AccountingController::class, 'storeCashOperation']);
    Route::get('accounting/vip-packets', [AccountingController::class, 'vipPackets']);
    Route::post('accounting/vip-packets', [AccountingController::class, 'buyVipPackets']);
    Route::get('/vip-packets/download', [AccountingController::class, 'downloadVipPacket']);
};

$registerBackofficeUserRoutes = function (): void {

    Route::get('members', [MemberController::class, 'index']);
    Route::get('members/{identifier}', [MemberController::class, 'show']);

    Route::get('catalog/categories', [CatalogController::class, 'memberCategories']);
    Route::get('catalog/countries', [CatalogController::class, 'countries']);
    Route::get('catalog/cities', [CatalogController::class, 'cities']);
    Route::post('/configuration/cities', [CatalogController::class, 'storeCity']);

    Route::get('catalog/product-categories', [CatalogController::class, 'productCategories']);
    Route::get('catalog/uoms', [CatalogController::class, 'uoms']);
    Route::get('catalog/products', [CatalogController::class, 'products']);

    Route::get('inventory/deposits', [InventoryController::class, 'deposits']);
    Route::get('inventory/deposits/{deposit}', [InventoryController::class, 'showDeposit']);
    Route::get('inventory/deposits/{deposit}/items', [InventoryController::class, 'depositInventory']);
    Route::get('inventory/movements', [InventoryController::class, 'movements']);
    Route::get('inventory/transfers', [InventoryController::class, 'transfers']);
    Route::get('inventory/stock-values', [InventoryController::class, 'stockValues']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
};

$registerBackofficeAdminRoutes = function (): void {
    Route::post('users/normal', [MemberController::class, 'storeNormal']);
    Route::patch('members/{identifier}', [MemberController::class, 'update']);
    Route::patch('members/{identifier}/status', [MemberController::class, 'updateStatus']);
    Route::patch('members/{identifier}/city', [MemberController::class, 'updateCity']);
    Route::delete('members/{identifier}', [MemberController::class, 'destroy']);

    Route::post('network/permutations', [NetworkController::class, 'permute']);
    Route::get('network/qualified/{level}', [NetworkController::class, 'qualified']);
    Route::post('network/qualified/{level}/validate', [NetworkController::class, 'validateLevelPayment']);

    Route::post('accounting/entries', [AccountingController::class, 'storeEntry']);
    Route::post('accounting/wayouts', [AccountingController::class, 'storeWayout']);
    Route::post('accounting/cash-operations/{cashId}/validate', [AccountingController::class, 'validateCashOperation']);
    Route::delete('accounting/cash-operations/{cashId}', [AccountingController::class, 'deleteCashOperation']);

    Route::get('settings/maj-points', [SettingsController::class, 'majPoints']);
    Route::put('settings/maj-points', [SettingsController::class, 'updateMajPoints']);
    Route::get('settings/inscription-cost', [SettingsController::class, 'inscriptionCost']);
    Route::put('settings/inscription-cost', [SettingsController::class, 'updateInscriptionCost']);
    Route::get('settings/validation-expiration', [SettingsController::class, 'validationExpiration']);
    Route::put('settings/validation-expiration', [SettingsController::class, 'updateValidationExpiration']);
    Route::get('settings/periodic-maj', [SettingsController::class, 'periodicMaj']);
    Route::post('settings/periodic-maj', [SettingsController::class, 'storePeriodicMaj']);
    Route::get('settings/steps', [SettingsController::class, 'steps']);
    Route::post('settings/steps', [SettingsController::class, 'storeStep']);
    Route::get('observations', [SettingsController::class, 'observations']);
    Route::post('catalog/categories', [CatalogController::class, 'storeMemberCategory']);
    Route::patch('catalog/categories/{category}', [CatalogController::class, 'updateMemberCategory']);
    Route::delete('catalog/categories/{category}', [CatalogController::class, 'deleteMemberCategory']);
    Route::post('catalog/countries', [CatalogController::class, 'storeCountry']);
    Route::patch('catalog/countries/{country}', [CatalogController::class, 'updateCountry']);
    Route::delete('catalog/countries/{country}', [CatalogController::class, 'deleteCountry']);
    Route::post('catalog/product-categories', [CatalogController::class, 'storeProductCategory']);
    Route::delete('catalog/product-categories/{category}', [CatalogController::class, 'deleteProductCategory']);
    Route::post('catalog/uoms', [CatalogController::class, 'storeUom']);
    Route::delete('catalog/uoms/{uom}', [CatalogController::class, 'deleteUom']);
    Route::post('catalog/products', [CatalogController::class, 'storeProduct']);
    Route::patch('catalog/products/{product}', [CatalogController::class, 'updateProduct']);
    Route::delete('catalog/products/{product}', [CatalogController::class, 'deleteProduct']);

    Route::post('inventory/deposits', [InventoryController::class, 'storeDeposit']);
    Route::delete('inventory/deposits/{deposit}', [InventoryController::class, 'deleteDeposit']);
    Route::post('inventory/deposits/{deposit}/users', [InventoryController::class, 'attachUser']);
    Route::delete('inventory/deposits/users/{affectationId}', [InventoryController::class, 'deleteAffectation']);
    Route::patch('inventory/items/{affectationId}', [InventoryController::class, 'updateInventoryItem']);
    Route::post('inventory/movements', [InventoryController::class, 'storeMovement']);
    Route::post('inventory/transfers', [InventoryController::class, 'storeTransfer']);
    Route::post('inventory/transfers/{transferId}/validate', [InventoryController::class, 'validateTransfer']);
    Route::post('inventory/transfers/{transferId}/deny', [InventoryController::class, 'denyTransfer']);

    Route::post('invoices', [InvoiceController::class, 'store']);
    // route pour le It a effacer apres les test 
    Route::get('settings/maj-points', [SettingsController::class, 'majPoints']);
    Route::put('settings/maj-points', [SettingsController::class, 'updateMajPoints']);
    Route::get('settings/adhesion-points', [SettingsController::class, 'adhesionPoints']);
    Route::put('settings/adhesion-points', [SettingsController::class, 'updateAdhesionPoints']);
    Route::get('settings/inscription-cost', [SettingsController::class, 'inscriptionCost']);
    Route::put('settings/inscription-cost', [SettingsController::class, 'updateInscriptionCost']);
    Route::get('settings/validation-expiration', [SettingsController::class, 'validationExpiration']);
    Route::put('settings/validation-expiration', [SettingsController::class, 'updateValidationExpiration']);

    Route::get('settings/periodic-maj', [SettingsController::class, 'periodicMaj']);
    Route::post('settings/periodic-maj', [SettingsController::class, 'storePeriodicMaj']);

    Route::get('settings/steps', [SettingsController::class, 'steps']);
    Route::post('settings/steps', [SettingsController::class, 'storeStep']);
    Route::get('observations', [SettingsController::class, 'observations']);
};

$registerBackofficeAdminItRoutes = function (): void {
    // Route::get('settings/maj-points', [SettingsController::class, 'majPoints']);
    // Route::put('settings/maj-points', [SettingsController::class, 'updateMajPoints']);
    // Route::get('settings/adhesion-points', [SettingsController::class, 'adhesionPoints']);
    // Route::put('settings/adhesion-points', [SettingsController::class, 'updateAdhesionPoints']);
    // Route::get('settings/inscription-cost', [SettingsController::class, 'inscriptionCost']);
    // Route::put('settings/inscription-cost', [SettingsController::class, 'updateInscriptionCost']);
    // Route::get('settings/validation-expiration', [SettingsController::class, 'validationExpiration']);
    // Route::put('settings/validation-expiration', [SettingsController::class, 'updateValidationExpiration']);

    // Route::get('settings/periodic-maj', [SettingsController::class, 'periodicMaj']);
    // Route::post('settings/periodic-maj', [SettingsController::class, 'storePeriodicMaj']);

    // Route::get('settings/steps', [SettingsController::class, 'steps']);
    // Route::post('settings/steps', [SettingsController::class, 'storeStep']);
    // Route::get('observations', [SettingsController::class, 'observations']);
};

// Legacy / compatibility auth endpoints.
$registerPublicAuthRoutes();

Route::middleware(['auth:sanctum', 'member.scope:member,backoffice'])->group($registerSharedAuthenticatedRoutes);
Route::middleware(['auth:sanctum', 'member.scope:member,backoffice'])->group($registerSharedBusinessRoutes);
Route::middleware(['auth:sanctum', 'member.scope:backoffice'])->group($registerBackofficeUserRoutes);

Route::prefix('v1')->group(function () use (
    $registerPublicAuthRoutes,
    $registerSharedAuthenticatedRoutes,
    $registerSharedBusinessRoutes,
    $registerMemberApplicationRoutes,
    $registerBackofficeUserRoutes,
    $registerBackofficeAdminRoutes,
    $registerBackofficeAdminItRoutes
): void {
    // Public authentication.
    $registerPublicAuthRoutes();
    Route::middleware(['auth:sanctum', 'member.scope:member,backoffice'])->group($registerSharedAuthenticatedRoutes);
    Route::middleware(['auth:sanctum', 'member.scope:member,backoffice'])->group($registerSharedBusinessRoutes);

    // Member application.
    Route::middleware(['auth:sanctum', 'member.scope:member,backoffice'])->group($registerMemberApplicationRoutes);

    // Backoffice application - normal user (comptable + admin + admin IT).
    Route::middleware(['auth:sanctum', 'member.scope:backoffice'])->group($registerBackofficeUserRoutes);

    // Backoffice application - admin.
    Route::middleware(['auth:sanctum', 'member.scope:backoffice_admin'])->group($registerBackofficeAdminRoutes);

    // Backoffice application - admin IT / super admin.
    Route::middleware(['auth:sanctum', 'member.scope:backoffice_admin_it'])->group($registerBackofficeAdminItRoutes);
});
