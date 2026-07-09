<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanySetting::create(
            [
                'invoice_notes' => ' <h5><u>TERMS AND CONDITIONS:</u></h5>
            (1) Payment Terms: (i) 100% payment after delivery <br/>
            (2) Offer Validity: Our offer is valid for 30 calender days subject exchange rate fluctuation of $1.00 = GHS 10.40 +/-3% <br/>
            (3) Contract Coming into Force: (i) Receipt of signed purchase order   (ii) Receipt of the advanced payment (if applicable)<br/>
            (4) Delivery Period: Within 10 - 15 working days from date of contract coming into force. <br/>
            (5) Incoterms: DDP Customer Location, Ghana <br/>
            (6) Local Taxes: (i) Levies - 6% (ii) VAT - 15%. Any levies and taxes required by the Tax Authority shall be paid by the Client. <br/>
            (7) Waranty: 12 Months Warranty (warranty is only for product/manufacturing defects; customer shall be charged for operation defects)',
            ]
        );
    }
}
