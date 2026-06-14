from fastapi import FastAPI

app = FastAPI(title="PeakTrack API")

@app.get("/")
def read_root():
    return {"message": "Привет! FastAPI успешно работает в Docker 🐳"}

@app.get("/health")
def health_check():
    return {"status": "ok", "service": "fastapi"}

@app.get("/api/data")
def get_data():
    # Пример возврата более сложных данных
    return {
        "items": [
            {"id": 1, "name": "Тестовый элемент 1"},
            {"id": 2, "name": "Тестовый элемент 2"}
        ]
    }