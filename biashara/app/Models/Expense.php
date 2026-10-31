<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const STATUSES = ['pending' => 'En attente', 'approved' => 'Validée', 'rejected' => 'Rejetée'];

    protected $fillable = [
        'number', 'expense_category_id', 'expense_date', 'amount', 'beneficiary', 'reason', 'payment_method_id',
        'paid_from_cash', 'cash_session_id', 'attachment_path', 'status', 'user_id', 'approved_by', 'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'paid_from_cash' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
