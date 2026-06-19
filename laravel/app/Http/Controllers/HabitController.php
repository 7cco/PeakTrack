<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class HabitController extends Controller
{
    // 1. Отображение списка
    public function index()
    {
        $today = Carbon::today();
        
        $habits = Habit::where('user_id', Auth::id())
                    ->where('is_active', true)
                    ->get()
                    ->map(function($habit) use ($today) {
                        // Проверяем, выполнена ли привычка сегодня
                        $isCompletedToday = HabitLog::where('habit_id', $habit->id)
                                                    ->where('user_id', Auth::id())
                                                    ->whereDate('log_date', $today)
                                                    ->exists();
                        
                        $habit->is_completed_today = $isCompletedToday;
                        return $habit;
                    });

        return view('habits.index', compact('habits'));
    }

    // 2. Создание привычки
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'metric_type' => 'required|in:boolean,integer,time',
            'target_value' => 'nullable|numeric',
            'unit' => 'nullable|string|max:20',
        ]);

        $habit = $request->user()->habits()->create($validated);

        // Для AJAX запросов возвращаем JSON
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'habit' => [
                    'id' => $habit->id,
                    'name' => $habit->name,
                    'metric_type' => $habit->metric_type,
                    'target_value' => $habit->target_value,
                    'unit' => $habit->unit,
                    'is_completed_today' => false,
                ]
            ]);
        }

        return redirect()->route('habits.index')->with('success', 'Привычка создана!');
    }

    // 3. Логирование выполнения
    public function log(Request $request, Habit $habit)
    {
        // Проверка: принадлежит ли привычка пользователю
        if ($habit->user_id !== Auth::id()) {
            abort(403);
        }

        $today = Carbon::today();

        // Проверка на дубликат
        $exists = HabitLog::where('user_id', Auth::id())
                          ->where('habit_id', $habit->id)
                          ->whereDate('log_date', $today)
                          ->exists();

        if ($exists) {
            return back()->with('error', 'Вы уже отметили эту привычку сегодня!');
        }

        // Определяем значение (для boolean привычек это 1, для остальных - из ввода или target_value)
        $value = $habit->metric_type === 'boolean' ? 1 : ($request->input('value', $habit->target_value) ?? 1);

        // --- УПРОЩЕННАЯ ЛОГИКА ПРОВЕРКИ РЕКОРДА ---
        $isRecord = false;
        $recordType = null;
        $recordMessage = '';

        if ($habit->metric_type !== 'boolean') {
            $maxPrev = HabitLog::where('habit_id', $habit->id)->max('value') ?? 0;
            if ($value > $maxPrev) {
                $isRecord = true;
                $recordType = 'max_value';
                $recordMessage = "Новый максимум: {$value} {$habit->unit}!";
            }
        } else {
            // Логика проверки серии (streak) реализуется отдельно
            $isRecord = true; // Для примера считаем каждое выполнение boolean привычки событием
            $recordType = 'streak';
            $recordMessage = "Отлично! Продолжай в том же духе!";
        }

        // 1. Сохраняем в БД
        $log = HabitLog::create([
            'user_id' => Auth::id(),
            'habit_id' => $habit->id,
            'log_date' => $today,
            'value' => $value,
            'is_record' => $isRecord,
            'record_type' => $recordType,
        ]);

        \Log::info('=== Habit Log Debug ===', [
            'habit_id' => $habit->id,
            'habit_name' => $habit->name,
            'value' => $value,
            'is_record' => $isRecord,
            'record_type' => $recordType,
            'record_message' => $recordMessage,
        ]);

        // 2. Если это рекорд, отправляем событие в Redis для FastAPI
        if ($isRecord) {
            $payload = [
                'event' => 'new_record',
                'user_id' => Auth::id(),
                'habit_name' => $habit->name,
                'message' => $recordMessage,
                'value' => $value,
                'unit' => $habit->unit,
            ];
            \Log::info('📤 Публикуем в Redis:', $payload);
            Redis::publish('peaktrack.events', json_encode($payload));
            \Log::info('✅ Опубликовано в Redis!');
        } else {
            \Log::warning('️ is_record = false, в Redis НЕ отправлено');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $isRecord ? "🏆 " . $recordMessage : 'Привычка выполнена!',
                'is_record' => $isRecord,
            ]);
        }
    }
}