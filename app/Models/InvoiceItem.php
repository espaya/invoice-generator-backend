<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 
        'description', 
        'quantity', 
        'unit_price', 
        'total', 
        'image'
        ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Accessor for full image url
    public function getImageUrlAttribute()
    {
        if($this->image){
            return url($this->image);
        }
        return null;
    }

}
