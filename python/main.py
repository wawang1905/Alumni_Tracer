#!/usr/bin/env python3
"""
Main entry point for Alumni Tracer Python modules
Provides easy access to all Python functionality
"""

import sys
import os
from typing import Dict, Any

# Add current directory to Python path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from charts.alumni_charts import AlumniCharts, get_sample_alumni_data
from api.chart_api import app
from data.alumni_data_processor import AlumniDataProcessor
from utils.config import Config, get_config
from utils.helpers import format_date, validate_email


class AlumniTracerPython:
    """Main class for Alumni Tracer Python functionality"""
    
    def __init__(self, env: str = 'development'):
        self.config = get_config(env)
        self.data_processor = AlumniDataProcessor(self.config.DATABASE_PATH)
        self.charts_generator = None
        
        # Validate configuration
        config_validation = self.config.validate_config()
        if not config_validation['valid']:
            print("Configuration issues found:")
            for issue in config_validation['issues']:
                print(f"  - {issue}")
    
    def initialize_charts(self, alumni_data: list = None):
        """Initialize charts generator with alumni data"""
        if alumni_data is None:
            alumni_data = self.data_processor.get_alumni_data()
        
        self.charts_generator = AlumniCharts(alumni_data)
        return self.charts_generator
    
    def generate_all_charts(self, output_dir: str = None) -> Dict[str, Any]:
        """Generate all alumni charts"""
        if output_dir is None:
            output_dir = self.config.CHARTS_DIR
        
        if self.charts_generator is None:
            self.initialize_charts()
        
        try:
            charts = self.charts_generator.generate_all_charts(output_dir)
            return {
                'success': True,
                'charts': charts,
                'message': f'Generated {len(charts)} charts'
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def get_alumni_statistics(self) -> Dict[str, Any]:
        """Get comprehensive alumni statistics"""
        return self.data_processor.get_alumni_statistics()
    
    def import_alumni_from_csv(self, csv_path: str) -> Dict[str, Any]:
        """Import alumni data from CSV file"""
        return self.data_processor.import_from_csv(csv_path)
    
    def export_alumni_to_csv(self, output_path: str, filters: Dict[str, Any] = None) -> Dict[str, Any]:
        """Export alumni data to CSV file"""
        return self.data_processor.export_to_csv(output_path, filters)
    
    def add_alumni(self, alumni_data: Dict[str, Any]) -> Dict[str, Any]:
        """Add a new alumni record"""
        return self.data_processor.add_alumni(alumni_data)
    
    def search_alumni(self, query: str) -> list:
        """Search alumni records"""
        return self.data_processor.search_alumni(query)
    
    def start_api_server(self, host: str = None, port: int = None, debug: bool = None):
        """Start the Flask API server"""
        if host is None:
            host = self.config.API_HOST
        if port is None:
            port = self.config.API_PORT
        if debug is None:
            debug = self.config.API_DEBUG
        
        print(f"Starting Alumni Tracer API Server...")
        print(f"Server: http://{host}:{port}")
        print(f"Environment: {'Development' if debug else 'Production'}")
        
        app.run(host=host, port=port, debug=debug)
    
    def run_sample_data_generation(self, count: int = 50) -> Dict[str, Any]:
        """Generate sample alumni data"""
        return self.data_processor.generate_sample_data(count)
    
    def get_system_info(self) -> Dict[str, Any]:
        """Get system information and status"""
        return {
            'config': {
                'database_path': self.config.DATABASE_PATH,
                'charts_dir': self.config.CHARTS_DIR,
                'api_port': self.config.API_PORT,
                'environment': 'development' if self.config.API_DEBUG else 'production'
            },
            'statistics': self.get_alumni_statistics(),
            'database_stats': self.data_processor.get_database_size()
        }


def main():
    """Main function for command line usage"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Alumni Tracer Python Modules')
    parser.add_argument('--env', choices=['development', 'production', 'testing'], 
                       default='development', help='Environment')
    parser.add_argument('--action', choices=['charts', 'api', 'import', 'export', 'stats', 'sample', 'info'],
                       default='info', help='Action to perform')
    parser.add_argument('--file', help='File path for import/export actions')
    parser.add_argument('--output', help='Output path for export action')
    parser.add_argument('--count', type=int, default=50, help='Count for sample data generation')
    parser.add_argument('--host', default='0.0.0.0', help='API server host')
    parser.add_argument('--port', type=int, default=5000, help='API server port')
    parser.add_argument('--debug', action='store_true', help='Enable debug mode')
    
    args = parser.parse_args()
    
    # Initialize Alumni Tracer
    tracer = AlumniTracerPython(args.env)
    
    if args.action == 'charts':
        print("Generating alumni charts...")
        result = tracer.generate_all_charts()
        if result['success']:
            print(f"[SUCCESS] {result['message']}")
        else:
            print(f"[ERROR] {result['error']}")
    
    elif args.action == 'api':
        tracer.start_api_server(args.host, args.port, args.debug)
    
    elif args.action == 'import':
        if not args.file:
            print("[ERROR] Please specify a file path with --file")
            return
        
        print(f"Importing alumni data from {args.file}...")
        result = tracer.import_alumni_from_csv(args.file)
        if result['success']:
            print(f"[SUCCESS] Imported {result['imported_count']} alumni records")
        else:
            print(f"❌ Error: {result['error']}")
    
    elif args.action == 'export':
        output_path = args.output or 'alumni_export.csv'
        print(f"Exporting alumni data to {output_path}...")
        result = tracer.export_alumni_to_csv(output_path)
        if result['success']:
            print(f"✅ Exported {result['exported_count']} alumni records")
        else:
            print(f"❌ Error: {result['error']}")
    
    elif args.action == 'stats':
        print("Alumni Statistics:")
        print("=" * 50)
        stats = tracer.get_alumni_statistics()
        for key, value in stats.items():
            print(f"{key.replace('_', ' ').title()}: {value}")
    
    elif args.action == 'sample':
        print(f"Generating {args.count} sample alumni records...")
        result = tracer.run_sample_data_generation(args.count)
        if result['success']:
            print(f"[SUCCESS] {result['message']}")
        else:
            print(f"[ERROR] {result['error']}")
    
    elif args.action == 'info':
        print("Alumni Tracer Python System Information:")
        print("=" * 50)
        info = tracer.get_system_info()
        
        print(f"Environment: {info['config']['environment']}")
        print(f"Database: {info['config']['database_path']}")
        print(f"Charts Directory: {info['config']['charts_dir']}")
        print(f"API Port: {info['config']['api_port']}")
        print()
        
        print("Database Statistics:")
        for key, value in info['database_stats'].get('table_counts', {}).items():
            print(f"  {key}: {value} records")
        print()
        
        print("Alumni Statistics:")
        stats = info['statistics']
        print(f"  Total Alumni: {stats['total_alumni']}")
        print(f"  Active Alumni: {stats['active_alumni']}")
        print(f"  Engagement Rate: {stats['engagement_rate']}%")


if __name__ == "__main__":
    main()
