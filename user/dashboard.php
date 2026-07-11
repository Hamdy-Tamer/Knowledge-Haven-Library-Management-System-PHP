<?php
// user/dashboard.php
session_start();
include "../config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
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

// If user doesn't have a member record, create one
if (!$user['member_id']) {
    $member_code = 'MEM' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
    $insert_member = "INSERT INTO members (user_id, member_code) VALUES (?, ?)";
    $stmt2 = mysqli_prepare($conn, $insert_member);
    mysqli_stmt_bind_param($stmt2, "is", $user_id, $member_code);
    mysqli_stmt_execute($stmt2);
    
    // Refresh user data
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
}

// Handle profile image upload
$upload_message = '';
$upload_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    if ($file['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if ($file['size'] <= 2097152) {
                if (!file_exists('../assets/uploads/')) {
                    mkdir('../assets/uploads/', 0777, true);
                }
                
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $destination = '../assets/uploads/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $old_image = $user['profile_image'] ?? '../assets/images/user_image.jpg';
                    if ($old_image != '../assets/images/user_image.jpg' && file_exists($old_image)) {
                        unlink($old_image);
                    }
                    
                    $update_query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "si", $destination, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $user['profile_image'] = $destination;
                        $upload_message = "Profile image updated successfully!";
                        $upload_type = 'success';
                    } else {
                        $upload_message = "Failed to update database.";
                        $upload_type = 'danger';
                    }
                } else {
                    $upload_message = "Error uploading file.";
                    $upload_type = 'danger';
                }
            } else {
                $upload_message = "File is too large. Maximum size is 2MB.";
                $upload_type = 'danger';
            }
        } else {
            $upload_message = "Invalid file type. Only JPG, PNG & GIF files are allowed.";
            $upload_type = 'danger';
        }
    }
}

// Get user's borrowed books
$borrowed_books_query = "SELECT t.*, b.title, b.isbn, a.name as author_name 
                         FROM transactions t
                         JOIN books b ON t.book_id = b.book_id
                         JOIN authors a ON b.author_id = a.author_id
                         WHERE t.member_id = ? AND t.status = 'active' AND t.transaction_type = 'borrow'
                         ORDER BY t.due_date ASC";
$stmt_books = mysqli_prepare($conn, $borrowed_books_query);
mysqli_stmt_bind_param($stmt_books, "i", $user['member_id']);
mysqli_stmt_execute($stmt_books);
$borrowed_books = mysqli_stmt_get_result($stmt_books);

// Get available books for borrowing
$available_books_query = "SELECT b.*, a.name as author_name, c.name as category_name 
                         FROM books b
                         JOIN authors a ON b.author_id = a.author_id
                         JOIN categories c ON b.category_id = c.category_id
                         WHERE b.available_copies > 0
                         ORDER BY b.book_id DESC
                         LIMIT 4";
$available_books_result = mysqli_query($conn, $available_books_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - User Dashboard</title>
    
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
            padding-top: 20px;
            overflow-x: hidden;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            min-height: 100vh;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            width: 25px;
        }
        
        .user-profile {
            text-align: center;
            padding: 30px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid white;
            object-fit: cover;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .profile-img:hover {
            opacity: 0.9;
        }
        
        .btn-change-img {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            margin-top: 10px;
        }
        
        .btn-change-img:hover {
            background: rgba(255, 255, 255, 0.3);
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
        
        .preview-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            margin: 0 auto 20px;
            display: block;
            cursor: pointer;
        }
        
        .file-input {
            display: none;
        }
        
        .card-stat {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .card-stat:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .book-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .book-cover {
            height: 180px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .btn-borrow {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-borrow:hover {
            background-color: #0d4a9e;
            color: white;
        }
        
        .due-soon {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .overdue {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .navbar-custom {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .welcome-text {
            color: var(--primary-color);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Upload Image Modal -->
    <div class="modal fade" id="uploadImageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-camera me-2"></i>Change Profile Picture
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <?php if ($upload_message): ?>
                            <div class="alert alert-<?php echo $upload_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $upload_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <img src="<?php echo htmlspecialchars($user['profile_image'] ?: '../assets/images/user_image.jpg'); ?>" 
                                 alt="Current Profile" 
                                 class="preview-img"
                                 id="imagePreview"
                                 onclick="document.getElementById('profile_image').click()"
                                 onerror="this.src='../assets/images/user_image.jpg'">
                            <p class="text-muted mt-2">Click image to select new one</p>
                        </div>
                        
                        <div class="mb-3">
                            <input type="file" 
                                   name="profile_image" 
                                   id="profile_image" 
                                   class="file-input"
                                   accept="image/*"
                                   onchange="previewImage(event)">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Supported formats: JPG, PNG, GIF (Max: 2MB)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Upload & Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700; color: var(--primary-color);">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <div class="d-flex align-items-center">
                <span class="welcome-text me-3 d-none d-md-block">
                    <i class="fas fa-user-circle me-1"></i>Welcome, <?php echo htmlspecialchars($user['username']); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="margin-top: 80px;">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 d-none d-lg-block sidebar">
                <div class="user-profile">
                    <img src="<?php echo htmlspecialchars($user['profile_image'] ?: '../assets/images/user_image.jpg'); ?>" 
                         alt="Profile" 
                         class="profile-img"
                         onclick="openUploadModal()"
                         onerror="this.src='../assets/images/user_image.jpg'">
                    
                    <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($user['username']); ?></h5>
                    <p class="mb-0 text-muted" style="color: rgba(255,255,255,0.7) !important;">
                        <small>Member ID: <?php echo htmlspecialchars($user['member_code']); ?></small>
                    </p>
                    
                    <!-- Change Image Button -->
                    <button type="button" class="btn-change-img" onclick="openUploadModal()">
                        <i class="fas fa-camera me-1"></i> Change Photo
                    </button>
                </div>
                
                <nav class="nav flex-column mt-3">
                    <a class="nav-link active" href="#">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="books/available.php">
                        <i class="fas fa-search me-2"></i>Browse Books
                    </a>
                    <a class="nav-link" href="books/return.php">
                        <i class="fas fa-book me-2"></i>Return Books
                    </a>
                    <a class="nav-link" href="history.php">
                        <i class="fas fa-history me-2"></i>History
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user-edit me-2"></i>Profile
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(26, 95, 180, 0.1); color: var(--primary-color);">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <h3 class="card-title"><?php echo $user['total_books_borrowed'] ?? 0; ?></h3>
                                <p class="card-text text-muted">Books Borrowed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(38, 162, 105, 0.1); color: var(--secondary-color);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="card-title"><?php echo mysqli_num_rows($borrowed_books); ?></h3>
                                <p class="card-text text-muted">Currently Borrowed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(229, 165, 10, 0.1); color: var(--accent-color);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h3 class="card-title"><?php echo $user['current_fines'] ?? '0.00'; ?>$</h3>
                                <p class="card-text text-muted">Current Fines</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(102, 126, 234, 0.1); color: #667eea;">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h3 class="card-title">Member</h3>
                                <p class="card-text text-muted">Account Status</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Borrowed Books Section -->
                <div class="card mb-4" id="borrowed">
                    <div class="card-header" style="background-color: var(--primary-color); color: white;">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Currently Borrowed Books</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($borrowed_books) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Book Title</th>
                                            <th>Author</th>
                                            <th>ISBN</th>
                                            <th>Borrow Date</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($book = mysqli_fetch_assoc($borrowed_books)): 
                                            $due_date = new DateTime($book['due_date']);
                                            $today = new DateTime();
                                            $days_diff = $today->diff($due_date)->days;
                                            $is_overdue = $due_date < $today;
                                            $is_due_soon = !$is_overdue && $days_diff <= 3;
                                        ?>
                                        <tr class="<?php echo $is_overdue ? 'overdue' : ($is_due_soon ? 'due-soon' : ''); ?>">
                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                            <td><?php echo htmlspecialchars($book['author_name']); ?></td>
                                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($book['due_date'])); ?></td>
                                            <td>
                                                <?php if ($is_overdue): ?>
                                                    <span class="badge bg-danger">Overdue</span>
                                                <?php elseif ($is_due_soon): ?>
                                                    <span class="badge bg-warning text-dark">Due Soon</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No books currently borrowed</h5>
                                <p class="text-muted">Browse our collection and borrow your first book!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Available Books Section -->
                <div class="card" id="available">
                    <div class="card-header" style="background-color: var(--secondary-color); color: white;">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Available Books</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (mysqli_num_rows($available_books_result) > 0): 
                                while($book = mysqli_fetch_assoc($available_books_result)): ?>
                                <div class="col-md-3 mb-4">
                                    <div class="card book-card">
                                        <div class="book-cover">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars(substr($book['title'], 0, 50)); ?><?php echo strlen($book['title']) > 50 ? '...' : ''; ?></h6>
                                            <p class="card-text small text-muted">
                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($book['author_name']); ?>
                                            </p>
                                            <p class="card-text small">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($book['category_name']); ?>
                                            </p>
                                            <p class="card-text small">
                                                <i class="fas fa-copy me-1"></i>Available: <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?>
                                            </p>
                                            <form action="books/borrow.php" method="POST" class="borrow-form">
                                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                <button type="submit" class="btn btn-borrow btn-sm w-100" 
                                                        <?php echo mysqli_num_rows($borrowed_books) >= 5 ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-bookmark me-1"></i>
                                                    <?php echo mysqli_num_rows($borrowed_books) >= 5 ? 'Borrow Limit Reached' : 'Borrow Book'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-4">
                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No books available at the moment</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle borrow book forms
        document.querySelectorAll('.borrow-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to borrow this book?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Open upload image modal
        function openUploadModal() {
            var uploadImageModal = new bootstrap.Modal(document.getElementById('uploadImageModal'));
            uploadImageModal.show();
        }
        
        // Preview image in upload modal
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Auto-close alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                });
            }, 5000);
        });
        
        // Refresh page when modal is closed (to show updated image)
        document.getElementById('uploadImageModal').addEventListener('hidden.bs.modal', function () {
            location.reload();
        });
    </script>
</body>
</html>