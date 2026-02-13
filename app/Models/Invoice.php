<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'tax_percent',
        'subtotal',
        'total',
        'notes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'total' => 'decimal:2',
    ];
}
