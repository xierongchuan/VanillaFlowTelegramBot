<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseRequest extends Model
{
    use HasFactory;

    protected $table = 'expense_requests';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'requester_id',
        'title',
        'description',
        'amount',
        'issued_amount',
        'currency',
        'status',
        'director_id',
        'cashier_id',
        'director_comment',
        'company_id',
        'approved_at',
        'issued_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'issued_amount' => 'float',
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function director()
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function approvals()
    {
        return $this->hasMany(ExpenseApproval::class, 'expense_request_id');
    }
}
