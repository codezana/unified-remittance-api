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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('invoice_number', 20, 0);
            $table->decimal('discount', 20, 0)->nullable();
            $table->date('date')->nullable();
            $table->decimal('total_amount', 20, 0); // Total amount for the sale
            $table->decimal('total_after_discount', 20, 0)->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('pcs')->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('meter', 20, 0)->nullable();

            $table->decimal('yard', 20, 0)->nullable();
            $table->decimal('cm', 20, 0)->nullable();
            $table->decimal('carton', 20, 0)->nullable();
            $table->decimal('amount', 20, 0); // Total amount for the item
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
