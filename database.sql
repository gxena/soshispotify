-- Create database
CREATE DATABASE IF NOT EXISTS soshispotify;
USE soshispotify;

-- Artist table
CREATE TABLE IF NOT EXISTS artist (
    artist_id VARCHAR(50) PRIMARY KEY,
    artist_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Track table
CREATE TABLE IF NOT EXISTS track (
    track_id VARCHAR(50) PRIMARY KEY,
    track_name VARCHAR(255) NOT NULL,
    album_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Track-Artist relationship (many-to-many)
CREATE TABLE IF NOT EXISTS track_artist (
    track_id VARCHAR(50),
    artist_id VARCHAR(50),
    PRIMARY KEY (track_id, artist_id),
    FOREIGN KEY (track_id) REFERENCES track(track_id),
    FOREIGN KEY (artist_id) REFERENCES artist(artist_id)
);

-- Streams table (track_id, stream_date, stream_count)
CREATE TABLE IF NOT EXISTS streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    track_id VARCHAR(50) NOT NULL,
    stream_date DATE NOT NULL,
    stream_count BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_track_date (track_id, stream_date),
    FOREIGN KEY (track_id) REFERENCES track(track_id)
);

-- Artist stats table (monthly listeners, followers)
CREATE TABLE IF NOT EXISTS artist_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artist_id VARCHAR(50) NOT NULL,
    stat_date DATE NOT NULL,
    monthly_listeners BIGINT DEFAULT 0,
    followers BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_artist_date (artist_id, stat_date)
);

-- Insert sample artists
INSERT IGNORE INTO artist (artist_id, artist_name) VALUES
('0Sadg1vgvaPqGTOjxu0N6c', "Girls' Generation"),
('3qNVuliS40BLgXGxhdBdqu', 'Taeyeon'),
('5TnQVqgGSoKBZ4yj6k9lLO', 'Hyoyeon'),
('1VwDG9aBflQupaFNjUru9A', 'Tiffany Young');

-- Insert sample tracks (you can add more)
INSERT IGNORE INTO track (track_id, track_name, album_name) VALUES
('2KkBhN7HhSBPPJJnbVpGQO', 'Gee', 'Gee'),
('6M6FgE7f7MlJQN5d2x1ynw', 'The Boys', 'The Boys'),
('3YOQHjMQo9RPPZG3J6a8j5', 'I Got A Boy', "I Got A Boy"),
('4VQP9FX0dXR5h8Hs7G8k4l', 'Into The New World', "Girls' Generation"),
('5nKBASqoNWKL6uR89j9b4C', 'Genie', 'Tell Me Your Wish');

-- Link tracks to artists
INSERT IGNORE INTO track_artist (track_id, artist_id) VALUES
('2KkBhN7HhSBPPJJnbVpGQO', '0Sadg1vgvaPqGTOjxu0N6c'),
('6M6FgE7f7MlJQN5d2x1ynw', '0Sadg1vgvaPqGTOjxu0N6c'),
('3YOQHjMQo9RPPZG3J6a8j5', '0Sadg1vgvaPqGTOjxu0N6c'),
('4VQP9FX0dXR5h8Hs7G8k4l', '0Sadg1vgvaPqGTOjxu0N6c'),
('5nKBASqoNWKL6uR89j9b4C', '0Sadg1vgvaPqGTOjxu0N6c');
