<?php
session_start();
require_once 'api/config.php';

// Get user data
$userId = '550e8400-e29b-41d4-a716-446655440000';
$pdo = getDBConnection();

// Use constant location data instead of database
$latestLocation = [
    'latitude' => 39.7392,
    'longitude' => -104.9903,
    'accuracy' => 3.0,
    'location_timestamp' => date('Y-m-d H:i:s'),
    'is_safe_zone' => true
];

// Get safe zones
$stmt = $pdo->prepare("SELECT * FROM safe_zones WHERE user_id = ? AND is_active = TRUE ORDER BY name");
$stmt->execute([$userId]);
$safeZones = $stmt->fetchAll();

// Use constant location history
$locationHistory = [
    [
        'latitude' => 39.7392,
        'longitude' => -104.9903,
        'location_timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        'zone_name' => 'Home',
        'accuracy' => 3.0
    ],
    [
        'latitude' => 39.7390,
        'longitude' => -104.9905,
        'location_timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
        'zone_name' => 'Home',
        'accuracy' => 4.0
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Tracking - VITA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    <span class="logo-text">VITA</span>
                </div>
                <button class="sidebar-close" id="sidebarClose">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                    </svg>
                    Dashboard
                </a></li>
                <li><a href="health-metrics.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    Health Metrics
                </a></li>
                <li><a href="location.php" class="nav-link active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    Location
                </a></li>
                <li><a href="medication.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4.5 16.5c-1.5 1.5-1.5 3.5 0 5s3.5 1.5 5 0l12-12c1.5-1.5 1.5-3.5 0-5s-3.5-1.5-5 0l-12 12z"></path>
                        <path d="M15 7l3 3"></path>
                    </svg>
                    Medication
                </a></li>
                <li><a href="alerts.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    Alerts
                </a></li>
                <li><a href="profile.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Profile
                </a></li>
                <li><a href="device-setup.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Device Setup
                </a></li>
            </ul>
        </nav>

        <!-- Mobile Header -->
        <header class="mobile-header">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <div class="mobile-logo">
                <svg class="logo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span class="logo-text">VITA</span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Location Tracking</h1>
                <p>Real-time location monitoring and safety zones</p>
            </div>

            <!-- Current Location -->
            <div class="card location-card">
                <div class="card-header">
                    <h2>Current Location</h2>
                    <div class="status-indicator online">
                        <div class="status-dot"></div>
                        <span>Live</span>
                    </div>
                </div>
                
                <div class="location-content">
                    <!-- Map Placeholder -->
                    <div class="map-placeholder">
                        <div class="map-content">
                            <svg class="map-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <p>Interactive Map View</p>
                            <p class="map-address">
                                <?php if ($latestLocation): ?>
                                    Lat: <?= number_format($latestLocation['latitude'], 6) ?>, 
                                    Lng: <?= number_format($latestLocation['longitude'], 6) ?>
                                <?php else: ?>
                                    123 Oak Street, Springfield
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Location Details -->
                    <div class="location-details">
                        <div class="current-location-info">
                            <svg class="location-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9,22 9,12 15,12 15,22"></polyline>
                            </svg>
                            <div>
                                <p class="location-status">
                                    Currently at Home
                                </p>
                                <p class="location-time">
                                    Safe Zone • Last updated 2 min ago
                                </p>
                            </div>
                        </div>
                        
                        <div class="location-stats">
                            <div class="stat-item">
                                <p class="stat-label">Coordinates</p>
                                <p class="stat-value">
                                    <?= number_format($latestLocation['latitude'], 4) ?>, <?= number_format($latestLocation['longitude'], 4) ?>
                                </p>
                            </div>
                            <div class="stat-item">
                                <p class="stat-label">Accuracy</p>
                                <p class="stat-value">
                                    ±<?= $latestLocation['accuracy'] ?> meters
                                </p>
                            </div>
                        </div>

                        <div class="safety-status">
                            <svg class="safety-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <p>All safety protocols active</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Safe Zones and Location History -->
            <div class="dashboard-grid">
                <!-- Safe Zones -->
                <div class="card">
                    <div class="card-header">
                        <h2>Safe Zones</h2>
                        <button class="add-zone-btn">+ Add Zone</button>
                    </div>
                    
                    <div class="zones-list">
                        <?php if (empty($safeZones)): ?>
                            <div class="zone-item active">
                                <div class="zone-status"></div>
                                <div class="zone-info">
                                    <div class="zone-header">
                                        <span class="zone-name">Home</span>
                                        <span class="zone-radius">50 meters</span>
                                    </div>
                                    <p class="zone-address">123 Oak Street, Springfield</p>
                                    <div class="zone-actions">
                                        <button class="zone-action-btn edit">Edit</button>
                                        <button class="zone-action-btn remove">Remove</button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($safeZones as $zone): ?>
                                <div class="zone-item active">
                                    <div class="zone-status"></div>
                                    <div class="zone-info">
                                        <div class="zone-header">
                                            <span class="zone-name"><?= htmlspecialchars($zone['name']) ?></span>
                                            <span class="zone-radius"><?= $zone['radius'] ?> meters</span>
                                        </div>
                                        <p class="zone-address">
                                            Lat: <?= number_format($zone['latitude'], 6) ?>, 
                                            Lng: <?= number_format($zone['longitude'], 6) ?>
                                        </p>
                                        <div class="zone-actions">
                                            <button class="zone-action-btn edit">Edit</button>
                                            <button class="zone-action-btn remove">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Location History -->
                <div class="card">
                    <div class="card-header">
                        <h2>Location History</h2>
                        <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"></path>
                        </svg>
                    </div>
                    
                    <div class="history-list">
                        <?php foreach ($locationHistory as $location): ?>
                            <div class="history-item safe">
                                <div class="history-status"></div>
                                <div class="history-info">
                                    <div class="history-header">
                                        <span class="history-location">
                                            <?= $location['zone_name'] ?: 'Home' ?>
                                        </span>
                                        <span class="history-time">
                                            <?= date('g:i A', strtotime($location['location_timestamp'])) ?>
                                        </span>
                                    </div>
                                    <p class="history-duration">
                                        Accuracy: ±<?= $location['accuracy'] ?>m
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Emergency & Safety Settings -->
            <div class="card">
                <div class="card-header">
                    <h2>Emergency & Safety Settings</h2>
                    <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </div>
                
                <div class="safety-settings">
                    <div class="setting-item">
                        <h3>Geofence Alerts</h3>
                        <p>Get notified when leaving safe zones</p>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <h3>Emergency Contacts</h3>
                        <p>Auto-notify in case of emergencies</p>
                        <button class="setting-btn">Manage Contacts</button>
                    </div>
                    
                    <div class="setting-item">
                        <h3>Check-in Reminders</h3>
                        <p>Regular safety check-ins</p>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/location.js"></script>
    <script>
        // Auto-refresh data every 5 seconds (but location data is constant)
        setInterval(function() {
            location.reload();
        }, 5000);
    </script>
</body>
</html>