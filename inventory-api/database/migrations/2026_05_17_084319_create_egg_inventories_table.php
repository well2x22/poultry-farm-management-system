<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code');
            $table->enum('egg_size', ['Large', 'Medium', 'Small', 'Cracked']);
            $table->integer('quantity')->default(0);
            $table->date('received_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_inventories');
    }
};
