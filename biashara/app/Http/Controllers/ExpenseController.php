<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\ExpenseService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request)
    {
        $this->authorizeAny(['expenses.view', 'expenses.create']);
        [$from, $to] = $this->period($request);
        $all = $request->user()->hasPermission('expenses.view');
        $query = Expense::query()->with(['category', 'method', 'user'])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->when(! $all, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category_id, fn ($q, $v) => $q->where('expense_category_id', $v))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('beneficiary', 'like', "%{$t}%")->orWhere('reason', 'like', "%{$t}%")->orWhere('number', 'like', "%{$t}%")));

        return view('expenses.index', [
            'expenses' => (clone $query)->latest('expense_date')->latest('id')->paginate(25)->withQueryString(),
            'approvedTotal' => (clone $query)->where('status', 'approved')->sum('amount'),
            'categories' => ExpenseCategory::query()->orderBy('name')->get(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function create(Request $request)
    {
        return view('expenses.create', [
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
            'hasSession' => (bool) $request->user()->openCashSession(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|gt:0',
            'beneficiary' => 'required|string|max:150',
            'reason' => 'required|string|max:255',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'attachment' => 'nullable|file|max:4096|mimes:pdf,jpg,jpeg,png',
        ]);
        $data['paid_from_cash'] = $request->boolean('paid_from_cash');
        unset($data['attachment']);
        if ($request->hasFile('attachment')) {
            // Stockage privé (disque « local ») : téléchargement uniquement via contrôleur avec contrôle d'accès.
            $data['attachment_path'] = $request->file('attachment')->store('expenses');
        }
        $expense = $this->service->create($data, $request->user());

        return redirect()->route('expenses.show', $expense)->with('success', $expense->status === 'approved' ? 'Dépense enregistrée et validée.' : 'Dépense enregistrée : en attente de validation.');
    }

    public function show(Request $request, Expense $expense)
    {
        $this->authorizeExpense($request, $expense);

        return view('expenses.show', ['expense' => $expense->load(['category', 'method', 'user', 'approver']), 'shop' => Settings::all()]);
    }

    public function attachment(Request $request, Expense $expense)
    {
        $this->authorizeExpense($request, $expense);
        abort_unless($expense->attachment_path && Storage::exists($expense->attachment_path), 404);

        return Storage::download($expense->attachment_path, $expense->number.'.'.pathinfo($expense->attachment_path, PATHINFO_EXTENSION));
    }

    public function approve(Request $request, Expense $expense)
    {
        $this->service->approve($expense, $request->user());

        return back()->with('success', 'Dépense validée.');
    }

    public function reject(Request $request, Expense $expense)
    {
        $data = $request->validate(['rejection_reason' => 'required|string|max:255']);
        $this->service->reject($expense, $request->user(), $data['rejection_reason']);

        return back()->with('success', 'Dépense rejetée.');
    }

    private function authorizeExpense(Request $request, Expense $expense): void
    {
        $user = $request->user();
        abort_unless($user->hasPermission('expenses.view') || $expense->user_id === $user->id, 403);
    }
}
