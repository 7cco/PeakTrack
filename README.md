# PeakTrack
## Веб-трекер для фиксации прогресса в личных привычках с мгновенным уведомлением о новых рекордах

## Схема архитектуры

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │ Blade +      │  │ React        │  │ Vanilla JS       │  │
│  │ Tailwind     │  │ Components   │  │ (AJAX, WS)       │  │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ HTTPS
                            │
┌─────────────────────────────────────────────────────────────┐
│                    Nginx (SSL Termination)                   │
│  - Reverse Proxy                                             │
│  - Load Balancing                                            │
│  - Static Files                                              │
└───────────┬──────────────────────────────┬──────────────────┘
            │                              │
            │ / (Laravel)                  │ /api/*, /ws/* (FastAPI)
            │                              │
┌───────────▼──────────┐         ┌────────▼──────────────┐
│   Laravel 13         │         │   FastAPI             │
│   ─────────────────  │         │   ──────────────────  │
│   • SSR Pages        │         │   • REST API          │
│   • Authentication   │         │   • Habit Logs        │
│   • CRUD Operations  │         │   • Statistics        │
│   • Business Logic   │         │   • WebSocket Server  │
│   • Redis Publisher  │◄───────▶│   • Real-time Events  │
└───────────┬──────────┘  Redis  └───────────────────────┘
            │           Pub/Sub
            │
┌───────────▼──────────┐
│   MySQL 8.0          │
│   ────────────────── │
│   • users            │
│   • habits           │
│   • habit_logs       │
└──────────────────────┘
```
## Структура БД

|Таблица|Назначение|Ключевые поля и связи|
|---|---|---|
|`users`|Пользователи системы|`id`, `name`, `email` (unique), `password`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`|
|`habits`|Привычки пользователя|`id`, **`user_id` → users**, `name`, `metric_type` (`boolean`/`integer`/`time`), `target_value`, `unit`, `is_active`, `is_public`, `created_at`, `updated_at`|
|`habit_logs`|Записи о выполнении привычек|`id`, **`user_id` → users**, **`habit_id` → habits**, `log_date`, `value`, `notes`, `is_record`, `record_type`, `created_at`, `updated_at`|


**Связи:**
users oneToMany habits (владелец привычки)
users oneToMany habit_logs (автор записи)
habits oneToMany habit_logs (привычка → её выполнения)

**Индексы:**
habits(user_id, is_active) — быстрый список активных привычек пользователя
habit_logs(user_id, habit_id, log_date) — UNIQUE, запрещает двойные записи за один день + ускоряет проверку «выполнено ли сегодня»
habit_logs(user_id, log_date) — ускоряет пагинацию и сортировку истории в FastAPI
Внешние ключи habits.user_id, habit_logs.user_id, habit_logs.habit_id с cascadeOnDelete (автоудаление при удалении пользователя)

Денормализация: в таблице habit_logs поле user_id продублировано намеренно — это позволяет FastAPI делать быстрые запросы истории без JOIN с таблицей habits, что критично для real-time нагрузки.

---


# ПОДГОТОВКА 

### 1. Добавить домены в `hosts`

**Windows** (PowerShell **от администратора**):

```powershell
Add-Content "$env:WINDIR\System32\drivers\etc\hosts" "127.0.0.1 localhost api.localhost"
```

**Linux / macOS:**

```bash
echo "127.0.0.1 localhost api.localhost" | sudo tee -a /etc/hosts
```

### 2. Сгенерировать сертификат

Из корня репозитория (WSL / Git Bash / Linux / macOS — нужен `openssl`):

```bash
bash docker/nginx/certs.sh
```

Скрипт создаёт сертификаты и ключи с SAN на оба домена.

### 3. Добавить сертификат в доверенные

### Без них React не сможет ходить с `localhost` на `api.localhost` (браузер режет cross-origin запросы к недоверенному сертификату).

**Windows** (PowerShell **от администратора**), затем **полностью перезапустить браузер**:

```powershell
Import-Certificate -FilePath "путь до проекта\PeakTrack\docker\nginx\ssl\laravel.crt" -CertStoreLocation Cert:\LocalMachine\Root

Import-Certificate -FilePath "путь до проекта\PeakTrack\docker\nginx\ssl\fastapi.crt" -CertStoreLocation Cert:\LocalMachine\Root
```

**Linux:**

```bash
sudo cp nginx/certs/laravel.crt /usr/local/share/ca-certificates/laravel.crt
sudo cp nginx/certs/fastapi.crt /usr/local/share/ca-certificates/fastapi.crt
sudo update-ca-certificates
```

**macOS:** открыть `laravel.crt` и `fastapi.crt`  в Keychain Access → добавить в System → выставить «Always Trust».


# ========================== ЗАПУСК ==========================

```bash
# 1. Клонировать
git clone https://github.com/7cco/PeakTrack.git
cd PeakTrack

# 2. Создать корневой .env
cp .env.example .env

# 3. Собрать проект
docker compose build

# 4. Поднять стек
docker compose up -d

# 5. Открыть в браузере
https://localhost
```

**Данные для теста**:

- Email: `demo@examle.com` · Пароль: `password`