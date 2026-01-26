<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->string('order_id');
    $table->string('user_id');

    $table->string('razorpay_order_id')->unique();
    $table->string('razorpay_payment_id')->nullable();
    $table->string('razorpay_signature')->nullable();

    $table->decimal('amount', 10, 2);
    $table->string('gateway')->default('razorpay');
    $table->string('status'); // CREATED | SUCCESS | FAILED

    $table->json('gateway_response')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
