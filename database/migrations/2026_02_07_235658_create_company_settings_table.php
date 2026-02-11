<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Company identity
            $table->string('company_name');
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->text('company_address')->nullable();

            // Branding
            $table->string('logo')->nullable(); // path to logo
            $table->string('primary_color')->default('#0d6efd'); // Bootstrap blue
            $table->string('secondary_color')->default('#6c757d');

            // Invoice info
            $table->string('invoice_prefix')->default('INV');
            $table->text('invoice_footer')->nullable();

            // Tax & Currency
            $table->string('tin')->nullable();
            $table->string('currency', 10)->default('GHS');
            $table->string('currency_symbol', 5)->default('₵');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
