<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'logo',
        'primary_color',
        'secondary_color',
        'invoice_prefix',
        'invoice_footer',
        'tin',
        'currency',
        'currency_symbol',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
