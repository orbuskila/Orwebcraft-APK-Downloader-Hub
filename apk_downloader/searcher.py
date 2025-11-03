"""
App search functionality using Google Search and Play Store
"""

import re
from typing import List, Dict, Optional
from googlesearch import search
from google_play_scraper import app as play_app
from google_play_scraper import search as play_search
from .config import Config


class AppSearcher:
    """Search for Android apps using Google Search and Play Store"""
    
    def __init__(self):
        self.config = Config()
    
    def search_by_name(self, app_name: str, max_results: int = 5) -> List[Dict]:
        """
        Search for apps by name using Google Play Store
        
        Args:
            app_name: Name of the app to search for
            max_results: Maximum number of results to return
            
        Returns:
            List of app information dictionaries
        """
        try:
            print(f"Searching for '{app_name}' in Google Play Store...")
            results = play_search(app_name, n_hits=max_results)
            
            app_list = []
            for result in results:
                app_info = {
                    'appId': result.get('appId'),
                    'title': result.get('title'),
                    'developer': result.get('developer'),
                    'icon': result.get('icon'),
                    'score': result.get('score'),
                    'free': result.get('free'),
                }
                app_list.append(app_info)
            
            return app_list
        except Exception as e:
            print(f"Error searching for app: {e}")
            return []
    
    def get_app_details(self, package_id: str) -> Optional[Dict]:
        """
        Get detailed information about an app by package ID
        
        Args:
            package_id: The package ID (e.g., com.example.app)
            
        Returns:
            Dictionary with app details or None if not found
        """
        try:
            print(f"Fetching details for package: {package_id}")
            app_data = play_app(package_id)
            
            app_info = {
                'appId': app_data.get('appId'),
                'title': app_data.get('title'),
                'description': app_data.get('description'),
                'developer': app_data.get('developer'),
                'developerId': app_data.get('developerId'),
                'icon': app_data.get('icon'),
                'score': app_data.get('score'),
                'ratings': app_data.get('ratings'),
                'reviews': app_data.get('reviews'),
                'installs': app_data.get('installs'),
                'minInstalls': app_data.get('minInstalls'),
                'version': app_data.get('version'),
                'updated': app_data.get('updated'),
                'androidVersion': app_data.get('androidVersion'),
                'contentRating': app_data.get('contentRating'),
                'free': app_data.get('free'),
                'price': app_data.get('price'),
                'url': app_data.get('url'),
            }
            
            return app_info
        except Exception as e:
            print(f"Error fetching app details: {e}")
            return None
    
    def search_google(self, query: str, max_results: int = 5) -> List[str]:
        """
        Search Google for app-related information
        
        Args:
            query: Search query
            max_results: Maximum number of results
            
        Returns:
            List of URLs from Google search
        """
        try:
            print(f"Searching Google for: {query}")
            search_query = f"{query} site:play.google.com"
            results = list(search(search_query, num_results=max_results))
            
            # Extract package IDs from Play Store URLs
            package_ids = []
            for url in results:
                match = re.search(r'id=([a-zA-Z0-9._]+)', url)
                if match:
                    package_ids.append(match.group(1))
            
            return package_ids
        except Exception as e:
            print(f"Error searching Google: {e}")
            return []
    
    def find_app(self, query: str, by_package_id: bool = False) -> List[Dict]:
        """
        Find apps by name or package ID
        
        Args:
            query: App name or package ID
            by_package_id: If True, search by package ID; otherwise by name
            
        Returns:
            List of app information dictionaries
        """
        if by_package_id:
            # Search by package ID
            app_details = self.get_app_details(query)
            return [app_details] if app_details else []
        else:
            # First try Play Store search
            results = self.search_by_name(query)
            
            # If no results, try Google search
            if not results:
                print("No results from Play Store, trying Google search...")
                package_ids = self.search_google(query)
                results = []
                for pkg_id in package_ids[:5]:
                    app_details = self.get_app_details(pkg_id)
                    if app_details:
                        results.append(app_details)
            
            return results
