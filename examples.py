"""
Example usage of the APK Downloader Hub API
"""

from apk_downloader.searcher import AppSearcher
from apk_downloader.gemini_ai import GeminiEnricher
from apk_downloader.downloader import APKDownloader

def example_search():
    """Example: Search for apps"""
    print("\n" + "="*60)
    print("Example 1: Searching for Apps")
    print("="*60)
    
    searcher = AppSearcher()
    
    # Search by name
    print("\n1. Search by name:")
    apps = searcher.search_by_name("WhatsApp", max_results=3)
    for app in apps:
        print(f"  - {app.get('title')} ({app.get('appId')})")
    
    # Get app details by package ID
    print("\n2. Get details by package ID:")
    app_details = searcher.get_app_details("com.whatsapp")
    if app_details:
        print(f"  Title: {app_details.get('title')}")
        print(f"  Developer: {app_details.get('developer')}")
        print(f"  Version: {app_details.get('version')}")
        print(f"  Rating: {app_details.get('score')}/5.0")

def example_gemini():
    """Example: Use Gemini AI for insights"""
    print("\n" + "="*60)
    print("Example 2: Gemini AI Integration")
    print("="*60)
    
    enricher = GeminiEnricher()
    
    if not enricher.is_available():
        print("  Gemini AI not available (API key not configured)")
        return
    
    # Sample app info
    app_info = {
        'title': 'WhatsApp Messenger',
        'developer': 'WhatsApp LLC',
        'description': 'WhatsApp Messenger is a FREE messaging app...',
        'contentRating': 'Everyone',
    }
    
    # Get insights
    print("\n1. Get app insights:")
    insights = enricher.get_app_insights(app_info)
    if insights:
        print(f"  {insights}")
    
    # Get recommendations
    print("\n2. Get similar apps:")
    recommendations = enricher.get_app_recommendations("WhatsApp")
    if recommendations:
        print(f"  {recommendations}")
    
    # Safety analysis
    print("\n3. Safety analysis:")
    safety = enricher.analyze_app_safety(app_info)
    if safety:
        print(f"  {safety}")

def example_download():
    """Example: Download APK information"""
    print("\n" + "="*60)
    print("Example 3: APK Download Information")
    print("="*60)
    
    downloader = APKDownloader()
    
    # Download info for an app
    print("\n1. Get download info:")
    result = downloader.download_apk(
        package_id="com.whatsapp",
        app_name="WhatsApp Messenger",
        version="2.23.25.84"
    )
    
    # Get mirror links
    print("\n2. Get mirror site links:")
    mirrors = downloader.get_apk_from_mirror(
        package_id="com.whatsapp",
        app_name="WhatsApp"
    )
    
    # List downloads
    print("\n3. List all downloads:")
    downloads = downloader.list_downloads()
    for download in downloads:
        print(f"  - {download}")

def example_complete_workflow():
    """Example: Complete workflow"""
    print("\n" + "="*60)
    print("Example 4: Complete Workflow")
    print("="*60)
    
    # Initialize components
    searcher = AppSearcher()
    enricher = GeminiEnricher()
    downloader = APKDownloader()
    
    # 1. Search for app
    print("\n1. Search for app...")
    query = "Instagram"
    apps = searcher.search_by_name(query, max_results=1)
    
    if not apps:
        print("  No apps found")
        return
    
    app = apps[0]
    package_id = app.get('appId')
    
    # 2. Get detailed info
    print("\n2. Get detailed information...")
    app_details = searcher.get_app_details(package_id)
    if app_details:
        print(f"  Title: {app_details.get('title')}")
        print(f"  Developer: {app_details.get('developer')}")
        print(f"  Version: {app_details.get('version')}")
    
    # 3. Get AI insights (if available)
    if enricher.is_available() and app_details:
        print("\n3. Get AI insights...")
        insights = enricher.get_app_insights(app_details)
        if insights:
            print(f"  {insights[:200]}...")
    
    # 4. Get download info
    print("\n4. Get download information...")
    downloader.download_apk(
        package_id=package_id,
        app_name=app_details.get('title') if app_details else None,
        version=app_details.get('version') if app_details else None
    )

if __name__ == '__main__':
    print("\n" + "="*60)
    print("APK Downloader Hub - Usage Examples")
    print("="*60)
    
    print("\nNote: These examples require internet connectivity.")
    print("In restricted environments, the API calls will fail gracefully.")
    
    try:
        example_search()
    except Exception as e:
        print(f"\nExample 1 failed: {e}")
    
    try:
        example_gemini()
    except Exception as e:
        print(f"\nExample 2 failed: {e}")
    
    try:
        example_download()
    except Exception as e:
        print(f"\nExample 3 failed: {e}")
    
    try:
        example_complete_workflow()
    except Exception as e:
        print(f"\nExample 4 failed: {e}")
    
    print("\n" + "="*60)
    print("Examples Complete!")
    print("="*60)
