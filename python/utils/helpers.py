"""
Helper functions for Alumni Tracer Python modules
"""

import re
from datetime import datetime
from typing import Any, Optional


def format_date(date_str: str, input_format: str = '%Y-%m-%d', output_format: str = '%B %d, %Y') -> str:
    """Format date string from one format to another"""
    try:
        if not date_str:
            return ''
        
        date_obj = datetime.strptime(date_str, input_format)
        return date_obj.strftime(output_format)
    except (ValueError, TypeError):
        return date_str  # Return original if formatting fails


def escape_html(text: str) -> str:
    """Escape HTML characters in text"""
    if not text:
        return ''
    
    return (str(text)
            .replace('&', '&amp;')
            .replace('<', '&lt;')
            .replace('>', '&gt;')
            .replace('"', '&quot;')
            .replace("'", '&#039;'))


def validate_email(email: str) -> bool:
    """Validate email address format"""
    if not email:
        return False
    
    pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
    return bool(re.match(pattern, email))


def validate_phone(phone: str) -> bool:
    """Validate phone number format"""
    if not phone:
        return True  # Phone is optional
    
    # Remove common phone number formatting
    clean_phone = re.sub(r'[^\d+]', '', phone)
    
    # Check if it's a valid phone number (10-15 digits, optional +)
    return bool(re.match(r'^\+?\d{10,15}$', clean_phone))


def sanitize_filename(filename: str) -> str:
    """Sanitize filename for safe file storage"""
    if not filename:
        return 'unnamed_file'
    
    # Remove or replace unsafe characters
    safe_chars = re.sub(r'[<>:"/\\|?*]', '_', filename)
    safe_chars = re.sub(r'[^\w\s.-]', '', safe_chars)
    safe_chars = re.sub(r'\s+', '_', safe_chars)
    
    # Limit length and ensure it's not empty
    return safe_chars[:100] or 'unnamed_file'


def calculate_engagement_rate(active_count: int, total_count: int) -> float:
    """Calculate engagement rate as percentage"""
    if total_count == 0:
        return 0.0
    
    return round((active_count / total_count) * 100, 1)


def format_currency(amount: float, currency: str = 'USD') -> str:
    """Format amount as currency"""
    try:
        if currency == 'USD':
            return f"${amount:,.2f}"
        elif currency == 'EUR':
            return f"€{amount:,.2f}"
        elif currency == 'GBP':
            return f"£{amount:,.2f}"
        else:
            return f"{amount:,.2f} {currency}"
    except (ValueError, TypeError):
        return f"0.00 {currency}"


def truncate_text(text: str, max_length: int = 100, suffix: str = '...') -> str:
    """Truncate text to specified length"""
    if not text or len(text) <= max_length:
        return text
    
    return text[:max_length - len(suffix)] + suffix


def generate_slug(text: str) -> str:
    """Generate URL-friendly slug from text"""
    if not text:
        return ''
    
    # Convert to lowercase and replace spaces with hyphens
    slug = text.lower().strip()
    slug = re.sub(r'[^\w\s-]', '', slug)
    slug = re.sub(r'[-\s]+', '-', slug)
    
    return slug


def get_file_extension(filename: str) -> str:
    """Get file extension from filename"""
    if not filename:
        return ''
    
    parts = filename.rsplit('.', 1)
    return parts[1].lower() if len(parts) > 1 else ''


def is_valid_file_type(filename: str, allowed_types: list) -> bool:
    """Check if file type is allowed"""
    extension = get_file_extension(filename)
    return extension in [ext.lower() for ext in allowed_types]


def format_file_size(size_bytes: int) -> str:
    """Format file size in human-readable format"""
    if size_bytes == 0:
        return "0 B"
    
    size_names = ["B", "KB", "MB", "GB", "TB"]
    i = 0
    size = float(size_bytes)
    
    while size >= 1024.0 and i < len(size_names) - 1:
        size /= 1024.0
        i += 1
    
    return f"{size:.1f} {size_names[i]}"


def deep_merge(dict1: dict, dict2: dict) -> dict:
    """Deep merge two dictionaries"""
    result = dict1.copy()
    
    for key, value in dict2.items():
        if key in result and isinstance(result[key], dict) and isinstance(value, dict):
            result[key] = deep_merge(result[key], value)
        else:
            result[key] = value
    
    return result


def clean_dict(data: dict, remove_empty: bool = True, remove_none: bool = True) -> dict:
    """Clean dictionary by removing empty or None values"""
    cleaned = {}
    
    for key, value in data.items():
        if remove_none and value is None:
            continue
        if remove_empty and value == '':
            continue
        if isinstance(value, dict):
            cleaned_value = clean_dict(value, remove_empty, remove_none)
            if cleaned_value:  # Only add if not empty
                cleaned[key] = cleaned_value
        else:
            cleaned[key] = value
    
    return cleaned


def pagination_info(total_items: int, page: int, page_size: int) -> dict:
    """Calculate pagination information"""
    if page_size <= 0:
        page_size = 20
    
    total_pages = (total_items + page_size - 1) // page_size
    start_item = (page - 1) * page_size + 1 if total_items > 0 else 0
    end_item = min(page * page_size, total_items)
    
    return {
        'total_items': total_items,
        'total_pages': total_pages,
        'current_page': page,
        'page_size': page_size,
        'start_item': start_item,
        'end_item': end_item,
        'has_next': page < total_pages,
        'has_prev': page > 1
    }


def build_query_params(base_url: str, params: dict) -> str:
    """Build URL with query parameters"""
    if not params:
        return base_url
    
    # Clean and filter parameters
    clean_params = {k: v for k, v in params.items() if v is not None and v != ''}
    
    if not clean_params:
        return base_url
    
    # Build query string
    query_string = '&'.join(f"{k}={v}" for k, v in clean_params.items())
    
    separator = '&' if '?' in base_url else '?'
    return f"{base_url}{separator}{query_string}"


def get_batch_years(data: list, year_field: str = 'graduationYear') -> list:
    """Get unique batch years from data"""
    years = set()
    
    for item in data:
        year = item.get(year_field, '')
        if year and year.isdigit():
            years.add(year)
    
    return sorted(years, reverse=True)


def get_colleges(data: list, college_field: str = 'college') -> list:
    """Get unique colleges from data"""
    colleges = set()
    
    for item in data:
        college = item.get(college_field, '')
        if college and college.strip():
            colleges.add(college.strip())
    
    return sorted(list(colleges))


def get_courses(data: list, course_field: str = 'course') -> list:
    """Get unique courses from data"""
    courses = set()
    
    for item in data:
        course = item.get(course_field, '')
        if course and course.strip():
            courses.add(course.strip())
    
    return sorted(list(courses))


def calculate_age(birth_date: str, reference_date: Optional[str] = None) -> int:
    """Calculate age from birth date"""
    try:
        if not birth_date:
            return 0
        
        birth = datetime.strptime(birth_date, '%Y-%m-%d')
        
        if reference_date:
            reference = datetime.strptime(reference_date, '%Y-%m-%d')
        else:
            reference = datetime.now()
        
        age = reference.year - birth.year
        
        # Adjust if birthday hasn't occurred yet this year
        if (reference.month, reference.day) < (birth.month, birth.day):
            age -= 1
        
        return age
    except (ValueError, TypeError):
        return 0


def is_recent_activity(activity_date: str, days_threshold: int = 30) -> bool:
    """Check if activity is recent"""
    try:
        if not activity_date:
            return False
        
        activity = datetime.strptime(activity_date, '%Y-%m-%d')
        now = datetime.now()
        delta = now - activity
        
        return delta.days <= days_threshold
    except (ValueError, TypeError):
        return False
