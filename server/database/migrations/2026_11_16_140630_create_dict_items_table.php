<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dict_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dict_type_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->string('value', 64);
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->unique(['dict_type_id', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dict_items');
    }
};
