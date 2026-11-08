<?php

use App\Http\Controllers as C;
use Illuminate\Support\Facades\Route;

// Authentification
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [C\AuthController::class, 'show'])->name('login');
    Route::post('/connexion', [C\AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [C\AuthController::class, 'logout'])->name('logout');
    Route::get('/mon-compte/mot-de-passe', [C\AuthController::class, 'passwordForm'])->name('account.password');
    Route::put('/mon-compte/mot-de-passe', [C\AuthController::class, 'updatePassword'])->name('account.password.update');

    Route::get('/', [C\DashboardController::class, 'home'])->name('home');
    Route::get('/tableau-de-bord', [C\DashboardController::class, 'index'])->middleware('perm:dashboard.view')->name('dashboard');
    Route::get('/statistiques-scientifiques', [C\DashboardController::class, 'scientific'])->middleware('perm:reports.view|dashboard.view')->name('stats.scientific');
    Route::get('/notifications', [C\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/lues', [C\NotificationController::class, 'markAll'])->name('notifications.read');

    // Personnel
    Route::get('/agents', [C\EmployeeController::class, 'index'])->middleware('perm:employees.view|employees.directory')->name('employees.index');
    Route::middleware('perm:employees.manage')->group(function () {
        Route::get('/agents/nouveau', [C\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/agents', [C\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/agents/{employee}/modifier', [C\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/agents/{employee}', [C\EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/agents/{employee}/photo', [C\EmployeeController::class, 'photo'])->name('employees.photo');
    });
    Route::get('/agents/{employee}', [C\EmployeeController::class, 'show'])->middleware('perm:employees.view')->name('employees.show');
    Route::get('/agents/{employee}/photo', [C\EmployeeController::class, 'photoFile'])->middleware('perm:employees.view|self.view')->name('employees.photo.show');
    Route::get('/agents/{employee}/fiche', [C\EmployeeController::class, 'sheet'])->middleware('perm:employees.view')->name('employees.sheet');
    Route::put('/agents/{employee}/banque', [C\EmployeeController::class, 'bank'])->middleware('perm:employees.bank')->name('employees.bank');
    Route::post('/agents/{employee}/documents', [C\DocumentController::class, 'store'])->middleware('perm:employees.documents')->name('documents.store');
    Route::get('/documents/{document}', [C\DocumentController::class, 'download'])->name('documents.download');

    // Carrière et qualifications
    Route::get('/carriere', [C\CareerController::class, 'index'])->middleware('perm:career.manage|career.validate')->name('career.index');
    Route::post('/agents/{employee}/carriere', [C\CareerController::class, 'store'])->middleware('perm:career.manage')->name('career.store');
    Route::post('/carriere/{event}/valider', [C\CareerController::class, 'validateEvent'])->middleware('perm:career.validate')->name('career.validate');
    Route::post('/carriere/{event}/rejeter', [C\CareerController::class, 'reject'])->middleware('perm:career.validate')->name('career.reject');
    Route::post('/agents/{employee}/diplomes', [C\QualificationController::class, 'store'])->middleware('perm:qualifications.manage')->name('qualifications.store');
    Route::put('/diplomes/{qualification}', [C\QualificationController::class, 'update'])->middleware('perm:qualifications.manage')->name('qualifications.update');

    // Organisation et affectations
    Route::get('/structure', [C\StructureController::class, 'index'])->middleware('perm:employees.view|structure.manage')->name('structure.index');
    Route::get('/structure/{unit}', [C\StructureController::class, 'show'])->middleware('perm:employees.view|structure.manage')->name('structure.show');
    Route::middleware('perm:structure.manage')->group(function () {
        Route::post('/structure', [C\StructureController::class, 'storeUnit'])->name('structure.store');
        Route::put('/structure/{unit}', [C\StructureController::class, 'updateUnit'])->name('structure.update');
        Route::post('/structure/{unit}/postes', [C\StructureController::class, 'storePosition'])->name('positions.store');
        Route::put('/postes/{position}', [C\StructureController::class, 'updatePosition'])->name('positions.update');
    });
    Route::post('/agents/{employee}/affectations', [C\AssignmentController::class, 'store'])->middleware('perm:assignments.manage')->name('assignments.store');
    Route::post('/affectations/{assignment}/cloturer', [C\AssignmentController::class, 'close'])->middleware('perm:assignments.manage')->name('assignments.close');

    // Mouvements
    Route::get('/mutations', [C\MutationController::class, 'index'])->middleware('perm:mutations.manage|mutations.validate')->name('mutations.index');
    Route::post('/agents/{employee}/mutations', [C\MutationController::class, 'store'])->middleware('perm:mutations.manage')->name('mutations.store');
    Route::post('/mutations/{mutation}/valider', [C\MutationController::class, 'validateMutation'])->middleware('perm:mutations.validate')->name('mutations.validate');
    Route::post('/mutations/{mutation}/rejeter', [C\MutationController::class, 'reject'])->middleware('perm:mutations.validate')->name('mutations.reject');
    Route::get('/situations', [C\SituationController::class, 'index'])->middleware('perm:situations.manage|situations.validate')->name('situations.index');
    Route::post('/agents/{employee}/situations', [C\SituationController::class, 'store'])->middleware('perm:situations.manage')->name('situations.store');
    Route::post('/situations/{situation}/approuver', [C\SituationController::class, 'approve'])->middleware('perm:situations.validate')->name('situations.approve');
    Route::post('/situations/{situation}/rejeter', [C\SituationController::class, 'reject'])->middleware('perm:situations.validate')->name('situations.reject');
    Route::post('/situations/{situation}/cloturer', [C\SituationController::class, 'close'])->middleware('perm:situations.validate')->name('situations.close');
    Route::post('/situations/{situation}/prolonger', [C\SituationController::class, 'extend'])->middleware('perm:situations.manage')->name('situations.extend');
    Route::get('/deces', [C\DeathController::class, 'index'])->middleware('perm:deaths.manage|deaths.validate')->name('deaths.index');
    Route::post('/agents/{employee}/deces', [C\DeathController::class, 'store'])->middleware('perm:deaths.manage')->name('deaths.store');
    Route::post('/deces/{death}/valider', [C\DeathController::class, 'validateDeath'])->middleware('perm:deaths.validate')->name('deaths.validate');

    // Primes et paiements
    Route::get('/primes', [C\PrimeController::class, 'index'])->middleware('perm:primes.view')->name('primes.index');
    Route::get('/primes/generer', [C\PrimeController::class, 'generateForm'])->middleware('perm:primes.generate')->name('primes.generate');
    Route::post('/primes/generer', [C\PrimeController::class, 'generate'])->middleware('perm:primes.generate')->name('primes.generate.run');
    Route::get('/primes/{entitlement}', [C\PrimeController::class, 'show'])->middleware('perm:primes.view')->name('primes.show');
    Route::post('/primes/{entitlement}/mouvements', [C\PrimeController::class, 'record'])->middleware('perm:payments.create')->name('primes.record');
    Route::post('/primes/{entitlement}/ajustements', [C\PrimeController::class, 'adjust'])->middleware('perm:payments.create')->name('primes.adjust');
    Route::get('/paiements', [C\PrimeController::class, 'payments'])->middleware('perm:payments.create|payments.validate')->name('payments.index');
    Route::post('/paiements/{payment}/valider', [C\PrimeController::class, 'validatePayment'])->middleware('perm:payments.validate')->name('payments.validate');
    Route::post('/paiements/{payment}/rejeter', [C\PrimeController::class, 'rejectPayment'])->middleware('perm:payments.validate')->name('payments.reject');
    Route::post('/paiements/{payment}/contrepasser', [C\PrimeController::class, 'reversePayment'])->middleware('perm:payments.validate')->name('payments.reverse');

    // Soutiens sociaux
    Route::get('/soutiens', [C\SocialController::class, 'index'])->middleware('perm:social.manage|social.approve|social.pay')->name('social.index');
    Route::post('/soutiens', [C\SocialController::class, 'store'])->middleware('perm:social.manage')->name('social.store');
    Route::post('/soutiens/{support}/approuver', [C\SocialController::class, 'approve'])->middleware('perm:social.approve')->name('social.approve');
    Route::post('/soutiens/{support}/rejeter', [C\SocialController::class, 'reject'])->middleware('perm:social.approve')->name('social.reject');
    Route::post('/soutiens/{support}/payer', [C\SocialController::class, 'pay'])->middleware('perm:social.pay')->name('social.pay');

    // Recrutement
    Route::middleware('perm:recruitment.manage|recruitment.engage')->group(function () {
        Route::get('/recrutement', [C\RecruitmentController::class, 'index'])->name('recruitment.index');
        Route::get('/recrutement/{candidate}', [C\RecruitmentController::class, 'show'])->name('recruitment.show');
        Route::get('/recrutement/documents/{document}', [C\RecruitmentController::class, 'document'])->name('recruitment.document');
    });
    Route::middleware('perm:recruitment.manage')->group(function () {
        Route::post('/recrutement', [C\RecruitmentController::class, 'store'])->name('recruitment.store');
        Route::post('/recrutement/{candidate}/statut', [C\RecruitmentController::class, 'status'])->name('recruitment.status');
        Route::post('/recrutement/{candidate}/documents', [C\RecruitmentController::class, 'upload'])->name('recruitment.upload');
    });
    Route::post('/recrutement/{candidate}/engager', [C\RecruitmentController::class, 'engage'])->middleware('perm:recruitment.engage')->name('recruitment.engage');

    // Patrimoine
    Route::get('/patrimoine', [C\AssetController::class, 'index'])->middleware('perm:assets.view')->name('assets.index');
    Route::get('/patrimoine/nouveau', [C\AssetController::class, 'create'])->middleware('perm:assets.manage')->name('assets.create');
    Route::post('/patrimoine', [C\AssetController::class, 'store'])->middleware('perm:assets.manage')->name('assets.store');
    Route::get('/patrimoine/{asset}', [C\AssetController::class, 'show'])->middleware('perm:assets.view')->name('assets.show');
    Route::middleware('perm:assets.manage')->group(function () {
        Route::put('/patrimoine/{asset}', [C\AssetController::class, 'update'])->name('assets.update');
        Route::post('/patrimoine/{asset}/transfert', [C\AssetController::class, 'transfer'])->name('assets.transfer');
        Route::post('/patrimoine/{asset}/etat', [C\AssetController::class, 'condition'])->name('assets.condition');
        Route::post('/patrimoine/{asset}/sortie', [C\AssetController::class, 'retire'])->name('assets.retire');
    });
    Route::middleware('perm:inventories.manage')->group(function () {
        Route::get('/inventaires', [C\InventoryController::class, 'index'])->name('inventories.index');
        Route::post('/inventaires', [C\InventoryController::class, 'store'])->name('inventories.store');
        Route::get('/inventaires/{inventory}', [C\InventoryController::class, 'show'])->name('inventories.show');
        Route::post('/inventaires/{inventory}/constat', [C\InventoryController::class, 'check'])->name('inventories.check');
        Route::post('/inventaires/{inventory}/cloturer', [C\InventoryController::class, 'close'])->name('inventories.close');
    });

    // Espace personnel
    Route::middleware('perm:self.view')->prefix('mon-espace')->group(function () {
        Route::get('/', [C\SelfServiceController::class, 'show'])->name('me.show');
        Route::get('/carriere', [C\SelfServiceController::class, 'career'])->name('me.career');
        Route::get('/finances', [C\SelfServiceController::class, 'finances'])->name('me.finances');
        Route::get('/situations', [C\SelfServiceController::class, 'situations'])->name('me.situations');
        Route::get('/corrections', [C\SelfServiceController::class, 'corrections'])->name('me.corrections');
        Route::post('/corrections', [C\SelfServiceController::class, 'submitCorrection'])->name('me.corrections.store');
        Route::get('/fiche', [C\SelfServiceController::class, 'sheet'])->name('me.sheet');
    });
    Route::get('/corrections', [C\CorrectionController::class, 'index'])->middleware('perm:corrections.handle')->name('corrections.index');
    Route::post('/corrections/{correction}', [C\CorrectionController::class, 'handle'])->middleware('perm:corrections.handle')->name('corrections.handle');

    // Éditions
    Route::get('/editions', [C\ReportController::class, 'index'])->middleware('perm:reports.view|reports.finance|assets.view')->name('reports.index');
    Route::get('/editions/{type}/{format}', [C\ReportController::class, 'export'])->whereIn('format', ['pdf', 'xlsx', 'html'])->name('reports.export');

    // Administration et paramétrage
    Route::middleware('perm:settings.manage')->group(function () {
        Route::get('/parametres', [C\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/parametres', [C\SettingsController::class, 'update'])->name('settings.update');
        Route::get('/parametres/{ref}', [C\ReferenceController::class, 'index'])->name('refs.index');
        Route::post('/parametres/{ref}', [C\ReferenceController::class, 'store'])->name('refs.store');
        Route::put('/parametres/{ref}/{id}', [C\ReferenceController::class, 'update'])->name('refs.update');
    });
    Route::get('/taux-de-primes', [C\ReferenceController::class, 'rates'])->middleware('perm:primes.configure')->name('rates.index');
    Route::post('/taux-de-primes', [C\ReferenceController::class, 'storeRate'])->middleware('perm:primes.configure')->name('rates.store');
    Route::put('/taux-de-primes/{rate}', [C\ReferenceController::class, 'updateRate'])->middleware('perm:primes.configure')->name('rates.update');
    Route::middleware('perm:users.manage')->group(function () {
        Route::get('/utilisateurs', [C\UserController::class, 'index'])->name('users.index');
        Route::post('/utilisateurs', [C\UserController::class, 'store'])->name('users.store');
        Route::get('/utilisateurs/{user}', [C\UserController::class, 'edit'])->name('users.edit');
        Route::put('/utilisateurs/{user}', [C\UserController::class, 'update'])->name('users.update');
        Route::post('/utilisateurs/{user}/roles', [C\UserController::class, 'assign'])->name('users.assign');
        Route::delete('/utilisateurs/{user}/roles/{assignment}', [C\UserController::class, 'unassign'])->name('users.unassign');
        Route::post('/utilisateurs/{user}/mot-de-passe', [C\UserController::class, 'resetPassword'])->name('users.password');
    });
    Route::get('/roles', [C\UserController::class, 'roles'])->middleware('perm:roles.manage|users.manage')->name('roles.index');
    Route::put('/roles/{role}', [C\UserController::class, 'updateRole'])->middleware('perm:roles.manage')->name('roles.update');
    Route::get('/audit', [C\AuditController::class, 'index'])->middleware('perm:audit.view')->name('audit.index');
    Route::get('/sauvegardes', [C\AuditController::class, 'backups'])->middleware('perm:backups.manage')->name('backups.index');
    Route::post('/sauvegardes', [C\AuditController::class, 'createBackup'])->middleware('perm:backups.manage')->name('backups.create');
    Route::get('/sauvegardes/{file}', [C\AuditController::class, 'downloadBackup'])->middleware('perm:backups.manage')->name('backups.download');
    Route::get('/jetons-api', [C\ApiTokenController::class, 'index'])->middleware('perm:api.tokens')->name('tokens.index');
    Route::post('/jetons-api', [C\ApiTokenController::class, 'store'])->middleware('perm:api.tokens')->name('tokens.store');
    Route::post('/jetons-api/{token}/revoquer', [C\ApiTokenController::class, 'revoke'])->middleware('perm:api.tokens')->name('tokens.revoke');
});
