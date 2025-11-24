<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('product_subcategories', function (Blueprint $table) {
        $table->id();
        $table->string('sub_category_id')->unique();
        $table->string('sub_category_name');
        $table->string('category_id'); // linked with product_categories.category_id
        $table->foreign('category_id')->references('category_id')->on('product_categories')->onDelete('cascade');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_subcategories');
    }
};
