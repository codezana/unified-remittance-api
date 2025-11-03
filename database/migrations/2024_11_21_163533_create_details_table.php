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
        Schema::create('details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('item');
            $table->string('invoice_number');
            $table->string('container');
            $table->date('date');
            $table->decimal('price', 20, 0)->default(0);
            $table->decimal('price_dollar', 20, 0)->default(0);
            $table->string('sender_company');
            $table->string('receiver_company');
            $table->longText('note')->nullable();
            $table->json('before_bank')->nullable();
            $table->json('after_bank')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('details');
    }
};
