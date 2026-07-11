<?php
// user/profile.php
session_start();
include "../config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user is employee (should redirect to employee dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    header("Location: ../admin/dashboard.php");
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, m.member_id, m.member_code, m.total_books_borrowed, m.current_fines 
          FROM users u 
          LEFT JOIN members m ON u.user_id = m.user_id 
          WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header("Location: ../logout.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$username_error = '';
$email_error = '';
$phone_error = '';
$gender_error = '';

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    // Validate inputs
    $has_error = false;
    
    if (empty($username)) {
        $username_error = "Username is required!";
        $has_error = true;
    } elseif (strlen($username) < 3) {
        $username_error = "Username must be at least 3 characters!";
        $has_error = true;
    }
    
    if (empty($email)) {
        $email_error = "Email is required!";
        $has_error = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = "Invalid email format!";
        $has_error = true;
    }
    
    if (empty($phone_number)) {
        $phone_error = "Phone number is required!";
        $has_error = true;
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone_number)) {
        $phone_error = "Phone number must be 10-15 digits!";
        $has_error = true;
    }
    
    if (empty($gender)) {
        $gender_error = "Gender is required!";
        $has_error = true;
    }
    
    if (!$has_error) {
        // Check if email already exists for another user
        $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = mysqli_prepare($conn, $check_email);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $email_error = "Email already registered by another user!";
            $error = "Email already registered by another user!";
        } else {
            mysqli_stmt_close($stmt);
            
            // Check if phone number already exists for another user
            $check_phone = "SELECT user_id FROM users WHERE phone_number = ? AND user_id != ?";
            $stmt = mysqli_prepare($conn, $check_phone);
            mysqli_stmt_bind_param($stmt, "si", $phone_number, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $phone_error = "Phone number already registered by another user!";
                $error = "Phone number already registered by another user!";
            } else {
                // Update user profile
                $update_query = "UPDATE users SET username = ?, email = ?, phone_number = ?, gender = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $phone_number, $gender, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Profile updated successfully!";
                    // Update session and local user data
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $user['username'] = $username;
                    $user['email'] = $email;
                    $user['phone_number'] = $phone_number;
                    $user['gender'] = $gender;
                } else {
                    $error = "Failed to update profile. Please try again.";
                }
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Please fix the errors below.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $has_error = false;
    
    if (empty($current_password)) {
        $error = "Current password is required!";
        $has_error = true;
    } elseif (empty($new_password)) {
        $error = "New password is required!";
        $has_error = true;
    } elseif (strlen($new_password) < 8 || strlen($new_password) > 25) {
        $error = "New password must be 8-25 characters!";
        $has_error = true;
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
        $has_error = true;
    }
    
    if (!$has_error) {
        // Verify current password
        $check_password = "SELECT password FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $check_password);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if ($row && password_verify($current_password, $row['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_password);
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect!";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowed)) {
            if ($fileError === 0) {
                if ($fileSize <= 5000000) {
                    $newFileName = "user_" . $user_id . "_" . time() . "." . $fileExt;
                    $fileDestination = '../assets/uploads/' . $newFileName;
                    
                    if (!file_exists('../assets/uploads/')) {
                        mkdir('../assets/uploads/', 0777, true);
                    }
                    
                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        $old_image = $user['profile_image'] ?? '../assets/images/user_image.jpg';
                        if ($old_image != '../assets/images/user_image.jpg' && file_exists($old_image)) {
                            unlink($old_image);
                        }
                        
                        $update_image = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $update_image);
                        mysqli_stmt_bind_param($stmt, "si", $fileDestination, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Profile image updated successfully!";
                            $_SESSION['profile_image'] = $fileDestination;
                            $user['profile_image'] = $fileDestination;
                        } else {
                            $error = "Failed to update database.";
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Error uploading file.";
                    }
                } else {
                    $error = "File is too large. Maximum size is 5MB.";
                }
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    } else {
        $error = "Please select an image to upload.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - My Profile</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../assets/images/logo-library.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a5fb4;
            --secondary-color: #26a269;
            --accent-color: #e5a50a;
            --dark-color: #2d2d2d;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding-top: 80px;
        }
        
        .navbar-custom {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .profile-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .profile-img-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .profile-img-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            cursor: pointer;
        }
        
        .profile-img-container:hover .profile-img-overlay {
            opacity: 1;
        }
        
        .profile-img-overlay i {
            color: white;
            font-size: 2rem;
        }
        
        .info-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .info-card .card-header {
            background-color: rgba(26, 95, 180, 0.1);
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            min-width: 150px;
        }
        
        .info-value {
            color: #495057;
        }
        
        .member-badge {
            background-color: var(--accent-color);
            color: var(--dark-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .btn-edit {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-edit:hover {
            background-color: #0d4a9e;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .btn-save {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }
        
        .btn-save:hover {
            background-color: #1e7b4d;
        }
        
        .tab-content {
            padding: 20px;
            background-color: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .nav-tabs .nav-link {
            color: var(--dark-color);
            font-weight: 500;
            border: none;
            padding: 12px 25px;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(26, 95, 180, 0.1);
            border-bottom: 3px solid var(--primary-color);
        }
        
        .gender-options {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .gender-option {
            flex: 1;
        }
        
        .gender-option input[type="radio"] {
            display: none;
        }
        
        .gender-option label {
            display: block;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .gender-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background-color: rgba(26, 95, 180, 0.1);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 95, 180, 0.25);
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        
        .field-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
        }
        
        .invalid-radio-label {
            border-color: #dc3545 !important;
            background-color: rgba(220, 53, 69, 0.05) !important;
        }
        
        @media (max-width: 768px) {
            .profile-img {
                width: 120px;
                height: 120px;
            }
            
            .gender-options {
                flex-direction: column;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700; color: var(--primary-color);">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <span class="me-3 d-none d-md-block text-muted">
                    <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Profile Header -->
                <div class="profile-card mb-4">
                    <div class="profile-header">
                        <div class="profile-img-container" data-bs-toggle="modal" data-bs-target="#imageModal">
                            <img src="<?php echo htmlspecialchars($user['profile_image'] ?? '../assets/images/user_image.jpg'); ?>" 
                                 alt="Profile" 
                                 class="profile-img"
                                 onerror="this.src='../assets/images/user_image.jpg'">
                            <div class="profile-img-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <h3 class="mb-2"><?php echo htmlspecialchars($user['username']); ?></h3>
                        <div class="member-badge mb-3">
                            <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($user['member_code']); ?>
                        </div>
                        <p class="mb-0">Library Member Since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    </div>
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button">
                                <i class="fas fa-user me-2"></i>Personal Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button">
                                <i class="fas fa-lock me-2"></i>Account Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                                <i class="fas fa-chart-line me-2"></i>Library Activity
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Personal Information Tab -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <?php if ($error && !isset($_POST['change_password'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success && !isset($_POST['change_password'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row">
                                    <!-- Username -->
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control <?php echo !empty($username_error) ? 'is-invalid' : ''; ?>" 
                                                   id="username" 
                                                   name="username"
                                                   value="<?php echo htmlspecialchars($user['username']); ?>">
                                        </div>
                                        <?php if (!empty($username_error)): ?>
                                            <div class="field-error">
                                                <i class="fas fa-exclamation-circle me-1"></i><?php echo $username_error; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Email -->
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" 
                                                   class="form-control <?php echo !empty($email_error) ? 'is-invalid' : ''; ?>" 
                                                   id="email" 
                                                   name="email"
                                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                                        </div>
                                        <?php if (!empty($email_error)): ?>
                                            <div class="field-error">
                                                <i class="fas fa-exclamation-circle me-1"></i><?php echo $email_error; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Phone Number -->
                                    <div class="col-md-6 mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-phone"></i>
                                            </span>
                                            <input type="tel" 
                                                   class="form-control <?php echo !empty($phone_error) ? 'is-invalid' : ''; ?>" 
                                                   id="phone_number" 
                                                   name="phone_number"
                                                   value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                                        </div>
                                        <?php if (!empty($phone_error)): ?>
                                            <div class="field-error">
                                                <i class="fas fa-exclamation-circle me-1"></i><?php echo $phone_error; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Gender -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Gender</label>
                                        <div class="gender-options">
                                            <div class="gender-option">
                                                <input type="radio" id="male" name="gender" value="Male" 
                                                    <?php echo ($user['gender'] ?? '') == 'Male' ? 'checked' : ''; ?>>
                                                <label for="male" id="maleLabel" class="<?php echo !empty($gender_error) ? 'invalid-radio-label' : ''; ?>">
                                                    <i class="fas fa-male me-2"></i>Male
                                                </label>
                                            </div>
                                            <div class="gender-option">
                                                <input type="radio" id="female" name="gender" value="Female"
                                                    <?php echo ($user['gender'] ?? '') == 'Female' ? 'checked' : ''; ?>>
                                                <label for="female" id="femaleLabel" class="<?php echo !empty($gender_error) ? 'invalid-radio-label' : ''; ?>">
                                                    <i class="fas fa-female me-2"></i>Female
                                                </label>
                                            </div>
                                        </div>
                                        <?php if (!empty($gender_error)): ?>
                                            <div class="field-error">
                                                <i class="fas fa-exclamation-circle me-1"></i><?php echo $gender_error; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-cancel" onclick="window.location.href='dashboard.php'">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save me-1"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Account Security Tab -->
                        <div class="tab-pane fade" id="account" role="tabpanel">
                            <?php if ($error && isset($_POST['change_password'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success && isset($_POST['change_password'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="row">
                                    <!-- Current Password -->
                                    <div class="col-md-12 mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-key"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="current_password" 
                                                   name="current_password">
                                            <span class="input-group-text password-toggle" onclick="togglePassword('current_password', this)">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- New Password -->
                                    <div class="col-md-6 mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="new_password" 
                                                   name="new_password">
                                            <span class="input-group-text password-toggle" onclick="togglePassword('new_password', this)">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Password must be 8-25 characters</small>
                                    </div>
                                    
                                    <!-- Confirm Password -->
                                    <div class="col-md-6 mb-4">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password" 
                                                   name="confirm_password">
                                            <span class="input-group-text password-toggle" onclick="togglePassword('confirm_password', this)">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-key me-1"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Library Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="info-card">
                                        <div class="card-header">
                                            <i class="fas fa-chart-bar me-2"></i>Borrowing Statistics
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3 d-flex justify-content-between">
                                                <span class="info-label">Total Books Borrowed:</span>
                                                <span class="info-value"><?php echo $user['total_books_borrowed'] ?? 0; ?></span>
                                            </div>
                                            <div class="mb-3 d-flex justify-content-between">
                                                <span class="info-label">Current Fines:</span>
                                                <span class="info-value">$<?php echo number_format($user['current_fines'] ?? 0.00, 2); ?></span>
                                            </div>
                                            <div class="mb-3 d-flex justify-content-between">
                                                <span class="info-label">Member Since:</span>
                                                <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <div class="info-card">
                                        <div class="card-header">
                                            <i class="fas fa-info-circle me-2"></i>Account Information
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3 d-flex justify-content-between">
                                                <span class="info-label">Member ID:</span>
                                                <span class="info-value"><?php echo htmlspecialchars($user['member_code']); ?></span>
                                            </div>
                                            <div class="mb-3 d-flex justify-content-between">
                                                <span class="info-label">Account Status:</span>
                                                <span class="info-value">
                                                    <span class="badge bg-success">Active</span>
                                                </span>
                                            </div>
                                            <div class="mb-3 d-flex justify-content-between">
                                                <span class="info-label">Last Updated:</span>
                                                <span class="info-value"><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Your borrowing history and transaction details can be viewed from your dashboard.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">
                        <i class="fas fa-camera me-2"></i>Update Profile Image
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="profile-img-container mb-3">
                            <img src="<?php echo htmlspecialchars($user['profile_image'] ?? '../assets/images/user_image.jpg'); ?>" 
                                 alt="Current Profile" 
                                 class="profile-img"
                                 id="profilePreview"
                                 onerror="this.src='../assets/images/user_image.jpg'">
                            <div class="profile-img-overlay" onclick="document.getElementById('profile_image').click()">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <p class="text-muted">Click on image to select new one</p>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="update_image" value="1">
                        
                        <div class="mb-3">
                            <label for="profile_image" class="form-label">Select New Image</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="profile_image" 
                                   name="profile_image"
                                   accept="image/*"
                                   onchange="previewImage(event)">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-save">
                                <i class="fas fa-upload me-2"></i>Upload Image
                            </button>
                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Image preview function
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('profilePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Click profile preview to trigger file input
        document.getElementById('profilePreview').addEventListener('click', function() {
            document.getElementById('profile_image').click();
        });
        
        // Toggle password visibility
        function togglePassword(inputId, element) {
            const passwordInput = document.getElementById(inputId);
            const icon = element.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Refresh page when image modal is closed (to show updated image)
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function () {
            location.reload();
        });
        
        // Initialize tabs
        document.addEventListener('DOMContentLoaded', function() {
            const triggerTabList = [].slice.call(document.querySelectorAll('#profileTabs button'));
            triggerTabList.forEach(function (triggerEl) {
                const tabTrigger = new bootstrap.Tab(triggerEl);
                
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    tabTrigger.show();
                });
            });
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Username validation
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.addEventListener('input', function() {
                    if (this.value.length < 3) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            // Email validation
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(this.value)) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            // Phone validation
            const phoneInput = document.getElementById('phone_number');
            if (phoneInput) {
                phoneInput.addEventListener('blur', function() {
                    const phoneRegex = /^[0-9]{10,15}$/;
                    if (!phoneRegex.test(this.value)) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
        });
    </script>
</body>
</html>