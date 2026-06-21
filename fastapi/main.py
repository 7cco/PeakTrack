from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from contextlib import asynccontextmanager
import redis.asyncio as aioredis
import json, asyncio, sys
from routers import habits, ws
from routers.ws_manager import manager

async def redis_subscriber():
    print("[REDIS] Функция redis_subscriber запущена", flush=True)
    try:
        print("[REDIS] Пытаемся подключиться к Redis... ", flush=True)
        redis = await aioredis.from_url('redis://redis:6379')
        print("[REDIS] Подключены к Redis ", flush=True)
        
        pong = await redis.ping()
        print(f"[REDIS] Redis ping: {pong} ", flush=True)
        
        pubsub = redis.pubsub()
        print("[REDIS] Подписываемся на канал 'peaktrack.events'... ", flush=True)
        await pubsub.subscribe('peaktrack.events')
        print("[REDIS] Успешно подписан на канал 'peaktrack.events' ", flush=True)
        
        async for message in pubsub.listen():
            if message['type'] != 'message':
                continue
                
            try:
                data = json.loads(message['data'])
                event_type = data.get('event')
                user_id = data.get('user_id') # ИСПРАВЛЕНО: было data.g et
                
                print(f"[REDIS] Получено событие: {event_type} для юзера {user_id}", flush=True)
                
                if not user_id:
                    print("[REDIS] Пропускаем: нет user_id", flush=True)
                    continue

                # 1. Обработка НОВОГО РЕКОРДА (Ваш старый код)
                if event_type == 'new_record':
                    print(f"[WS] Отправляем NEW_RECORD для юзера {user_id}", flush=True)
                    await manager.send_to_user(user_id, {
                        'type': 'NEW_RECORD',
                        'event': 'new_record',
                        'habit_name': data.get('habit_name'),
                        'message': data.get('message'),
                        'value': data.get('value'),
                        'unit': data.get('unit')
                    })
                
                # 2. Обработка СОЗДАНИЯ привычки
                elif event_type == 'habit_created':
                    print(f"[WS] Отправляем habit_created для юзера {user_id}", flush=True)
                    await manager.send_to_user(user_id, {
                        'event': 'habit_created',
                        'habit': data.get('habit'),
                        'message': data.get('message')
                    })
                
                # 3. Обработка РЕДАКТИРОВАНИЯ привычки
                elif event_type == 'habit_updated':
                    print(f"[WS] Отправляем habit_updated для юзера {user_id}", flush=True)
                    await manager.send_to_user(user_id, {
                        'event': 'habit_updated',
                        'habit': data.get('habit'),
                        'message': data.get('message')
                    })
                
                # 4. Обработка УДАЛЕНИЯ привычки
                elif event_type == 'habit_deleted':
                    print(f"[WS] Отправляем habit_deleted для юзера {user_id}", flush=True)
                    await manager.send_to_user(user_id, {
                        'event': 'habit_deleted',
                        'habit_id': data.get('habit_id'),
                        'message': data.get('message')
                    })
                    
                else:
                    print(f"[REDIS] Неизвестный тип события: {event_type}", flush=True)
                
            except Exception as e:
                print(f"[REDIS] Ошибка обработки JSON сообщения: {e}", flush=True)
                import traceback
                traceback.print_exc()
                
    except Exception as e:
        print(f"[REDIS] Критическая ошибка подключения: {e}", flush=True)
        import traceback
        traceback.print_exc()

@asynccontextmanager
async def lifespan(app: FastAPI):
    print("[LIFESPAN] FastAPI запускается...", flush=True)
    task = asyncio.create_task(redis_subscriber())
    print(f"[LIFESPAN] Задача Redis создана: {task}", flush=True)
    await asyncio.sleep(1)
    
    try:
        yield
    finally:
        print("[LIFESPAN] Отменяем задачу Redis...", flush=True)
        task.cancel()

app = FastAPI(title='PeakTrack API', version='1.0.0', lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(habits.router)
app.include_router(ws.router)

@app.get("/api/health")
def health():
    return {"status": "PeakTrack API is running"}