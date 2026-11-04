"""
Configuration settings for Alumni Tracer Python modules
"""

import os
from typing import Dict, Any

class Config:
    """Configuration class for Alumni Tracer"""
    
    # Database settings
    DATABASE_PATH = '../../alumni_data.db'
    
    # API settings
    API_HOST = '0.0.0.0'
    API_PORT = 5000
    API_DEBUG = True
    
    # Chart settings
    CHARTS_DIR = '../../charts'
    CHART_DPI = 300
    CHART_STYLE = 'seaborn-v0_8-whitegrid'
    
    # Chart colors matching the original JavaScript design
    CHART_COLORS = [
        '#1e4a76',  # Primary blue
        '#3f6ea1',  # Light blue
        '#5f8fbe',  # Medium blue
        '#7ba6cd',  # Very light blue
        '#f59e0b',  # Amber
        '#ef4444',  # Red
        '#10b981',  # Green
        '#8b5cf6'   # Purple
    ]
    
    # Alumni status colors
    STATUS_COLORS = {
        'active': '#1e4a76',
        'pending': '#f59e0b',
        'inactive': '#94a3b8'
    }
    
    # Pagination settings
    DEFAULT_PAGE_SIZE = 20
    MAX_PAGE_SIZE = 100
    
    # File upload settings
    MAX_FILE_SIZE = 10 * 1024 * 1024  # 10MB
    ALLOWED_FILE_TYPES = ['csv', 'xlsx', 'xls']
    
    # Background task settings
    CHART_REGENERATION_INTERVAL = 300  # 5 minutes in seconds
    
    @classmethod
    def get_database_url(cls) -> str:
        """Get database connection URL"""
        return f"sqlite:///{os.path.abspath(cls.DATABASE_PATH)}"
    
    @classmethod
    def get_charts_url(cls) -> str:
        """Get charts directory URL"""
        return os.path.abspath(cls.CHARTS_DIR)
    
    @classmethod
    def get_api_base_url(cls) -> str:
        """Get API base URL"""
        return f"http://{cls.API_HOST}:{cls.API_PORT}"
    
    @classmethod
    def validate_config(cls) -> Dict[str, Any]:
        """Validate configuration settings"""
        issues = []
        
        # Check if directories exist
        if not os.path.exists(os.path.dirname(cls.DATABASE_PATH)):
            issues.append("Database directory does not exist")
        
        if not os.path.exists(cls.CHARTS_DIR):
            try:
                os.makedirs(cls.CHARTS_DIR, exist_ok=True)
            except Exception as e:
                issues.append(f"Cannot create charts directory: {e}")
        
        # Check port availability
        import socket
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        result = sock.connect_ex(('localhost', cls.API_PORT))
        sock.close()
        
        if result == 0:
            issues.append(f"Port {cls.API_PORT} is already in use")
        
        return {
            'valid': len(issues) == 0,
            'issues': issues
        }


# Environment-specific configurations
class DevelopmentConfig(Config):
    """Development environment configuration"""
    API_DEBUG = True
    CHART_REGENERATION_INTERVAL = 60  # 1 minute for development


class ProductionConfig(Config):
    """Production environment configuration"""
    API_DEBUG = False
    CHART_REGENERATION_INTERVAL = 300  # 5 minutes for production


class TestingConfig(Config):
    """Testing environment configuration"""
    DATABASE_PATH = ':memory:'  # In-memory database for testing
    API_DEBUG = False
    CHART_REGENERATION_INTERVAL = 0  # Disable background tasks


# Configuration mapping
config_map = {
    'development': DevelopmentConfig,
    'production': ProductionConfig,
    'testing': TestingConfig,
    'default': DevelopmentConfig
}


def get_config(env: str = 'default') -> Config:
    """Get configuration based on environment"""
    return config_map.get(env, DevelopmentConfig)
