"""
Database utilities for Alumni Tracer Python modules
"""

import sqlite3
import os
from typing import List, Dict, Any, Optional, Tuple
from contextlib import contextmanager
from datetime import datetime


class DatabaseManager:
    """Database connection and query management"""
    
    def __init__(self, db_path: str = '../../alumni_data.db'):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize database with required tables"""
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        
        with self.get_connection() as conn:
            cursor = conn.cursor()
            
            # Create alumni table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS alumni (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    firstName TEXT NOT NULL,
                    lastName TEXT NOT NULL,
                    email TEXT UNIQUE,
                    graduationYear TEXT,
                    status TEXT DEFAULT 'active',
                    college TEXT,
                    course TEXT,
                    phone TEXT,
                    address TEXT,
                    city TEXT,
                    country TEXT,
                    linkedin TEXT,
                    employment_status TEXT,
                    company TEXT,
                    job_title TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            # Create events table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    date TEXT,
                    time TEXT,
                    location TEXT,
                    status TEXT DEFAULT 'upcoming',
                    attendees INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            # Create surveys table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS surveys (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    description TEXT,
                    status TEXT DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            # Create survey_responses table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS survey_responses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    survey_id INTEGER,
                    alumni_id INTEGER,
                    responses TEXT,  -- JSON string of responses
                    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (survey_id) REFERENCES surveys (id),
                    FOREIGN KEY (alumni_id) REFERENCES alumni (id)
                )
            ''')
            
            # Create activity_log table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS activity_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    user_type TEXT,  -- 'staff' or 'alumni'
                    action TEXT,
                    category TEXT,
                    details TEXT,  -- JSON string of additional details
                    activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES alumni (id)
                )
            ''')
            
            # Create indexes for better performance
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_alumni_email ON alumni(email)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_alumni_status ON alumni(status)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_alumni_college ON alumni(college)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_alumni_year ON alumni(graduationYear)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_events_status ON events(status)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_activity_date ON activity_log(activity_date)')
            
            conn.commit()
    
    @contextmanager
    def get_connection(self):
        """Get database connection with automatic cleanup"""
        conn = sqlite3.connect(self.db_path)
        conn.row_factory = sqlite3.Row  # Allow dict-like access
        try:
            yield conn
        finally:
            conn.close()
    
    def execute_query(self, query: str, params: Optional[Tuple] = None) -> List[Dict[str, Any]]:
        """Execute a SELECT query and return results as list of dictionaries"""
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(query, params or ())
            
            if query.strip().upper().startswith('SELECT'):
                results = [dict(row) for row in cursor.fetchall()]
                return results
            else:
                conn.commit()
                return [{'affected_rows': cursor.rowcount}]
    
    def execute_many(self, query: str, params_list: List[Tuple]) -> int:
        """Execute a query multiple times with different parameters"""
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.executemany(query, params_list)
            conn.commit()
            return cursor.rowcount
    
    def insert_record(self, table: str, data: Dict[str, Any]) -> int:
        """Insert a record and return the ID"""
        # Remove id from data if present
        insert_data = {k: v for k, v in data.items() if k != 'id'}
        
        columns = ', '.join(insert_data.keys())
        placeholders = ', '.join(['?' for _ in insert_data])
        query = f"INSERT INTO {table} ({columns}) VALUES ({placeholders})"
        
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(query, tuple(insert_data.values()))
            conn.commit()
            return cursor.lastrowid
    
    def update_record(self, table: str, record_id: int, data: Dict[str, Any]) -> bool:
        """Update a record by ID"""
        # Remove id from data if present
        update_data = {k: v for k, v in data.items() if k != 'id'}
        
        if not update_data:
            return False
        
        set_clause = ', '.join([f"{k} = ?" for k in update_data.keys()])
        query = f"UPDATE {table} SET {set_clause}, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(query, tuple(update_data.values()) + (record_id,))
            conn.commit()
            return cursor.rowcount > 0
    
    def delete_record(self, table: str, record_id: int) -> bool:
        """Delete a record by ID"""
        query = f"DELETE FROM {table} WHERE id = ?"
        
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(query, (record_id,))
            conn.commit()
            return cursor.rowcount > 0
    
    def get_record_by_id(self, table: str, record_id: int) -> Optional[Dict[str, Any]]:
        """Get a single record by ID"""
        query = f"SELECT * FROM {table} WHERE id = ?"
        results = self.execute_query(query, (record_id,))
        return results[0] if results else None
    
    def get_records_with_pagination(self, table: str, page: int = 1, page_size: int = 20, 
                                  where_clause: Optional[str] = None, 
                                  order_by: str = 'id DESC') -> Dict[str, Any]:
        """Get records with pagination"""
        offset = (page - 1) * page_size
        
        # Build count query
        count_query = f"SELECT COUNT(*) as total FROM {table}"
        if where_clause:
            count_query += f" WHERE {where_clause}"
        
        # Build data query
        data_query = f"SELECT * FROM {table}"
        if where_clause:
            data_query += f" WHERE {where_clause}"
        data_query += f" ORDER BY {order_by} LIMIT ? OFFSET ?"
        
        with self.get_connection() as conn:
            cursor = conn.cursor()
            
            # Get total count
            cursor.execute(count_query)
            total = cursor.fetchone()[0]
            
            # Get data
            cursor.execute(data_query, (page_size, offset))
            records = [dict(row) for row in cursor.fetchall()]
        
        # Calculate pagination info
        total_pages = (total + page_size - 1) // page_size
        
        return {
            'records': records,
            'pagination': {
                'total_items': total,
                'total_pages': total_pages,
                'current_page': page,
                'page_size': page_size,
                'has_next': page < total_pages,
                'has_prev': page > 1
            }
        }
    
    def search_records(self, table: str, search_query: str, search_fields: List[str],
                       page: int = 1, page_size: int = 20) -> Dict[str, Any]:
        """Search records across multiple fields"""
        where_conditions = []
        params = []
        
        for field in search_fields:
            where_conditions.append(f"{field} LIKE ?")
            params.append(f"%{search_query}%")
        
        where_clause = ' OR '.join(where_conditions)
        return self.get_records_with_pagination(table, page, page_size, where_clause)
    
    def get_table_stats(self, table: str) -> Dict[str, Any]:
        """Get basic statistics about a table"""
        stats = {}
        
        # Total records
        total_query = f"SELECT COUNT(*) as total FROM {table}"
        result = self.execute_query(total_query)
        stats['total_records'] = result[0]['total']
        
        # Get column information
        with self.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(f"PRAGMA table_info({table})")
            columns = [dict(row) for row in cursor.fetchall()]
            stats['columns'] = columns
        
        return stats
    
    def backup_database(self, backup_path: str) -> bool:
        """Create a backup of the database"""
        try:
            import shutil
            shutil.copy2(self.db_path, backup_path)
            return True
        except Exception:
            return False
    
    def restore_database(self, backup_path: str) -> bool:
        """Restore database from backup"""
        try:
            import shutil
            shutil.copy2(backup_path, self.db_path)
            return True
        except Exception:
            return False
    
    def vacuum_database(self) -> bool:
        """Vacuum the database to optimize space"""
        try:
            with self.get_connection() as conn:
                conn.execute('VACUUM')
                return True
        except Exception:
            return False
    
    def get_database_size(self) -> Dict[str, Any]:
        """Get database size information"""
        try:
            size_bytes = os.path.getsize(self.db_path)
            
            # Get table sizes
            table_sizes = {}
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
                tables = [row[0] for row in cursor.fetchall()]
                
                for table in tables:
                    cursor.execute(f"SELECT COUNT(*) as count FROM {table}")
                    count = cursor.fetchone()[0]
                    table_sizes[table] = count
            
            return {
                'size_bytes': size_bytes,
                'size_mb': round(size_bytes / (1024 * 1024), 2),
                'table_counts': table_sizes
            }
        except Exception:
            return {
                'size_bytes': 0,
                'size_mb': 0,
                'table_counts': {}
            }


# Database migration utilities
class MigrationManager:
    """Handle database schema migrations"""
    
    def __init__(self, db_manager: DatabaseManager):
        self.db = db_manager
        self.init_migrations_table()
    
    def init_migrations_table(self):
        """Initialize the migrations tracking table"""
        with self.db.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration_name TEXT UNIQUE NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            conn.commit()
    
    def has_migration_run(self, migration_name: str) -> bool:
        """Check if a migration has already been run"""
        result = self.db.execute_query(
            "SELECT COUNT(*) as count FROM migrations WHERE migration_name = ?",
            (migration_name,)
        )
        return result[0]['count'] > 0
    
    def mark_migration_run(self, migration_name: str):
        """Mark a migration as executed"""
        self.db.execute_query(
            "INSERT INTO migrations (migration_name) VALUES (?)",
            (migration_name,)
        )
    
    def run_migration(self, migration_name: str, migration_sql: str) -> bool:
        """Run a migration if it hasn't been run yet"""
        if self.has_migration_run(migration_name):
            return True  # Already run
        
        try:
            with self.db.get_connection() as conn:
                cursor = conn.cursor()
                cursor.executescript(migration_sql)
                conn.commit()
            
            self.mark_migration_run(migration_name)
            return True
        except Exception as e:
            print(f"Migration {migration_name} failed: {e}")
            return False
