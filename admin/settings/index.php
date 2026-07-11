<?php
// admin/settings/index.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Get employee details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employee = mysqli_fetch_assoc($result);

// Initialize messages
$success_msg = '';
$error_msg = '';
$field_errors = [];

// Handle different form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update Profile Information
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone_number = trim($_POST['phone_number']);
        $gender = $_POST['gender'];
        
        // Validation
        $errors = [];
        
        if (empty($username)) {
            $errors['username'] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors['username'] = "Username must be at least 3 characters";
        }
        
        if (empty($email)) {
            $errors['email'] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        }
        
        if (empty($phone_number)) {
            $errors['phone_number'] = "Phone number is required";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone_number)) {
            $errors['phone_number'] = "Phone number must be 10-15 digits";
        }
        
        if (empty($gender)) {
            $errors['gender'] = "Gender is required";
        }
        
        if (empty($errors)) {
            // Check if email already exists (excluding current user)
            $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $stmt = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors['email'] = "Email already registered to another user";
                mysqli_stmt_close($stmt);
            } else {
                mysqli_stmt_close($stmt);
                
                // Update user information
                $update_query = "UPDATE users SET username = ?, email = ?, phone_number = ?, gender = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $phone_number, $gender, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Update session
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    
                    $success_msg = "Profile information updated successfully!";
                } else {
                    $error_msg = "Failed to update profile. Please try again.";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $field_errors = $errors;
            $error_msg = "Please correct the errors below.";
        }
        
    } elseif (isset($_POST['change_password'])) {
        // Change Password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        if (empty($current_password)) {
            $errors['current_password'] = "Current password is required";
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = "Password must be at least 8 characters";
        }
        
        if (empty($confirm_password)) {
            $errors['confirm_password'] = "Confirm password is required";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match";
        }
        
        if (empty($errors)) {
            // Get current hashed password from database
            $check_password = "SELECT password FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $check_password);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $db_hashed_password);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            
            // Verify current password
            if ($db_hashed_password && password_verify($current_password, $db_hashed_password)) {
                // Update password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_password);
                mysqli_stmt_bind_param($stmt, "si", $new_hashed_password, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "Password changed successfully!";
                } else {
                    $error_msg = "Failed to change password. Please try again.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors['current_password'] = "Current password is incorrect";
                $field_errors = $errors;
                $error_msg = "Please correct the errors below.";
            }
        } else {
            $field_errors = $errors;
            $error_msg = "Please correct the errors below.";
        }
        
    } elseif (isset($_POST['update_employee_info'])) {
        // Update Employee Information
        $position = $_POST['position'];
        $employment_type = $_POST['employment_type'];
        
        $errors = [];
        
        if (empty($position)) {
            $errors['position'] = "Position is required";
        }
        
        if (empty($employment_type)) {
            $errors['employment_type'] = "Employment type is required";
        }
        
        if (empty($errors)) {
            $update_employee = "UPDATE employees SET position = ?, employment_type = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_employee);
            mysqli_stmt_bind_param($stmt, "ssi", $position, $employment_type, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update session if needed
                $_SESSION['position'] = $position;
                $success_msg = "Employee information updated successfully!";
            } else {
                $error_msg = "Failed to update employee information. Please try again.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $field_errors = $errors;
            $error_msg = "Please correct the errors below.";
        }
        
    } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        // Handle profile image upload
        $file = $_FILES['profile_image'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowed)) {
            if ($fileError === 0) {
                if ($fileSize <= 5000000) { // 5MB max
                    // Generate unique filename
                    $newFileName = "employee_" . $user_id . "_" . time() . "." . $fileExt;
                    $fileDestination = '../../assets/uploads/' . $newFileName;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists('../../assets/uploads/')) {
                        mkdir('../../assets/uploads/', 0777, true);
                    }
                    
                    // Move uploaded file
                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        // Delete old image if it exists and is not default
                        $old_image = $employee['profile_image'] ?? '../../assets/images/user_image.jpg';
                        if ($old_image != '../../assets/images/user_image.jpg' && file_exists($old_image)) {
                            unlink($old_image);
                        }
                        
                        // Update database
                        $update_image = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $update_image);
                        mysqli_stmt_bind_param($stmt, "si", $fileDestination, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $_SESSION['profile_image'] = $fileDestination;
                            $employee['profile_image'] = $fileDestination;
                            $success_msg = "Profile image updated successfully!";
                        } else {
                            $error_msg = "Failed to update database.";
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error_msg = "Error uploading file.";
                    }
                } else {
                    $error_msg = "File is too large. Maximum size is 5MB.";
                }
            } else {
                $error_msg = "Error uploading file.";
            }
        } else {
            $error_msg = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
}

// Refresh employee data after updates
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employee = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Settings</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .sidebar {
            background-color: white;
            min-height: calc(100vh - 70px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
        }
        
        .sidebar .nav-link {
            color: var(--dark-color);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(26, 95, 180, 0.1);
        }
        
        .main-content {
            padding: 20px;
        }
        
        .settings-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: transform 0.3s;
        }
        
        .settings-card:hover {
            transform: translateY(-2px);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .card-header-secondary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #1e7b4d 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .card-header-accent {
            background: linear-gradient(135deg, var(--accent-color) 0%, #d19408 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .profile-img-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .profile-img:hover {
            opacity: 0.8;
            transform: scale(1.05);
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
            color: white;
        }
        
        .profile-img-container:hover .profile-img-overlay {
            opacity: 1;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(26, 95, 180, 0.1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border: none;
            padding: 10px 30px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 95, 180, 0.3);
            color: white;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .password-weak {
            background-color: #dc3545;
            width: 25%;
        }
        
        .password-medium {
            background-color: #ffc107;
            width: 50%;
        }
        
        .password-strong {
            background-color: #28a745;
            width: 75%;
        }
        
        .password-very-strong {
            background-color: #20c997;
            width: 100%;
        }
        
        .toggle-password {
            cursor: pointer;
            color: var(--primary-color);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .settings-nav {
            position: sticky;
            top: 90px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .settings-nav-link {
            display: block;
            padding: 10px 15px;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .settings-nav-link:hover,
        .settings-nav-link.active {
            background-color: rgba(26, 95, 180, 0.1);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .settings-nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .profile-img {
                width: 120px;
                height: 120px;
            }
            
            .settings-nav {
                position: static;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700;">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto align-items-center">
                    <span class="nav-item me-3 text-white d-none d-md-block">
                        <i class="fas fa-cog me-1"></i>Settings
                    </span>
                    <a href="../../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="margin-top: 70px;">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 sidebar">
                <div class="text-center py-4">
                    <div class="profile-img-container mb-3" onclick="document.getElementById('profileImageInput').click()">
                        <img src="<?php echo htmlspecialchars($employee['profile_image'] ?? '../../assets/images/user_image.jpg'); ?>" 
                             alt="Profile" 
                             class="profile-img"
                             onerror="this.src='../../assets/images/user_image.jpg'">
                        <div class="profile-img-overlay">
                            <i class="fas fa-camera fa-2x"></i>
                        </div>
                    </div>
                    <h6 class="mb-1"><?php echo htmlspecialchars($employee['username'] ?? 'Employee'); ?></h6>
                    <p class="text-muted mb-3">
                        <small><?php echo htmlspecialchars($employee['employee_code'] ?? 'EMP000'); ?></small>
                    </p>
                    <form method="POST" action="" enctype="multipart/form-data" id="imageUploadForm" style="display: none;">
                        <input type="file" id="profileImageInput" name="profile_image" accept="image/*" onchange="this.form.submit()">
                    </form>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="../books/manage.php">
                        <i class="fas fa-book me-2"></i>Manage Books
                    </a>
                    <a class="nav-link" href="../reports/index.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    <a class="nav-link active" href="#">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 main-content">
                <!-- Messages -->
                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Settings Navigation -->
                <div class="row mb-4">
                    <div class="col-lg-3">
                        <div class="settings-nav">
                            <a href="#profile" class="settings-nav-link active" onclick="showSection('profile')">
                                <i class="fas fa-user"></i> Profile
                            </a>
                            <a href="#security" class="settings-nav-link" onclick="showSection('security')">
                                <i class="fas fa-lock"></i> Security
                            </a>
                            <a href="#employee" class="settings-nav-link" onclick="showSection('employee')">
                                <i class="fas fa-briefcase"></i> Employee Info
                            </a>
                            <a href="#account" class="settings-nav-link" onclick="showSection('account')">
                                <i class="fas fa-user-cog"></i> Account
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-lg-9">
                        <!-- Profile Settings -->
                        <div id="profileSection" class="settings-section">
                            <div class="card settings-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center mb-4 mb-md-0">
                                            <div class="profile-img-container mb-3" onclick="document.getElementById('profileImageInput').click()">
                                                <img src="<?php echo htmlspecialchars($employee['profile_image'] ?? '../../assets/images/user_image.jpg'); ?>" 
                                                     alt="Profile" 
                                                     class="profile-img"
                                                     onerror="this.src='../../assets/images/user_image.jpg'">
                                                <div class="profile-img-overlay">
                                                    <i class="fas fa-camera fa-2x"></i>
                                                </div>
                                            </div>
                                            <p class="text-muted">
                                                <small>Click image to upload new photo<br>Max size: 5MB</small>
                                            </p>
                                        </div>
                                        
                                        <div class="col-md-8">
                                            <form method="POST" action="">
                                                <div class="form-section">
                                                    <h6 class="form-section-title">Personal Information</h6>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Username</label>
                                                            <input type="text" 
                                                                   class="form-control <?php echo isset($field_errors['username']) ? 'is-invalid' : ''; ?>" 
                                                                   name="username" 
                                                                   value="<?php echo htmlspecialchars($employee['username'] ?? ''); ?>">
                                                            <?php if (isset($field_errors['username'])): ?>
                                                                <div class="invalid-feedback"><?php echo $field_errors['username']; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Email Address</label>
                                                            <input type="email" 
                                                                   class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" 
                                                                   name="email" 
                                                                   value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                                                            <?php if (isset($field_errors['email'])): ?>
                                                                <div class="invalid-feedback"><?php echo $field_errors['email']; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Phone Number</label>
                                                            <input type="tel" 
                                                                   class="form-control <?php echo isset($field_errors['phone_number']) ? 'is-invalid' : ''; ?>" 
                                                                   name="phone_number" 
                                                                   value="<?php echo htmlspecialchars($employee['phone_number'] ?? ''); ?>">
                                                            <?php if (isset($field_errors['phone_number'])): ?>
                                                                <div class="invalid-feedback"><?php echo $field_errors['phone_number']; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Gender</label>
                                                            <select class="form-select <?php echo isset($field_errors['gender']) ? 'is-invalid' : ''; ?>" name="gender">
                                                                <option value="">Select Gender</option>
                                                                <option value="Male" <?php echo ($employee['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                                <option value="Female" <?php echo ($employee['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                                            </select>
                                                            <?php if (isset($field_errors['gender'])): ?>
                                                                <div class="invalid-feedback"><?php echo $field_errors['gender']; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-end">
                                                    <button type="submit" name="update_profile" class="btn btn-save">
                                                        <i class="fas fa-save me-2"></i>Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Settings -->
                        <div id="securitySection" class="settings-section" style="display: none;">
                            <div class="card settings-card">
                                <div class="card-header card-header-secondary">
                                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="" id="passwordForm">
                                        <div class="form-section">
                                            <h6 class="form-section-title">Change Password</h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Current Password</label>
                                                <div class="input-group">
                                                    <input type="password" 
                                                           class="form-control <?php echo isset($field_errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                                           name="current_password" 
                                                           id="currentPassword">
                                                    <span class="input-group-text toggle-password" onclick="togglePassword('currentPassword')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                    <?php if (isset($field_errors['current_password'])): ?>
                                                        <div class="invalid-feedback"><?php echo $field_errors['current_password']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">New Password</label>
                                                <div class="input-group">
                                                    <input type="password" 
                                                           class="form-control <?php echo isset($field_errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                                           name="new_password" 
                                                           id="newPassword"
                                                           onkeyup="checkPasswordStrength(this.value)">
                                                    <span class="input-group-text toggle-password" onclick="togglePassword('newPassword')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                    <?php if (isset($field_errors['new_password'])): ?>
                                                        <div class="invalid-feedback"><?php echo $field_errors['new_password']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">Password must be at least 8 characters long</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Confirm New Password</label>
                                                <div class="input-group">
                                                    <input type="password" 
                                                           class="form-control <?php echo isset($field_errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                                           name="confirm_password" 
                                                           id="confirmPassword">
                                                    <span class="input-group-text toggle-password" onclick="togglePassword('confirmPassword')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                    <?php if (isset($field_errors['confirm_password'])): ?>
                                                        <div class="invalid-feedback"><?php echo $field_errors['confirm_password']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" name="change_password" class="btn btn-save">
                                                <i class="fas fa-key me-2"></i>Change Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employee Information -->
                        <div id="employeeSection" class="settings-section" style="display: none;">
                            <div class="card settings-card">
                                <div class="card-header card-header-accent">
                                    <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Employee Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Employee Code</label>
                                                <div class="form-control bg-light"><?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Hire Date</label>
                                                <div class="form-control bg-light"><?php echo date('F j, Y', strtotime($employee['hire_date'] ?? 'N/A')); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <h6 class="form-section-title">Update Employee Details</h6>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Position</label>
                                                    <select class="form-select <?php echo isset($field_errors['position']) ? 'is-invalid' : ''; ?>" name="position">
                                                        <option value="">Select Position</option>
                                                        <option value="librarian" <?php echo ($employee['position'] ?? '') == 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                                                        <option value="assistant_librarian" <?php echo ($employee['position'] ?? '') == 'assistant_librarian' ? 'selected' : ''; ?>>Assistant Librarian</option>
                                                        <option value="library_assistant" <?php echo ($employee['position'] ?? '') == 'library_assistant' ? 'selected' : ''; ?>>Library Assistant</option>
                                                        <option value="technician" <?php echo ($employee['position'] ?? '') == 'technician' ? 'selected' : ''; ?>>Technician</option>
                                                        <option value="manager" <?php echo ($employee['position'] ?? '') == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                        <option value="director" <?php echo ($employee['position'] ?? '') == 'director' ? 'selected' : ''; ?>>Director</option>
                                                        <option value="admin" <?php echo ($employee['position'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    </select>
                                                    <?php if (isset($field_errors['position'])): ?>
                                                        <div class="invalid-feedback"><?php echo $field_errors['position']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Employment Type</label>
                                                    <select class="form-select <?php echo isset($field_errors['employment_type']) ? 'is-invalid' : ''; ?>" name="employment_type">
                                                        <option value="">Select Type</option>
                                                        <option value="full_time" <?php echo ($employee['employment_type'] ?? '') == 'full_time' ? 'selected' : ''; ?>>Full Time</option>
                                                        <option value="part_time" <?php echo ($employee['employment_type'] ?? '') == 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                                                        <option value="contract" <?php echo ($employee['employment_type'] ?? '') == 'contract' ? 'selected' : ''; ?>>Contract</option>
                                                    </select>
                                                    <?php if (isset($field_errors['employment_type'])): ?>
                                                        <div class="invalid-feedback"><?php echo $field_errors['employment_type']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" name="update_employee_info" class="btn btn-save">
                                                <i class="fas fa-save me-2"></i>Update Employee Info
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Settings -->
                        <div id="accountSection" class="settings-section" style="display: none;">
                            <div class="card settings-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Account Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-section">
                                        <h6 class="form-section-title">Account Information</h6>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted">Account Status</label>
                                                <div class="form-control bg-light">
                                                    <span class="status-badge badge-active">
                                                        <i class="fas fa-check-circle me-1"></i>Active
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted">Member Since</label>
                                                <div class="form-control bg-light">
                                                    <?php echo date('F j, Y', strtotime($employee['registration_date'] ?? 'N/A')); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted">Last Login</label>
                                                <div class="form-control bg-light">
                                                    <?php echo isset($_SESSION['last_login']) ? date('F j, Y \a\t g:i A', strtotime($_SESSION['last_login'])) : 'Never'; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted">Account Type</label>
                                                <div class="form-control bg-light">
                                                    <span class="badge bg-info">Employee</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h6 class="form-section-title">Danger Zone</h6>
                                        
                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning</h6>
                                            <p class="mb-2">These actions are irreversible. Please proceed with caution.</p>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                                <i class="fas fa-user-slash me-2"></i>Deactivate Account
                                            </button>
                                            
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                                <i class="fas fa-trash-alt me-2"></i>Delete Account
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="deactivateModalLabel">
                        <i class="fas fa-user-slash me-2"></i>Deactivate Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will deactivate your account.
                    </div>
                    <p>Your account will be temporarily disabled. You can reactivate it by contacting the system administrator.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeactivate">
                        <label class="form-check-label" for="confirmDeactivate">
                            I understand this action is temporary and reversible
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="deactivateBtn" disabled>
                        <i class="fas fa-user-slash me-2"></i>Deactivate Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-trash-alt me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Danger:</strong> This action cannot be undone!
                    </div>
                    <p>This will permanently delete your account and all associated data, including:</p>
                    <ul>
                        <li>Your profile information</li>
                        <li>Employee records</li>
                        <li>All transaction history</li>
                        <li>Any associated fines or records</li>
                    </ul>
                    <div class="mb-3">
                        <label for="deleteConfirm" class="form-label">Type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="deleteConfirm" placeholder="Type DELETE here">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteBtn" disabled>
                        <i class="fas fa-trash-alt me-2"></i>Permanently Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Show/hide settings sections
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionId + 'Section').style.display = 'block';
            
            // Update active nav link
            document.querySelectorAll('.settings-nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Modal confirmations
        document.addEventListener('DOMContentLoaded', function() {
            // Deactivate confirmation
            const deactivateCheckbox = document.getElementById('confirmDeactivate');
            const deactivateBtn = document.getElementById('deactivateBtn');
            
            deactivateCheckbox.addEventListener('change', function() {
                deactivateBtn.disabled = !this.checked;
            });
            
            deactivateBtn.addEventListener('click', function() {
                alert('Account deactivation request sent. This would deactivate the account in a real system.');
                const modal = bootstrap.Modal.getInstance(document.getElementById('deactivateModal'));
                modal.hide();
            });
            
            // Delete confirmation
            const deleteInput = document.getElementById('deleteConfirm');
            const deleteBtn = document.getElementById('deleteBtn');
            
            deleteInput.addEventListener('input', function() {
                deleteBtn.disabled = this.value !== 'DELETE';
            });
            
            deleteBtn.addEventListener('click', function() {
                if (deleteInput.value === 'DELETE') {
                    if (confirm('Are you absolutely sure? This action cannot be undone!')) {
                        alert('Account deletion would be processed here. In a real system, this would permanently delete the account.');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                        modal.hide();
                    }
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Image preview before upload
        document.getElementById('profileImageInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const img = document.querySelector('.profile-img');
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>