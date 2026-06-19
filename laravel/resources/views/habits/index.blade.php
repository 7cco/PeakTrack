@extends('layouts.app') {{-- Ваш основной layout --}}

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-6">Мои привычки</h1>

    {{-- Форма создания новой привычки --}}
    <form action="{{ route('habits.store') }}" method="POST" class="mb-8 p-4 bg-gray-100 rounded-lg">
        @csrf
        <div class="flex gap-4">
            <input type="text" name="name" placeholder="Название (напр., Бег)" class="border p-2 rounded flex-1" required>
            <select name="metric_type" class="border p-2 rounded">
                <option value="boolean">Факт (Да/Нет)</option>
                <option value="integer">Число (км, шт)</option>
                <option value="time">Время (мин)</option>
            </select>
            <input type="text" name="unit" placeholder="Ед. изм. (км)" class="border p-2 rounded w-24">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Создать</button>
        </div>
    </form>

    {{-- Список привычек --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($habits as $habit)
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-lg">{{ $habit->name }}</h3>
                        <p class="text-sm text-gray-500">
                            Цель: {{ $habit->target_value }} {{ $habit->unit }}
                        </p>
                    </div>
                    
                    {{-- Кнопка выполнения --}}
                    <form action="{{ route('habits.log', $habit) }}" method="POST" class="habit-log-form" data-habit-id="{{ $habit->id }}">
                        @csrf
                        @if($habit->metric_type !== 'boolean')
                            <input type="number" step="0.1" name="value" placeholder="Значение" class="w-20 border rounded mb-2 p-1 text-sm">
                        @endif
                        
                        <button 
                            type="submit" 
                            class="px-3 py-1 rounded text-sm transition {{ $habit->is_completed_today ? 'bg-gray-400 cursor-not-allowed text-white' : 'bg-green-500 hover:bg-green-600 text-white' }}"
                            {{ $habit->is_completed_today ? 'disabled' : '' }}
                        >
                            {{ $habit->is_completed_today ? '✓ Готово' : '✓ Выполнено' }}
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
        
        @if($habits->isEmpty())
            <p class="text-gray-500 col-span-full text-center">У вас пока нет привычек. Создайте первую!</p>
        @endif
    </div>
</div>

{{-- уведомлений с бэкенда --}}
@if(session('success'))
    <div class="fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg animate-bounce">
        {{ session('success') }}
    </div>
@endif
<div id="react-notifications-root" data-user-id="{{ auth()->id() }}"></div>

@viteReactRefresh
@vite(['resources/js/app.jsx'])
<script>
// Обработка формы создания привычки
const createForm = document.querySelector('form[action*="habits.store"]');
if (createForm) {
    createForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(createForm);
        const submitButton = createForm.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        
        submitButton.disabled = true;
        submitButton.textContent = '⏳ Создание...';
        
        try {
            const response = await fetch(createForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success || data.habit) {
                // Очищаем форму
                createForm.reset();
                
                // Добавляем новую привычку в список
                addHabitToDOM(data.habit || data);
                
                showNotification('Привычка создана!', 'success');
            } else {
                showNotification(data.message || 'Ошибка при создании', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Ошибка сети', 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    });
}

function addHabitToDOM(habit) {
    const habitsGrid = document.querySelector('.grid.grid-cols-1');
    if (!habitsGrid) return;
    
    const habitHTML = `
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500" data-habit-id="${habit.id}">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-bold text-lg">${habit.name}</h3>
                    <p class="text-sm text-gray-500">
                        Цель: ${habit.target_value || '-'} ${habit.unit || ''}
                    </p>
                </div>
                
                <form action="/habits/${habit.id}/log" method="POST" class="habit-log-form" data-habit-id="${habit.id}">
                    <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
                    ${habit.metric_type !== 'boolean' ? `
                        <input type="number" step="0.1" name="value" placeholder="Значение" class="w-20 border rounded mb-2 p-1 text-sm">
                    ` : ''}
                    
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition">
                        ✓ Выполнено
                    </button>
                </form>
            </div>
        </div>
    `;
    
    // Вставляем перед сообщением "нет привычек"
    const emptyMessage = habitsGrid.querySelector('.text-gray-500');
    if (emptyMessage) {
        emptyMessage.remove();
    }
    
    habitsGrid.insertAdjacentHTML('beforeend', habitHTML);
    
    // Добавляем обработчик на новую форму
    const newForm = habitsGrid.querySelector(`[data-habit-id="${habit.id}"] .habit-log-form`);
    if (newForm) {
        newForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(newForm);
            const button = newForm.querySelector('button');
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = '⏳';
            
            try {
                const response = await fetch(newForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, data.is_record ? 'success' : 'info');
                    button.disabled = true;
                    button.textContent = '✓ Готово';
                    button.classList.remove('bg-green-500', 'hover:bg-green-600');
                    button.classList.add('bg-gray-400', 'cursor-not-allowed');
                } else {
                    showNotification(data.message || 'Ошибка', 'error');
                    button.disabled = false;
                    button.textContent = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Ошибка сети', 'error');
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.habit-log-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault(); // Отменяем редирект
            
            const formData = new FormData(form);
            const button = form.querySelector('button');
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = '⏳';
            
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    
                    // Блокируем кнопку
                    button.disabled = true;
                    button.textContent = '✓ Готово';
                    button.classList.remove('bg-green-500', 'hover:bg-green-600');
                    button.classList.add('bg-gray-400', 'cursor-not-allowed');
                    console.log('✅ AJAX запрос успешен, ждем WebSocket...');
                } else {
                    showNotification(data.message || 'Ошибка', 'error');
                    button.disabled = false;
                    button.textContent = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Ошибка сети', 'error');
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });
});

function showNotification(message, type = 'info') {
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        info: 'bg-blue-600'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg animate-bounce z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endsection