<?php
session_start();
include "config.php";

$error = "";
$success = "";

// Individual field errors
$username_error = "";
$email_error = "";
$gender_error = "";
$phone_error = "";
$password_error = "";
$confirm_password_error = "";

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'employee') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and trim whitespace
    $username = trim($_POST["username"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $gender = $_POST["gender"] ?? '';
    $phone_number = trim($_POST["phone_number"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';
    
    // Handle profile image upload
    $profile_image = 'assets/images/user_image.jpg'; // Default image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file
        if (!in_array($fileExt, ALLOWED_TYPES)) {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed!";
        } elseif ($fileSize > MAX_FILE_SIZE) {
            $error = "File is too large! Maximum size is 5MB.";
        } elseif ($fileError !== UPLOAD_ERR_OK) {
            $error = "Error uploading file!";
        } else {
            // Generate unique filename
            $newFileName = uniqid('profile_', true) . '.' . $fileExt;
            $uploadPath = UPLOAD_DIR . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                $profile_image = $uploadPath;
            } else {
                $error = "Failed to upload image!";
            }
        }
    }
    
    // PHP Validation for empty fields (only if no upload error)
    if (!$error) {
        $has_error = false;
        
        // Validate username
        if (empty($username)) {
            $username_error = "Username is required!";
            $has_error = true;
        }
        
        // Validate email
        if (empty($email)) {
            $email_error = "Email is required!";
            $has_error = true;
        }
        
        // Validate gender
        if (empty($gender)) {
            $gender_error = "Gender is required!";
            $has_error = true;
        }
        
        // Validate phone
        if (empty($phone_number)) {
            $phone_error = "Phone number is required!";
            $has_error = true;
        }
        
        // Validate password
        if (empty($password)) {
            $password_error = "Password is required!";
            $has_error = true;
        }
        
        // Validate confirm password
        if (empty($confirm_password)) {
            $confirm_password_error = "Confirm password is required!";
            $has_error = true;
        }
        
        if ($has_error) {
            $error = "Please fill in all required fields!";
        }
        // Check password length (8-25 characters) - PHP validation
        elseif (strlen($password) < 8 || strlen($password) > 25) {
            $password_error = "Password must be between 8 and 25 characters!";
            $error = "Password must be between 8 and 25 characters!";
        }
        // Check password match - PHP validation
        elseif ($password !== $confirm_password) {
            $confirm_password_error = "Passwords do not match!";
            $error = "Passwords do not match!";
        }
        // Check email format - PHP validation
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_error = "Invalid email format!";
            $error = "Invalid email format!";
        }
        // Check phone number format - PHP validation
        elseif (!preg_match('/^[0-9]{10,15}$/', $phone_number)) {
            $phone_error = "Phone number must be 10-15 digits!";
            $error = "Phone number must be 10-15 digits!";
        } else {
            // Check if email already exists
            $check_email = "SELECT user_id FROM users WHERE email = ?";
            $stmt = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $email_error = "Email already registered!";
                $error = "Email already registered!";
                mysqli_stmt_close($stmt);
            } else {
                mysqli_stmt_close($stmt);
                
                // Check if phone number already exists
                $check_phone = "SELECT user_id FROM users WHERE phone_number = ?";
                $stmt = mysqli_prepare($conn, $check_phone);
                mysqli_stmt_bind_param($stmt, "s", $phone_number);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $phone_error = "Phone number already registered!";
                    $error = "Phone number already registered!";
                    mysqli_stmt_close($stmt);
                } else {
                    mysqli_stmt_close($stmt);
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $insert_sql = "INSERT INTO users (username, email, gender, phone_number, profile_image, password, role) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'user')";
                    $stmt = mysqli_prepare($conn, $insert_sql);
                    mysqli_stmt_bind_param($stmt, "ssssss", $username, $email, $gender, $phone_number, $profile_image, $hashed_password);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header("Location: login.php?registered=true"); 
                        exit;
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Register</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="assets/images/logo-library.png" type="image/png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
            padding: 40px;
            width: 100%;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .library-logo {
            width: 70px;
            height: 70px;
            margin-bottom: 15px;
            border-radius: 50%;
            padding: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .form-icon {
            color: #764ba2;
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 0;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .login-link {
            color: #764ba2;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        .password-toggle {
            cursor: pointer;
            color: #764ba2;
        }

        .library-title {
            color: #764ba2;
            font-weight: 700;
            margin-bottom: 10px;
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
            border-color: #764ba2;
            background-color: rgba(118, 75, 162, 0.1);
            color: #764ba2;
            font-weight: 500;
        }

        .form-control:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.25);
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

        .profile-image-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #764ba2;
            cursor: pointer;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #764ba2;
            font-size: 40px;
            margin: 0 auto;
        }

        .profile-preview:hover {
            opacity: 0.8;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-btn:hover {
            background-color: #e9ecef;
            border-color: #764ba2;
        }

        .image-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .current-image-text {
            font-size: 0.8rem;
            color: #28a745;
            margin-top: 5px;
        }
    
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            body {
                padding: 0;
                display: block;
            }

            .container {
                padding: 0;
                margin: 0;
                max-width: 100%;
            }

            .register-container {
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 30px 25px;
                box-shadow: none;
                margin: 0;
            }
        
        /* Make gender options stack vertically on very small screens */
        @media (max-width: 480px) {
            .gender-options {
                flex-direction: column;
                gap: 10px;
            }
        }
    }
    
        /* Small mobile devices */
        @media (max-width: 480px) {
            .register-container {
                padding: 25px 20px;
            }

            .library-logo {
                width: 60px;
                height: 60px;
            }

            .library-title {
                font-size: 1.5rem;
            }

            h2.library-title {
                font-size: 1.6rem;
            }
        
            .btn-register {
                padding: 14px 0;
                font-size: 1.1rem;
            }

            .profile-preview {
                width: 100px;
                height: 100px;
                font-size: 35px;
            }
        }
    
        /* Very small devices */
        @media (max-width: 360px) {
            .register-container {
                padding: 20px 15px;
            }

            .mb-3, .mb-4 {
                margin-bottom: 1rem !important;
            }

            .form-control {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .gender-option label {
                padding: 10px;
                font-size: 0.9rem;
            }

            .profile-preview {
                width: 90px;
                height: 90px;
                font-size: 30px;
            }
        }
</style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="logo-container">
                <img src="assets/images/logo-library.png" alt="Library Logo" class="library-logo">
                <h2 class="library-title">Create Library Account</h2>
                <p class="text-muted">Join our library community</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                <!-- Profile Image Upload -->
                <div class="mb-4">
                    <div class="profile-image-container">
                        <div class="profile-preview" id="profilePreview">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <label class="form-label">
                        <i class="fas fa-camera form-icon me-2"></i>Profile Image (Optional)
                    </label>
                    <div class="file-input-wrapper mb-2">
                        <div class="file-upload-btn">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Choose Profile Image
                        </div>
                        <input type="file" 
                               class="form-control" 
                               name="profile_image" 
                               id="profile_image"
                               accept=".jpg,.jpeg,.png,.gif"
                               onchange="previewImage(event)">
                    </div>
                    <p class="image-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Max size: 5MB | Allowed: JPG, JPEG, PNG, GIF
                    </p>
                </div>
                
                <!-- Username -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-user form-icon me-2"></i>Username
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control <?php echo !empty($username_error) ? 'is-invalid' : ''; ?>" 
                               name="username" 
                               id="username"
                               placeholder="Enter username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <?php if (!empty($username_error)): ?>
                        <div class="field-error">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $username_error; ?>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted">Can contain letters, numbers, and special characters</small>
                </div>
                
                <!-- Email -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-envelope form-icon me-2"></i>Email Address
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-at"></i>
                        </span>
                        <input type="email" 
                               class="form-control <?php echo !empty($email_error) ? 'is-invalid' : ''; ?>" 
                               name="email" 
                               id="email"
                               placeholder="Enter your email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <?php if (!empty($email_error)): ?>
                        <div class="field-error">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $email_error; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Gender -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-venus-mars form-icon me-2"></i>Gender
                    </label>
                    <div class="gender-options">
                        <div class="gender-option">
                            <input type="radio" id="male" name="gender" value="Male" 
                                <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'checked' : ''; ?>>
                            <label for="male" id="maleLabel" class="<?php echo !empty($gender_error) ? 'invalid-radio-label' : ''; ?>">
                                <i class="fas fa-male me-2"></i>Male
                            </label>
                        </div>
                        <div class="gender-option">
                            <input type="radio" id="female" name="gender" value="Female"
                                <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'checked' : ''; ?>>
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
                
                <!-- Phone Number -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-phone form-icon me-2"></i>Phone Number
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-mobile-alt"></i>
                        </span>
                        <input type="tel" 
                               class="form-control <?php echo !empty($phone_error) ? 'is-invalid' : ''; ?>" 
                               name="phone_number" 
                               id="phone_number"
                               placeholder="Enter 10-15 digit phone number"
                               pattern="[0-9]{10,15}"
                               value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                    </div>
                    <?php if (!empty($phone_error)): ?>
                        <div class="field-error">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $phone_error; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-lock form-icon me-2"></i>Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" 
                               class="form-control <?php echo !empty($password_error) ? 'is-invalid' : ''; ?>" 
                               name="password" 
                               id="password"
                               placeholder="Enter password (8-25 characters)">
                        <span class="input-group-text password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <?php if (!empty($password_error)): ?>
                        <div class="field-error">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $password_error; ?>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted">Password must be 8-25 characters long</small>
                </div>
                
                <!-- Confirm Password -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-lock form-icon me-2"></i>Confirm Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" 
                               class="form-control <?php echo !empty($confirm_password_error) ? 'is-invalid' : ''; ?>" 
                               name="confirm_password" 
                               id="confirm_password"
                               placeholder="Confirm your password">
                        <span class="input-group-text password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <?php if (!empty($confirm_password_error)): ?>
                        <div class="field-error">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $confirm_password_error; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-register btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </div>
                
                <div class="text-center">
                    <p class="mb-0">Already have an account? 
                        <a href="login.php" class="login-link">
                            <i class="fas fa-sign-in-alt me-1"></i>Login here
                        </a>
                    </p>
                </div>
            </form>
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
                    preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="profile-preview" style="object-fit: cover;">`;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Click profile preview to trigger file input
        document.getElementById('profilePreview').addEventListener('click', function() {
            document.getElementById('profile_image').click();
        });

        // Toggle password visibility for password field 
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Toggle password visibility for confirm password field
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

    </script>
</body>
</html>