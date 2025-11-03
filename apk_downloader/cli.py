"""
Main CLI interface for APK Downloader Hub
"""

import click
from .config import Config
from .searcher import AppSearcher
from .gemini_ai import GeminiEnricher
from .downloader import APKDownloader


def print_app_info(app: dict, index: int = None, detailed: bool = False):
    """Print formatted app information"""
    prefix = f"{index}. " if index is not None else ""
    
    print(f"\n{prefix}{'='*60}")
    print(f"📱 {app.get('title', 'Unknown')}")
    print(f"{'='*60}")
    print(f"Package ID: {app.get('appId', 'N/A')}")
    print(f"Developer: {app.get('developer', 'N/A')}")
    
    if detailed:
        print(f"Version: {app.get('version', 'N/A')}")
        print(f"Rating: {app.get('score', 'N/A')}/5.0")
        print(f"Installs: {app.get('installs', 'N/A')}")
        print(f"Updated: {app.get('updated', 'N/A')}")
        print(f"Android Version: {app.get('androidVersion', 'N/A')}")
        print(f"Content Rating: {app.get('contentRating', 'N/A')}")
        print(f"Free: {'Yes' if app.get('free') else 'No'}")
        print(f"Price: {app.get('price', 'Free') if app.get('price') else 'Free'}")
        
        if app.get('description'):
            desc = app['description'][:200] + "..." if len(app['description']) > 200 else app['description']
            print(f"\nDescription: {desc}")
        
        if app.get('url'):
            print(f"\nPlay Store: {app['url']}")
    else:
        score = app.get('score', 'N/A')
        free = "Free" if app.get('free') else "Paid"
        print(f"Rating: {score}/5.0 | {free}")


@click.group()
@click.version_option(version="1.0.0")
def cli():
    """
    🚀 Orwebcraft APK Downloader Hub
    
    Search for Android apps and get download information
    with Google Search and Gemini AI integration.
    """
    Config.validate()


@cli.command()
@click.argument('query')
@click.option('--package-id', '-p', is_flag=True, help='Search by package ID instead of name')
@click.option('--detailed', '-d', is_flag=True, help='Show detailed information')
@click.option('--gemini', '-g', is_flag=True, help='Use Gemini AI for insights')
def search(query, package_id, detailed, gemini):
    """
    🔎 Search for Android applications
    
    Examples:
        apk-hub search "WhatsApp"
        apk-hub search com.whatsapp -p
        apk-hub search "Instagram" --gemini
    """
    searcher = AppSearcher()
    
    print(f"\n🔍 Searching for: {query}")
    print(f"   Search mode: {'Package ID' if package_id else 'App Name'}")
    
    results = searcher.find_app(query, by_package_id=package_id)
    
    if not results:
        print("\n❌ No apps found.")
        return
    
    print(f"\n✅ Found {len(results)} app(s):")
    
    for i, app in enumerate(results, 1):
        print_app_info(app, index=i, detailed=detailed)
        
        if gemini and detailed:
            enricher = GeminiEnricher()
            if enricher.is_available():
                print(f"\n🤖 Gemini AI Insights:")
                insights = enricher.get_app_insights(app)
                if insights:
                    print(insights)
                
                safety = enricher.analyze_app_safety(app)
                if safety:
                    print(f"\n🔒 Safety Analysis:")
                    print(safety)


@cli.command()
@click.argument('package_id')
@click.option('--version', '-v', help='Specific version to download')
@click.option('--name', '-n', help='App name (for filename)')
def download(package_id, version, name):
    """
    ⬇️  Download APK for a specific app
    
    Examples:
        apk-hub download com.whatsapp
        apk-hub download com.instagram.android --version 2.5.0
    """
    downloader = APKDownloader()
    
    print(f"\n📥 Preparing download for: {package_id}")
    
    # Get app details first
    searcher = AppSearcher()
    app_info = searcher.get_app_details(package_id)
    
    if app_info:
        print_app_info(app_info, detailed=True)
        app_name = name or app_info.get('title')
        app_version = version or app_info.get('version')
    else:
        app_name = name
        app_version = version
        print(f"\n⚠️  Could not fetch app details from Play Store")
    
    # Download (or get download info)
    result = downloader.download_apk(package_id, app_name, app_version)
    
    # Also show mirror links
    downloader.get_apk_from_mirror(package_id, app_name)


@cli.command()
@click.argument('package_id')
def info(package_id):
    """
    ℹ️  Get detailed information about an app
    
    Examples:
        apk-hub info com.whatsapp
    """
    searcher = AppSearcher()
    enricher = GeminiEnricher()
    
    print(f"\n📋 Fetching information for: {package_id}")
    
    app = searcher.get_app_details(package_id)
    
    if not app:
        print("\n❌ App not found.")
        return
    
    print_app_info(app, detailed=True)
    
    if enricher.is_available():
        print(f"\n{'='*60}")
        print("🤖 Gemini AI Insights")
        print(f"{'='*60}")
        
        insights = enricher.get_app_insights(app)
        if insights:
            print(f"\n{insights}")
        
        safety = enricher.analyze_app_safety(app)
        if safety:
            print(f"\n🔒 Safety Analysis:")
            print(f"{safety}")
        
        recommendations = enricher.get_app_recommendations(app.get('title', ''))
        if recommendations:
            print(f"\n💡 Similar Apps:")
            print(f"{recommendations}")


@cli.command()
def downloads():
    """
    📂 List downloaded files
    """
    downloader = APKDownloader()
    files = downloader.list_downloads()
    
    if not files:
        print("\n📭 No downloaded files.")
        return
    
    print(f"\n📂 Downloaded files ({len(files)}):")
    for i, file in enumerate(files, 1):
        print(f"{i}. {file}")


@cli.command()
@click.argument('app_name')
def similar(app_name):
    """
    🔄 Find similar apps using Gemini AI
    
    Examples:
        apk-hub similar "WhatsApp"
    """
    enricher = GeminiEnricher()
    
    if not enricher.is_available():
        print("\n❌ Gemini AI not available. Please configure GEMINI_API_KEY.")
        return
    
    print(f"\n🔄 Finding apps similar to: {app_name}")
    
    recommendations = enricher.get_app_recommendations(app_name)
    
    if recommendations:
        print(f"\n{recommendations}")
    else:
        print("\n❌ Could not generate recommendations.")


if __name__ == '__main__':
    cli()
