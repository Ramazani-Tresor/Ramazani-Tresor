<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

// Fichiers PWA servis à la racine (portée du Service Worker = toute l'application).
Route::get('/sw.js', [Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/manifest.webmanifest', [Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::view('/hors-ligne', 'pwa.offline')->name('pwa.offline');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [Controllers\AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [Controllers\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [Controllers\AuthController::class, 'logout'])->name('logout');
    Route::get('/mon-compte', [Controllers\AuthController::class, 'account'])->name('account');
    Route::put('/mon-compte', [Controllers\AuthController::class, 'updatePassword'])->name('account.password');

    Route::get('/', [Controllers\DashboardController::class, 'home'])->name('home');
    Route::get('/tableau-de-bord', [Controllers\DashboardController::class, 'index'])->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/consultation-a-distance', [Controllers\RemoteController::class, 'index'])->middleware('can:remote.view')->name('remote');

    // Catalogue
    Route::middleware('can:categories.manage')->group(function () {
        Route::resource('categories', Controllers\CategoryController::class)->except(['show', 'destroy']);
        Route::patch('categories/{category}/statut', [Controllers\CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::delete('categories/{category}', [Controllers\CategoryController::class, 'destroy'])->name('categories.destroy');
    });
    Route::middleware('can:products.import')->group(function () {
        Route::get('produits/import', [Controllers\ProductController::class, 'importForm'])->name('products.import');
        Route::post('produits/import', [Controllers\ProductController::class, 'import'])->name('products.import.run');
        Route::get('produits/import/modele', [Controllers\ProductController::class, 'importTemplate'])->name('products.import.template');
    });
    Route::get('produits', [Controllers\ProductController::class, 'index'])->middleware('can:products.view')->name('products.index');
    Route::middleware('can:products.manage')->group(function () {
        Route::get('produits/nouveau', [Controllers\ProductController::class, 'create'])->name('products.create');
        Route::post('produits', [Controllers\ProductController::class, 'store'])->name('products.store');
        Route::get('produits/{product}/modifier', [Controllers\ProductController::class, 'edit'])->name('products.edit');
        Route::put('produits/{product}', [Controllers\ProductController::class, 'update'])->name('products.update');
        Route::patch('produits/{product}/statut', [Controllers\ProductController::class, 'toggle'])->name('products.toggle');
    });
    Route::get('produits/{product}', [Controllers\ProductController::class, 'show'])->middleware('can:products.view')->name('products.show');

    // Fournisseurs et approvisionnements
    Route::middleware('can:suppliers.manage')->group(function () {
        Route::resource('fournisseurs', Controllers\SupplierController::class)->parameters(['fournisseurs' => 'supplier'])->names('suppliers')->except(['destroy']);
    });
    Route::get('approvisionnements', [Controllers\PurchaseController::class, 'index'])->middleware('can:purchases.view')->name('purchases.index');
    Route::middleware('can:purchases.create')->group(function () {
        Route::get('approvisionnements/nouveau', [Controllers\PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('approvisionnements', [Controllers\PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('approvisionnements/{purchase}/modifier', [Controllers\PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('approvisionnements/{purchase}', [Controllers\PurchaseController::class, 'update'])->name('purchases.update');
        Route::post('approvisionnements/{purchase}/annuler', [Controllers\PurchaseController::class, 'cancel'])->name('purchases.cancel');
    });
    Route::post('approvisionnements/{purchase}/valider', [Controllers\PurchaseController::class, 'validatePurchase'])->middleware('can:purchases.validate')->name('purchases.validate');
    Route::get('approvisionnements/{purchase}', [Controllers\PurchaseController::class, 'show'])->middleware('can:purchases.view')->name('purchases.show');
    Route::get('approvisionnements/{purchase}/imprimer', [Controllers\PurchaseController::class, 'print'])->middleware('can:purchases.view')->name('purchases.print');

    // Stock
    Route::middleware('can:stock.view')->group(function () {
        Route::get('stock', [Controllers\StockController::class, 'index'])->name('stock.index');
        Route::get('stock/mouvements', [Controllers\StockController::class, 'movements'])->name('stock.movements');
    });
    Route::middleware('can:stock.adjust')->group(function () {
        Route::get('stock/sortie', [Controllers\StockController::class, 'exitForm'])->name('stock.exit');
        Route::post('stock/sortie', [Controllers\StockController::class, 'exitStore'])->name('stock.exit.store');
    });

    // Point de vente
    Route::middleware('can:sales.create')->group(function () {
        Route::get('caisse-vente', [Controllers\PosController::class, 'index'])->name('pos');
        Route::get('api/pos/catalogue', [Controllers\PosController::class, 'catalog'])->name('api.pos.catalog');
        Route::post('api/ventes', [Controllers\PosController::class, 'store'])->name('api.sales.store');
        Route::post('api/sync/ventes', [Controllers\SyncController::class, 'push'])->name('api.sync.sales');
        Route::post('api/sync/etat', [Controllers\SyncController::class, 'status'])->name('api.sync.status');
    });
    Route::get('api/clients', [Controllers\CustomerController::class, 'search'])->middleware('can:customers.view')->name('api.customers');
    Route::get('api/csrf', fn () => response()->json(['token' => csrf_token()]))->name('api.csrf');
    Route::get('api/ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]))->name('api.ping');

    // Ventes, reçus, retours
    Route::get('ventes', [Controllers\SaleController::class, 'index'])->name('sales.index');
    Route::get('ventes/{sale}', [Controllers\SaleController::class, 'show'])->name('sales.show');
    Route::get('ventes/{sale}/recu', [Controllers\SaleController::class, 'receipt'])->name('sales.receipt');
    Route::get('ventes/{sale}/pdf', [Controllers\SaleController::class, 'pdf'])->name('sales.pdf');
    Route::post('ventes/{sale}/annuler', [Controllers\SaleController::class, 'cancel'])->middleware('can:sales.cancel')->name('sales.cancel');
    Route::middleware('can:returns.create')->group(function () {
        Route::get('ventes/{sale}/retour', [Controllers\ReturnController::class, 'create'])->name('returns.create');
        Route::post('ventes/{sale}/retour', [Controllers\ReturnController::class, 'store'])->name('returns.store');
    });
    Route::get('retours', [Controllers\ReturnController::class, 'index'])->name('returns.index');
    Route::get('retours/{saleReturn}', [Controllers\ReturnController::class, 'show'])->name('returns.show');

    // Clients et créances
    Route::middleware('can:customers.view')->group(function () {
        Route::get('clients', [Controllers\CustomerController::class, 'index'])->name('customers.index');
        Route::get('clients/{customer}', [Controllers\CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::get('clients/{customer}/releve', [Controllers\CustomerController::class, 'statement'])->name('customers.statement');
        Route::get('reglements/{settlement}', [Controllers\CustomerController::class, 'settlementReceipt'])->name('settlements.show');
    });
    Route::middleware('can:customers.manage')->group(function () {
        Route::get('clients/nouveau', [Controllers\CustomerController::class, 'create'])->name('customers.create');
        Route::post('clients', [Controllers\CustomerController::class, 'store'])->name('customers.store');
        Route::get('clients/{customer}/modifier', [Controllers\CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('clients/{customer}', [Controllers\CustomerController::class, 'update'])->name('customers.update');
    });
    Route::post('clients/{customer}/reglement', [Controllers\CustomerController::class, 'settle'])->middleware('can:customers.settle')->name('customers.settle');

    // Caisse
    Route::middleware('can:cash.use')->group(function () {
        Route::get('caisse', [Controllers\CashController::class, 'index'])->name('cash.index');
        Route::post('caisse/ouvrir', [Controllers\CashController::class, 'open'])->name('cash.open');
        Route::post('caisse/mouvement', [Controllers\CashController::class, 'movement'])->name('cash.movement');
    });
    Route::get('caisse/sessions', [Controllers\CashController::class, 'sessions'])->name('cash.sessions');
    Route::get('caisse/sessions/{session}', [Controllers\CashController::class, 'show'])->name('cash.show');
    Route::post('caisse/sessions/{session}/cloturer', [Controllers\CashController::class, 'close'])->name('cash.close');

    // Dépenses
    Route::get('depenses', [Controllers\ExpenseController::class, 'index'])->name('expenses.index');
    Route::middleware('can:expenses.create')->group(function () {
        Route::get('depenses/nouvelle', [Controllers\ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('depenses', [Controllers\ExpenseController::class, 'store'])->name('expenses.store');
    });
    Route::get('depenses/{expense}', [Controllers\ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('depenses/{expense}/justificatif', [Controllers\ExpenseController::class, 'attachment'])->name('expenses.attachment');
    Route::middleware('can:expenses.approve')->group(function () {
        Route::post('depenses/{expense}/valider', [Controllers\ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('depenses/{expense}/rejeter', [Controllers\ExpenseController::class, 'reject'])->name('expenses.reject');
    });

    // Inventaires
    Route::middleware('can:inventories.view')->group(function () {
        Route::get('inventaires', [Controllers\InventoryController::class, 'index'])->name('inventories.index');
        Route::get('inventaires/{inventory}', [Controllers\InventoryController::class, 'show'])->whereNumber('inventory')->name('inventories.show');
        Route::get('inventaires/{inventory}/export', [Controllers\InventoryController::class, 'export'])->name('inventories.export');
    });
    Route::post('inventaires/{inventory}/comptage', [Controllers\InventoryController::class, 'count'])->middleware('can:inventories.count')->name('inventories.count');
    Route::middleware('can:inventories.validate')->group(function () {
        Route::get('inventaires/nouveau', [Controllers\InventoryController::class, 'create'])->name('inventories.create');
        Route::post('inventaires', [Controllers\InventoryController::class, 'store'])->name('inventories.store');
        Route::post('inventaires/{inventory}/valider', [Controllers\InventoryController::class, 'validateInventory'])->name('inventories.validate');
        Route::post('inventaires/{inventory}/annuler', [Controllers\InventoryController::class, 'cancel'])->name('inventories.cancel');
    });

    // Rapports
    Route::middleware('can:reports.view')->group(function () {
        Route::get('rapports', [Controllers\ReportController::class, 'index'])->name('reports.index');
        Route::get('rapports/{type}', [Controllers\ReportController::class, 'show'])->name('reports.show');
        Route::get('rapports/{type}/export/{format}', [Controllers\ReportController::class, 'export'])->middleware('can:reports.export')->name('reports.export');
    });

    // Synchronisation : conflits
    Route::middleware('can:sync.resolve')->group(function () {
        Route::get('synchronisation', [Controllers\SyncController::class, 'index'])->name('sync.index');
        Route::post('synchronisation/{operation}/resoudre', [Controllers\SyncController::class, 'resolve'])->name('sync.resolve');
    });

    // Administration
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('utilisateurs', Controllers\UserController::class)->parameters(['utilisateurs' => 'user'])->names('users')->except(['show', 'destroy']);
        Route::patch('utilisateurs/{user}/archiver', [Controllers\UserController::class, 'archive'])->name('users.archive');
    });
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('roles', [Controllers\RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}', [Controllers\RoleController::class, 'update'])->name('roles.update');
    });
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('parametres', [Controllers\SettingController::class, 'index'])->name('settings.index');
        Route::put('parametres', [Controllers\SettingController::class, 'update'])->name('settings.update');
        Route::post('parametres/modes-paiement', [Controllers\SettingController::class, 'storePaymentMethod'])->name('settings.payment-methods.store');
        Route::patch('parametres/modes-paiement/{method}', [Controllers\SettingController::class, 'togglePaymentMethod'])->name('settings.payment-methods.toggle');
        Route::post('parametres/taxes', [Controllers\SettingController::class, 'storeTax'])->name('settings.taxes.store');
        Route::patch('parametres/taxes/{tax}', [Controllers\SettingController::class, 'toggleTax'])->name('settings.taxes.toggle');
        Route::put('parametres/numerotation', [Controllers\SettingController::class, 'updateSequences'])->name('settings.sequences');
        Route::post('parametres/categories-depenses', [Controllers\SettingController::class, 'storeExpenseCategory'])->name('settings.expense-categories.store');
        Route::post('parametres/sauvegarde', [Controllers\SettingController::class, 'backup'])->name('settings.backup');
        Route::get('parametres/sauvegarde/{file}', [Controllers\SettingController::class, 'downloadBackup'])->name('settings.backup.download');
    });
    Route::get('journal-audit', [Controllers\AuditController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
});
