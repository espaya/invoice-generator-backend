<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $invoice;
    public array $company;
    public string $currency;

    public function __construct(object $invoice, array $company, string $currency)
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

        return $this->subject("Invoice " . $this->invoice->invoice_number)
            ->view("mails.invoice_mail", [
                "invoice" => $this->invoice,
                "company" => $this->company,
                "currency" => $this->currency,
                // "downloadUrl" => $downloadUrl,
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
