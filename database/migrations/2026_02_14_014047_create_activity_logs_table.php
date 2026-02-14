<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action'); // e.g "updated_user"
            $table->string('model')->nullable(); // e.g "User"
            $table->unsignedBigInteger('model_id')->nullable();

            $table->json('changes')->nullable(); // stores dirty fields
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
