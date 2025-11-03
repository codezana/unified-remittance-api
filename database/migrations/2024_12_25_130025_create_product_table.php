<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->decimal('qty', 20, 0)->nullable();
            $table->decimal('meter', 20, 0)->nullable();
            $table->decimal('unit_price', 20, 0);
            $table->decimal('sell_price', 20, 0);
            $table->decimal('sold_meter', 20, 0)->nullable();
            $table->decimal('sold_qty', 20, 0)->nullable();
            $table->decimal('amount', 20, 0);
            $table->decimal('sold', 20, 0)->nullable();
            $table->decimal('profit', 20, 0)->nullable();
            $table->string('supplier_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
