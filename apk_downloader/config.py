"""
Configuration management for APK Downloader Hub
"""

import os
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()


class Config:
    """Configuration class for the APK Downloader Hub"""
    
    # API Keys
    GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
    
    # User Agent
    USER_AGENT = os.getenv(
        "USER_AGENT",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    )
    
    # Download settings
    DOWNLOAD_DIR = Path("downloads")
    
    # Search settings
    MAX_SEARCH_RESULTS = 10
    
    @classmethod
    def validate(cls):
        """Validate required configuration"""
        if not cls.GEMINI_API_KEY:
            print("Warning: GEMINI_API_KEY not set. Gemini features will be limited.")
        
        # Create download directory if it doesn't exist
        cls.DOWNLOAD_DIR.mkdir(exist_ok=True)
        
        return True
