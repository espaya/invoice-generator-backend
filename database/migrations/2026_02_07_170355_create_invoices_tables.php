<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // who created the invoice
            $table->foreignId('customer_id')->constrained()->onDelete('cascade'); // billed to
            $table->string('invoice_number')->unique(); // auto-generated
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['paid', 'pending', 'overdue'])->default('pending');
            $table->decimal('tax_percent', 5, 2)->default(0); // tax on total
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
