<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Создаём пользователя (или находим существующего)
        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✅ Пользователь создан/найден: {$user->email} (ID: {$user->id})");

        // 2. Создаём 2 привычки
        $habit1 = Habit::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Бег'],
            [
                'metric_type' => 'integer',
                'target_value' => 5,
                'unit' => 'км',
                'is_active' => true,
            ]
        );

        $habit2 = Habit::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Медитация'],
            [
                'metric_type' => 'time',
                'target_value' => 15,
                'unit' => 'мин',
                'is_active' => true,
            ]
        );

        $this->command->info("✅ Привычка 1: {$habit1->name} (ID: {$habit1->id})");
        $this->command->info("✅ Привычка 2: {$habit2->name} (ID: {$habit2->id})");

        // 3. Удаляем старые логи для этих привычек (чтобы не было дублей при повторном запуске)
        HabitLog::where('habit_id', $habit1->id)->delete();
        HabitLog::where('habit_id', $habit2->id)->delete();

        // 4. Генерируем историю выполнения за последние 30 дней
        $today = Carbon::today();

        // Для "Бега" — выполняем через день с разными значениями
        for ($i = 0; $i < 30; $i++) {
            if ($i % 2 === 0) { // каждый чётный день
                $value = rand(3, 8); // от 3 до 8 км
                HabitLog::create([
                    'user_id' => $user->id,
                    'habit_id' => $habit1->id,
                    'log_date' => $today->copy()->subDays($i),
                    'value' => $value,
                    'is_record' => $value >= 7,
                    'record_type' => $value >= 7 ? 'max_value' : null,
                ]);
            }
        }

        // Для "Медитации" — выполняем каждый день
        for ($i = 0; $i < 30; $i++) {
            $value = rand(10, 25); // от 10 до 25 минут
            HabitLog::create([
                'user_id' => $user->id,
                'habit_id' => $habit2->id,
                'log_date' => $today->copy()->subDays($i),
                'value' => $value,
                'is_record' => $value >= 20,
                'record_type' => $value >= 20 ? 'max_value' : null,
            ]);
        }

        $this->command->info("✅ Создано записей в habit_logs: " . HabitLog::where('user_id', $user->id)->count());
        $this->command->info("🎉 Готово! Войдите как demo@example.com / password");
    }
}