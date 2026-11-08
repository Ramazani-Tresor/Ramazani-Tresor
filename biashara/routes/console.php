<?php

use App\Models\Product;
use App\Services\BackupService;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;

Artisan::command('app:backup', function (BackupService $backups) {
    $file = $backups->create();
    $this->info("Sauvegarde créée : storage/app/backups/{$file}");
})->purpose('Sauvegarder la base de données');

Artisan::command('app:check-stock', function (StockService $stock) {
    $errors = 0;
    foreach (Product::query()->where('type', 'product')->cursor() as $product) {
        foreach (['available' => 'stock_quantity', 'damaged' => 'damaged_quantity'] as $bucket => $column) {
            $computed = $stock->recomputedQuantity($product, $bucket);
            if (Money::cmp($computed, $product->{$column}) !== 0) {
                $errors++;
                $this->error("{$product->reference} ({$bucket}) : fiche {$product->{$column}} / mouvements {$computed}");
            }
        }
    }
    $errors ? $this->warn("{$errors} incohérence(s) détectée(s).") : $this->info('Stock cohérent avec le journal des mouvements.');

    return $errors ? 1 : 0;
})->purpose('Vérifier que le stock de chaque article correspond à la somme de ses mouvements');
