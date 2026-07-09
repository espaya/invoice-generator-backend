<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        try {
            // Set memory limit for PDF generation
            ini_set('memory_limit', '512M');
            set_time_limit(120);
            
            // Generate PDF with optimized settings
            $pdf = Pdf::loadView("pdf.invoice", [
                "invoice" => $this->invoice,
                "company" => $this->company,
                "currency" => $this->currency,
            ])->setOptions([
                "isRemoteEnabled" => false, // Disable remote to prevent external requests
                "isHtml5ParserEnabled" => true,
                "defaultFont" => "sans-serif",
                "chroot" => public_path(), // Allow access to public directory
                "logOutputFile" => storage_path('logs/dompdf.log'),
                "enable_remote" => false,
                "enable_html5_parser" => true,
                "enable_javascript" => false,
            ]);

            return $this->subject("Invoice " . $this->invoice->invoice_number)
                ->view("mails.invoice_mail", [
                    "invoice" => $this->invoice,
                    "company" => $this->company,
                    "currency" => $this->currency,
                ])
                ->attachData(
                    $pdf->output(),
                    "invoice-" . $this->invoice->invoice_number . ".pdf",
                    [
                        "mime" => "application/pdf"
                    ]
                );
                
        } catch (\Exception $e) {
            Log::error("PDF Generation Error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            // Fallback: Send email without PDF
            return $this->subject("Invoice " . $this->invoice->invoice_number)
                ->view("mails.invoice_mail", [
                    "invoice" => $this->invoice,
                    "company" => $this->company,
                    "currency" => $this->currency,
                ]);
        }
    }
}