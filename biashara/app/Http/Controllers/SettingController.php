<?php

namespace App\Http\Controllers;

use App\Models\DocumentSequence;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index(BackupService $backups)
    {
        return view('settings.index', [
            'settings' => Settings::all(),
            'paymentMethods' => PaymentMethod::query()->orderBy('sort_order')->get(),
            'taxes' => Tax::query()->orderBy('name')->get(),
            'sequences' => DocumentSequence::query()->orderBy('id')->get(),
            'expenseCategories' => ExpenseCategory::query()->orderBy('name')->get(),
            'backups' => $backups->list(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'shop_name' => 'required|string|max:120',
            'shop_address' => 'nullable|string|max:255',
            'shop_phone' => 'nullable|string|max:80',
            'shop_email' => 'nullable|email|max:150',
            'shop_tax_id' => 'nullable|string|max:80',
            'logo' => 'nullable|image|max:1024',
            'currency_code' => 'required|string|size:3',
            'currency_symbol' => 'required|string|max:5',
            'secondary_currency_code' => 'nullable|string|size:3',
            'secondary_currency_symbol' => 'nullable|string|max:5',
            'exchange_rate' => 'required|numeric|gt:0',
            'timezone' => 'required|timezone',
            'receipt_format' => 'required|in:a4,80,58',
            'receipt_footer' => 'nullable|string|max:255',
            'default_alert_threshold' => 'required|numeric|min:0',
            'max_discount_percent' => 'required|numeric|min:0|max:100',
            'offline_max_sale_amount' => 'required|numeric|min:0',
            'backup_mysqldump_path' => 'nullable|string|max:255',
        ]);
        foreach (['secondary_currency_enabled', 'allow_negative_stock', 'offline_sales_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        unset($data['logo']);
        if ($request->hasFile('logo')) {
            $data['shop_logo'] = $request->file('logo')->store('branding', 'public');
        }
        $old = array_intersect_key(Settings::all(), $data);
        Settings::set(array_map(fn ($v) => $v ?? '', $data));
        AuditService::log('settings.update', null, 'Modification des paramètres', $old, $data);

        return back()->with('success', 'Paramètres enregistrés.');
    }

    public function storePaymentMethod(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'kind' => 'required|in:cash,electronic']);
        $method = PaymentMethod::create($data + ['code' => Str::slug($data['name'], '_').'_'.Str::lower(Str::random(4)), 'is_active' => true, 'sort_order' => 50]);
        AuditService::log('settings.payment_method', $method, "Mode de paiement « {$method->name} » ajouté");

        return back()->with('success', 'Mode de paiement ajouté.');
    }

    public function togglePaymentMethod(PaymentMethod $method)
    {
        $method->update(['is_active' => ! $method->is_active]);
        AuditService::log('settings.payment_method', $method, ($method->is_active ? 'Activation' : 'Désactivation')." du mode « {$method->name} »");

        return back()->with('success', 'Mode de paiement mis à jour.');
    }

    public function storeTax(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'rate' => 'required|numeric|min:0|max:100']);
        $tax = Tax::create($data + ['is_active' => true]);
        AuditService::log('settings.tax', $tax, "Taxe « {$tax->name} » ({$tax->rate} %) ajoutée");

        return back()->with('success', 'Taxe ajoutée. Associez-la aux articles concernés.');
    }

    public function toggleTax(Tax $tax)
    {
        $tax->update(['is_active' => ! $tax->is_active]);
        AuditService::log('settings.tax', $tax, ($tax->is_active ? 'Activation' : 'Désactivation')." de la taxe « {$tax->name} »");

        return back()->with('success', 'Taxe mise à jour (sans effet sur les ventes passées).');
    }

    public function storeExpenseCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120|unique:expense_categories,name']);
        ExpenseCategory::create($data + ['is_active' => true]);

        return back()->with('success', 'Catégorie de dépense ajoutée.');
    }

    public function updateSequences(Request $request)
    {
        $data = $request->validate([
            'sequences' => 'required|array',
            'sequences.*.prefix' => 'required|alpha_dash|max:20',
            'sequences.*.padding' => 'required|integer|min:3|max:10',
        ]);
        foreach ($data['sequences'] as $id => $row) {
            DocumentSequence::query()->whereKey($id)->update(['prefix' => strtoupper($row['prefix']), 'padding' => $row['padding']]);
        }
        AuditService::log('settings.sequences', null, 'Modification de la numérotation des documents');

        return back()->with('success', 'Numérotation mise à jour (les documents existants gardent leur numéro).');
    }

    public function backup(BackupService $backups)
    {
        $file = $backups->create();
        AuditService::log('backup.create', null, "Sauvegarde créée : {$file}");

        return back()->with('success', "Sauvegarde créée : {$file}");
    }

    public function downloadBackup(string $file, BackupService $backups)
    {
        $path = $backups->path($file);
        AuditService::log('backup.download', null, "Téléchargement de la sauvegarde {$file}");

        return response()->download($path);
    }
}
