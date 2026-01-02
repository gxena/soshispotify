# app.py - Main Flask application
from flask import Flask, render_template, jsonify, request
from flask_sqlalchemy import SQLAlchemy
from datetime import datetime
import os
import spotipy
from spotipy.oauth2 import SpotifyClientCredentials
import json

app = Flask(__name__)

# Database configuration
database_url = os.environ.get('DATABASE_URL', 'sqlite:///spotify_data.db')
if database_url.startswith('postgres://'):
    database_url = database_url.replace('postgres://', 'postgresql://', 1)
app.config['SQLALCHEMY_DATABASE_URI'] = database_url
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

db = SQLAlchemy(app)

# Database Models
class PlayCount(db.Model):
    __tablename__ = 'play_counts'
    
    id = db.Column(db.Integer, primary_key=True)
    track_id = db.Column(db.String(100), nullable=False)
    track_name = db.Column(db.String(255), nullable=False)
    artist_name = db.Column(db.String(255), nullable=False)
    album_name = db.Column(db.String(255))
    play_count = db.Column(db.Integer, nullable=False)
    popularity = db.Column(db.Integer)
    scraped_at = db.Column(db.DateTime, nullable=False, default=datetime.utcnow)
    
    def to_dict(self):
        return {
            'id': self.id,
            'track_id': self.track_id,
            'track_name': self.track_name,
            'artist_name': self.artist_name,
            'album_name': self.album_name,
            'play_count': self.play_count,
            'popularity': self.popularity,
            'scraped_at': self.scraped_at.isoformat()
        }

# Initialize Spotify client
def get_spotify_client():
    client_id = os.environ.get('SPOTIFY_CLIENT_ID')
    client_secret = os.environ.get('SPOTIFY_CLIENT_SECRET')
    
    if not client_id or not client_secret:
        raise ValueError("Spotify credentials not set in environment variables")
    
    client_credentials_manager = SpotifyClientCredentials(
        client_id=client_id,
        client_secret=client_secret
    )
    return spotipy.Spotify(client_credentials_manager=client_credentials_manager)

# Scraping function
def scrape_spotify_data(track_ids=None, playlist_id=None):
    """
    Scrape Spotify data for given tracks or playlist
    """
    sp = get_spotify_client()
    results = []
    
    if playlist_id:
        # Get tracks from playlist
        playlist_tracks = sp.playlist_tracks(playlist_id)
        track_ids = [item['track']['id'] for item in playlist_tracks['items'] if item['track']]
    
    if not track_ids:
        raise ValueError("No track IDs or playlist ID provided")
    
    # Get track details in batches (Spotify API allows max 50 tracks per request)
    for i in range(0, len(track_ids), 50):
        batch = track_ids[i:i+50]
        tracks = sp.tracks(batch)
        
        for track in tracks['tracks']:
            if track:
                play_count_entry = PlayCount(
                    track_id=track['id'],
                    track_name=track['name'],
                    artist_name=', '.join([artist['name'] for artist in track['artists']]),
                    album_name=track['album']['name'],
                    play_count=track['popularity'],  # Note: Spotify doesn't expose actual play counts, using popularity
                    popularity=track['popularity'],
                    scraped_at=datetime.utcnow()
                )
                db.session.add(play_count_entry)
                results.append(play_count_entry.to_dict())
    
    db.session.commit()
    return results

# Routes
@app.route('/')
def index():
    return render_template('index.html')

@app.route('/dashboard')
def dashboard():
    return render_template('dashboard.html')

@app.route('/api/scrape', methods=['POST'])
def api_scrape():
    try:
        data = request.get_json() or {}
        track_ids = data.get('track_ids', [])
        playlist_id = data.get('playlist_id')
        
        if not track_ids and not playlist_id:
            # Default: Use your predefined tracks/playlist
            playlist_id = os.environ.get('DEFAULT_PLAYLIST_ID')
        
        results = scrape_spotify_data(track_ids=track_ids, playlist_id=playlist_id)
        
        return jsonify({
            'success': True,
            'message': f'Successfully scraped {len(results)} tracks',
            'data': results
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': str(e)
        }), 500

@app.route('/api/stats')
def api_stats():
    """Get dashboard statistics"""
    try:
        # Total tracks scraped
        total_tracks = db.session.query(db.func.count(db.distinct(PlayCount.track_id))).scalar()
        
        # Total scrapes
        total_scrapes = PlayCount.query.count()
        
        # Latest scrape time
        latest_scrape = db.session.query(db.func.max(PlayCount.scraped_at)).scalar()
        
        # Top tracks by latest popularity
        top_tracks = db.session.query(
            PlayCount.track_name,
            PlayCount.artist_name,
            PlayCount.popularity,
            PlayCount.scraped_at
        ).order_by(PlayCount.scraped_at.desc(), PlayCount.popularity.desc()).limit(10).all()
        
        return jsonify({
            'success': True,
            'stats': {
                'total_tracks': total_tracks,
                'total_scrapes': total_scrapes,
                'latest_scrape': latest_scrape.isoformat() if latest_scrape else None,
                'top_tracks': [
                    {
                        'track_name': t[0],
                        'artist_name': t[1],
                        'popularity': t[2],
                        'scraped_at': t[3].isoformat()
                    } for t in top_tracks
                ]
            }
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': str(e)
        }), 500

@app.route('/api/data')
def api_data():
    """Get all play count data for charts"""
    try:
        page = request.args.get('page', 1, type=int)
        per_page = request.args.get('per_page', 100, type=int)
        track_id = request.args.get('track_id')
        
        query = PlayCount.query
        
        if track_id:
            query = query.filter_by(track_id=track_id)
        
        query = query.order_by(PlayCount.scraped_at.desc())
        paginated = query.paginate(page=page, per_page=per_page, error_out=False)
        
        return jsonify({
            'success': True,
            'data': [item.to_dict() for item in paginated.items],
            'total': paginated.total,
            'pages': paginated.pages,
            'current_page': page
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': str(e)
        }), 500

@app.route('/api/trends/<track_id>')
def api_trends(track_id):
    """Get historical trends for a specific track"""
    try:
        trends = PlayCount.query.filter_by(track_id=track_id).order_by(PlayCount.scraped_at.asc()).all()
        
        return jsonify({
            'success': True,
            'data': [item.to_dict() for item in trends]
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': str(e)
        }), 500

@app.route('/api/tracks')
def api_tracks():
    """Get list of all unique tracks"""
    try:
        tracks = db.session.query(
            PlayCount.track_id,
            PlayCount.track_name,
            PlayCount.artist_name,
            db.func.max(PlayCount.scraped_at).label('last_scraped')
        ).group_by(PlayCount.track_id, PlayCount.track_name, PlayCount.artist_name).all()
        
        return jsonify({
            'success': True,
            'data': [
                {
                    'track_id': t[0],
                    'track_name': t[1],
                    'artist_name': t[2],
                    'last_scraped': t[3].isoformat()
                } for t in tracks
            ]
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': str(e)
        }), 500

# Initialize database
with app.app_context():
    db.create_all()

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
