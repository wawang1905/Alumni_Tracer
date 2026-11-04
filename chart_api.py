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
from alumni_charts import AlumniCharts
import threading
import time

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# Configuration
CHARTS_DIR = 'charts'
DATABASE_PATH = 'alumni_data.db'  # SQLite database for alumni data
API_PORT = 5000

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
            'chart_url': f'/charts/alumni_status_chart.png',
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
            'chart_url': f'/charts/year_distribution_chart.png',
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
            'chart_url': f'/charts/college_distribution_chart.png',
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
            'chart_url': f'/charts/course_distribution_chart.png',
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

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'service': 'Alumni Charts API'
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
