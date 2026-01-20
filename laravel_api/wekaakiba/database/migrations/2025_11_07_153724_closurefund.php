<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->json('billages')->nullable();
            $table->foreignId('currency_id')->constrained('moneys');
            $table->enum('status', ['pending', 'validated', 'rejected'])->default('pending');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('closure_note')->nullable();
            $table->text('receiver_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closures');
    }
};
