import sqlite3
import os

def _find_db_path():
    """Find or create the database directory, searching up from this file."""
    # Check env var first (can be set on Render)
    if os.environ.get("DB_PATH"):
        os.makedirs(os.path.dirname(os.environ["DB_PATH"]), exist_ok=True)
        return os.environ["DB_PATH"]
    # Search upward from app/ for a database/ folder
    candidate = os.path.dirname(os.path.abspath(__file__))
    for _ in range(5):
        candidate = os.path.dirname(candidate)
        db_dir = os.path.join(candidate, "database")
        if os.path.isdir(db_dir):
            return os.path.join(db_dir, "rworld.db")
    # Fallback: create database/ next to this file's parent (backend/database/)
    fallback = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "database")
    os.makedirs(fallback, exist_ok=True)
    return os.path.join(fallback, "rworld.db")

DB_PATH = _find_db_path()


def get_db():
    """Returns a SQLite connection with dict_factory enabled for dictionary results"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    try:
        yield conn
    finally:
        conn.close()

def get_db_direct():
    """Helper to get a direct connection (non-generator)"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn
