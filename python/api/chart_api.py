#!/usr/bin/env python3
"""
Flask API for Alumni Chart Generation and Data Analytics
Integrates Python chart generation with the Alumni Tracer web application
"""

from flask import Flask, jsonify, request, send_file
from flask_cors import CORS
import os
import json
import sqlite3
from datetime import datetime
import sys
import threading
import time
import pandas as pd
from werkzeug.utils import secure_filename

# Add parent directory to path to import from data module
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from data.excel_import_processor import ExcelImportProcessor
from data.alumni_excel_cleaner import (
    clean_uploaded_excel,
    clean_alumni_records_for_db,
)

# Add parent directory to path to import from charts module
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from charts.alumni_charts import AlumniCharts

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# Configuration
CHARTS_DIR = '../../charts'
DATABASE_PATH = '../../alumni_data.db'  # SQLite database for alumni data
API_PORT = 5000
UPLOAD_FOLDER = '../../uploads'
ALLOWED_EXTENSIONS = {'xlsx', 'xls', 'csv'}

# Ensure directories exist
os.makedirs(CHARTS_DIR, exist_ok=True)
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(os.path.dirname(DATABASE_PATH), exist_ok=True)

# Initialize Excel processor
excel_processor = ExcelImportProcessor()

# Ensure charts directory exists
os.makedirs(CHARTS_DIR, exist_ok=True)

class AlumniDataAPI:
    """Handle alumni data operations"""
    
    def __init__(self, db_path=DATABASE_PATH):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize SQLite database with sample data if it doesn't exist"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # Create alumni table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS alumni (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firstName TEXT,
                lastName TEXT,
                email TEXT,
                graduationYear TEXT,
                status TEXT,
                college TEXT,
                course TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        # Check if we have data, if not add sample data
        cursor.execute("SELECT COUNT(*) FROM alumni")
        count = cursor.fetchone()[0]
        
        if count == 0:
            self.insert_sample_data()
        
        conn.commit()
        conn.close()
    
    def insert_sample_data(self):
        """Insert sample alumni data"""
        sample_data = [
            ('John', 'Doe', 'john@example.com', '2018', 'active', 'Engineering', 'Computer Science'),
            ('Jane', 'Smith', 'jane@example.com', '2019', 'active', 'Business', 'Marketing'),
            ('Mike', 'Johnson', 'mike@example.com', '2020', 'pending', 'Engineering', 'Electrical Engineering'),
            ('Sarah', 'Williams', 'sarah@example.com', '2018', 'active', 'Arts', 'Fine Arts'),
            ('David', 'Brown', 'david@example.com', '2021', 'inactive', 'Engineering', 'Computer Science'),
            ('Emily', 'Davis', 'emily@example.com', '2019', 'active', 'Business', 'Finance'),
            ('Robert', 'Miller', 'robert@example.com', '2020', 'active', 'Science', 'Biology'),
            ('Lisa', 'Wilson', 'lisa@example.com', '2018', 'pending', 'Engineering', 'Mechanical Engineering'),
            ('James', 'Moore', 'james@example.com', '2021', 'active', 'Arts', 'Music'),
            ('Patricia', 'Taylor', 'patricia@example.com', '2019', 'active', 'Science', 'Chemistry'),
        ]
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.executemany('''
            INSERT INTO alumni (firstName, lastName, email, graduationYear, status, college, course)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ''', sample_data)
        
        conn.commit()
        conn.close()
    
    def get_alumni_data(self):
        """Get all alumni data as list of dictionaries"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT id, firstName, lastName, email, graduationYear, status, college, course
            FROM alumni
            ORDER BY lastName, firstName
        ''')
        
        columns = [desc[0] for desc in cursor.description]
        alumni_data = [dict(zip(columns, row)) for row in cursor.fetchall()]
        
        conn.close()
        return alumni_data
    
    def get_alumni_stats(self):
        """Get alumni statistics for dashboard"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # Total alumni
        cursor.execute("SELECT COUNT(*) FROM alumni")
        total_alumni = cursor.fetchone()[0]
        
        # Active alumni
        cursor.execute("SELECT COUNT(*) FROM alumni WHERE status = 'active'")
        active_alumni = cursor.fetchone()[0]
        
        # Alumni by status
        cursor.execute("SELECT status, COUNT(*) FROM alumni GROUP BY status")
        status_counts = dict(cursor.fetchall())
        
        # Alumni by graduation year
        cursor.execute("SELECT graduationYear, COUNT(*) FROM alumni GROUP BY graduationYear ORDER BY graduationYear DESC")
        year_counts = dict(cursor.fetchall())
        
        # Alumni by college
        cursor.execute("SELECT college, COUNT(*) FROM alumni GROUP BY college ORDER BY COUNT(*) DESC")
        college_counts = dict(cursor.fetchall())
        
        # Alumni by course
        cursor.execute("SELECT course, COUNT(*) FROM alumni GROUP BY course ORDER BY COUNT(*) DESC")
        course_counts = dict(cursor.fetchall())
        
        conn.close()
        
        return {
            'total_alumni': total_alumni,
            'active_alumni': active_alumni,
            'engagement_rate': round((active_alumni / total_alumni * 100) if total_alumni > 0 else 0, 1),
            'status_counts': status_counts,
            'year_counts': year_counts,
            'college_counts': college_counts,
            'course_counts': course_counts
        }

# Initialize data API
data_api = AlumniDataAPI()

@app.route('/api/alumni/data', methods=['GET'])
def get_alumni_data():
    """Get all alumni data"""
    try:
        alumni_data = data_api.get_alumni_data()
        return jsonify({
            'success': True,
            'data': alumni_data
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/alumni/stats', methods=['GET'])
def get_alumni_stats():
    """Get alumni statistics"""
    try:
        stats = data_api.get_alumni_stats()
        return jsonify({
            'success': True,
            'data': stats
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/charts/generate', methods=['POST'])
def generate_charts():
    """Generate all charts"""
    try:
        # Get alumni data
        alumni_data = data_api.get_alumni_data()
        
        # Create chart generator
        chart_generator = AlumniCharts(alumni_data)
        
        # Generate all charts
        charts = chart_generator.generate_all_charts(output_dir=CHARTS_DIR, show=False)
        
        return jsonify({
            'success': True,
            'charts': charts,
            'message': f'Generated {len(charts)} charts'
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/charts/status', methods=['GET'])
def generate_status_chart():
    """Generate alumni status chart"""
    try:
        alumni_data = data_api.get_alumni_data()
        chart_generator = AlumniCharts(alumni_data)
        
        chart_path = os.path.join(CHARTS_DIR, 'alumni_status_chart.png')
        chart_generator.render_alumni_status_chart(save_path=chart_path, show=False)
        
        return jsonify({
            'success': True,
            'chart_url': '/charts/alumni_status_chart.png',
            'message': 'Status chart generated successfully'
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/charts/year-distribution', methods=['GET'])
def generate_year_chart():
    """Generate year distribution chart"""
    try:
        alumni_data = data_api.get_alumni_data()
        chart_generator = AlumniCharts(alumni_data)
        
        chart_path = os.path.join(CHARTS_DIR, 'year_distribution_chart.png')
        chart_generator.render_year_distribution(save_path=chart_path, show=False)
        
        return jsonify({
            'success': True,
            'chart_url': '/charts/year_distribution_chart.png',
            'message': 'Year distribution chart generated successfully'
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/charts/college-distribution', methods=['GET'])
def generate_college_chart():
    """Generate college distribution chart"""
    try:
        alumni_data = data_api.get_alumni_data()
        chart_generator = AlumniCharts(alumni_data)
        
        batch_filter = request.args.get('batch_filter', 'all')
        chart_path = os.path.join(CHARTS_DIR, 'college_distribution_chart.png')
        chart_generator.render_college_distribution(save_path=chart_path, show=False, batch_filter=batch_filter)
        
        return jsonify({
            'success': True,
            'chart_url': '/charts/college_distribution_chart.png',
            'message': 'College distribution chart generated successfully'
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/charts/course-distribution', methods=['GET'])
def generate_course_chart():
    """Generate course distribution chart"""
    try:
        alumni_data = data_api.get_alumni_data()
        chart_generator = AlumniCharts(alumni_data)
        
        batch_filter = request.args.get('batch_filter', 'all')
        college_filter = request.args.get('college_filter', 'all')
        chart_path = os.path.join(CHARTS_DIR, 'course_distribution_chart.png')
        chart_generator.render_course_distribution(save_path=chart_path, show=False, 
                                                 batch_filter=batch_filter, college_filter=college_filter)
        
        return jsonify({
            'success': True,
            'chart_url': '/charts/course_distribution_chart.png',
            'message': 'Course distribution chart generated successfully'
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/charts/<filename>')
def serve_chart(filename):
    """Serve chart images"""
    return send_file(os.path.join(CHARTS_DIR, filename))

@app.route('/api/analytics/engagement', methods=['GET'])
def engagement_analytics():
    """Get engagement analytics"""
    try:
        stats = data_api.get_alumni_stats()
        
        # Calculate additional analytics
        total = stats['total_alumni']
        active = stats['active_alumni']
        engagement_rate = stats['engagement_rate']
        
        # Engagement trend (mock data for demonstration)
        engagement_trend = [
            {'month': 'Jan', 'rate': 65},
            {'month': 'Feb', 'rate': 68},
            {'month': 'Mar', 'rate': 72},
            {'month': 'Apr', 'rate': 75},
            {'month': 'May', 'rate': engagement_rate}
        ]
        
        return jsonify({
            'success': True,
            'data': {
                'total_alumni': total,
                'active_alumni': active,
                'engagement_rate': engagement_rate,
                'engagement_trend': engagement_trend,
                'status_breakdown': stats['status_counts']
            }
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/analytics/demographics', methods=['GET'])
def demographics_analytics():
    """Get demographics analytics"""
    try:
        stats = data_api.get_alumni_stats()
        
        return jsonify({
            'success': True,
            'data': {
                'by_year': stats['year_counts'],
                'by_college': stats['college_counts'],
                'by_course': stats['course_counts']
            }
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

def allowed_file(filename):
    """Check if file has allowed extension"""
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

@app.route('/api/excel/upload', methods=['POST'])
def upload_excel_file():
    """Upload and process Excel file with duplicate column detection"""
    try:
        if 'file' not in request.files:
            return jsonify({
                'success': False,
                'error': 'No file provided'
            }), 400
        
        file = request.files['file']
        if file.filename == '':
            return jsonify({
                'success': False,
                'error': 'No file selected'
            }), 400
        
        if not allowed_file(file.filename):
            return jsonify({
                'success': False,
                'error': f'File type not allowed. Allowed types: {", ".join(ALLOWED_EXTENSIONS)}'
            }), 400
        
        # Get duplicate handling strategy
        strategy = request.form.get('strategy', 'first')
        if strategy not in ['first', 'last', 'merge']:
            strategy = 'first'
        
        # Save uploaded file
        filename = secure_filename(file.filename)
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f"{timestamp}_{filename}"
        file_path = os.path.join(UPLOAD_FOLDER, filename)
        file.save(file_path)
        
        # Clean and process the Excel file (duplicate columns, trimming, etc.)
        result = clean_uploaded_excel(file_path, strategy, processor=excel_processor)
        
        # Clean up uploaded file
        try:
            os.remove(file_path)
        except:
            pass
        
        if result['success']:
            # Generate import report
            report = excel_processor.generate_import_report(result)
            
            # Convert DataFrame to JSON for response
            alumni_data = result['data'].to_dict('records')
            
            return jsonify({
                'success': True,
                'data': alumni_data,
                'report': report,
                'statistics': {
                    'total_rows': result['total_rows'],
                    'duplicate_rows_removed': result['duplicate_rows_removed'],
                    'duplicate_columns': result['duplicate_columns'],
                    'original_columns': result['original_columns'],
                    'final_columns': result['final_columns']
                },
                'message': f'Successfully processed {result["total_rows"]} alumni records'
            })
        else:
            return jsonify({
                'success': False,
                'error': result['error'],
                'duplicate_columns': result.get('duplicate_columns', {}),
                'original_columns': result.get('original_columns', [])
            }), 400
            
    except Exception as e:
        return jsonify({
            'success': False,
            'error': f'Error processing file: {str(e)}'
        }), 500

@app.route('/api/excel/preview', methods=['POST'])
def preview_excel_file():
    """Preview Excel file without importing"""
    try:
        if 'file' not in request.files:
            return jsonify({
                'success': False,
                'error': 'No file provided'
            }), 400
        
        file = request.files['file']
        if file.filename == '':
            return jsonify({
                'success': False,
                'error': 'No file selected'
            }), 400
        
        if not allowed_file(file.filename):
            return jsonify({
                'success': False,
                'error': f'File type not allowed. Allowed types: {", ".join(ALLOWED_EXTENSIONS)}'
            }), 400
        
        # Save uploaded file temporarily
        filename = secure_filename(file.filename)
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f"preview_{timestamp}_{filename}"
        file_path = os.path.join(UPLOAD_FOLDER, filename)
        file.save(file_path)
        
        # Read and preview the file
        try:
            df = pd.read_excel(file_path, nrows=10)  # Read only first 10 rows for preview
            
            # Detect duplicate columns
            duplicate_info = excel_processor.detect_duplicate_columns(df)
            
            # Standardize column names for preview
            df_standardized = excel_processor.standardize_column_names(df)
            
            preview_data = df_standardized.head(5).to_dict('records')  # Show first 5 rows
            
            return jsonify({
                'success': True,
                'preview': preview_data,
                'columns': df_standardized.columns.tolist(),
                'duplicate_columns': duplicate_info,
                'total_rows_preview': len(df),
                'message': f'Preview generated successfully'
            })
            
        finally:
            # Clean up temporary file
            try:
                os.remove(file_path)
            except:
                pass
                
    except Exception as e:
        return jsonify({
            'success': False,
            'error': f'Error previewing file: {str(e)}'
        }), 500

@app.route('/api/excel/import', methods=['POST'])
def import_processed_data():
    """Import processed alumni data into database"""
    try:
        alumni_data = request.json.get('data', [])
        
        if not alumni_data:
            return jsonify({
                'success': False,
                'error': 'No data to import'
            }), 400

        # Final safety cleaning right before DB insertion.
        alumni_data, cleaning_stats = clean_alumni_records_for_db(alumni_data)
        if not alumni_data:
            return jsonify({
                'success': False,
                'error': 'No valid alumni records after cleaning'
            }), 400

        # Avoid UNIQUE(email) violations against data that may already exist.
        # This prevents "duplicate row" import errors when re-importing a file.
        emails = [rec.get('email') for rec in alumni_data if rec.get('email')]
        unique_emails = list(dict.fromkeys(emails))  # stable unique
        existing_emails = set()

        if unique_emails:
            conn = sqlite3.connect(data_api.db_path)
            cursor = conn.cursor()

            # SQLite has a limit on query size; chunk for safety.
            chunk_size = 500
            for i in range(0, len(unique_emails), chunk_size):
                chunk = unique_emails[i:i + chunk_size]
                placeholders = ','.join(['?'] * len(chunk))
                cursor.execute(
                    f"SELECT email FROM alumni WHERE email IN ({placeholders})",
                    chunk
                )
                existing_emails.update([row[0] for row in cursor.fetchall()])

            conn.close()

        skipped_existing = 0
        final_data = []
        for rec in alumni_data:
            if rec.get('email') in existing_emails:
                skipped_existing += 1
                continue
            final_data.append(rec)

        alumni_data = final_data
        cleaning_stats['skipped_existing_emails'] = skipped_existing

        if not alumni_data:
            return jsonify({
                'success': False,
                'error': 'No new alumni records to import (all emails already exist).',
                'cleaning_stats': cleaning_stats,
            }), 400
        
        imported_count = 0
        errors = []
        
        for alumni in alumni_data:
            try:
                result = data_api.add_alumni(alumni)
                if result['success']:
                    imported_count += 1
                else:
                    errors.append(f"Row {imported_count + 1}: {result['error']}")
            except Exception as e:
                errors.append(f"Row {imported_count + 1}: {str(e)}")
        
        return jsonify({
            'success': True,
            'imported_count': imported_count,
            'total_processed': len(alumni_data),
            'errors': errors,
            'message': f'Successfully imported {imported_count} out of {len(alumni_data)} records',
            'cleaning_stats': cleaning_stats,
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': f'Error importing data: {str(e)}'
        }), 500

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'service': 'Alumni Charts API',
        'features': {
            'excel_import': True,
            'duplicate_column_detection': True,
            'chart_generation': True,
            'data_analytics': True
        }
    })

def run_background_chart_generation():
    """Run chart generation in background periodically"""
    while True:
        try:
            # Generate charts every 5 minutes
            time.sleep(300)
            
            alumni_data = data_api.get_alumni_data()
            chart_generator = AlumniCharts(alumni_data)
            chart_generator.generate_all_charts(output_dir=CHARTS_DIR, show=False)
            
            print(f"Charts regenerated at {datetime.now()}")
        except Exception as e:
            print(f"Error in background chart generation: {e}")

if __name__ == '__main__':
    print("Starting Alumni Charts API Server...")
    print(f"Charts will be served from: http://localhost:{API_PORT}")
    print(f"Charts directory: {CHARTS_DIR}")
    
    # Start background chart generation
    background_thread = threading.Thread(target=run_background_chart_generation, daemon=True)
    background_thread.start()
    
    # Run Flask app
    app.run(host='0.0.0.0', port=API_PORT, debug=True)
