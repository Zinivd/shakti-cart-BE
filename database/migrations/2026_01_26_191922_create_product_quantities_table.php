<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_quantities', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('size');
            $table->string('unit')->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->foreign('product_id')
                ->references('product_id')
                ->on('products')
                ->onDelete('cascade');

            $table->unique(['product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_quantities');
    }
};
