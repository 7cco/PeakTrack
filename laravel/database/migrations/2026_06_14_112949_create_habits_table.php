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
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            // Внешний ключ на таблицу users. cascadeOnDelete удалит привычки при удалении пользователя
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('name', 100);
            $table->string('metric_type', 20)->comment('boolean, integer, time');
            $table->decimal('target_value', 8, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false); // На будущее для соц. функций
            $table->timestamps();

            // ИНДЕКС: для быстрого получения списка активных привычек пользователя
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
