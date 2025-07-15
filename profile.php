<?php
session_start();
require_once 'api/config.php';

// Get user data
$userId = '550e8400-e29b-41d4-a716-446655440000';
$pdo = getDBConnection();

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get family members
$stmt = $pdo->prepare("SELECT * FROM family_members WHERE user_id = ? AND is_active = TRUE ORDER BY name");
$stmt->execute([$userId]);
$familyMembers = $stmt->fetchAll();

// Handle form submission
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        $stmt = $pdo->prepare("
            UPDATE users SET 
                full_name = ?, phone = ?, address = ?, 
                breakfast_time = ?, lunch_time = ?, dinner_time = ?,
                snack_times = ?, dietary_restrictions = ?, preferred_meal_size = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $snackTimes = explode(',', $_POST['snack_times']);
        $dietaryRestrictions = explode(',', $_POST['dietary_restrictions']);
        
        $stmt->execute([
            $_POST['full_name'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['breakfast_time'],
            $_POST['lunch_time'],
            $_POST['dinner_time'],
            json_encode($snackTimes),
            json_encode($dietaryRestrictions),
            $_POST['preferred_meal_size'],
            $userId
        ]);
        
        header('Location: profile.php?updated=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - VITA</title>
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
                <li><a href="location.php" class="nav-link">
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
                <li><a href="profile.php" class="nav-link active">
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
                <h1>Profile Settings</h1>
                <p>Manage your personal information and preferences</p>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="success-message">Profile updated successfully!</div>
                <?php endif; ?>
            </div>

            <!-- Profile Overview -->
            <div class="card profile-overview">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="profile-info">
                        <h2><?= htmlspecialchars($user['full_name'] ?? 'Margaret Thompson') ?></h2>
                        <p>Patient ID: VT-2024-001</p>
                        <span class="profile-status active">Active Monitoring</span>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-label">Age</span>
                        <span class="stat-value">
                            <?php 
                                if ($user && $user['date_of_birth']) {
                                    $age = date_diff(date_create($user['date_of_birth']), date_create('today'))->y;
                                    echo $age . ' years';
                                } else {
                                    echo '72 years';
                                }
                            ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Monitoring Since</span>
                        <span class="stat-value">Jan 2024</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Device Status</span>
                        <span class="stat-value">Connected</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Last Check-in</span>
                        <span class="stat-value">2 min ago</span>
                    </div>
                </div>
            </div>

            <!-- Personal Information Form -->
            <div class="card">
                <div class="card-header">
                    <h2>Personal Information & Meal Preferences</h2>
                </div>
                
                <form method="POST" class="info-form">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? 'Margaret Thompson') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '(555) 123-4567') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address"><?= htmlspecialchars($user['address'] ?? '123 Oak Street\nSpringfield, IL 62701') ?></textarea>
                    </div>
                    
                    <h3>Meal Times & Preferences</h3>
                    
                    <div class="form-group">
                        <label>Breakfast Time</label>
                        <input type="time" name="breakfast_time" value="<?= $user['breakfast_time'] ?? '07:30' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Lunch Time</label>
                        <input type="time" name="lunch_time" value="<?= $user['lunch_time'] ?? '12:30' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Dinner Time</label>
                        <input type="time" name="dinner_time" value="<?= $user['dinner_time'] ?? '18:30' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Snack Times (comma separated, e.g., 10:00, 15:30)</label>
                        <input type="text" name="snack_times" value="<?= 
                            $user && $user['snack_times'] ? 
                            implode(', ', json_decode($user['snack_times'], true)) : 
                            '10:00, 15:30' 
                        ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Dietary Restrictions (comma separated)</label>
                        <input type="text" name="dietary_restrictions" value="<?= 
                            $user && $user['dietary_restrictions'] ? 
                            implode(', ', json_decode($user['dietary_restrictions'], true)) : 
                            'Low Sodium, Diabetic Friendly' 
                        ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Preferred Meal Size</label>
                        <select name="preferred_meal_size">
                            <option value="small" <?= ($user['preferred_meal_size'] ?? 'small') === 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= ($user['preferred_meal_size'] ?? 'small') === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= ($user['preferred_meal_size'] ?? 'small') === 'large' ? 'selected' : '' ?>>Large</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_profile" class="edit-profile-btn">Update Profile</button>
                </form>
            </div>

            <!-- Health Profile -->
            <div class="card">
                <div class="card-header">
                    <h2>Health Profile</h2>
                </div>
                
                <div class="health-info">
                    <div class="health-section">
                        <h3>Medical Conditions</h3>
                        <div class="condition-list">
                            <?php 
                                $conditions = $user && $user['medical_conditions'] ? 
                                    json_decode($user['medical_conditions'], true) : 
                                    ['Hypertension', 'Type 2 Diabetes', 'High Cholesterol'];
                                foreach ($conditions as $condition):
                            ?>
                                <span class="condition-tag"><?= htmlspecialchars($condition) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="health-section">
                        <h3>Allergies</h3>
                        <div class="allergy-list">
                            <?php 
                                $allergies = $user && $user['allergies'] ? 
                                    json_decode($user['allergies'], true) : 
                                    ['Penicillin', 'Shellfish'];
                                foreach ($allergies as $allergy):
                            ?>
                                <span class="allergy-tag"><?= htmlspecialchars($allergy) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="health-section">
                        <h3>Emergency Information</h3>
                        <div class="emergency-info">
                            <p><strong>Blood Type:</strong> <?= htmlspecialchars($user['blood_type'] ?? 'O+') ?></p>
                            <p><strong>Primary Physician:</strong> <?= htmlspecialchars($user['primary_physician'] ?? 'Dr. Michael Chen') ?></p>
                            <p><strong>Insurance:</strong> <?= htmlspecialchars($user['insurance_info'] ?? 'Medicare + Supplemental') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Family Access -->
            <div class="card">
                <div class="card-header">
                    <h2>Family Access</h2>
                    <button class="add-family-btn">+ Add Family Member</button>
                </div>
                
                <div class="family-list">
                    <?php if (empty($familyMembers)): ?>
                        <!-- Default family members -->
                        <div class="family-member">
                            <div class="member-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div class="member-info">
                                <h3>Sarah Thompson</h3>
                                <p>Daughter • Full Access</p>
                                <span class="member-email">sarah.thompson@email.com</span>
                            </div>
                            <div class="member-permissions">
                                <span class="permission-tag">Health Data</span>
                                <span class="permission-tag">Location</span>
                                <span class="permission-tag">Alerts</span>
                            </div>
                            <div class="member-actions">
                                <button class="member-action-btn">Edit</button>
                                <button class="member-action-btn">Remove</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($familyMembers as $member): ?>
                            <div class="family-member">
                                <div class="member-avatar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <div class="member-info">
                                    <h3><?= htmlspecialchars($member['name']) ?></h3>
                                    <p><?= htmlspecialchars($member['relationship']) ?> • <?= ucfirst($member['access_level']) ?> Access</p>
                                    <span class="member-email"><?= htmlspecialchars($member['email']) ?></span>
                                </div>
                                <div class="member-permissions">
                                    <?php 
                                        $permissions = json_decode($member['permissions'], true);
                                        foreach ($permissions as $permission):
                                    ?>
                                        <span class="permission-tag"><?= ucfirst(str_replace('_', ' ', $permission)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="member-actions">
                                    <button class="member-action-btn">Edit</button>
                                    <button class="member-action-btn">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/profile.js"></script>
</body>
</html>