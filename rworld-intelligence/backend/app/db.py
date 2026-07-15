import sqlite3
import os

def _find_db_path():
    """Find the database directory by searching up from this file's location."""
    candidate = os.path.abspath(__file__)
    for _ in range(6):  # search up to 6 levels
        candidate = os.path.dirname(candidate)
        db_dir = os.path.join(candidate, "database")
        if os.path.isdir(db_dir):
            return os.path.join(db_dir, "rworld.db")
    # Final fallback: use current working directory
    return os.path.join(os.getcwd(), "database", "rworld.db")

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
