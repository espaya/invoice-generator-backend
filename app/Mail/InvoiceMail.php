<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $company;
    public $currency;

    public function __construct($invoice, $company, $currency)
    {
        $this->invoice = $invoice;
        $this->company = $company;
        $this->currency = $currency;
    }

    public function build()
    {
        $pdf = Pdf::loadView("pdf.invoice", [
            "invoice" => $this->invoice,
            "company" => $this->company,
            "currency" => $this->currency,
        ])->setOptions([
            "isRemoteEnabled" => true
        ]);

        $downloadUrl = config("app.frontend_url") . "/user/dashboard/invoice/" . $this->invoice->invoice_number;

        return $this->subject("Invoice " . $this->invoice->invoice_number)
            ->view("mails.invoice_mail", [
                "invoice" => $this->invoice,
                "company" => $this->company,
                "currency" => $this->currency,
                "downloadUrl" => $downloadUrl,
            ])
            ->attachData(
                $pdf->output(),
                "invoice-" . $this->invoice->invoice_number . ".pdf",
                [
                    "mime" => "application/pdf"
                ]
            );
    }
}
