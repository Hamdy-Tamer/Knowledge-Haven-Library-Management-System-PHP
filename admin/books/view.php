<?php
// admin/books/view.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Book ID is required!&type=danger");
    exit;
}

$book_id = intval($_GET['id']);

// Get book details with joins
$query = "SELECT b.*, a.name as author_name, a.birth_year, a.death_year, a.nationality,
                 c.name as category_name, l.name as location_name, l.location_code, l.floor, l.shelf
          FROM books b
          LEFT JOIN authors a ON b.author_id = a.author_id
          LEFT JOIN categories c ON b.category_id = c.category_id
          LEFT JOIN locations l ON b.location_id = l.location_id
          WHERE b.book_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $book_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$book = mysqli_fetch_assoc($result);

if (!$book) {
    header("Location: manage.php?message=Book not found!&type=danger");
    exit;
}

// Get employee details for sidebar
$user_id = $_SESSION['user_id'];
$emp_query = "SELECT u.*, e.* FROM users u 
              JOIN employees e ON u.user_id = e.user_id 
              WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $emp_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$emp_result = mysqli_stmt_get_result($stmt);
$employee = mysqli_fetch_assoc($emp_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Book - Knowledge Haven</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1a5fb4;
            --secondary-color: #26a269;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }
        
        .info-value {
            color: #212529;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700;">
                        Knowledge <span style="color: #e5a50a;">Haven</span>
                    </span>
                </div>
            </a>
            <div class="d-flex">
                <a href="manage.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
                <a href="edit.php?id=<?php echo $book_id; ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
                <a href="delete.php?id=<?php echo $book_id; ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to delete this book?\n\nBook: <?php echo addslashes($book['title']); ?>')">
                    <i class="fas fa-trash me-1"></i>Delete
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-book-open me-2"></i>Book Details
                            </h4>
                            <span class="status-badge <?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>">
                                <?php echo $book['available_copies'] > 0 ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h2 class="text-primary"><?php echo htmlspecialchars($book['title']); ?></h2>
                                <h5 class="text-muted">by <?php echo htmlspecialchars($book['author_name']); ?></h5>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Basic Information
                                </h5>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Book ID:</span>
                                    <span class="info-value"><?php echo $book['book_id']; ?></span>
                                </div>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">ISBN:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($book['isbn']); ?></span>
                                </div>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Category:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($book['category_name']); ?></span>
                                </div>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Publication Year:</span>
                                    <span class="info-value">
                                        <?php echo $book['publication_year'] ?: '<span class="text-muted">Not specified</span>'; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Publisher:</span>
                                    <span class="info-value">
                                        <?php echo $book['publisher'] ? htmlspecialchars($book['publisher']) : '<span class="text-muted">Not specified</span>'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Inventory Information -->
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-boxes me-2"></i>Inventory Information
                                </h5>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Total Copies:</span>
                                    <span class="info-value"><?php echo $book['total_copies']; ?></span>
                                </div>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Available Copies:</span>
                                    <span class="info-value <?php echo $book['available_copies'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $book['available_copies']; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Borrowed Copies:</span>
                                    <span class="info-value">
                                        <?php echo $book['total_copies'] - $book['available_copies']; ?>
                                    </span>
                                </div>
                                
                                <?php if ($book['location_name']): ?>
                                <div class="mb-3 d-flex">
                                    <span class="info-label">Location:</span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars($book['location_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            Code: <?php echo htmlspecialchars($book['location_code']); ?> | 
                                            Floor: <?php echo $book['floor']; ?> | 
                                            Shelf: <?php echo htmlspecialchars($book['shelf']); ?>
                                        </small>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Author Information -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-user-edit me-2"></i>Author Information
                                </h5>
                                
                                <div class="mb-2">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($book['author_name']); ?>
                                </div>
                                
                                <?php if ($book['birth_year']): ?>
                                <div class="mb-2">
                                    <strong>Born:</strong> <?php echo $book['birth_year']; ?>
                                    <?php if ($book['death_year']): ?>
                                        - <?php echo $book['death_year']; ?>
                                    <?php else: ?>
                                        (Still alive)
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($book['nationality']): ?>
                                <div class="mb-2">
                                    <strong>Nationality:</strong> <?php echo htmlspecialchars($book['nationality']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <?php if ($book['description']): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-align-left me-2"></i>Description
                                </h5>
                                <p class="mb-0" style="line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($book['description'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Timestamps -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <div class="text-muted small">
                                    <i class="fas fa-calendar-plus me-1"></i>
                                    Added: <?php echo date('F j, Y \a\t g:i A', strtotime($book['created_at'])); ?>
                                    <?php if ($book['updated_at'] != $book['created_at']): ?>
                                        | <i class="fas fa-calendar-edit ms-3 me-1"></i>
                                        Last Updated: <?php echo date('F j, Y \a\t g:i A', strtotime($book['updated_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="manage.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Books
                                    </a>
                                    <div>
                                        <a href="edit.php?id=<?php echo $book_id; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit me-1"></i>Edit Book
                                        </a>
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
</body>
</html>