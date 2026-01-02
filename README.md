# Spotify Scraper & Dashboard

A Flask web application for scraping Spotify track data and visualizing it with an interactive dashboard.

## Features

- 🎵 One-click Spotify data scraping
- 📊 Interactive dashboard with charts
- 💾 PostgreSQL database storage
- 🚀 Easy deployment on Render
- 📈 Track popularity trends over time
- 🎯 Filter by specific tracks

## Setup Instructions

### 1. Get Spotify API Credentials

1. Go to [Spotify Developer Dashboard](https://developer.spotify.com/dashboard)
2. Create a new app
3. Copy your Client ID and Client Secret

### 2. Local Development

```bash
# Create virtual environment
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Set environment variables
export SPOTIFY_CLIENT_ID="your_client_id"
export SPOTIFY_CLIENT_SECRET="your_client_secret"
export DEFAULT_PLAYLIST_ID="your_playlist_id"  # Optional

# Run the app
python app.py
```

Visit http://localhost:5000

### 3. Deploy to Render

1. Push this code to GitHub
2. Go to [Render Dashboard](https://dashboard.render.com/)
3. Click "New +" → "Blueprint"
4. Connect your GitHub repository
5. Render will detect `render.yaml` and set up:
   - Web Service
   - PostgreSQL Database
6. Add environment variables in Render dashboard:
   - `SPOTIFY_CLIENT_ID`
   - `SPOTIFY_CLIENT_SECRET`
   - `DEFAULT_PLAYLIST_ID` (optional)

### 4. Usage

1. **Home Page**: Enter playlist ID or track IDs and click "Start Scraping"
2. **Dashboard**: View statistics and trends of your scraped data

## API Endpoints

- `POST /api/scrape` - Trigger scraping
- `GET /api/stats` - Get dashboard statistics
- `GET /api/data` - Get all play count data
- `GET /api/trends/<track_id>` - Get historical trends for a track
- `GET /api/tracks` - Get list of all unique tracks

## Database Schema

**PlayCount Table:**
- id (Primary Key)
- track_id
- track_name
- artist_name
- album_name
- play_count
- popularity
- scraped_at (timestamp)

## Tech Stack

- **Backend**: Flask, SQLAlchemy
- **Database**: PostgreSQL (Render) / SQLite (local)
- **Charts**: Chart.js
- **Spotify API**: Spotipy library
- **Hosting**: Render

## Notes

- Spotify doesn't expose actual play counts via their public API
- The app uses "popularity" score (0-100) as a proxy
- For actual play counts, you'd need access to Spotify for Artists API
- Free tier on Render may sleep after inactivity - first request might be slow

## Future Enhancements

- Add authentication
- Schedule automatic daily scraping
- Export data to CSV
- More chart types and analytics
- Email notifications
- Multiple playlist support