# database.py
import aiomysql

DB_CONFIG = {
    'host': 'mysql',
    'port': 3306,
    'user': 'odoo',
    'password': '250177',
    'db': 'track_db',
    'charset': 'utf8mb4',
}


async def db_query(query, *args):
    async with aiomysql.connect(**DB_CONFIG) as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(query, args)
            return await cur.fetchall()

async def db_query_one(query, *args):
    res = await db_query(query, *args)
    return res[0] if res else None