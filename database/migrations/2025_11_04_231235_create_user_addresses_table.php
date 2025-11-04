<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auth_user_id'); // FK to auth_users
            $table->string('name');
            $table->string('phone');
            $table->string('building_name');
            $table->string('address_1');
            $table->string('address_2')->nullable();
            $table->string('city');
            $table->string('district');
            $table->string('state');
            $table->string('pincode');
            $table->string('landmark')->nullable();
            $table->enum('address_type', ['home', 'work']);
            $table->timestamps();

            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
