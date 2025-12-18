<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->string('review_id')->unique();
            $table->string('product_id');

            // USER REVIEW
            $table->string('user_id')->nullable();

            // ADMIN REVIEW
            $table->boolean('is_admin')->default(false);
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('rating'); // 1–5

            $table->timestamps();

            // one user → one review per product
            $table->unique(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
