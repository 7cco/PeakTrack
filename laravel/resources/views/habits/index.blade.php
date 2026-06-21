@extends('layouts.app')
@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-6">Мои привычки</h1>

    {{-- Форма создания --}}
    <form action="{{ route('habits.store') }}" method="POST" class="mb-8 p-4 bg-gray-100 rounded-lg" id="create-form">
        @csrf
        <div class="flex gap-4 flex-wrap">
            <input type="text" name="name" placeholder="Название (напр., Бег)" class="border p-2 rounded flex-1" required>
            <select name="metric_type" class="border p-2 rounded">
                <option value="boolean">Факт (Да/Нет)</option>
                <option value="integer">Число (км, шт)</option>
                <option value="time">Время (мин)</option>
            </select>
            <input type="text" name="unit" placeholder="Ед. изм. (км)" class="border p-2 rounded w-24">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Создать</button>
        </div>
    </form>

    {{-- Список привычек --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="habits-grid">
        @foreach($habits as $habit)
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500" data-habit-id="{{ $habit->id }}">
                <div class="flex justify-between items-start">
                    <div class="flex-1 cursor-pointer" onclick="window.location='{{ route('habits.show', $habit) }}'">
                                <h3 class="font-bold text-lg hover:text-blue-600">{{ $habit->name }}</h3>
                                <p class="text-sm text-gray-500">
                                Цель: {{ $habit->target_value ?? '-' }} {{ $habit->unit }}
                                </p>
                            </div>
                   
                    <form action="{{ route('habits.log', $habit) }}" method="POST" class="habit-log-form" data-habit-id="{{ $habit->id }}">
                        @csrf
                        @if($habit->metric_type !== 'boolean')
                            <input type="number" step="0.1" name="value" placeholder="Значение" class="w-20 border rounded mb-2 p-1 text-sm">
                        @endif
                        
                        <button type="submit" class="px-3 py-1 rounded text-sm transition {{ $habit->is_completed_today ? 'bg-gray-400 cursor-not-allowed text-white' : 'bg-green-500 hover:bg-green-600 text-white' }}" {{ $habit->is_completed_today ? 'disabled' : '' }}>
                            {{ $habit->is_completed_today ? '✓ Готово' : '✓ Выполнено' }}
                        </button>
                    </form>
                </div>
                
                {{-- Кнопки управления --}}
                <div class="flex gap-3 mt-3 pt-3 border-t">
                    <button class="edit-btn text-blue-500 hover:text-blue-700 text-sm font-medium" data-habit='@json($habit)'>✏️ Редактировать</button>
                    <button class="delete-btn text-red-500 hover:text-red-700 text-sm font-medium" data-habit-id="{{ $habit->id }}" data-habit-name="{{ $habit->name }}">🗑️ Удалить</button>
                </div>
            </div>
        @endforeach
        
        @if($habits->isEmpty())
            <p class="text-gray-500 col-span-full text-center" id="empty-message">У вас пока нет привычек. Создайте первую!</p>
        @endif
    </div>
</div>

{{-- Модальное окно редактирования --}}
<div id="edit-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold mb-4">Редактировать привычку</h2>
        <form id="edit-form">
            <input type="hidden" id="edit-habit-id" name="habit_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Название</label>
                <input type="text" id="edit-name" name="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Тип метрики</label>
                <select id="edit-metric-type" name="metric_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
                    <option value="boolean">Факт (Да/Нет)</option>
                    <option value="integer">Число (км, шт)</option>
                    <option value="time">Время (мин)</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Ед. изм.</label>
                <input type="text" id="edit-unit" name="unit" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<div id="react-notifications-root" data-user-id="{{ auth()->id() }}"></div>
@viteReactRefresh
@vite(['resources/js/app.jsx'])

<script>
// Уведомления
function showNotification(message, type = 'info') {
    const colors = { success: 'bg-green-600', error: 'bg-red-600', info: 'bg-blue-600' };
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg animate-bounce z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// Добавление привычки в DOM (используется и локально, и через WS)
function addHabitToDOM(habit) {
    const habitsGrid = document.getElementById('habits-grid');
    const emptyMessage = document.getElementById('empty-message');
    if (emptyMessage) emptyMessage.remove();

    const habitHTML = `
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500" data-habit-id="${habit.id}">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-bold text-lg">${habit.name}</h3>
                    <p class="text-sm text-gray-500">Цель: ${habit.target_value || '-'} ${habit.unit || ''}</p>
                </div>
                <form action="/habits/${habit.id}/log" method="POST" class="habit-log-form" data-habit-id="${habit.id}">
                    <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
                    ${habit.metric_type !== 'boolean' ? `<input type="number" step="0.1" name="value" placeholder="Значение" class="w-20 border rounded mb-2 p-1 text-sm">` : ''}
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition">✓ Выполнено</button>
                </form>
            </div>
            <div class="flex gap-3 mt-3 pt-3 border-t">
                <button class="edit-btn text-blue-500 hover:text-blue-700 text-sm font-medium" data-habit='${JSON.stringify(habit)}'>✏️ Редактировать</button>
                <button class="delete-btn text-red-500 hover:text-red-700 text-sm font-medium" data-habit-id="${habit.id}" data-habit-name="${habit.name}">🗑️ Удалить</button>
            </div>
        </div>`;
    habitsGrid.insertAdjacentHTML('beforeend', habitHTML);
}

// --- ЛОГИКА СОЗДАНИЯ ---
document.getElementById('create-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; 
    btn.textContent = '⏳';

    try {
        const res = await fetch(this.action, {
            method: 'POST', 
            body: formData,
            headers: { 
                'X-Requested-With': 'XMLHttpRequest', 
                'Accept': 'application/json' 
            }
        });
        const data = await res.json();
    } catch (err) { 
        showNotification('Ошибка сети', 'error'); 
    }
});

// --- ЛОГИКА ВЫПОЛНЕНИЯ ---
document.getElementById('habits-grid').addEventListener('submit', async function(e) {
    if (e.target.classList.contains('habit-log-form')) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button');
        const originalText = btn.textContent;
        btn.disabled = true; 
        btn.textContent = '⏳';

        try {
            const res = await fetch(form.action, {
                method: 'POST', 
                body: new FormData(form),
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest', 
                    'Accept': 'application/json' 
                }
            });
            const data = await res.json();
            
            if (data.success) {
                btn.textContent = '✓ Готово';
                btn.classList.remove('bg-green-500', 'hover:bg-green-600');
                btn.classList.add('bg-gray-400', 'cursor-not-allowed');
                btn.disabled = true;
            } else {
                showNotification(data.message || 'Ошибка', 'error');
                btn.disabled = false; 
                btn.textContent = originalText;
            }
        } catch (err) { 
            showNotification('Ошибка сети', 'error'); 
            btn.disabled = false; 
            btn.textContent = originalText; 
        }
    }
});

// --- ЛОГИКА РЕДАКТИРОВАНИЯ И УДАЛЕНИЯ ---
const editModal = document.getElementById('edit-modal');
const editForm = document.getElementById('edit-form');

document.addEventListener('click', async function(e) {
    // Открытие модалки
    if (e.target.closest('.edit-btn')) {
        const habit = JSON.parse(e.target.closest('.edit-btn').dataset.habit);
        document.getElementById('edit-habit-id').value = habit.id;
        document.getElementById('edit-name').value = habit.name;
        document.getElementById('edit-metric-type').value = habit.metric_type;
        document.getElementById('edit-unit').value = habit.unit || '';
        editModal.classList.remove('hidden'); editModal.classList.add('flex');
    }
    // Удаление
    if (e.target.closest('.delete-btn')) {
        const btn = e.target.closest('.delete-btn');
        if (confirm(`Удалить привычку "${btn.dataset.habitName}"?`)) {
            btn.disabled = true; btn.textContent = '⏳';
            try {
                const res = await fetch(`/habits/${btn.dataset.habitId}`, {
                    method: 'POST', // Laravel spoofing
                    body: new URLSearchParams({ '_method': 'DELETE', '_token': document.querySelector('meta[name="csrf-token"]').content }),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await res.json();
            } catch (err) { showNotification('Ошибка сети', 'error'); btn.disabled = false; btn.textContent = '🗑️ Удалить'; }
        }
    }
});

document.getElementById('cancel-edit').addEventListener('click', () => {
    editModal.classList.add('hidden'); editModal.classList.remove('flex');
});

editForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const habitId = document.getElementById('edit-habit-id').value;
    const formData = new FormData(this);
    formData.append('_method', 'PUT'); // Laravel spoofing

    try {
        const res = await fetch(`/habits/${habitId}`, {
            method: 'POST', body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const data = await res.json();
    } catch (err) { showNotification('Ошибка сети', 'error'); }
});

// --- СЛУШАТЕЛИ WEBSOCKET СОБЫТИЙ ---
window.addEventListener('habit-ws-created', (e) => addHabitToDOM(e.detail));
window.addEventListener('habit-ws-updated', (e) => {
    const card = document.querySelector(`[data-habit-id="${e.detail.id}"]`);
    if (card) {
        card.querySelector('h3').textContent = e.detail.name;
        card.querySelector('.text-sm.text-gray-500').textContent = `Цель: ${e.detail.target_value || '-'} ${e.detail.unit || ''}`;
        card.querySelector('.edit-btn').dataset.habit = JSON.stringify(e.detail);
    }
});
window.addEventListener('habit-ws-deleted', (e) => {
    document.querySelector(`[data-habit-id="${e.detail.id}"]`)?.remove();
});
</script>
@endsection