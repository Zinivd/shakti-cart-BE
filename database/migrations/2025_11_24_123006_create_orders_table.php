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
    Schema::create('orders', function (Blueprint $table) {

        $table->id();
        $table->string('order_id')->unique();

        // USER DETAILS
        $table->string('user_id');
        $table->string('user_name');
        $table->string('user_phone');
        $table->string('user_email');

        // DELIVERY ADDRESS
        $table->string('address_building');
        $table->string('address_line1');
        $table->string('address_line2')->nullable();
        $table->string('city');
        $table->string('district');
        $table->string('state');
        $table->string('pincode');
        $table->string('landmark')->nullable();
        $table->string('address_type');

        // PAYMENT
        $table->string('payment_id')->nullable();
        $table->string('payment_mode'); // COD, UPI, CARD
        $table->string('payment_status')->default('PENDING');

        // AMOUNT
        $table->decimal('total_amount', 10, 2);

        // ORDER TRACKING STATUS
        $table->string('order_status')->default('PLACED');
        // PLACED, CONFIRMED, PACKED, SHIPPED, OUT_FOR_DELIVERY, DELIVERED, CANCELLED

        $table->timestamp('shipped_at')->nullable();
        $table->timestamp('delivered_at')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
