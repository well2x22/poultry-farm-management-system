<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egg_inventories', function (Blueprint $table) {
            $table->unique(['batch_code', 'egg_size'], 'unique_batch_egg_size');
        });
    }

    public function down(): void
    {
        Schema::table('egg_inventories', function (Blueprint $table) {
            $table->dropUnique('unique_batch_egg_size');
        });
    }
};
