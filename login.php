<?php
session_start();
include "config.php";

$error = "";
$success = "";
$email_error = "";
$password_error = "";

// Check if user is already logged in
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
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    
    // PHP Validation for empty fields
    $has_error = false;
    
    if (empty($email)) {
        $email_error = "Email is required!";
        $has_error = true;
    }
    
    if (empty($password)) {
        $password_error = "Password is required!";
        $has_error = true;
    }
    
    // If no empty fields, check database
    if (!$has_error) {
        // Check user in database
        $sql = "SELECT user_id, username, email, password, role FROM users WHERE email = ? AND is_active = TRUE";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['password'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                
                // Redirect based on role
                if ($row['role'] === 'employee') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid email or password!";
            }
        } else {
            $error = "Invalid email or password!";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Please fill in all required fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Login</title>
    
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

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
            width: 100%; 
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .library-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            border-radius: 50%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .form-icon {
            color: #764ba2;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 0;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .register-link {
            color: #764ba2;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link:hover {
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
            
            .login-container {
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 30px 25px;
                box-shadow: none;
                margin: 0;
            }
        }
    
        /* Small mobile devices */
        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
            }

            .library-logo {
                width: 70px;
                height: 70px;
            }

            .library-title {
                font-size: 1.5rem;
            }

            h2.library-title {
                font-size: 1.6rem;
            }

            .btn-login {
                padding: 12px 0;
                font-size: 1.1rem;
            }
        }
    
            /* Very small devices */
            @media (max-width: 360px) {
            .login-container {
                padding: 20px 15px;
            }

            .mb-4 {
                margin-bottom: 1rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo-container">
                <img src="assets/images/logo-library.png" alt="Library Logo" class="library-logo">
                <h2 class="library-title">Library Login</h2>
                <p class="text-muted">Access your library account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>Registration successful! Please login.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
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
                
                <div class="mb-4">
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
                               placeholder="Enter your password">
                        <span class="input-group-text password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <?php if (!empty($password_error)): ?>
                        <div class="field-error">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $password_error; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-login btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
                
                <div class="text-center">
                    <p class="mb-0">Don't have an account? 
                        <a href="register.php" class="register-link">
                            <i class="fas fa-user-plus me-1"></i>Register here
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility - ONLY JavaScript function allowed
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
    </script>
</body>
</html>