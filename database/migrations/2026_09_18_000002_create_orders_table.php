<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique()->nullable();
            $table->string('status', 32)->default('new')->index();
            $table->string('customer_name', 120);
            $table->string('phone', 30);
            $table->string('city', 120);
            $table->text('comment')->nullable();
            $table->decimal('subtotal_kzt', 12, 2);
            $table->decimal('total_kzt', 12, 2);
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
