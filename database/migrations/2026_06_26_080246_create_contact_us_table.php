<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_us', function (Blueprint $table) {
            $table->id();
            $table->string('contact_names');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('business_type')->nullable();
            $table->text('contact_message');
            $table->timestamp('contact_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_us');
    }
};
