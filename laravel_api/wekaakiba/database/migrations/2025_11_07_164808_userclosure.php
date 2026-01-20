<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('currency_id')->constrained('moneys')->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0);   
            $table->decimal('total_received', 15, 2)->default(0); 
            $table->integer('closure_count')->default(0);        
            $table->date('closure_date');
            $table->timestamp('received_at')->nullable();
            $table->text('closure_note')->nullable();
            $table->text('receiver_note')->nullable(); 
            $table->enum('status', ['pending', 'validated', 'rejected'])->default('pending');                        
            $table->timestamps();
            $table->unique(['user_id', 'currency_id', 'closure_date'], 'user_currency_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_closures');
    }
};
