<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('customer_email')->nullable();
            $table->string('delivery_method', 20)->default('delivery');
            $table->string('city')->nullable();
            $table->string('address', 500)->nullable();
            $table->text('comment')->nullable();
            $table->string('payment', 20)->default('card');
            $table->integer('subtotal');
            $table->integer('delivery_price')->default(0);
            $table->integer('total');
            $table->string('status', 20)->default('new');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->string('slug', 100);
            $table->string('name');
            $table->integer('price');
            $table->integer('qty');
            $table->integer('total');
            $table->string('image_url', 500)->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
    }
};
