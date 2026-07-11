<?php
// admin/dashboard.php
session_start();
include "../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php");
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

// Handle profile image upload
$upload_success = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
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
                    $newFileName = "employee_" . $user_id . "_" . time() . "." . $fileExt;
                    $fileDestination = '../assets/uploads/' . $newFileName;
                    
                    if (!file_exists('../assets/uploads/')) {
                        mkdir('../assets/uploads/', 0777, true);
                    }
                    
                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        $old_image = $employee['profile_image'] ?? '../assets/images/user_image.jpg';
                        if ($old_image != '../assets/images/user_image.jpg' && file_exists($old_image)) {
                            unlink($old_image);
                        }
                        
                        $update_query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($stmt, "si", $fileDestination, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $upload_success = "Profile image updated successfully!";
                            $_SESSION['profile_image'] = $fileDestination;
                            $employee['profile_image'] = $fileDestination;
                        } else {
                            $upload_error = "Failed to update database.";
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $upload_error = "Error uploading file.";
                    }
                } else {
                    $upload_error = "File is too large. Maximum size is 5MB.";
                }
            } else {
                $upload_error = "Error uploading file.";
            }
        } else {
            $upload_error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    } else {
        $upload_error = "Please select an image to upload.";
    }
}

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
    (SELECT COUNT(*) FROM books) as total_books,
    (SELECT COUNT(*) FROM transactions WHERE status = 'active' AND transaction_type = 'borrow') as active_loans,
    (SELECT COUNT(*) FROM books WHERE available_copies = 0) as books_unavailable,
    (SELECT SUM(current_fines) FROM members) as total_fines";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent transactions
$recent_query = "SELECT t.*, u.username, b.title 
                 FROM transactions t
                 JOIN members m ON t.member_id = m.member_id
                 JOIN users u ON m.user_id = u.user_id
                 JOIN books b ON t.book_id = b.book_id
                 ORDER BY t.created_at DESC LIMIT 10";
$recent_result = mysqli_query($conn, $recent_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Employee Dashboard</title>
    
    <link rel="icon" href="../assets/images/logo-library.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background-color: white;
            min-height: calc(100vh - 70px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
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
        
        .sidebar .nav-link i {
            width: 25px;
        }
        
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }
        
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid white;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .profile-img:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }
        
        .profile-img-container {
            position: relative;
            display: inline-block;
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
            font-size: 1.5rem;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(26, 95, 180, 0.05);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700;">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <div class="d-flex align-items-center">
                <span class="me-3">
                    <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($employee['position']); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 d-none d-md-block sidebar">
                <div class="text-center py-4">
                    <div class="profile-img-container mb-3" data-bs-toggle="modal" data-bs-target="#profileModal">
                        <img src="<?php echo htmlspecialchars($employee['profile_image'] ?? '../assets/images/user_image.jpg'); ?>" 
                             alt="Profile" 
                             class="profile-img"
                             onerror="this.src='../assets/images/user_image.jpg'">
                        <div class="profile-img-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <h6 class="mb-1"><?php echo htmlspecialchars($employee['username']); ?></h6>
                    <p class="text-muted mb-3">
                        <small><?php echo htmlspecialchars($employee['employee_code']); ?></small>
                    </p>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal">
                        <i class="fas fa-edit me-1"></i>Change Photo
                    </button>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="#">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="books/manage.php">
                        <i class="fas fa-book me-2"></i>Manage Books
                    </a>
                    <a class="nav-link" href="users/manage.php">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a class="nav-link" href="transactions/manage.php">
                        <i class="fas fa-exchange-alt me-2"></i>Transactions
                    </a>
                    <a class="nav-link" href="reports/index.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    <a class="nav-link" href="settings/index.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9" style="margin-top: 20px;">
                <!-- Welcome Card -->
                <div class="card welcome-card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="card-title">Welcome, <?php echo htmlspecialchars($employee['username']); ?>!</h3>
                                <p class="card-text">Here's what's happening in your library today.</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-book-reader fa-4x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                        <p class="text-muted mb-0">Total Users</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(26, 95, 180, 0.1); color: var(--primary-color);">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo $stats['total_books']; ?></h2>
                                        <p class="text-muted mb-0">Total Books</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(38, 162, 105, 0.1); color: var(--secondary-color);">
                                        <i class="fas fa-book"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo $stats['active_loans']; ?></h2>
                                        <p class="text-muted mb-0">Active Loans</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(229, 165, 10, 0.1); color: var(--accent-color);">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0">$<?php echo number_format($stats['total_fines'], 2); ?></h2>
                                        <p class="text-muted mb-0">Total Fines</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Book</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recent_result) > 0): 
                                        while($transaction = mysqli_fetch_assoc($recent_result)): ?>
                                        <tr>
                                            <td>#<?php echo $transaction['transaction_id']; ?></td>
                                            <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($transaction['title'], 0, 30)); ?><?php echo strlen($transaction['title']) > 30 ? '...' : ''; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $transaction['transaction_type'] == 'borrow' ? 'bg-primary' : 
                                                           ($transaction['transaction_type'] == 'return' ? 'bg-success' : 'bg-info'); ?>">
                                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $transaction['status'] == 'active' ? 'bg-warning text-dark' : 
                                                           ($transaction['status'] == 'completed' ? 'bg-success' : 'bg-danger'); ?>">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-exchange-alt fa-2x text-muted mb-3"></i>
                                                <p class="text-muted mb-0">No transactions yet</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Image Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="fas fa-camera me-2"></i>Update Profile Image
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($upload_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $upload_success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($upload_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $upload_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mb-4">
                        <div class="profile-img-container mb-3">
                            <img src="<?php echo htmlspecialchars($employee['profile_image'] ?? '../assets/images/user_image.jpg'); ?>" 
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
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Image
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
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
        
        document.getElementById('profilePreview').addEventListener('click', function() {
            document.getElementById('profile_image').click();
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        document.getElementById('profileModal').addEventListener('hidden.bs.modal', function () {
            location.reload();
        });
    </script>
</body>
</html>