<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ProductService
{
    public function __construct(private StockService $stock) {}

    /** Création ; le stock initial éventuel est enregistré comme mouvement « initial » identifiable. */
    public function create(array $data, ?string $initialStock, User $user): Product
    {
        return DB::transaction(function () use ($data, $initialStock, $user) {
            $product = Product::create($data);
            $product->average_cost = Money::cost($product->cost_price);
            $product->save();
            if ($product->type === 'product' && Money::isPositive($initialStock ?? 0)) {
                $locked = $this->stock->lockProducts([$product->id])->get($product->id);
                $this->stock->move($locked, 'initial', Money::qty($initialStock), $user, $product, $product->reference, 'Stock initial', 'available', (string) $product->cost_price);
            } elseif ($product->type === 'service' && Money::isPositive($initialStock ?? 0)) {
                throw new BusinessException('Un service n\'a pas de stock.');
            }
            AuditService::log('product.create', $product, "Création de « {$product->name} »", null, $product->only(['reference', 'name', 'sale_price', 'cost_price']));

            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        if ($product->type !== ($data['type'] ?? $product->type) && $product->movements()->exists()) {
            throw new BusinessException('Le type ne peut pas être modifié : cet article a déjà des mouvements de stock.');
        }
        $old = $product->only(['name', 'sale_price', 'cost_price', 'is_active', 'alert_threshold']);
        $product->update($data);
        $new = $product->only(array_keys($old));
        if ($old != $new) {
            AuditService::log('product.update', $product, "Modification de « {$product->name} »", $old, $new);
        }

        return $product;
    }

    /**
     * Import contrôlé : toutes les lignes sont validées ; si une seule est invalide, rien n'est importé.
     * Colonnes attendues (1re ligne = en-têtes) :
     * reference, designation, type, categorie, unite, prix_achat, prix_vente, seuil_alerte, code_barres, stock_initial, description
     *
     * @return array{created:int, updated:int, errors:array<int,string>}
     */
    public function import(string $path, string $extension, User $user, bool $dryRun = false): array
    {
        $rows = $this->readRows($path, $extension);
        if (count($rows) < 2) {
            throw new BusinessException('Le fichier est vide ou ne contient que les en-têtes.');
        }
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), array_shift($rows));
        foreach (['reference', 'designation', 'categorie', 'prix_vente'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw new BusinessException("Colonne obligatoire manquante : « {$required} ».");
            }
        }

        $errors = [];
        $valid = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $row = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), null));
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            $row['type'] = in_array(strtolower((string) ($row['type'] ?? '')), ['service', 's'], true) ? 'service' : 'product';
            foreach (['prix_achat', 'prix_vente', 'seuil_alerte', 'stock_initial'] as $num) {
                if (isset($row[$num]) && $row[$num] !== '') {
                    $row[$num] = str_replace([' ', ','], ['', '.'], (string) $row[$num]);
                }
            }
            $v = Validator::make($row, [
                'reference' => 'required|string|max:50',
                'designation' => 'required|string|max:190',
                'categorie' => 'required|string|max:120',
                'prix_vente' => 'required|numeric|min:0',
                'prix_achat' => 'nullable|numeric|min:0',
                'seuil_alerte' => 'nullable|numeric|min:0',
                'stock_initial' => 'nullable|numeric|min:0',
                'code_barres' => 'nullable|max:64',
            ]);
            if ($v->fails()) {
                $errors[$line] = implode(' ', $v->errors()->all());

                continue;
            }
            if (isset($seen[$row['reference']])) {
                $errors[$line] = "Référence « {$row['reference']} » en double dans le fichier (ligne {$seen[$row['reference']]}).";

                continue;
            }
            $seen[$row['reference']] = $line;
            $valid[$line] = $row;
        }

        if ($errors || $dryRun) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors, 'valid' => count($valid)];
        }

        return DB::transaction(function () use ($valid, $user) {
            $created = $updated = 0;
            $defaultUnit = Unit::query()->orderBy('id')->firstOrFail();
            foreach ($valid as $row) {
                $category = Category::query()->firstOrCreate(['name' => $row['categorie']]);
                $unit = ! empty($row['unite'])
                    ? (Unit::query()->where('symbol', $row['unite'])->orWhere('name', $row['unite'])->first() ?? $defaultUnit)
                    : $defaultUnit;
                $attrs = [
                    'name' => $row['designation'],
                    'type' => $row['type'],
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'description' => $row['description'] ?? null,
                    'barcode' => ($row['code_barres'] ?? '') !== '' ? (string) $row['code_barres'] : null,
                    'cost_price' => Money::money($row['prix_achat'] ?? 0),
                    'sale_price' => Money::money($row['prix_vente']),
                    'alert_threshold' => Money::qty($row['seuil_alerte'] ?? 0),
                    'is_active' => true,
                ];
                $existing = Product::query()->where('reference', $row['reference'])->first();
                if ($existing) {
                    // Mise à jour des informations uniquement : le stock d'un article existant ne change jamais par import.
                    unset($attrs['type']);
                    $this->update($existing, $attrs);
                    $updated++;
                } else {
                    $this->create($attrs + ['reference' => $row['reference']], ($row['stock_initial'] ?? '') !== '' ? (string) $row['stock_initial'] : null, $user);
                    $created++;
                }
            }
            AuditService::log('product.import', null, "Import : {$created} créés, {$updated} mis à jour");

            return ['created' => $created, 'updated' => $updated, 'errors' => [], 'valid' => count($valid)];
        });
    }

    private function readRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'xlsx') {
            $reader = new XlsxReader;
        } elseif (in_array($extension, ['csv', 'txt'], true)) {
            $firstLine = (string) fgets(fopen($path, 'r'));
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $reader = new CsvReader($options);
        } else {
            throw new BusinessException('Format non pris en charge : utilisez .xlsx ou .csv.');
        }
        $rows = [];
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($c) => $c instanceof \DateTimeInterface ? $c->format('Y-m-d') : $c, $row->toArray());
                if (count($rows) > 5001) {
                    throw new BusinessException('Fichier trop volumineux : 5 000 lignes maximum par import.');
                }
            }
            break; // première feuille uniquement
        }
        $reader->close();

        return $rows;
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', 'û' => 'u', 'ô' => 'o', 'î' => 'i']);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        $aliases = ['nom' => 'designation', 'libelle' => 'designation', 'categorie_' => 'categorie', 'prix' => 'prix_vente', 'barcode' => 'code_barres', 'seuil' => 'seuil_alerte', 'stock' => 'stock_initial', 'cout' => 'prix_achat'];

        return $aliases[trim($h, '_')] ?? trim($h, '_');
    }
}
