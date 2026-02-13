<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('photo')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('photo')->nullable(false)->change();
        });
    }
};
