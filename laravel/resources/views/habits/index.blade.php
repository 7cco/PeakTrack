@extends('layouts.app') {{-- Ваш основной layout --}}

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-6">Мои привычки</h1>

    {{-- Форма создания новой привычки (можно вынести в модальное окно) --}}
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
                    <form action="{{ route('habits.log', $habit) }}" method="POST">
                        @csrf
                        {{-- Если привычка не boolean, можно добавить поле ввода значения --}}
                        @if($habit->metric_type !== 'boolean')
                            <input type="number" step="0.1" name="value" placeholder="Значение" class="w-20 border rounded mb-2 p-1 text-sm">
                        @endif
                        
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition">
                            ✓ Выполнено
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

{{-- Место для отображения Flash-сообщений (уведомлений с бэкенда) --}}
@if(session('success'))
    <div class="fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg animate-bounce">
        {{ session('success') }}
    </div>
@endif
@endsection