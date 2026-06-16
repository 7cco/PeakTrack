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
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            
            // Денормализация: дублируем user_id для сверхбыстрых запросов в FastAPI без JOIN
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            
            $table->date('log_date');
            $table->decimal('value', 8, 2);
            $table->text('notes')->nullable();
            $table->boolean('is_record')->default(false);
            $table->string('record_type', 50)->nullable(); // 'new_streak', 'max_value' и т.д.
            $table->timestamps();

            // ИНДЕКС №1: Уникальность. Запрещает двойные записи за один день + ускоряет проверку
            $table->unique(['user_id', 'habit_id', 'log_date']);

            // ИНДЕКС №2: Ускоряет пагинацию и сортировку истории в FastAPI
            $table->index(['user_id', 'log_date']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
    }
};
