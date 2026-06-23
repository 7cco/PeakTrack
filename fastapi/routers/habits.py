# routers/habits.py
from fastapi import APIRouter, Query, HTTPException
from database import db_query, db_query_one

router = APIRouter(prefix="/api", tags=["Habits"])

@router.get("/habits")
async def get_habits(user_id: int, limit: int = Query(50, le=100), offset: int = 0):
    """Список привычек пользователя"""
    habits = await db_query(
        "SELECT id, name, metric_type, target_value, unit, is_active "
        "FROM habits WHERE user_id = %s AND is_active = 1 "
        "ORDER BY created_at DESC LIMIT %s OFFSET %s",
        user_id, limit, offset
    )
    return {"habits": habits, "count": len(habits)}

@router.get("/habits/{habit_id}/logs")
async def get_habit_logs(
    user_id: int, 
    habit_id: int, 
    limit: int = Query(30, le=100), 
    offset: int = 0
):
    """История выполнения конкретной привычки с пагинацией"""
    # Проверяем, что привычка принадлежит юзеру
    habit = await db_query_one("SELECT id FROM habits WHERE id = %s AND user_id = %s", habit_id, user_id)
    if not habit:
        raise HTTPException(status_code=404, detail="Habit not found")

    logs = await db_query(
        "SELECT log_date, value, notes, is_record, record_type "
        "FROM habit_logs WHERE user_id = %s AND habit_id = %s "
        "ORDER BY log_date DESC LIMIT %s OFFSET %s",
        user_id, habit_id, limit, offset
    )
    
    # Преобразуем даты в строки для JSON
    for log in logs:
        log['log_date'] = str(log['log_date'])
        
    return {"logs": logs, "count": len(logs)}