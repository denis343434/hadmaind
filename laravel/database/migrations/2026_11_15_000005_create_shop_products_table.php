<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('shop_categories')->cascadeOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->integer('price');
            $table->integer('old_price')->nullable();
            $table->string('badge', 50)->nullable();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('specs');
            $table->string('image_url', 500)->nullable();
            $table->string('tone', 20)->default('');
            $table->decimal('zoom', 4, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
