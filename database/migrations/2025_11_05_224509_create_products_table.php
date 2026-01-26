<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique();
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->string('category_id');
            $table->string('category_name');
            $table->string('sub_category_id')->nullable();
            $table->string('sub_category_name')->nullable();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->json('size_unit')->nullable(); // e.g. [{ "size": "L", "unit": "piece" }]
           $table->integer('total_quantity')->default(0);
            $table->decimal('actual_price', 10, 2);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('selling_price', 10, 2);
            $table->string('product_list_type')->nullable(); // e.g. "featured", "new", "sale"
            $table->json('images')->nullable(); // up to 5 image names
            $table->json('product_specification')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('category_id')->on('product_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
