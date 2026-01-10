<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Girls' Generation Spotify Analytics - SoshiSpotify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="landing-container">
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-music"></i>
                <span>SoshiSpotify</span>
            </div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="dashboard.php" class="btn-primary">Dashboard</a>
            </div>
        </nav>

        <section class="hero">
            <div class="hero-content">
                <h1 class="hero-title">
                    Track Girls' Generation
                    <span class="gradient-text">Spotify Stats</span>
                </h1>
                <p class="hero-subtitle">
                    Comprehensive analytics for Girls' Generation and members' solo projects. 
                    Monitor streams, discover trends, and celebrate the legendary K-pop group's success.
                </p>
                <div class="hero-buttons">
                    <a href="dashboard.php" class="btn btn-large btn-primary">
                        <i class="fas fa-chart-line"></i> View Dashboard
                    </a>
                    <a href="#features" class="btn btn-large btn-secondary">
                        <i class="fas fa-info-circle"></i> Learn More
                    </a>
                </div>
            </div>
            <div class="hero-image">
                <div class="floating-card card-1">
                    <i class="fas fa-headphones"></i>
                    <div>
                        <h3>1.23B</h3>
                        <p>Total Streams</p>
                    </div>
                </div>
                <div class="floating-card card-2">
                    <i class="fas fa-users"></i>
                    <div>
                        <h3>4.28M</h3>
                        <p>Listeners</p>
                    </div>
                </div>
                <div class="floating-card card-3">
                    <i class="fas fa-heart"></i>
                    <div>
                        <h3>2.04M</h3>
                        <p>Followers</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="features">
            <h2 class="section-title">Powerful Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Real-time Analytics</h3>
                    <p>Track Girls' Generation and members' streaming data with beautiful visualizations</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Top Charts</h3>
                    <p>View most streamed songs and albums from group and solo activities</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Member Stats</h3>
                    <p>Compare performance across all 8 members' solo projects</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Dedicated to SONE</h3>
                    <p>Built by fans, for fans. Celebrating Girls' Generation's legacy</p>
                </div>
            </div>
        </section>

        <section id="about" class="about">
            <div class="about-content">
                <h2 class="section-title">About This Project</h2>
                <p>
                    Girls' Generation Spotify Analytics is a fan-made dashboard tracking the legendary 
                    K-pop group and their members' solo activities on Spotify. Monitor streams, analyze 
                    trends, and celebrate the success of the Nation's Girl Group.
                </p>
                <div class="stats-row">
                    <div class="stat-item">
                        <h3>8</h3>
                        <p>Members</p>
                    </div>
                    <div class="stat-item">
                        <h3>15+</h3>
                        <p>Years of Legacy</p>
                    </div>
                    <div class="stat-item">
                        <h3>1B+</h3>
                        <p>Streams</p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer">
            <p>&copy; 2026 Girls' Generation Spotify Analytics. Made with <i class="fas fa-heart"></i> by SONE</p>
        </footer>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
