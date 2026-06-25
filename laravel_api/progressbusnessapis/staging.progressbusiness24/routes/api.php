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

Route::post('auth/sign-in', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/password', [AuthController::class, 'changePassword']);
    Route::post('auth/e-wallet-password', [AuthController::class, 'changeEwalletPassword']);

    // Compatibility aliases for the mobile/frontend contract.
    Route::get('mlm/dashboard', [AccountingController::class, 'summary']);
    Route::get('mlm/members', [MemberController::class, 'index']);
    Route::get('wallet/overview', [AccountingController::class, 'summary']);
    Route::get('wallet/commissions', [AccountingController::class, 'reports']);
});

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/password', [AuthController::class, 'changePassword']);
        Route::post('auth/e-wallet-password', [AuthController::class, 'changeEwalletPassword']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('members', [MemberController::class, 'index']);
        Route::post('members', [MemberController::class, 'store']);
        Route::get('members/{identifier}', [MemberController::class, 'show']);
        Route::patch('members/{identifier}', [MemberController::class, 'update']);
        Route::patch('members/{identifier}/status', [MemberController::class, 'updateStatus']);
        Route::patch('members/{identifier}/city', [MemberController::class, 'updateCity']);
        Route::delete('members/{identifier}', [MemberController::class, 'destroy']);

        Route::get('network/directs', [NetworkController::class, 'directs']);
        Route::get('network/tree/{identifier?}', [NetworkController::class, 'tree']);
        Route::get('network/downline/{identifier?}', [NetworkController::class, 'downline']);
        Route::post('network/permutations', [NetworkController::class, 'permute']);
        Route::get('network/qualified/{level}', [NetworkController::class, 'qualified']);
        Route::post('network/qualified/{level}/validate', [NetworkController::class, 'validateLevelPayment']);

        Route::get('accounting/summary', [AccountingController::class, 'summary']);
        Route::get('accounting/reports', [AccountingController::class, 'reports']);
        Route::post('accounting/entries', [AccountingController::class, 'storeEntry']);
        Route::post('accounting/wayouts', [AccountingController::class, 'storeWayout']);
        Route::post('accounting/transfers', [AccountingController::class, 'transfer']);
        Route::get('accounting/members/{identifier}/ledger', [AccountingController::class, 'memberLedger']);
        Route::get('accounting/cash-operations', [AccountingController::class, 'cashOperations']);
        Route::get('accounting/cash-operations/{cashId}', [AccountingController::class, 'showCashOperation']);
        Route::post('accounting/cash-operations', [AccountingController::class, 'storeCashOperation']);
        Route::post('accounting/cash-operations/{cashId}/validate', [AccountingController::class, 'validateCashOperation']);
        Route::delete('accounting/cash-operations/{cashId}', [AccountingController::class, 'deleteCashOperation']);
        Route::get('accounting/vip-packets', [AccountingController::class, 'vipPackets']);
        Route::post('accounting/vip-packets', [AccountingController::class, 'buyVipPackets']);

        Route::get('catalog/categories', [CatalogController::class, 'memberCategories']);
        Route::post('catalog/categories', [CatalogController::class, 'storeMemberCategory']);
        Route::patch('catalog/categories/{category}', [CatalogController::class, 'updateMemberCategory']);
        Route::delete('catalog/categories/{category}', [CatalogController::class, 'deleteMemberCategory']);
        Route::get('catalog/countries', [CatalogController::class, 'countries']);
        Route::get('catalog/cities', [CatalogController::class, 'cities']);
        Route::get('catalog/product-categories', [CatalogController::class, 'productCategories']);
        Route::get('catalog/uoms', [CatalogController::class, 'uoms']);
        Route::get('catalog/products', [CatalogController::class, 'products']);
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

        Route::get('inventory/deposits', [InventoryController::class, 'deposits']);
        Route::post('inventory/deposits', [InventoryController::class, 'storeDeposit']);
        Route::get('inventory/deposits/{deposit}', [InventoryController::class, 'showDeposit']);
        Route::delete('inventory/deposits/{deposit}', [InventoryController::class, 'deleteDeposit']);
        Route::post('inventory/deposits/{deposit}/users', [InventoryController::class, 'attachUser']);
        Route::delete('inventory/deposits/users/{affectationId}', [InventoryController::class, 'deleteAffectation']);
        Route::get('inventory/deposits/{deposit}/items', [InventoryController::class, 'depositInventory']);
        Route::patch('inventory/items/{affectationId}', [InventoryController::class, 'updateInventoryItem']);
        Route::post('inventory/movements', [InventoryController::class, 'storeMovement']);
        Route::get('inventory/movements', [InventoryController::class, 'movements']);
        Route::post('inventory/transfers', [InventoryController::class, 'storeTransfer']);
        Route::post('inventory/transfers/{transferId}/validate', [InventoryController::class, 'validateTransfer']);
        Route::post('inventory/transfers/{transferId}/deny', [InventoryController::class, 'denyTransfer']);
        Route::get('inventory/transfers', [InventoryController::class, 'transfers']);
        Route::get('inventory/stock-values', [InventoryController::class, 'stockValues']);

        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);

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

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    });
});
