<?php
// user/books/return.php
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
$query = "SELECT u.*, m.member_id, m.member_code 
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
$book_title = '';
$isbn = '';
$book_details = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_book'])) {
    $book_title = trim($_POST['book_title'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    
    // Validate inputs
    if (empty($book_title) || empty($isbn)) {
        $error = "Please enter both book title and ISBN!";
    } else {
        // Check if book exists and is borrowed by this user
        $check_query = "SELECT b.*, t.transaction_id, t.borrow_date, t.due_date, 
                               DATEDIFF(CURDATE(), t.due_date) as days_overdue
                        FROM books b
                        JOIN transactions t ON b.book_id = t.book_id
                        WHERE b.title LIKE ? 
                        AND b.isbn = ? 
                        AND t.member_id = ? 
                        AND t.status = 'active' 
                        AND t.transaction_type = 'borrow'";
        
        $search_title = "%" . $book_title . "%";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ssi", $search_title, $isbn, $user['member_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $book_details = mysqli_fetch_assoc($result);
        
        if (!$book_details) {
            $error = "No active borrowing found for this book with the provided details!";
        } else {
            // Start transaction for return process
            mysqli_begin_transaction($conn);
            
            try {
                $return_date = date('Y-m-d');
                $transaction_id = $book_details['transaction_id'];
                $days_overdue = max(0, $book_details['days_overdue'] ?? 0);
                
                // 1. Update transaction status
                $update_transaction = "UPDATE transactions 
                                      SET return_date = ?, status = 'completed' 
                                      WHERE transaction_id = ?";
                $stmt = mysqli_prepare($conn, $update_transaction);
                mysqli_stmt_bind_param($stmt, "si", $return_date, $transaction_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update transaction.");
                }
                
                // 2. Update book available copies
                $update_book = "UPDATE books SET available_copies = available_copies + 1 
                               WHERE book_id = ?";
                $stmt = mysqli_prepare($conn, $update_book);
                mysqli_stmt_bind_param($stmt, "i", $book_details['book_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update book availability.");
                }
                
                // 3. Calculate fine if overdue
                if ($days_overdue > 0) {
                    $fine_amount = $days_overdue * 0.50; // $0.50 per day
                    
                    // Insert fine record
                    $insert_fine = "INSERT INTO fines 
                                   (member_id, transaction_id, amount, fine_date, reason, days_overdue) 
                                   VALUES (?, ?, ?, ?, 'overdue', ?)";
                    $stmt = mysqli_prepare($conn, $insert_fine);
                    mysqli_stmt_bind_param($stmt, "iidsi", 
                        $user['member_id'], $transaction_id, $fine_amount, $return_date, $days_overdue);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to record fine.");
                    }
                    
                    // Update member's current fines
                    $update_fine = "UPDATE members 
                                   SET current_fines = current_fines + ? 
                                   WHERE member_id = ?";
                    $stmt = mysqli_prepare($conn, $update_fine);
                    mysqli_stmt_bind_param($stmt, "di", $fine_amount, $user['member_id']);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to update member fines.");
                    }
                    
                    $success = "Book returned successfully! Days overdue: $days_overdue. Fine: $" . number_format($fine_amount, 2);
                } else {
                    $success = "Book returned successfully! Thank you for returning on time.";
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Clear form
                $book_title = '';
                $isbn = '';
                $book_details = null;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $error = "Error returning book: " . $e->getMessage();
            }
        }
    }
}

// Get user's currently borrowed books for reference
$borrowed_books_query = "SELECT b.title, b.isbn, t.borrow_date, t.due_date 
                        FROM transactions t
                        JOIN books b ON t.book_id = b.book_id
                        WHERE t.member_id = ? 
                        AND t.status = 'active' 
                        AND t.transaction_type = 'borrow'
                        ORDER BY t.due_date ASC";
$stmt = mysqli_prepare($conn, $borrowed_books_query);
mysqli_stmt_bind_param($stmt, "i", $user['member_id']);
mysqli_stmt_execute($stmt);
$borrowed_books = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Return Book</title>
    
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
        
        .return-card {
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
        
        .form-icon {
            color: var(--primary-color);
        }
        
        .btn-return {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 30px;
            font-weight: 600;
        }
        
        .btn-return:hover {
            background-color: #1e7b4d;
        }
        
        .btn-clear {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 30px;
            font-weight: 600;
        }
        
        .btn-clear:hover {
            background-color: #5a6268;
        }
        
        .book-list-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .book-list-card .card-header {
            background-color: rgba(26, 95, 180, 0.1);
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .due-soon {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .overdue {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .book-info-card {
            background-color: rgba(38, 162, 105, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .info-value {
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .btn-return, .btn-clear {
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
                <a href="../dashboard.php" class="btn btn-outline-primary btn-sm me-2">
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
                <div class="return-card">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-book-return me-2"></i>Return Borrowed Book</h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Left Column: Return Form -->
                            <div class="col-lg-6">
                                <div class="mb-4">
                                    <h5><i class="fas fa-arrow-circle-right form-icon me-2"></i>Return Book Form</h5>
                                    <p class="text-muted">Enter the book title and ISBN to return it.</p>
                                </div>
                                
                                <form method="POST" action="" id="returnForm">
                                    <input type="hidden" name="return_book" value="1">
                                    
                                    <!-- Book Title -->
                                    <div class="mb-4">
                                        <label for="book_title" class="form-label">
                                            <i class="fas fa-book form-icon me-2"></i>Book Title
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-heading"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="book_title" 
                                                   name="book_title"
                                                   placeholder="Enter book title"
                                                   value="<?php echo htmlspecialchars($book_title); ?>"
                                                   required>
                                        </div>
                                        <small class="text-muted">You can enter partial title (e.g., "Harry Potter")</small>
                                    </div>
                                    
                                    <!-- ISBN -->
                                    <div class="mb-4">
                                        <label for="isbn" class="form-label">
                                            <i class="fas fa-barcode form-icon me-2"></i>ISBN Code
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-hashtag"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="isbn" 
                                                   name="isbn"
                                                   placeholder="Enter ISBN number"
                                                   value="<?php echo htmlspecialchars($isbn); ?>"
                                                   required>
                                        </div>
                                        <small class="text-muted">Enter the 10 or 13 digit ISBN</small>
                                    </div>
                                    
                                    <!-- Display Book Info if found -->
                                    <?php if ($book_details && !$success): ?>
                                    <div class="book-info-card">
                                        <h6 class="mb-3">Book Found - Ready to Return:</h6>
                                        <div class="mb-2">
                                            <span class="info-label">Title:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($book_details['title']); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="info-label">ISBN:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($book_details['isbn']); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="info-label">Borrowed On:</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($book_details['borrow_date'])); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="info-label">Due Date:</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($book_details['due_date'])); ?></span>
                                        </div>
                                        <?php if ($book_details['days_overdue'] > 0): ?>
                                            <div class="mb-2">
                                                <span class="info-label">Days Overdue:</span>
                                                <span class="info-value text-danger fw-bold"><?php echo $book_details['days_overdue']; ?> days</span>
                                            </div>
                                            <div class="mb-2">
                                                <span class="info-label">Estimated Fine:</span>
                                                <span class="info-value text-danger fw-bold">$<?php echo number_format($book_details['days_overdue'] * 0.50, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-clear" onclick="clearForm()">
                                            <i class="fas fa-times me-1"></i>Clear Form
                                        </button>
                                        <button type="submit" class="btn btn-return" id="returnButton">
                                            <i class="fas fa-check-circle me-1"></i>Return Book
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Right Column: Currently Borrowed Books -->
                            <div class="col-lg-6">
                                <div class="book-list-card">
                                    <div class="card-header">
                                        <i class="fas fa-book-open me-2"></i>Your Currently Borrowed Books
                                    </div>
                                    <div class="card-body">
                                        <?php if (mysqli_num_rows($borrowed_books) > 0): ?>
                                            <p class="text-muted mb-3">Use this list to copy book details for returning:</p>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Book Title</th>
                                                            <th>ISBN</th>
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
                                                            <td>
                                                                <div class="fw-medium"><?php echo htmlspecialchars($book['title']); ?></div>
                                                            </td>
                                                            <td>
                                                                <code><?php echo htmlspecialchars($book['isbn']); ?></code>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($book['due_date'])); ?></td>
                                                            <td>
                                                                <?php if ($is_overdue): ?>
                                                                    <span class="badge bg-danger">Overdue</span>
                                                                <?php elseif ($is_due_soon): ?>
                                                                    <span class="badge bg-warning text-dark">Due Soon</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success">On Time</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <div class="alert alert-info mt-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Tip:</strong> Click on a book row to auto-fill the return form.
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                                <h5 class="text-muted">No books currently borrowed</h5>
                                                <p class="text-muted">You have no books to return at this time.</p>
                                            </div>
                                        <?php endif; ?>
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
        // Clear form function
        function clearForm() {
            document.getElementById('book_title').value = '';
            document.getElementById('isbn').value = '';
            document.getElementById('book_title').focus();
        }
        
        // Auto-fill form when clicking on borrowed book row
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                const cells = this.querySelectorAll('td');
                if (cells.length >= 2) {
                    const title = cells[0].querySelector('.fw-medium').textContent.trim();
                    const isbn = cells[1].querySelector('code').textContent.trim();
                    
                    document.getElementById('book_title').value = title;
                    document.getElementById('isbn').value = isbn;
                    
                    // Scroll to form
                    document.getElementById('book_title').focus();
                    document.getElementById('book_title').scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
        
        // Form submission handler
        document.getElementById('returnForm').addEventListener('submit', function(e) {
            const bookTitle = document.getElementById('book_title').value.trim();
            const isbn = document.getElementById('isbn').value.trim();
            const returnButton = document.getElementById('returnButton');
            
            if (!bookTitle || !isbn) {
                e.preventDefault();
                alert('Please fill in both book title and ISBN.');
                return;
            }
            
            // Show loading state
            returnButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            returnButton.disabled = true;
            
            // Final confirmation
            const confirmMessage = "Are you sure you want to return this book?\n\n" +
                                 "Book Title: " + bookTitle + "\n" +
                                 "ISBN: " + isbn + "\n\n" +
                                 "Note: Fines may apply if the book is overdue.";
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                returnButton.innerHTML = '<i class="fas fa-check-circle me-1"></i>Return Book';
                returnButton.disabled = false;
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
        
        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('book_title').focus();
        });
    </script>
</body>
</html>