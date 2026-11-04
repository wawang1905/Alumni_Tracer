# Alumni Charts - Python Implementation

This is a Python transcription of the JavaScript alumni graph functionality from the Alumni Tracer project. The original JavaScript code used pure CSS and custom HTML elements to create charts, which have been converted to use Python's matplotlib and seaborn libraries.

## Features

The Python implementation includes all four chart types from the original JavaScript:

1. **Alumni Status Chart** - Donut chart showing active/pending/inactive alumni distribution
2. **Year Distribution Chart** - Horizontal bar chart showing alumni by graduation year
3. **College Distribution Chart** - Pie chart showing alumni distribution by college
4. **Course Distribution Chart** - Pie chart showing alumni distribution by course

## Installation

1. Install the required dependencies:
```bash
pip install -r requirements.txt
```

2. The script requires Python 3.7 or higher.

## Usage

### Basic Usage

```python
from alumni_charts import AlumniCharts, get_sample_alumni_data

# Load your alumni data (list of dictionaries)
alumni_data = get_sample_alumni_data()  # or load your own data

# Create chart generator
chart_generator = AlumniCharts(alumni_data)

# Generate individual charts
chart_generator.render_alumni_status_chart()
chart_generator.render_year_distribution()
chart_generator.render_college_distribution()
chart_generator.render_course_distribution()

# Or generate all charts at once
charts = chart_generator.generate_all_charts(output_dir='my_charts')
```

### Data Format

The alumni data should be a list of dictionaries with the following keys:

```python
[
    {
        'id': 1,
        'firstName': 'John',
        'lastName': 'Doe',
        'email': 'john@example.com',
        'graduationYear': '2018',
        'status': 'active',  # 'active', 'pending', or 'inactive'
        'college': 'Engineering',
        'course': 'Computer Science'
    },
    # ... more records
]
```

### Chart Customization

#### Alumni Status Chart
```python
chart_generator.render_alumni_status_chart(
    save_path='status_chart.png',  # Save to file
    show=True                      # Display on screen
)
```

#### Year Distribution Chart
```python
chart_generator.render_year_distribution(
    save_path='year_chart.png',
    show=True
)
```

#### College Distribution Chart
```python
chart_generator.render_college_distribution(
    save_path='college_chart.png',
    show=True,
    batch_filter='2018'  # Filter by graduation year
)
```

#### Course Distribution Chart
```python
chart_generator.render_course_distribution(
    save_path='course_chart.png',
    show=True,
    batch_filter='2018',    # Filter by graduation year
    college_filter='Engineering'  # Filter by college
)
```

## Chart Features

### Visual Design
- **Color Palette**: Matches the original JavaScript design with the same hex colors
- **Layout**: Two-panel layout with chart on left and legend on right
- **Typography**: Consistent font weights and sizes
- **Background**: White background with clean grid lines

### Data Processing
- **Sorting**: Years sorted descending, colleges/courses by count
- **Filtering**: Support for batch and college filtering
- **Aggregation**: Automatic counting and percentage calculation
- **Error Handling**: Graceful handling of missing or invalid data

### Output Options
- **Display**: Interactive matplotlib windows
- **Save**: High-resolution PNG files (300 DPI)
- **Batch Generation**: Generate all charts with one function call

## Comparison with JavaScript Version

| Feature | JavaScript | Python |
|---------|------------|---------|
| Technology | Pure CSS + HTML | matplotlib + seaborn |
| Interactivity | DOM manipulation | matplotlib widgets |
| Styling | CSS classes | matplotlib styling |
| Data Processing | JavaScript arrays | Python lists/dictionaries |
| File Output | HTML generation | PNG image files |
| Dependencies | None (browser-based) | Python packages required |

## File Structure

```
Alumni_Tracer/
├── alumni_charts.py          # Main chart generation code
├── requirements.txt           # Python dependencies
├── README_python_charts.md   # This documentation
└── charts/                   # Generated charts directory
    ├── alumni_status_chart.png
    ├── year_distribution_chart.png
    ├── college_distribution_chart.png
    └── course_distribution_chart.png
```

## Running the Example

To test the implementation with sample data:

```bash
python alumni_charts.py
```

This will:
1. Load sample alumni data
2. Generate all four chart types
3. Save them to the `charts/` directory
4. Print a summary of generated files

## Integration Notes

### Web Integration
To integrate with a web application:
1. Generate charts to files using `save_path` parameter
2. Serve the generated image files
3. Update HTML to reference the image files instead of inline CSS charts

### Database Integration
Replace the sample data function with your database connection:

```python
import sqlite3

def load_alumni_from_database():
    conn = sqlite3.connect('alumni.db')
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM alumni")
    columns = [desc[0] for desc in cursor.description]
    alumni_data = [dict(zip(columns, row)) for row in cursor.fetchall()]
    conn.close()
    return alumni_data

alumni_data = load_alumni_from_database()
chart_generator = AlumniCharts(alumni_data)
```

## Troubleshooting

### Common Issues

1. **Import Errors**: Ensure all dependencies are installed with `pip install -r requirements.txt`
2. **Display Issues**: On headless servers, set `show=False` and use `save_path` parameter
3. **Memory Issues**: For large datasets, consider processing in batches
4. **Font Issues**: On some systems, you may need to install additional fonts

### Performance Tips

1. For large datasets (>10,000 records), consider data sampling
2. Use `show=False` when generating multiple charts to avoid display conflicts
3. Close figures explicitly when generating many charts in a loop

## License

This Python implementation maintains compatibility with the original Alumni Tracer project's license.
