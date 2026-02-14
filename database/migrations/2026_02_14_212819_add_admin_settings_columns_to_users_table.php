<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->boolean('is_blocked')->default(false);

            $table->boolean('force_password_reset')->default(false);

            $table->boolean('can_create_invoice')->default(true);
            $table->boolean('can_download_pdf')->default(true);
            $table->boolean('can_send_email')->default(true);

            $table->longText('admin_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'is_blocked',
                'force_password_reset',
                'can_create_invoice',
                'can_download_pdf',
                'can_send_email',
                'admin_notes',
            ]);
        });
    }
};
