#!/usr/bin/env python3
"""
Test script to demonstrate APK Downloader Hub functionality
"""

import sys
import os

# Add the project to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from apk_downloader.searcher import AppSearcher
from apk_downloader.gemini_ai import GeminiEnricher
from apk_downloader.downloader import APKDownloader
from apk_downloader.config import Config

def test_configuration():
    """Test configuration loading"""
    print("=" * 60)
    print("Testing Configuration")
    print("=" * 60)
    
    Config.validate()
    print(f"Download directory: {Config.DOWNLOAD_DIR}")
    print(f"Max search results: {Config.MAX_SEARCH_RESULTS}")
    print(f"Gemini API configured: {'Yes' if Config.GEMINI_API_KEY else 'No'}")
    print()

def test_searcher():
    """Test app searcher"""
    print("=" * 60)
    print("Testing App Searcher")
    print("=" * 60)
    
    searcher = AppSearcher()
    print("✓ AppSearcher initialized successfully")
    print("  Methods available:")
    print("  - search_by_name(app_name, max_results=5)")
    print("  - get_app_details(package_id)")
    print("  - search_google(query, max_results=5)")
    print("  - find_app(query, by_package_id=False)")
    print()

def test_gemini():
    """Test Gemini AI integration"""
    print("=" * 60)
    print("Testing Gemini AI Integration")
    print("=" * 60)
    
    enricher = GeminiEnricher()
    print(f"✓ GeminiEnricher initialized")
    print(f"  Gemini available: {enricher.is_available()}")
    print("  Methods available:")
    print("  - get_app_insights(app_info)")
    print("  - get_app_recommendations(app_name, category)")
    print("  - analyze_app_safety(app_info)")
    print()

def test_downloader():
    """Test APK downloader"""
    print("=" * 60)
    print("Testing APK Downloader")
    print("=" * 60)
    
    downloader = APKDownloader()
    print(f"✓ APKDownloader initialized")
    print(f"  Download directory: {downloader.download_dir}")
    print("  Methods available:")
    print("  - download_apk(package_id, app_name, version)")
    print("  - get_apk_from_mirror(package_id, app_name)")
    print("  - list_downloads()")
    print()
    
    # Test download info generation
    print("Testing download info generation...")
    result = downloader.download_apk(
        "com.example.testapp",
        "Test App",
        "1.0.0"
    )
    
    if result:
        print(f"✓ Download info created: {result}")
    
    # List downloads
    downloads = downloader.list_downloads()
    print(f"✓ Total files in downloads: {len(downloads)}")
    print()

def test_cli_import():
    """Test CLI module import"""
    print("=" * 60)
    print("Testing CLI Module")
    print("=" * 60)
    
    try:
        from apk_downloader.cli import cli
        print("✓ CLI module imported successfully")
        print("  Available commands:")
        print("  - search: Search for Android applications")
        print("  - info: Get detailed app information")
        print("  - download: Get APK download information")
        print("  - similar: Find similar apps (Gemini)")
        print("  - downloads: List downloaded files")
        print()
    except Exception as e:
        print(f"✗ Error importing CLI: {e}")
        print()

def main():
    """Run all tests"""
    print("\n" + "=" * 60)
    print("Orwebcraft APK Downloader Hub - Test Suite")
    print("=" * 60 + "\n")
    
    test_configuration()
    test_searcher()
    test_gemini()
    test_downloader()
    test_cli_import()
    
    print("=" * 60)
    print("All Tests Completed!")
    print("=" * 60)
    print("\nTo use the CLI:")
    print("  python main.py --help")
    print("  python main.py search 'WhatsApp'")
    print("  python main.py info com.whatsapp")
    print("  python main.py download com.whatsapp")
    print()

if __name__ == '__main__':
    main()
