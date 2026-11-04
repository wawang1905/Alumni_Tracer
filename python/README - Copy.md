# Alumni Tracer Python Modules

This directory contains all Python modules for the Alumni Tracer system, organized by functionality.

## 📁 Directory Structure

```
python/
├── charts/                  # Chart generation modules
│   └── alumni_charts.py    # Main chart generation class
├── api/                     # Flask API server
│   └── chart_api.py        # REST API for charts and data
├── data/                    # Data processing modules
│   ├── alumni_data_processor.py  # Alumni data operations
│   └── sales_analytics_example.py # Sample analytics script
├── utils/                   # Utility modules
│   ├── __init__.py         # Package initialization
│   ├── config.py           # Configuration settings
│   ├── helpers.py          # Helper functions
│   └── database.py         # Database utilities
├── main.py                 # Main entry point
├── requirements.txt        # Python dependencies
└── README.md              # This file
```

## 🚀 Quick Start

### 1. Install Dependencies
```bash
cd python
pip install -r requirements.txt
```

### 2. Run the Main Application
```bash
# Show system information
python main.py --action info

# Generate charts
python main.py --action charts

# Start API server
python main.py --action api

# Generate sample data
python main.py --action sample --count 100

# Import alumni from CSV
python main.py --action import --file alumni_data.csv

# Export alumni to CSV
python main.py --action export --output alumni_export.csv

# Show statistics
python main.py --action stats
```

## 📊 Chart Generation

The `charts/alumni_charts.py` module provides comprehensive chart generation capabilities:

### Available Charts:
- **Alumni Status Chart** - Donut chart showing active/pending/inactive distribution
- **Year Distribution Chart** - Horizontal bar chart showing alumni by graduation year  
- **College Distribution Chart** - Pie chart showing alumni by college
- **Course Distribution Chart** - Pie chart showing alumni by course

### Usage:
```python
from charts.alumni_charts import AlumniCharts, get_sample_alumni_data

# Load data
alumni_data = get_sample_alumni_data()

# Create chart generator
chart_generator = AlumniCharts(alumni_data)

# Generate individual charts
chart_generator.render_alumni_status_chart()
chart_generator.render_year_distribution()
chart_generator.render_college_distribution()
chart_generator.render_course_distribution()

# Generate all charts at once
charts = chart_generator.generate_all_charts()
```

## 🔌 API Server

The `api/chart_api.py` module provides a RESTful API for chart generation and data analytics:

### Endpoints:
- `GET /api/health` - Health check
- `GET /api/alumni/stats` - Alumni statistics
- `GET /api/alumni/data` - All alumni data
- `POST /api/charts/generate` - Generate all charts
- `GET /api/charts/status` - Generate status chart
- `GET /api/charts/year-distribution` - Generate year chart
- `GET /api/charts/college-distribution` - Generate college chart
- `GET /api/charts/course-distribution` - Generate course chart
- `GET /api/analytics/engagement` - Engagement analytics
- `GET /api/analytics/demographics` - Demographics analytics
- `GET /charts/<filename>` - Serve chart images

### Starting the API Server:
```bash
# Development mode
python main.py --action api --debug

# Production mode
python main.py --action api --host 0.0.0.0 --port 5000
```

## 📄 Data Processing

The `data/alumni_data_processor.py` module handles all alumni data operations:

### Features:
- **Database Management** - SQLite database with alumni, events, surveys tables
- **Import/Export** - CSV import/export with validation
- **CRUD Operations** - Create, read, update, delete alumni records
- **Search** - Full-text search across multiple fields
- **Statistics** - Comprehensive alumni analytics
- **Sample Data** - Generate test data for development

### Usage:
```python
from data.alumni_data_processor import AlumniDataProcessor

# Initialize processor
processor = AlumniDataProcessor()

# Add alumni
result = processor.add_alumni({
    'firstName': 'John',
    'lastName': 'Doe',
    'email': 'john@example.com',
    'graduationYear': '2020',
    'college': 'Engineering',
    'course': 'Computer Science'
})

# Get statistics
stats = processor.get_alumni_statistics()

# Search alumni
results = processor.search_alumni('John')

# Export to CSV
processor.export_to_csv('alumni_export.csv')
```

## 🛠️ Utilities

The `utils/` package provides common utilities:

### Configuration (`utils/config.py`)
- Environment-specific settings
- Database paths and API configuration
- Chart colors and styling
- Validation functions

### Helpers (`utils/helpers.py`)
- Date formatting and validation
- Email and phone validation
- Text sanitization and formatting
- Pagination and search utilities
- File handling functions

### Database (`utils/database.py`)
- Connection management
- Query execution helpers
- Migration support
- Backup and restore utilities

## ⚙️ Configuration

Configuration is handled through the `utils/config.py` module:

### Environment Settings:
- **Development** - Debug mode, fast chart regeneration
- **Production** - Optimized settings, security features
- **Testing** - In-memory database, disabled background tasks

### Key Settings:
```python
# Database
DATABASE_PATH = '../../alumni_data.db'

# API
API_HOST = '0.0.0.0'
API_PORT = 5000
API_DEBUG = True

# Charts
CHARTS_DIR = '../../charts'
CHART_DPI = 300
CHART_COLORS = ['#1e4a76', '#3f6ea1', '#5f8fbe', ...]
```

## 🔧 Development

### Running Tests:
```bash
# Run with testing environment
python main.py --env testing --action info
```

### Database Migrations:
```python
from utils.database import MigrationManager

# Initialize migration manager
migration_manager = MigrationManager(db_manager)

# Run migration
migration_manager.run_migration('add_new_field', '''
    ALTER TABLE alumni ADD COLUMN new_field TEXT;
''')
```

### Background Tasks:
The API server includes automatic chart regeneration every 5 minutes (configurable).

## 📝 Integration with Web Application

### JavaScript Integration:
The Python API integrates seamlessly with the existing JavaScript frontend:

```javascript
// API base URL
const CHART_API_BASE = 'http://localhost:5000/api';

// Get alumni statistics
const stats = await fetch(`${CHART_API_BASE}/alumni/stats`);

// Generate charts
const charts = await fetch(`${CHART_API_BASE}/charts/generate`, {
    method: 'POST'
});
```

### File Paths:
- Charts are saved to `../../charts/` relative to the API server
- Database is stored at `../../alumni_data.db`
- All paths are configurable through the Config class

## 🐛 Troubleshooting

### Common Issues:

1. **Port Already in Use**
   ```bash
   # Check what's using port 5000
   netstat -ano | findstr :5000
   
   # Use different port
   python main.py --action api --port 5001
   ```

2. **Database Lock Error**
   ```bash
   # Check for existing processes
   # Close any existing API server instances
   ```

3. **Missing Dependencies**
   ```bash
   # Reinstall dependencies
   pip install -r requirements.txt --force-reinstall
   ```

4. **Import Errors**
   ```bash
   # Check Python path
   python -c "import sys; print(sys.path)"
   
   # Run from correct directory
   cd python
   python main.py --action info
   ```

## 📚 API Documentation

### Response Format:
All API responses follow this format:
```json
{
    "success": true,
    "data": { ... },
    "message": "Operation completed successfully"
}
```

### Error Handling:
```json
{
    "success": false,
    "error": "Error description"
}
```

## 🔄 Version History

- **v1.0** - Initial implementation with chart generation
- **v1.1** - Added API server and data processing
- **v1.2** - Organized into modular structure
- **v1.3** - Added utilities and configuration management

## 📄 License

This Python implementation maintains compatibility with the original Alumni Tracer project's license.

## 🤝 Contributing

When adding new functionality:
1. Follow the existing directory structure
2. Add appropriate documentation
3. Include error handling
4. Update configuration if needed
5. Test with different environments

## 📞 Support

For issues or questions:
1. Check the troubleshooting section
2. Review the configuration settings
3. Test with sample data first
4. Check API server logs for errors
