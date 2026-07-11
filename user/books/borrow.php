<?php
// user/books/borrow.php
session_start();
include "../../config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Check if user is employee (should redirect to employee dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    header("Location: ../../admin/dashboard.php");
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, m.member_id, m.member_code, m.total_books_borrowed, m.current_fines 
          FROM users u 
          LEFT JOIN members m ON u.user_id = m.user_id 
          WHERE u.user_id = ? AND u.role = 'user'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !$user['member_id']) {
    header("Location: ../dashboard.php?message=Member account not found!&type=danger");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$book_id = 0;
$book_details = null;

// Check if book_id is provided
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_id'])) {
    $book_id = intval($_POST['book_id']);
} elseif (isset($_GET['id'])) {
    $book_id = intval($_GET['id']);
} else {
    header("Location: available.php?message=Book ID is required!&type=danger");
    exit;
}

// Get book details
$book_query = "SELECT b.*, a.name as author_name, c.name as category_name 
               FROM books b
               LEFT JOIN authors a ON b.author_id = a.author_id
               LEFT JOIN categories c ON b.category_id = c.category_id
               WHERE b.book_id = ?";
$stmt = mysqli_prepare($conn, $book_query);
mysqli_stmt_bind_param($stmt, "i", $book_id);
mysqli_stmt_execute($stmt);
$book_result = mysqli_stmt_get_result($stmt);
$book_details = mysqli_fetch_assoc($book_result);

if (!$book_details) {
    header("Location: available.php?message=Book not found!&type=danger");
    exit;
}

// Check if book is available
if ($book_details['available_copies'] <= 0) {
    header("Location: available.php?message=Book is not available for borrowing!&type=danger");
    exit;
}

// Check user's borrowing limit (max 5 books)
$borrow_limit = 5;
$current_borrows_query = "SELECT COUNT(*) as count FROM transactions 
                         WHERE member_id = ? AND status = 'active' AND transaction_type = 'borrow'";
$stmt = mysqli_prepare($conn, $current_borrows_query);
mysqli_stmt_bind_param($stmt, "i", $user['member_id']);
mysqli_stmt_execute($stmt);
$current_result = mysqli_stmt_get_result($stmt);
$current_row = mysqli_fetch_assoc($current_result);
$current_borrows = $current_row['count'] ?? 0;

if ($current_borrows >= $borrow_limit) {
    header("Location: available.php?message=You have reached the borrowing limit (5 books)!&type=danger");
    exit;
}

// Check if user already borrowed this book
$check_borrowed = "SELECT transaction_id FROM transactions 
                  WHERE member_id = ? AND book_id = ? AND status = 'active' AND transaction_type = 'borrow'";
$stmt = mysqli_prepare($conn, $check_borrowed);
mysqli_stmt_bind_param($stmt, "ii", $user['member_id'], $book_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    header("Location: available.php?message=You have already borrowed this book!&type=danger");
    exit;
}

// Handle borrow confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_borrow'])) {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Calculate due date (14 days from today)
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+14 days'));
        
        // Insert transaction record
        $transaction_query = "INSERT INTO transactions 
                             (member_id, book_id, transaction_type, borrow_date, due_date, status) 
                             VALUES (?, ?, 'borrow', ?, ?, 'active')";
        $stmt = mysqli_prepare($conn, $transaction_query);
        mysqli_stmt_bind_param($stmt, "iiss", $user['member_id'], $book_id, $borrow_date, $due_date);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create transaction record.");
        }
        
        $transaction_id = mysqli_insert_id($conn);
        
        // Update book available copies
        $update_book_query = "UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ? AND available_copies > 0";
        $stmt = mysqli_prepare($conn, $update_book_query);
        mysqli_stmt_bind_param($stmt, "i", $book_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update book availability.");
        }
        
        // Update member's total books borrowed
        $update_member_query = "UPDATE members SET total_books_borrowed = total_books_borrowed + 1 WHERE member_id = ?";
        $stmt = mysqli_prepare($conn, $update_member_query);
        mysqli_stmt_bind_param($stmt, "i", $user['member_id']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update member record.");
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Success - redirect to user dashboard
        header("Location: ../dashboard.php?message=Book borrowed successfully! Due date: " . date('M d, Y', strtotime($due_date)) . "&type=success");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $error = "Error borrowing book: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Borrow Book</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
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
        
        .borrow-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .book-cover {
            height: 300px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 5rem;
            margin-bottom: 20px;
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
        
        .btn-confirm {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .btn-confirm:hover {
            background-color: #1e7b4d;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .terms-card {
            background-color: #f8f9fa;
            border-left: 4px solid var(--accent-color);
            padding: 15px;
            border-radius: 5px;
        }
        
        .due-date-badge {
            background-color: var(--accent-color);
            color: var(--dark-color);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .book-cover {
                height: 200px;
                font-size: 3rem;
            }
            
            .btn-confirm, .btn-cancel {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700; color: var(--primary-color);">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <div class="d-flex align-items-center">
                <a href="available.php" class="btn btn-outline-primary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Books
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
                <div class="borrow-card">
                    <div class="card-header card-header-custom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-bookmark me-2"></i>Confirm Book Borrowing</h4>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-book me-1"></i>Book ID: <?php echo $book_id; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column: Book Details -->
                            <div class="col-lg-6">
                                <div class="book-cover mb-4">
                                    <i class="fas fa-book"></i>
                                </div>
                                
                                <div class="info-card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-info-circle me-2"></i>Book Information
                                    </div>
                                    <div class="card-body">
                                        <h3 class="card-title text-primary mb-3"><?php echo htmlspecialchars($book_details['title']); ?></h3>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Author:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($book_details['author_name']); ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Category:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($book_details['category_name']); ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">ISBN:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($book_details['isbn']); ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Publication Year:</span>
                                            <span class="info-value">
                                                <?php echo $book_details['publication_year'] ?: 'Not specified'; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Publisher:</span>
                                            <span class="info-value">
                                                <?php echo $book_details['publisher'] ? htmlspecialchars($book_details['publisher']) : 'Not specified'; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Available Copies:</span>
                                            <span class="info-value">
                                                <?php echo $book_details['available_copies']; ?> / <?php echo $book_details['total_copies']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($book_details['description']): ?>
                                <div class="info-card">
                                    <div class="card-header">
                                        <i class="fas fa-align-left me-2"></i>Description
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0" style="line-height: 1.6;">
                                            <?php echo nl2br(htmlspecialchars($book_details['description'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Right Column: Borrowing Details -->
                            <div class="col-lg-6">
                                <div class="info-card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-user-circle me-2"></i>Your Information
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="info-label">Member Name:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Member ID:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($user['member_code']); ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Email:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Currently Borrowed:</span>
                                            <span class="info-value">
                                                <?php echo $current_borrows; ?> / <?php echo $borrow_limit; ?> books
                                            </span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Total Borrowed:</span>
                                            <span class="info-value"><?php echo $user['total_books_borrowed'] ?? 0; ?> books</span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="info-label">Current Fines:</span>
                                            <span class="info-value">
                                                $<?php echo number_format($user['current_fines'] ?? 0.00, 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Borrowing Summary -->
                                <div class="info-card mb-4">
                                    <div class="card-header">
                                        <i class="fas fa-calendar-check me-2"></i>Borrowing Summary
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center mb-4">
                                            <h5 class="text-muted">Borrow Period</h5>
                                            <div class="due-date-badge mb-3">
                                                <i class="fas fa-calendar-alt me-2"></i>
                                                14 Days
                                            </div>
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <div class="h6 mb-1">Borrow Date</div>
                                                        <div class="text-primary fw-bold"><?php echo date('M d, Y'); ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <div class="h6 mb-1">Due Date</div>
                                                        <div class="text-warning fw-bold"><?php echo date('M d, Y', strtotime('+14 days')); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="terms-card mb-4">
                                            <h6 class="mb-2"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Important Notes:</h6>
                                            <ul class="mb-0 small">
                                                <li>Books must be returned by the due date</li>
                                                <li>Late returns incur fines of $0.50 per day</li>
                                                <li>Maximum borrowing period is 14 days</li>
                                                <li>You can renew books once if not reserved by others</li>
                                                <li>Damaged or lost books must be replaced or paid for</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <i class="fas fa-clock me-2"></i>
                                            <strong>Reminder:</strong> Please return the book on time to avoid fines and ensure availability for other members.
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Confirmation Form -->
                                <div class="info-card">
                                    <div class="card-header">
                                        <i class="fas fa-check-circle me-2"></i>Confirmation
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="" id="borrowForm">
                                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                                            <input type="hidden" name="confirm_borrow" value="1">
                                            
                                            <div class="form-check mb-4">
                                                <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                                <label class="form-check-label" for="agreeTerms">
                                                    I have read and agree to the library's borrowing terms and conditions.
                                                    I understand that I am responsible for returning this book by the due date.
                                                </label>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <a href="available.php" class="btn btn-cancel me-md-2 mb-2 mb-md-0">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </a>
                                                <button type="submit" class="btn btn-confirm" id="borrowButton">
                                                    <i class="fas fa-bookmark me-1"></i>Confirm Borrow
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation and submission
        document.getElementById('borrowForm').addEventListener('submit', function(e) {
            const agreeTerms = document.getElementById('agreeTerms');
            const borrowButton = document.getElementById('borrowButton');
            
            if (!agreeTerms.checked) {
                e.preventDefault();
                alert('Please agree to the terms and conditions before borrowing.');
                agreeTerms.focus();
                return;
            }
            
            // Show loading state
            borrowButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            borrowButton.disabled = true;
            
            // Final confirmation
            if (!confirm('Are you sure you want to borrow this book?\n\nBook: <?php echo addslashes($book_details['title']); ?>\nDue Date: <?php echo date('M d, Y', strtotime("+14 days")); ?>')) {
                e.preventDefault();
                borrowButton.innerHTML = '<i class="fas fa-bookmark me-1"></i>Confirm Borrow';
                borrowButton.disabled = false;
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
        
        // Terms checkbox validation
        document.getElementById('agreeTerms').addEventListener('change', function() {
            const borrowButton = document.getElementById('borrowButton');
            borrowButton.disabled = !this.checked;
        });
        
        // Initialize button state
        document.addEventListener('DOMContentLoaded', function() {
            const agreeTerms = document.getElementById('agreeTerms');
            const borrowButton = document.getElementById('borrowButton');
            borrowButton.disabled = !agreeTerms.checked;
            
            // Show book details in console for debugging
            console.log('Book ID: <?php echo $book_id; ?>');
            console.log('Book Title: <?php echo addslashes($book_details['title']); ?>');
            console.log('Member ID: <?php echo $user['member_id']; ?>');
            console.log('Current Borrows: <?php echo $current_borrows; ?>/<?php echo $borrow_limit; ?>');
        });
    </script>
</body>
</html>