from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="orwebcraft-apk-downloader-hub",
    version="1.0.0",
    author="Orwebcraft",
    description="Search and download Android APKs with Google Search and Gemini AI integration",
    long_description=long_description,
    long_description_content_type="text/markdown",
    packages=find_packages(),
    classifiers=[
        "Programming Language :: Python :: 3",
        "License :: OSI Approved :: MIT License",
        "Operating System :: OS Independent",
    ],
    python_requires=">=3.7",
    install_requires=[
        "google-play-scraper>=1.2.4",
        "requests>=2.31.0",
        "google-generativeai>=0.3.2",
        "googlesearch-python>=1.2.4",
        "python-dotenv>=1.0.0",
        "beautifulsoup4>=4.12.2",
        "click>=8.1.7",
    ],
    entry_points={
        "console_scripts": [
            "apk-hub=apk_downloader.cli:cli",
        ],
    },
)
