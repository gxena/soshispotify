# Spotify Analytics Dashboard - PHP Version

Dashboard analytics Spotify yang dibangun dengan PHP, MySQL, dan desain modern.

## 🚀 Fitur

- ✅ Dashboard interaktif dengan statistik real-time
- ✅ Visualisasi data dengan Chart.js
- ✅ Desain modern dan responsive
- ✅ Top tracks dan artists
- ✅ Recent activity tracking
- ✅ Mudah di-deploy ke hosting gratis

## 📋 Requirements

- PHP 7.4 atau lebih tinggi
- MySQL Database
- Web Server (Apache/Nginx)

## 🛠️ Installation

### Untuk InfinityFree atau Hosting Gratis Lainnya:

1. **Upload semua file** ke folder `htdocs` atau `public_html`:
   ```
   - index.php
   - dashboard.php
   - config.php
   - .htaccess
   - assets/
     - css/style.css
     - js/main.js
   ```

2. **Setup Database**:
   - Login ke control panel hosting Anda
   - Buat database MySQL baru
   - Catat: hostname, username, password, dan nama database

3. **Konfigurasi Database**:
   - Edit file `config.php`
   - Ganti nilai berikut dengan data database Anda:
   ```php
   define('DB_HOST', 'sql123.infinityfree.com'); // atau 'localhost'
   define('DB_USER', 'ifXXXXX_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'ifXXXXX_dbname');
   ```

4. **Akses Website**:
   - Buka browser dan akses domain Anda
   - Contoh: `http://yoursite.infinityfreeapp.com`

## 📁 Struktur File

```
soshispotify/
├── index.php           # Landing page
├── dashboard.php       # Dashboard dengan dummy data
├── config.php          # Konfigurasi database
├── .htaccess          # Security & routing
├── README.md          # Dokumentasi
├── assets/
│   ├── css/
│   │   └── style.css  # Styling modern
│   └── js/
│       └── main.js    # JavaScript interactions
```

## 🎨 Fitur Dashboard

### Statistics Cards
- Total Tracks
- Total Artists
- Total Plays
- Average Daily Plays

### Visualizations
- Activity Chart (Chart.js)
- Top 5 Tracks dengan play count
- Top 5 Artists dengan statistik
- Recent Activity timeline

### Design Features
- Dark theme (Spotify style)
- Responsive layout
- Smooth animations
- Interactive charts
- Modern UI components

## 🔒 Security Features

File `.htaccess` sudah include:
- Prevent directory browsing
- Protect config files
- Security headers
- Cache control
- File compression

## 🌐 Deployment

### InfinityFree
1. Signup di [InfinityFree](https://infinityfree.net)
2. Buat account hosting gratis
3. Upload files via File Manager atau FTP
4. Setup database MySQL
5. Update `config.php`
6. Done! ✅

### Alternative Hosting Gratis:
- 000webhost
- FreeHosting.com
- Awardspace
- x10hosting

## 📝 Customization

### Mengubah Warna:
Edit `assets/css/style.css`, bagian `:root`:
```css
:root {
    --spotify-green: #1DB954;
    --bg-primary: #121212;
    /* ... */
}
```

### Menambah Data Dummy:
Edit `dashboard.php`, bagian array:
```php
$topTracks = [
    ['name' => 'Song Name', 'artist' => 'Artist', 'plays' => 100, 'duration' => '3:45'],
    // tambah disini
];
```

## 🔮 Next Steps

Untuk integrasi Spotify API (fase berikutnya):
1. Register app di [Spotify Developer Dashboard](https://developer.spotify.com/dashboard)
2. Tambah OAuth authentication
3. Implement Spotify Web API calls
4. Store data ke MySQL database
5. Update dashboard dengan data real

## 📸 Screenshots

**Landing Page:**
- Hero section dengan floating cards
- Features grid
- About section

**Dashboard:**
- Sidebar navigation
- Statistics overview
- Activity charts
- Top tracks & artists list

## 💡 Tips

- Untuk production, ganti dummy data dengan data real dari database
- Enable SSL/HTTPS jika hosting support
- Backup database secara regular
- Test di mobile devices untuk responsive design

## 🐛 Troubleshooting

**Error: Cannot connect to database**
- Cek credentials di `config.php`
- Pastikan MySQL service running
- Verify database exists

**CSS/JS tidak load**
- Cek path file di HTML
- Pastikan folder `assets` ter-upload
- Clear browser cache

**Page tidak load**
- Cek file permissions (755 untuk folder, 644 untuk file)
- Review error logs di control panel
- Pastikan PHP version compatible

## 📄 License

Free to use and modify.

## 👨‍💻 Author

Created with ❤️ for learning purposes

---

**Note:** Ini adalah versi dummy dengan data static. Untuk integrasi Spotify API yang sebenarnya, perlu tambahan:
- Spotify OAuth
- API calls implementation
- Database schema untuk menyimpan tracks/plays
- Cron job untuk sync data
