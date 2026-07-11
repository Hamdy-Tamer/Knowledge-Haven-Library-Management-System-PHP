<?php
// admin/books/edit.php
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

// Get book details
$query = "SELECT * FROM books WHERE book_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $book_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$book = mysqli_fetch_assoc($result);

if (!$book) {
    header("Location: manage.php?message=Book not found!&type=danger");
    exit;
}

// Get dropdown data
$authors_query = "SELECT * FROM authors ORDER BY name";
$authors_result = mysqli_query($conn, $authors_query);

$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

$locations_query = "SELECT * FROM locations WHERE is_active = 1 ORDER BY name";
$locations_result = mysqli_query($conn, $locations_query);

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author_id = intval($_POST['author_id']);
    $category_id = intval($_POST['category_id']);
    $location_id = !empty($_POST['location_id']) ? intval($_POST['location_id']) : NULL;
    $isbn = mysqli_real_escape_string($conn, $_POST['isbn']);
    $publication_year = !empty($_POST['publication_year']) ? intval($_POST['publication_year']) : NULL;
    $publisher = !empty($_POST['publisher']) ? mysqli_real_escape_string($conn, $_POST['publisher']) : NULL;
    $total_copies = intval($_POST['total_copies']);
    $available_copies = intval($_POST['available_copies']);
    $description = !empty($_POST['description']) ? mysqli_real_escape_string($conn, $_POST['description']) : NULL;
    
    // Validate available copies
    if ($available_copies > $total_copies) {
        $message = "Error: Available copies cannot be more than total copies!";
        $message_type = "danger";
    } else {
        // Check if ISBN exists for another book
        $check_isbn = "SELECT book_id FROM books WHERE isbn = ? AND book_id != ?";
        $stmt = mysqli_prepare($conn, $check_isbn);
        mysqli_stmt_bind_param($stmt, "si", $isbn, $book_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $message = "Error: ISBN already exists for another book!";
            $message_type = "danger";
        } else {
            // Update book
            $update_query = "UPDATE books SET 
                            title = ?, 
                            author_id = ?, 
                            category_id = ?, 
                            location_id = ?, 
                            isbn = ?, 
                            publication_year = ?, 
                            publisher = ?, 
                            total_copies = ?, 
                            available_copies = ?, 
                            description = ? 
                            WHERE book_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "siissssiisi", 
                $title, $author_id, $category_id, $location_id, $isbn,
                $publication_year, $publisher, $total_copies, $available_copies, 
                $description, $book_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to manage_books.php with success message
                header("Location: manage.php?message=Book updated successfully!&type=success");
                exit;
            } else {
                $message = "Error updating book: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
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
    <title>Edit Book - Knowledge Haven</title>
    
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            border: none;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
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
            <div>
                <a href="view.php?id=<?php echo $book_id; ?>" class="btn btn-outline-light me-2">
                    <i class="fas fa-eye me-1"></i>View
                </a>
                <a href="manage.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i>Back
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
                                <i class="fas fa-edit me-2"></i>Edit Book
                            </h4>
                            <span class="badge bg-info">Book ID: <?php echo $book_id; ?></span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <!-- Book Title -->
                                <div class="col-md-12 mb-4">
                                    <label for="title" class="form-label required">Book Title</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($book['title']); ?>" required>
                                </div>
                                
                                <!-- Author -->
                                <div class="col-md-6 mb-4">
                                    <label for="author_id" class="form-label required">Author</label>
                                    <select class="form-select" id="author_id" name="author_id" required>
                                        <option value="">Select Author</option>
                                        <?php while($author = mysqli_fetch_assoc($authors_result)): ?>
                                            <option value="<?php echo $author['author_id']; ?>"
                                                <?php echo $author['author_id'] == $book['author_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($author['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <!-- Category -->
                                <div class="col-md-6 mb-4">
                                    <label for="category_id" class="form-label required">Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                                            <option value="<?php echo $category['category_id']; ?>"
                                                <?php echo $category['category_id'] == $book['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <!-- Location -->
                                <div class="col-md-6 mb-4">
                                    <label for="location_id" class="form-label">Location</label>
                                    <select class="form-select" id="location_id" name="location_id">
                                        <option value="">Select Location (Optional)</option>
                                        <?php while($location = mysqli_fetch_assoc($locations_result)): ?>
                                            <option value="<?php echo $location['location_id']; ?>"
                                                <?php echo $location['location_id'] == $book['location_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($location['name'] . ' (' . $location['location_code'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <!-- ISBN -->
                                <div class="col-md-6 mb-4">
                                    <label for="isbn" class="form-label required">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" 
                                           value="<?php echo htmlspecialchars($book['isbn']); ?>" required>
                                </div>
                                
                                <!-- Publication Year -->
                                <div class="col-md-4 mb-4">
                                    <label for="publication_year" class="form-label">Publication Year</label>
                                    <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                           min="1000" max="<?php echo date('Y'); ?>" 
                                           value="<?php echo $book['publication_year']; ?>">
                                </div>
                                
                                <!-- Publisher -->
                                <div class="col-md-8 mb-4">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher" 
                                           value="<?php echo htmlspecialchars($book['publisher']); ?>">
                                </div>
                                
                                <!-- Total Copies -->
                                <div class="col-md-6 mb-4">
                                    <label for="total_copies" class="form-label required">Total Copies</label>
                                    <input type="number" class="form-control" id="total_copies" name="total_copies" 
                                           min="1" value="<?php echo $book['total_copies']; ?>" required>
                                </div>
                                
                                <!-- Available Copies -->
                                <div class="col-md-6 mb-4">
                                    <label for="available_copies" class="form-label required">Available Copies</label>
                                    <input type="number" class="form-control" id="available_copies" name="available_copies" 
                                           min="0" value="<?php echo $book['available_copies']; ?>" required>
                                    <div class="form-text">Must be less than or equal to total copies</div>
                                </div>
                                
                                <!-- Description -->
                                <div class="col-12 mb-4">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="4"><?php echo htmlspecialchars($book['description']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="view.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Book
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validate available copies
        document.getElementById('total_copies').addEventListener('input', function() {
            const total = parseInt(this.value) || 0;
            const available = document.getElementById('available_copies');
            if (parseInt(available.value) > total) {
                available.value = total;
            }
            available.max = total;
        });
        
        document.getElementById('available_copies').addEventListener('input', function() {
            const total = parseInt(document.getElementById('total_copies').value) || 0;
            const available = parseInt(this.value) || 0;
            if (available > total) {
                this.value = total;
                alert('Available copies cannot be more than total copies!');
            }
        });
    </script>
</body>
</html>