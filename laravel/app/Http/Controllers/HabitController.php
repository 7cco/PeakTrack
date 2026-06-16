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
        // Берем только активные привычки текущего пользователя
        $habits = Habit::where('user_id', Auth::id())
                       ->where('is_active', true)
                       ->withCount(['logs as current_streak' => function ($query) {
                           // Здесь можно добавить сложную логику подсчета серии, 
                           // для начала просто вернем все логи
                       }]) 
                       ->get();

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

        $request->user()->habits()->create($validated);

        return redirect()->route('habits.index')->with('success', 'Привычка создана!');
    }

    // 3. Логирование выполнения (Ядро приложения)
    public function log(Request $request, Habit $habit)
    {
        // Проверка: принадлежит ли привычка пользователю
        if ($habit->user_id !== Auth::id()) {
            abort(403);
        }

        $now = Carbon::now();

        // Проверка на дубликат (защита от двойного клика)
        $exists = HabitLog::where('user_id', Auth::id())
                          ->where('habit_id', $habit->id)
                          ->whereDate('log_date', $now)
                          ->exists();

        if ($exists) {
            return back()->with('error', 'Вы уже отметили эту привычку сегодня!');
        }

        // Определяем значение (для boolean привычек это 1, для остальных - из ввода или target_value)
        $value = $habit->metric_type === 'boolean' ? 1 : ($request->input('value', $habit->target_value) ?? 1);

        // --- УПРОЩЕННАЯ ЛОГИКА ПРОВЕРКИ РЕКОРДА ---
        // В реальности здесь нужен сервис для проверки серии дней или макс. значения
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
            
            // Публикуем в канал, который слушает FastAPI
            Redis::publish('peaktrack.events', json_encode($payload));
        }

        // Возвращаем ответ. 
        // Совет: если используете Alpine.js или HTMX, можно вернуть только часть HTML или JSON, 
        // чтобы не перезагружать всю страницу.
        return back()->with('success', $isRecord ? "🏆 " . $recordMessage : 'Привычка выполнена!');
    }
}