#!/usr/bin/env python3
"""
Alumni Data Processing Module
Handles data import, export, and processing for alumni records
"""

import pandas as pd
import numpy as np
import sqlite3
from typing import List, Dict, Any, Optional
import json
from datetime import datetime
import os

class AlumniDataProcessor:
    """Handles all alumni data operations"""
    
    def __init__(self, db_path: str = '../../alumni_data.db'):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize SQLite database for alumni data"""
        conn = sqlite3.connect(self.db_path)
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        conn.commit()
        conn.close()
    
    def import_from_csv(self, csv_path: str) -> Dict[str, Any]:
        """Import alumni data from CSV file"""
        try:
            df = pd.read_csv(csv_path)
            
            # Validate required columns
            required_columns = ['firstName', 'lastName', 'email']
            missing_columns = [col for col in required_columns if col not in df.columns]
            
            if missing_columns:
                return {
                    'success': False,
                    'error': f'Missing required columns: {missing_columns}'
                }
            
            # Clean data
            df = df.fillna('')
            
            # Insert into database
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            imported_count = 0
            for _, row in df.iterrows():
                try:
                    cursor.execute('''
                        INSERT INTO alumni (firstName, lastName, email, graduationYear, status, 
                                         college, course, phone, address, city, country, linkedin,
                                         employment_status, company, job_title)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ''', [
                        row.get('firstName', ''),
                        row.get('lastName', ''),
                        row.get('email', ''),
                        row.get('graduationYear', ''),
                        row.get('status', 'active'),
                        row.get('college', ''),
                        row.get('course', ''),
                        row.get('phone', ''),
                        row.get('address', ''),
                        row.get('city', ''),
                        row.get('country', ''),
                        row.get('linkedin', ''),
                        row.get('employment_status', ''),
                        row.get('company', ''),
                        row.get('job_title', '')
                    ])
                    imported_count += 1
                except sqlite3.IntegrityError:
                    # Skip duplicate emails
                    continue
            
            conn.commit()
            conn.close()
            
            return {
                'success': True,
                'imported_count': imported_count,
                'total_rows': len(df)
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def export_to_csv(self, output_path: str, filters: Optional[Dict] = None) -> Dict[str, Any]:
        """Export alumni data to CSV file"""
        try:
            conn = sqlite3.connect(self.db_path)
            
            # Build query with filters
            query = "SELECT * FROM alumni WHERE 1=1"
            params = []
            
            if filters:
                if filters.get('college'):
                    query += " AND college = ?"
                    params.append(filters['college'])
                if filters.get('graduationYear'):
                    query += " AND graduationYear = ?"
                    params.append(filters['graduationYear'])
                if filters.get('status'):
                    query += " AND status = ?"
                    params.append(filters['status'])
            
            df = pd.read_sql_query(query, conn, params=params)
            conn.close()
            
            # Export to CSV
            df.to_csv(output_path, index=False)
            
            return {
                'success': True,
                'exported_count': len(df),
                'file_path': output_path
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def get_database_size(self) -> Dict[str, Any]:
        """Get database size information"""
        try:
            size_bytes = os.path.getsize(self.db_path)
            
            # Get table sizes
            table_counts = {}
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
            tables = [row[0] for row in cursor.fetchall()]
            
            for table in tables:
                try:
                    cursor.execute(f"SELECT COUNT(*) as count FROM {table}")
                    count = cursor.fetchone()[0]
                    table_counts[table] = count
                except sqlite3.OperationalError:
                    table_counts[table] = 0
            
            conn.close()
            
            return {
                'size_bytes': size_bytes,
                'size_mb': round(size_bytes / (1024 * 1024), 2),
                'table_counts': table_counts
            }
        except Exception:
            return {
                'size_bytes': 0,
                'size_mb': 0,
                'table_counts': {}
            }
    
    def get_alumni_statistics(self) -> Dict[str, Any]:
        """Get comprehensive alumni statistics"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # Basic counts
        cursor.execute("SELECT COUNT(*) FROM alumni")
        total_alumni = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM alumni WHERE status = 'active'")
        active_alumni = cursor.fetchone()[0]
        
        # Status breakdown
        cursor.execute("SELECT status, COUNT(*) FROM alumni GROUP BY status")
        status_breakdown = dict(cursor.fetchall())
        
        # College distribution
        cursor.execute("SELECT college, COUNT(*) FROM alumni WHERE college != '' GROUP BY college ORDER BY COUNT(*) DESC")
        college_distribution = dict(cursor.fetchall())
        
        # Year distribution
        cursor.execute("SELECT graduationYear, COUNT(*) FROM alumni WHERE graduationYear != '' GROUP BY graduationYear ORDER BY graduationYear DESC")
        year_distribution = dict(cursor.fetchall())
        
        # Employment statistics (handle missing column gracefully)
        try:
            cursor.execute("SELECT employment_status, COUNT(*) FROM alumni WHERE employment_status != '' GROUP BY employment_status")
            employment_stats = dict(cursor.fetchall())
        except sqlite3.OperationalError:
            # Column doesn't exist yet
            employment_stats = {}
        
        # Geographic distribution (handle missing column gracefully)
        try:
            cursor.execute("SELECT city, COUNT(*) FROM alumni WHERE city != '' GROUP BY city ORDER BY COUNT(*) DESC LIMIT 10")
            top_cities = dict(cursor.fetchall())
        except sqlite3.OperationalError:
            # Column doesn't exist yet
            top_cities = {}
        
        conn.close()
        
        return {
            'total_alumni': total_alumni,
            'active_alumni': active_alumni,
            'engagement_rate': round((active_alumni / total_alumni * 100) if total_alumni > 0 else 0, 1),
            'status_breakdown': status_breakdown,
            'college_distribution': college_distribution,
            'year_distribution': year_distribution,
            'employment_stats': employment_stats,
            'top_cities': top_cities
        }
    
    def get_alumni_data(self, limit: Optional[int] = None, offset: Optional[int] = 0) -> List[Dict[str, Any]]:
        """Get alumni data with pagination"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        query = "SELECT * FROM alumni ORDER BY lastName, firstName"
        params = []
        
        if limit:
            query += " LIMIT ? OFFSET ?"
            params = [limit, offset]
        
        cursor.execute(query, params)
        columns = [desc[0] for desc in cursor.description]
        alumni_data = [dict(zip(columns, row)) for row in cursor.fetchall()]
        
        conn.close()
        return alumni_data
    
    def add_alumni(self, alumni_data: Dict[str, Any]) -> Dict[str, Any]:
        """Add a new alumni record"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO alumni (firstName, lastName, email, graduationYear, status, 
                                 college, course, phone, address, city, country, linkedin,
                                 employment_status, company, job_title)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', [
                alumni_data.get('firstName', ''),
                alumni_data.get('lastName', ''),
                alumni_data.get('email', ''),
                alumni_data.get('graduationYear', ''),
                alumni_data.get('status', 'active'),
                alumni_data.get('college', ''),
                alumni_data.get('course', ''),
                alumni_data.get('phone', ''),
                alumni_data.get('address', ''),
                alumni_data.get('city', ''),
                alumni_data.get('country', ''),
                alumni_data.get('linkedin', ''),
                alumni_data.get('employment_status', ''),
                alumni_data.get('company', ''),
                alumni_data.get('job_title', '')
            ])
            
            alumni_id = cursor.lastrowid
            conn.commit()
            conn.close()
            
            return {
                'success': True,
                'alumni_id': alumni_id,
                'message': 'Alumni record added successfully'
            }
            
        except sqlite3.IntegrityError as e:
            return {
                'success': False,
                'error': 'Email already exists' if 'UNIQUE' in str(e) else str(e)
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def update_alumni(self, alumni_id: int, alumni_data: Dict[str, Any]) -> Dict[str, Any]:
        """Update an existing alumni record"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            # Build dynamic update query
            update_fields = []
            values = []
            
            for field, value in alumni_data.items():
                if field != 'id' and value is not None:
                    update_fields.append(f"{field} = ?")
                    values.append(value)
            
            if update_fields:
                values.append(alumni_id)
                query = f"UPDATE alumni SET {', '.join(update_fields)}, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
                cursor.execute(query, values)
                
                conn.commit()
                conn.close()
                
                return {
                    'success': True,
                    'message': 'Alumni record updated successfully'
                }
            else:
                return {
                    'success': False,
                    'error': 'No fields to update'
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def delete_alumni(self, alumni_id: int) -> Dict[str, Any]:
        """Delete an alumni record"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute("DELETE FROM alumni WHERE id = ?", [alumni_id])
            
            if cursor.rowcount > 0:
                conn.commit()
                conn.close()
                return {
                    'success': True,
                    'message': 'Alumni record deleted successfully'
                }
            else:
                conn.close()
                return {
                    'success': False,
                    'error': 'Alumni record not found'
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def search_alumni(self, query: str, search_fields: Optional[List[str]] = None) -> List[Dict[str, Any]]:
        """Search alumni records"""
        if search_fields is None:
            search_fields = ['firstName', 'lastName', 'email', 'college', 'course']
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # Build search query
        where_conditions = []
        params = []
        
        for field in search_fields:
            where_conditions.append(f"{field} LIKE ?")
            params.append(f"%{query}%")
        
        search_query = f"SELECT * FROM alumni WHERE {' OR '.join(where_conditions)} ORDER BY lastName, firstName"
        
        cursor.execute(search_query, params)
        columns = [desc[0] for desc in cursor.description]
        results = [dict(zip(columns, row)) for row in cursor.fetchall()]
        
        conn.close()
        return results
    
    def generate_sample_data(self, count: int = 50) -> Dict[str, Any]:
        """Generate sample alumni data for testing"""
        try:
            import random
            
            first_names = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'James', 'Patricia']
            last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez']
            colleges = ['Engineering', 'Business', 'Arts', 'Science', 'Medicine', 'Law']
            courses = ['Computer Science', 'Marketing', 'Fine Arts', 'Biology', 'Medicine', 'Law', 'Electrical Engineering', 'Finance']
            statuses = ['active', 'pending', 'inactive']
            employment_statuses = ['Employed', 'Unemployed', 'Self-employed', 'Student']
            
            generated = 0
            for _ in range(count):
                alumni_data = {
                    'firstName': random.choice(first_names),
                    'lastName': random.choice(last_names),
                    'email': f"user{generated}@example.com",
                    'graduationYear': str(random.randint(1990, 2023)),
                    'status': random.choice(statuses),
                    'college': random.choice(colleges),
                    'course': random.choice(courses),
                    'phone': f"+1{random.randint(2000000000, 9999999999)}",
                    'city': random.choice(['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix']),
                    'country': 'USA',
                    'employment_status': random.choice(employment_statuses),
                    'company': f"Company {random.randint(1, 100)}",
                    'job_title': f"Position {random.randint(1, 50)}"
                }
                
                result = self.add_alumni(alumni_data)
                if result['success']:
                    generated += 1
            
            return {
                'success': True,
                'generated_count': generated,
                'message': f'Generated {generated} sample alumni records'
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }


# Utility functions
def create_import_template(output_path: str = 'alumni_import_template.csv') -> Dict[str, Any]:
    """Create a CSV template for alumni data import"""
    try:
        template_data = {
            'firstName': ['John'],
            'lastName': ['Doe'],
            'email': ['john.doe@example.com'],
            'graduationYear': ['2020'],
            'status': ['active'],
            'college': ['Engineering'],
            'course': ['Computer Science'],
            'phone': ['+1234567890'],
            'address': ['123 Main St'],
            'city': ['New York'],
            'country': ['USA'],
            'linkedin': ['https://linkedin.com/in/johndoe'],
            'employment_status': ['Employed'],
            'company': ['Tech Company'],
            'job_title': ['Software Engineer']
        }
        
        df = pd.DataFrame(template_data)
        df.to_csv(output_path, index=False)
        
        return {
            'success': True,
            'template_path': output_path,
            'message': f'Import template created at {output_path}'
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }


if __name__ == "__main__":
    # Example usage
    processor = AlumniDataProcessor()
    
    # Generate sample data
    result = processor.generate_sample_data(20)
    print(f"Sample data generation: {result}")
    
    # Get statistics
    stats = processor.get_alumni_statistics()
    print(f"Alumni statistics: {stats}")
    
    # Create import template
    template_result = create_import_template()
    print(f"Template creation: {template_result}")
