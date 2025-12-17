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
            $table->string('order_number', 50)->unique()->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('quantity')->nullable();
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'completed'])->default('pending');
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->nullable();

            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['customer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

