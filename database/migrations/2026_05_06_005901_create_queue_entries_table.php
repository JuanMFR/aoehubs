<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('joined_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
