import aiomysql
import os


DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'mysql'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'user': os.getenv('DB_USER', 'user'),
    'password': os.getenv('DB_PASSWORD', 'password'),
    'db': os.getenv('DB_NAME', 'tracker'),
    'charset': os.getenv('DB_CHARSET', 'utf8mb4'),
}


async def db_query(query, *args):
    async with aiomysql.connect(**DB_CONFIG) as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(query, args)
            return await cur.fetchall()

async def db_query_one(query, *args):
    res = await db_query(query, *args)
    return res[0] if res else None