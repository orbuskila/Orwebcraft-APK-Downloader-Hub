# 🚀 Orwebcraft APK Downloader Hub

A powerful tool to search for Android applications and get APK download information with **Google Search** and **Gemini AI** integration.

## ✨ Features

- 🔎 **Search:** Search for applications by name or package ID
- ⬇️ **Download:** Get APK download information and links for specific app versions
- 🤖 **Gemini AI:** Enrich app information with AI-generated insights and recommendations
- 🔍 **Google Search:** Find apps using Google Search integration
- 📊 **App Details:** Get comprehensive information from Google Play Store
- 🔒 **Safety Analysis:** AI-powered safety and privacy analysis

## 📋 Requirements

- Python 3.7 or higher
- Google Gemini API key (optional, for AI features)

## 🔧 Installation

### 1. Clone the repository

```bash
git clone https://github.com/orbuskila/Orwebcraft-APK-Downloader-Hub.git
cd Orwebcraft-APK-Downloader-Hub
```

### 2. Install dependencies

```bash
pip install -r requirements.txt
```

Or install as a package:

```bash
pip install -e .
```

### 3. Configure API Keys (Optional)

Copy the example environment file and add your API keys:

```bash
cp .env.example .env
```

Edit `.env` and add your Gemini API key:

```
GEMINI_API_KEY=your_actual_gemini_api_key_here
```

> **Note:** Gemini AI features are optional. The tool works without an API key, but AI features will be disabled.

## 🚀 Usage

### Command Line Interface

After installation, you can use the `apk-hub` command:

#### Search for apps by name

```bash
apk-hub search "WhatsApp"
apk-hub search "Instagram" --detailed
apk-hub search "Facebook" --gemini --detailed
```

#### Search by package ID

```bash
apk-hub search com.whatsapp -p
apk-hub search com.instagram.android -p --detailed
```

#### Get detailed app information

```bash
apk-hub info com.whatsapp
```

#### Download APK (get download links)

```bash
apk-hub download com.whatsapp
apk-hub download com.instagram.android --version 2.5.0
apk-hub download com.facebook.katana --name "Facebook"
```

#### Find similar apps

```bash
apk-hub similar "WhatsApp"
```

#### List downloads

```bash
apk-hub downloads
```

### Using as a Python module

```python
from apk_downloader.searcher import AppSearcher
from apk_downloader.gemini_ai import GeminiEnricher
from apk_downloader.downloader import APKDownloader

# Search for apps
searcher = AppSearcher()
apps = searcher.search_by_name("WhatsApp")

# Get app details
app_info = searcher.get_app_details("com.whatsapp")

# Use Gemini AI
enricher = GeminiEnricher()
insights = enricher.get_app_insights(app_info)

# Get download info
downloader = APKDownloader()
downloader.download_apk("com.whatsapp")
```

## 📖 Commands

| Command | Description |
|---------|-------------|
| `search <query>` | Search for apps by name |
| `search <package_id> -p` | Search by package ID |
| `info <package_id>` | Get detailed app information |
| `download <package_id>` | Get APK download information |
| `similar <app_name>` | Find similar apps (requires Gemini) |
| `downloads` | List downloaded files |

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--package-id` | `-p` | Search by package ID |
| `--detailed` | `-d` | Show detailed information |
| `--gemini` | `-g` | Use Gemini AI for insights |
| `--version` | `-v` | Specify app version |
| `--name` | `-n` | Specify app name |

## 🔑 Getting a Gemini API Key

1. Go to [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Sign in with your Google account
3. Click "Create API Key"
4. Copy the key and add it to your `.env` file

## ⚠️ Important Notes

### APK Downloads

Direct APK downloads from Google Play Store require:
- Google account authentication
- Device registration
- Compliance with Play Store Terms of Service

This tool provides **download links** to APK mirror sites instead of direct downloads. Supported mirrors:
- APKPure
- APKMirror
- Uptodown
- APKMonk

For automated downloads, consider using:
- `gplaycli` (requires Google account)
- Third-party APK repositories with APIs
- Aurora Store (open-source Play Store client)

### Legal Considerations

- Respect app developers' rights and licenses
- Only download apps for personal use
- Follow Google Play Store Terms of Service
- Some apps may not be redistributable

## 🛠️ Development

### Project Structure

```
Orwebcraft-APK-Downloader-Hub/
├── apk_downloader/
│   ├── __init__.py
│   ├── cli.py           # CLI interface
│   ├── config.py        # Configuration management
│   ├── searcher.py      # App search functionality
│   ├── gemini_ai.py     # Gemini AI integration
│   └── downloader.py    # APK download functionality
├── downloads/           # Downloaded files directory
├── .env.example         # Example environment file
├── .gitignore
├── requirements.txt
├── setup.py
├── main.py
└── README.md
```

### Running from source

```bash
python main.py search "WhatsApp"
```

Or use the module directly:

```bash
python -m apk_downloader.cli search "WhatsApp"
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Google Play Scraper for Play Store data
- Google Gemini for AI-powered insights
- APK mirror sites for providing download alternatives

## ⚡ Quick Start Example

```bash
# Install
pip install -r requirements.txt

# Search for an app
apk-hub search "TikTok" --detailed

# Get detailed info with AI insights (requires Gemini API key)
apk-hub info com.zhiliaoapp.musically --gemini

# Get download links
apk-hub download com.zhiliaoapp.musically

# Find similar apps
apk-hub similar "TikTok"
```

---

**Made with ❤️ by Orwebcraft**
