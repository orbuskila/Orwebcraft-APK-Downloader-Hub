"""
Gemini AI integration for app information enrichment
"""

from typing import Dict, Optional
try:
    import google.generativeai as genai
    GEMINI_AVAILABLE = True
except ImportError:
    GEMINI_AVAILABLE = False
    
from .config import Config


class GeminiEnricher:
    """Use Gemini AI to enrich app information"""
    
    def __init__(self):
        self.config = Config()
        self.model = None
        
        if GEMINI_AVAILABLE and self.config.GEMINI_API_KEY:
            try:
                genai.configure(api_key=self.config.GEMINI_API_KEY)
                self.model = genai.GenerativeModel('gemini-pro')
                print("Gemini AI initialized successfully")
            except Exception as e:
                print(f"Error initializing Gemini: {e}")
                self.model = None
        else:
            if not GEMINI_AVAILABLE:
                print("Gemini AI library not available. Install google-generativeai to use this feature.")
            elif not self.config.GEMINI_API_KEY:
                print("Gemini API key not configured. Set GEMINI_API_KEY in .env file.")
    
    def is_available(self) -> bool:
        """Check if Gemini is available and configured"""
        return self.model is not None
    
    def get_app_insights(self, app_info: Dict) -> Optional[str]:
        """
        Get AI-generated insights about an app
        
        Args:
            app_info: Dictionary containing app information
            
        Returns:
            String with AI-generated insights or None
        """
        if not self.is_available():
            return None
        
        try:
            app_name = app_info.get('title', 'Unknown')
            developer = app_info.get('developer', 'Unknown')
            description = app_info.get('description', '')[:500]  # Limit description length
            
            prompt = f"""Provide a brief, informative summary about the Android application '{app_name}' 
developed by {developer}. 

App Description: {description}

Please include:
1. Main purpose and features
2. Target audience
3. Notable aspects or concerns (if any)

Keep the response concise (3-4 sentences)."""
            
            response = self.model.generate_content(prompt)
            return response.text
            
        except Exception as e:
            print(f"Error getting Gemini insights: {e}")
            return None
    
    def get_app_recommendations(self, app_name: str, category: str = "") -> Optional[str]:
        """
        Get app recommendations based on an app name
        
        Args:
            app_name: Name of the app
            category: Optional category
            
        Returns:
            String with recommendations or None
        """
        if not self.is_available():
            return None
        
        try:
            prompt = f"""Suggest 3-5 similar Android applications to '{app_name}' 
{f'in the {category} category' if category else ''}.

For each app, provide:
- App name
- Brief description (one sentence)

Format as a numbered list."""
            
            response = self.model.generate_content(prompt)
            return response.text
            
        except Exception as e:
            print(f"Error getting recommendations: {e}")
            return None
    
    def analyze_app_safety(self, app_info: Dict) -> Optional[str]:
        """
        Get AI analysis of app safety and privacy
        
        Args:
            app_info: Dictionary containing app information
            
        Returns:
            String with safety analysis or None
        """
        if not self.is_available():
            return None
        
        try:
            app_name = app_info.get('title', 'Unknown')
            developer = app_info.get('developer', 'Unknown')
            content_rating = app_info.get('contentRating', 'Unknown')
            
            prompt = f"""Provide a brief safety and privacy analysis for the Android app '{app_name}' 
by {developer} with content rating: {content_rating}.

Consider:
1. General safety concerns for this type of app
2. Privacy considerations
3. Recommendations for users

Keep it brief (2-3 sentences) and factual."""
            
            response = self.model.generate_content(prompt)
            return response.text
            
        except Exception as e:
            print(f"Error analyzing safety: {e}")
            return None
