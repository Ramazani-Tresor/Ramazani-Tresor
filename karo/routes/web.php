<?php

use App\Http\Controllers as C;
use Illuminate\Support\Facades\Route;

// PWA et vérification publique des documents (données minimales, limitation de débit)
Route::get('/sw.js', [C\PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/manifest.webmanifest', [C\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::view('/hors-connexion', 'pwa.offline')->name('pwa.offline');
Route::get('/verifier/{token}', [C\DocumentController::class, 'verify'])->middleware('throttle:verify')->where('token', '[a-f0-9]{40}')->name('documents.verify');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [C\AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [C\AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::view('/compte-perdu', 'auth.recovery')->name('account.recovery');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [C\AuthController::class, 'logout'])->name('logout');
    Route::get('/mon-compte', [C\AuthController::class, 'account'])->name('account.show');
    Route::put('/mon-compte/mot-de-passe', [C\AuthController::class, 'updatePassword'])->name('account.password');
    Route::get('/double-authentification/configuration', [C\TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/double-authentification/configuration', [C\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::get('/double-authentification', [C\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/double-authentification', [C\TwoFactorController::class, 'verify'])->middleware('throttle:two-factor')->name('two-factor.verify');

    Route::get('/', [C\DashboardController::class, 'home'])->name('home');
    Route::get('/tableau-de-bord', [C\DashboardController::class, 'index'])->middleware('perm:dashboard.view')->name('dashboard');
    Route::get('/mon-espace', [C\PortalController::class, 'show'])->middleware('perm:student.self')->name('portal.show');

    // Étudiants
    Route::middleware('perm:students.view')->group(function () {
        Route::get('/etudiants', [C\StudentController::class, 'index'])->name('students.index');
        Route::get('/etudiants/{student}', [C\StudentController::class, 'show'])->whereNumber('student')->name('students.show');
        Route::get('/etudiants/{student}/documents/{document}', [C\StudentController::class, 'downloadDocument'])->name('students.documents.download');
    });
    Route::middleware('perm:students.manage')->group(function () {
        Route::get('/etudiants/nouveau', [C\StudentController::class, 'create'])->name('students.create');
        Route::post('/etudiants', [C\StudentController::class, 'store'])->name('students.store');
        Route::get('/etudiants/{student}/modifier', [C\StudentController::class, 'edit'])->name('students.edit');
        Route::put('/etudiants/{student}', [C\StudentController::class, 'update'])->name('students.update');
        Route::post('/etudiants/{student}/inscriptions', [C\StudentController::class, 'enroll'])->name('students.enroll');
        Route::post('/etudiants/{student}/documents', [C\StudentController::class, 'uploadDocument'])->name('students.documents.store');
    });
    Route::middleware('perm:students.import')->group(function () {
        Route::get('/imports/etudiants', [C\StudentImportController::class, 'create'])->name('imports.students.create');
        Route::get('/imports/etudiants/modele', [C\StudentImportController::class, 'template'])->name('imports.students.template');
        Route::post('/imports/etudiants', [C\StudentImportController::class, 'upload'])->name('imports.students.upload');
        Route::get('/imports/etudiants/{batch}', [C\StudentImportController::class, 'show'])->name('imports.students.show');
        Route::post('/imports/etudiants/{batch}/previsualiser', [C\StudentImportController::class, 'preview'])->name('imports.students.preview');
        Route::post('/imports/etudiants/{batch}/confirmer', [C\StudentImportController::class, 'confirm'])->name('imports.students.confirm');
        Route::get('/imports/etudiants/{batch}/rapport', [C\StudentImportController::class, 'report'])->name('imports.students.report');
    });

    // Créances et ajustements
    Route::post('/etudiants/{student}/creances/generer', [C\ChargeController::class, 'generateForStudent'])->middleware('perm:charges.generate')->name('charges.generate.student');
    Route::post('/creances/generer', [C\ChargeController::class, 'generateBulk'])->middleware('perm:charges.generate')->name('charges.generate.bulk');
    Route::post('/etudiants/{student}/creances', [C\ChargeController::class, 'storeManual'])->middleware('perm:charges.manage')->name('charges.store');
    Route::post('/creances/{charge}/annuler', [C\ChargeController::class, 'cancel'])->middleware('perm:charges.manage')->name('charges.cancel');
    Route::get('/ajustements', [C\AdjustmentController::class, 'index'])->middleware('perm:adjustments.request|adjustments.approve')->name('adjustments.index');
    Route::post('/creances/{charge}/ajustements', [C\AdjustmentController::class, 'store'])->middleware('perm:adjustments.request')->name('adjustments.store');
    Route::post('/ajustements/{adjustment}/approuver', [C\AdjustmentController::class, 'approve'])->middleware('perm:adjustments.approve')->name('adjustments.approve');
    Route::post('/ajustements/{adjustment}/rejeter', [C\AdjustmentController::class, 'reject'])->middleware('perm:adjustments.approve')->name('adjustments.reject');
    Route::get('/ajustements/{adjustment}/justificatif', [C\AdjustmentController::class, 'justification'])->middleware('perm:adjustments.request|adjustments.approve|audit.view')->name('adjustments.justification');

    // Frais, barèmes, tranches
    Route::middleware('perm:fees.view')->group(function () {
        Route::get('/frais', [C\FeeController::class, 'index'])->name('fees.index');
        Route::get('/frais/baremes/{schedule}', [C\FeeController::class, 'showSchedule'])->whereNumber('schedule')->name('fees.schedules.show');
    });
    Route::middleware('perm:fees.manage')->group(function () {
        Route::get('/frais/categories/nouvelle', [C\FeeController::class, 'createCategory'])->name('fees.categories.create');
        Route::post('/frais/categories', [C\FeeController::class, 'storeCategory'])->name('fees.categories.store');
        Route::get('/frais/categories/{category}', [C\FeeController::class, 'editCategory'])->name('fees.categories.edit');
        Route::put('/frais/categories/{category}', [C\FeeController::class, 'updateCategory'])->name('fees.categories.update');
        Route::get('/frais/baremes/nouveau', [C\FeeController::class, 'createSchedule'])->name('fees.schedules.create');
        Route::post('/frais/baremes', [C\FeeController::class, 'storeSchedule'])->name('fees.schedules.store');
    });
    Route::post('/frais/baremes/{schedule}/approuver', [C\FeeController::class, 'approve'])->middleware('perm:fees.approve')->name('fees.schedules.approve');
    Route::post('/frais/baremes/{schedule}/rejeter', [C\FeeController::class, 'reject'])->middleware('perm:fees.approve')->name('fees.schedules.reject');

    // Paiements
    Route::get('/bordereaux/saisie', [C\PaymentController::class, 'entry'])->middleware('perm:payments.create')->name('payments.entry');
    Route::post('/bordereaux', [C\PaymentController::class, 'store'])->middleware('perm:payments.create')->name('payments.store');
    Route::get('/controle', [C\PaymentController::class, 'control'])->middleware('perm:payments.check|payments.validate')->name('payments.control');
    Route::middleware('perm:payments.view')->group(function () {
        Route::get('/paiements', [C\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/paiements/{payment}', [C\PaymentController::class, 'show'])->name('payments.show');
        Route::get('/paiements/{payment}/justificatif', [C\PaymentController::class, 'proof'])->name('payments.proof');
    });
    Route::put('/paiements/{payment}/affectations', [C\PaymentController::class, 'updateAllocations'])->middleware('perm:payments.create|payments.check')->name('payments.allocations');
    Route::post('/paiements/{payment}/controler', [C\PaymentController::class, 'check'])->middleware('perm:payments.check')->name('payments.check');
    Route::post('/paiements/{payment}/valider', [C\PaymentController::class, 'validatePayment'])->middleware('perm:payments.validate')->name('payments.validate');
    Route::post('/paiements/{payment}/rejeter', [C\PaymentController::class, 'reject'])->middleware('perm:payments.check|payments.validate')->name('payments.reject');
    Route::post('/paiements/{payment}/contrepasser', [C\PaymentController::class, 'reverse'])->middleware('perm:payments.reverse')->name('payments.reverse');

    // Banque et rapprochement
    Route::middleware('perm:bank.import|bank.reconcile|bank.review')->group(function () {
        Route::get('/banque', [C\BankController::class, 'index'])->name('bank.index');
        Route::get('/banque/lignes', [C\BankController::class, 'lines'])->name('bank.lines');
        Route::get('/banque/lignes/{line}', [C\BankController::class, 'line'])->name('bank.lines.show');
        Route::get('/banque/rapport', [C\BankController::class, 'report'])->name('bank.report');
        Route::get('/banque/imports/{batch}', [C\BankController::class, 'batch'])->name('bank.batches.show');
        Route::get('/banque/imports/{batch}/source', [C\BankController::class, 'source'])->name('bank.batches.source');
    });
    Route::middleware('perm:bank.import')->group(function () {
        Route::post('/banque/imports', [C\BankController::class, 'upload'])->name('bank.upload');
        Route::post('/banque/imports/{batch}/previsualiser', [C\BankController::class, 'preview'])->name('bank.preview');
        Route::post('/banque/imports/{batch}/confirmer', [C\BankController::class, 'confirm'])->name('bank.confirm');
    });
    Route::post('/banque/lignes/{line}/verifier', [C\BankController::class, 'review'])->middleware('perm:bank.review')->name('bank.review');
    Route::middleware('perm:bank.reconcile')->group(function () {
        Route::post('/banque/imports/{batch}/auto', [C\BankController::class, 'autoMatch'])->name('bank.auto');
        Route::post('/banque/lignes/{line}/rapprocher', [C\BankController::class, 'match'])->name('bank.match');
        Route::post('/banque/rapprochements/{match}/annuler', [C\BankController::class, 'unmatch'])->name('bank.unmatch');
        Route::post('/banque/lignes/{line}/ecarter', [C\BankController::class, 'ignore'])->name('bank.ignore');
    });

    // Excédents
    Route::middleware('perm:credits.request|credits.approve')->group(function () {
        Route::get('/excedents', [C\CreditController::class, 'index'])->name('credits.index');
        Route::get('/excedents/operations/{operation}', [C\CreditController::class, 'show'])->name('credits.show');
    });
    Route::post('/excedents/{payment}', [C\CreditController::class, 'store'])->middleware('perm:credits.request')->name('credits.store');
    Route::post('/excedents/operations/{operation}/approuver', [C\CreditController::class, 'approve'])->middleware('perm:credits.approve')->name('credits.approve');
    Route::post('/excedents/operations/{operation}/rejeter', [C\CreditController::class, 'reject'])->middleware('perm:credits.approve')->name('credits.reject');

    // Contrats
    Route::middleware('perm:contracts.view')->group(function () {
        Route::get('/contrats', [C\ContractController::class, 'index'])->name('contracts.index');
        Route::get('/contrats/{contract}', [C\ContractController::class, 'show'])->whereNumber('contract')->name('contracts.show');
        Route::get('/contrats/{contract}/pdf', [C\ContractController::class, 'pdf'])->name('contracts.pdf');
        Route::get('/contrats/{contract}/signe', [C\ContractController::class, 'signed'])->name('contracts.signed');
    });
    Route::middleware('perm:contracts.manage')->group(function () {
        Route::get('/etudiants/{student}/contrats/nouveau', [C\ContractController::class, 'create'])->name('contracts.create');
        Route::post('/etudiants/{student}/contrats', [C\ContractController::class, 'store'])->name('contracts.store');
        Route::get('/contrats/{contract}/nouvelle-version', [C\ContractController::class, 'editVersion'])->name('contracts.version');
        Route::post('/contrats/{contract}/nouvelle-version', [C\ContractController::class, 'storeVersion'])->name('contracts.version.store');
    });
    Route::post('/contrats/{contract}/approuver', [C\ContractController::class, 'approve'])->middleware('perm:contracts.approve')->name('contracts.approve');
    Route::post('/contrats/{contract}/version-signee', [C\ContractController::class, 'archiveSigned'])->middleware('perm:contracts.approve')->name('contracts.archive');

    // Documents
    Route::post('/etudiants/{student}/documents-financiers', [C\DocumentController::class, 'issue'])->middleware('perm:documents.issue|student.self')->name('documents.issue');
    Route::get('/documents/{document}', [C\DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/pdf', [C\DocumentController::class, 'pdf'])->name('documents.pdf');

    // Examens
    Route::middleware('perm:exams.view')->group(function () {
        Route::get('/examens', [C\ExamController::class, 'index'])->name('exams.index');
        Route::get('/examens/{session}', [C\ExamController::class, 'show'])->name('exams.show');
        Route::get('/examens/{session}/export/{format}', [C\ExamController::class, 'export'])->middleware('perm:reports.export')->name('exams.export');
    });
    Route::put('/examens/{session}/conditions', [C\ExamController::class, 'updateConditions'])->middleware('perm:exams.manage')->name('exams.conditions');
    Route::post('/examens/{session}/creances', [C\ExamController::class, 'generateCharges'])->middleware('perm:charges.generate')->name('exams.charges');
    Route::post('/examens/{session}/derogations', [C\ExamController::class, 'storeWaiver'])->middleware('perm:exams.waive')->name('exams.waivers.store');
    Route::post('/derogations/{waiver}/revoquer', [C\ExamController::class, 'revokeWaiver'])->middleware('perm:exams.waive')->name('exams.waivers.revoke');

    // Années académiques et clôtures
    Route::middleware('perm:years.manage|years.close|years.reopen')->group(function () {
        Route::get('/annees', [C\YearController::class, 'index'])->name('years.index');
        Route::get('/annees/{year}', [C\YearController::class, 'show'])->name('years.show');
    });
    Route::middleware('perm:years.manage')->group(function () {
        Route::post('/annees', [C\YearController::class, 'store'])->name('years.store');
        Route::post('/annees/{year}/ouvrir', [C\YearController::class, 'open'])->name('years.open');
        Route::post('/annees/{year}/courante', [C\YearController::class, 'makeCurrent'])->name('years.current');
        Route::post('/annees/{year}/sessions', [C\YearController::class, 'storeSession'])->name('years.sessions.store');
    });
    Route::post('/annees/{year}/cloturer', [C\YearController::class, 'close'])->middleware('perm:years.close')->name('years.close');
    Route::post('/annees/{year}/rouvrir', [C\YearController::class, 'reopen'])->middleware('perm:years.reopen')->name('years.reopen');

    // Rapports
    Route::middleware('perm:reports.view')->group(function () {
        Route::get('/rapports', [C\ReportController::class, 'index'])->name('reports.index');
        Route::get('/rapports/{type}', [C\ReportController::class, 'show'])->name('reports.show');
        Route::get('/rapports/{type}/export/{format}', [C\ReportController::class, 'export'])->middleware('perm:reports.export')->name('reports.export');
    });

    // Synchronisation (API authentifiée par session + CSRF)
    Route::middleware('perm:sync.use')->group(function () {
        Route::get('/api/hors-connexion/instantane', [C\SyncController::class, 'snapshot'])->name('api.offline.snapshot');
        Route::post('/api/sync/bordereaux', [C\SyncController::class, 'push'])->middleware('throttle:sync')->name('api.sync.push');
    });
    Route::get('/api/ping', fn () => response()->json(['ok' => true, 'server_time' => now()->toIso8601String()]))->name('api.ping');
    Route::get('/api/csrf', fn () => response()->json(['token' => csrf_token()]))->name('api.csrf');
    Route::get('/synchronisation', [C\SyncController::class, 'index'])->middleware('perm:sync.use|sync.resolve')->name('sync.index');
    Route::post('/synchronisation/{operation}/resoudre', [C\SyncController::class, 'resolve'])->middleware('perm:sync.resolve')->name('sync.resolve');

    // Administration
    Route::middleware('perm:users.manage')->group(function () {
        Route::get('/utilisateurs', [C\UserController::class, 'index'])->name('users.index');
        Route::get('/utilisateurs/nouveau', [C\UserController::class, 'create'])->name('users.create');
        Route::post('/utilisateurs', [C\UserController::class, 'store'])->name('users.store');
        Route::get('/utilisateurs/{user}', [C\UserController::class, 'edit'])->name('users.edit');
        Route::put('/utilisateurs/{user}', [C\UserController::class, 'update'])->name('users.update');
        Route::post('/utilisateurs/{user}/affectations', [C\UserController::class, 'addAssignment'])->name('users.assignments.store');
        Route::delete('/affectations/{assignment}', [C\UserController::class, 'removeAssignment'])->name('users.assignments.destroy');
        Route::post('/utilisateurs/{user}/mot-de-passe-temporaire', [C\UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/utilisateurs/{user}/reinitialiser-2fa', [C\UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
    });
    Route::middleware('perm:roles.manage')->group(function () {
        Route::get('/roles', [C\RoleController::class, 'index'])->name('roles.index');
        Route::put('/roles/{role}', [C\RoleController::class, 'update'])->name('roles.update');
    });
    Route::middleware('perm:audit.view')->group(function () {
        Route::get('/audit', [C\AuditController::class, 'index'])->name('audit.index');
        Route::post('/audit/verifier', [C\AuditController::class, 'verify'])->name('audit.verify');
        Route::get('/audit/export', [C\AuditController::class, 'export'])->name('audit.export');
    });
    Route::get('/parametres', [C\SettingsController::class, 'index'])->middleware('perm:settings.manage|structure.manage|finance_settings.manage|backups.manage')->name('settings.index');
    Route::middleware('perm:settings.manage')->group(function () {
        Route::put('/parametres/institution', [C\SettingsController::class, 'updateInstitution'])->name('settings.institution');
        Route::put('/parametres/regles', [C\SettingsController::class, 'updateRules'])->name('settings.rules');
        Route::put('/parametres/numerotation', [C\SettingsController::class, 'updateSequences'])->name('settings.sequences');
    });
    Route::middleware('perm:structure.manage')->group(function () {
        Route::post('/parametres/structure/{type}', [C\SettingsController::class, 'storeStructure'])->name('settings.structure.store');
        Route::patch('/parametres/structure/{type}/{id}', [C\SettingsController::class, 'toggleStructure'])->name('settings.structure.toggle');
    });
    Route::middleware('perm:finance_settings.manage')->group(function () {
        Route::post('/parametres/banques', [C\SettingsController::class, 'storeBank'])->name('settings.banks.store');
        Route::post('/parametres/comptes', [C\SettingsController::class, 'storeAccount'])->name('settings.accounts.store');
        Route::patch('/parametres/comptes/{account}', [C\SettingsController::class, 'toggleAccount'])->name('settings.accounts.toggle');
        Route::post('/parametres/devises', [C\SettingsController::class, 'storeCurrency'])->name('settings.currencies.store');
    });
    Route::post('/parametres/taux', [C\SettingsController::class, 'storeRate'])->middleware('perm:rates.approve')->name('settings.rates.store');
    Route::middleware('perm:backups.manage')->group(function () {
        Route::post('/parametres/sauvegardes', [C\SettingsController::class, 'backup'])->name('settings.backup');
        Route::get('/parametres/sauvegardes/{file}', [C\SettingsController::class, 'downloadBackup'])->name('settings.backup.download');
    });
});
