@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 max-w-4xl">
    
    {{-- Навигация --}}
    <div class="mb-6">
        <a href="{{ route('habits.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
            ← Назад к списку привычек
        </a>
    </div>

    {{-- Шапка привычки --}}
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6 rounded-xl shadow-lg mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $habit->name }}</h1>
                <p class="text-blue-100">
                    Цель: {{ $habit->target_value ?? '—' }} {{ $habit->unit }}
                    <span class="ml-3 px-2 py-1 bg-white/20 rounded text-sm">
                        {{ $habit->metric_type === 'boolean' ? 'Факт' : ($habit->metric_type === 'integer' ? 'Число' : 'Время') }}
                    </span>
                </p>
            </div>
            
            {{-- Кнопка выполнения --}}
            <form action="{{ route('habits.log', $habit) }}" method="POST" class="habit-log-form" data-habit-id="{{ $habit->id }}">
                @csrf
                @if($habit->metric_type !== 'boolean')
                    <input type="number" step="0.1" name="value" placeholder="Значение" 
                           class="w-24 border rounded p-2 text-gray-800 mb-2" 
                           {{ $habit->is_completed_today ? 'disabled' : '' }}>
                @endif
                <button type="submit" 
                        class="w-full px-6 py-2 rounded font-bold transition 
                               {{ $habit->is_completed_today ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-500 hover:bg-green-600' }}"
                        {{ $habit->is_completed_today ? 'disabled' : '' }}>
                    {{ $habit->is_completed_today ? '✓ Выполнено сегодня' : '✓ Отметить выполнение' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Блок статистики --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-5 rounded-lg shadow border-l-4 border-green-500">
            <div class="text-sm text-gray-500 mb-1">Всего выполнений</div>
            <div class="text-3xl font-bold text-gray-800">{{ $totalCompletions }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg shadow border-l-4 border-orange-500">
            <div class="text-sm text-gray-500 mb-1">🔥 Текущая серия</div>
            <div class="text-3xl font-bold text-gray-800">{{ $streak }} <span class="text-sm text-gray-500">дн.</span></div>
        </div>
        <div class="bg-white p-5 rounded-lg shadow border-l-4 border-purple-500">
            <div class="text-sm text-gray-500 mb-1">🏆 Лучший результат</div>
            <div class="text-3xl font-bold text-gray-800">
                {{ $bestValue }} <span class="text-sm text-gray-500">{{ $habit->unit }}</span>
            </div>
        </div>
    </div>

    {{-- Heatmap (тепловая карта за последние 90 дней) --}}
    <div class="bg-white p-5 rounded-lg shadow mb-6">
        <h2 class="text-lg font-bold mb-4 text-gray-700">📅 Активность за последние 90 дней</h2>
        <div id="heatmap" class="flex flex-wrap gap-1"></div>
        <div class="flex items-center gap-2 mt-3 text-xs text-gray-500">
            <span>Меньше</span>
            <div class="w-3 h-3 bg-gray-200 rounded"></div>
            <div class="w-3 h-3 bg-green-300 rounded"></div>
            <div class="w-3 h-3 bg-green-500 rounded"></div>
            <div class="w-3 h-3 bg-green-700 rounded"></div>
            <span>Больше</span>
        </div>
    </div>

    {{-- История выполнения --}}
    <div class="bg-white p-5 rounded-lg shadow">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-700">📊 История выполнения</h2>
            <button id="load-more-btn" class="text-blue-600 hover:text-blue-800 text-sm hidden">
                Загрузить ещё →
            </button>
        </div>
        
        <div id="history-loader" class="text-center py-8 text-gray-400">
            ⏳ Загрузка истории...
        </div>
        
        <div id="history-list" class="space-y-2"></div>
        
        <div id="history-empty" class="hidden text-center py-8 text-gray-400">
            📭 История пуста. Отметьте первое выполнение!
        </div>
    </div>
</div>

<div id="react-notifications-root" data-user-id="{{ auth()->id() }}"></div>
@viteReactRefresh
@vite(['resources/js/app.jsx'])

<script>
const HABIT_ID = {{ $habit->id }};
const USER_ID = {{ auth()->id() }};
const API_BASE_URL = 'https://api.localhost/api';

let historyOffset = 0;
const LIMIT = 20;

// ===== ЗАГРУЗКА ИСТОРИИ ИЗ FASTAPI =====
async function loadHistory(reset = false) {
    if (reset) {
        historyOffset = 0;
        document.getElementById('history-list').innerHTML = '';
    }
    
    try {
        const url = `${API_BASE_URL}/habits/${HABIT_ID}/logs?user_id=${USER_ID}&limit=${LIMIT}&offset=${historyOffset}`;
        const response = await fetch(url);
        
        if (!response.ok) throw new Error('Ошибка API');
        
        const data = await response.json();
        const logs = data.logs || [];
        
        document.getElementById('history-loader').classList.add('hidden');
        
        if (logs.length === 0 && historyOffset === 0) {
            document.getElementById('history-empty').classList.remove('hidden');
            return;
        }
        
        const list = document.getElementById('history-list');
        logs.forEach(log => {
            const date = new Date(log.log_date);
            const dateStr = date.toLocaleDateString('ru-RU', { 
                day: 'numeric', month: 'long', year: 'numeric' 
            });
            
            const recordBadge = log.is_record 
                ? `<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full ml-2">🏆 Рекорд</span>` 
                : '';
            
            const html = `
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center font-bold">
                            ✓
                        </div>
                        <div>
                            <div class="font-medium text-gray-800">${dateStr}</div>
                            ${log.record_type ? `<div class="text-xs text-gray-500">${log.record_type}</div>` : ''}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-gray-800">
                            ${log.value} <span class="text-sm text-gray-500">{{ $habit->unit }}</span>
                        </div>
                        ${recordBadge}
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        });
        
        historyOffset += logs.length;
        
        // Показываем/скрываем кнопку "Загрузить ещё"
        const loadMoreBtn = document.getElementById('load-more-btn');
        if (logs.length < LIMIT) {
            loadMoreBtn.classList.add('hidden');
        } else {
            loadMoreBtn.classList.remove('hidden');
        }
        
    } catch (error) {
        console.error('Ошибка загрузки истории:', error);
        document.getElementById('history-loader').innerHTML = 
            '<div class="text-red-500">❌ Не удалось загрузить историю. Проверьте, что FastAPI запущен.</div>';
    }
}

// ===== HEATMAP (тепловая карта) =====
async function loadHeatmap() {
    try {
        // Загружаем последние 90 дней
        const url = `${API_BASE_URL}/habits/${HABIT_ID}/logs?user_id=${USER_ID}&limit=100&offset=0`;
        const response = await fetch(url);
        const data = await response.json();
        
        const heatmap = document.getElementById('heatmap');
        const today = new Date();
        const logDates = new Set((data.logs || []).map(l => l.log_date.split(' ')[0]));
        
        // Генерируем 90 дней назад
        for (let i = 89; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            
            const hasLog = logDates.has(dateStr);
            const color = hasLog ? 'bg-green-500' : 'bg-gray-200';
            
            const cell = document.createElement('div');
            cell.className = `w-3 h-3 ${color} rounded`;
            cell.title = `${dateStr}${hasLog ? ' ✓' : ''}`;
            heatmap.appendChild(cell);
        }
    } catch (error) {
        console.error('Ошибка heatmap:', error);
    }
}

// ===== ОБРАБОТКА ФОРМЫ ВЫПОЛНЕНИЯ =====
document.querySelector('.habit-log-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('button');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳';
    
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, data.is_record ? 'success' : 'info');
            btn.disabled = true;
            btn.textContent = '✓ Выполнено сегодня';
            btn.classList.remove('bg-green-500', 'hover:bg-green-600');
            btn.classList.add('bg-gray-400', 'cursor-not-allowed');
            
            // Перезагружаем историю и heatmap
            setTimeout(() => {
                loadHistory(true);
                loadHeatmap();
                // Обновляем статистику через перезагрузку страницы
                // (или можно сделать отдельный API для статистики)
            }, 500);
        } else {
            showNotification(data.message || 'Ошибка', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (error) {
        showNotification('Ошибка сети', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
});

// ===== КНОПКА "ЗАГРУЗИТЬ ЕЩЁ" =====
document.getElementById('load-more-btn')?.addEventListener('click', () => {
    const btn = document.getElementById('load-more-btn');
    btn.textContent = '⏳ Загрузка...';
    loadHistory(false).finally(() => {
        btn.textContent = 'Загрузить ещё →';
    });
});

// ===== УВЕДОМЛЕНИЯ =====
function showNotification(message, type = 'info') {
    const colors = { success: 'bg-green-600', error: 'bg-red-600', info: 'bg-blue-600' };
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg animate-bounce z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// ===== СТАРТ =====
document.addEventListener('DOMContentLoaded', () => {
    loadHistory();
    loadHeatmap();
});

// ===== WEBSOCKET: обновление при рекорде с другого устройства =====
window.addEventListener('new-record-ws', (e) => {
    showNotification(`🏆 ${e.detail.message}`, 'success');
    loadHistory(true);
    loadHeatmap();
});
</script>
@endsection