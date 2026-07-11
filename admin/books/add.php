<?php
// admin/books/add.php
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
        // Check if ISBN already exists
        $check_isbn = "SELECT book_id FROM books WHERE isbn = '$isbn'";
        $result = mysqli_query($conn, $check_isbn);
        
        if (mysqli_num_rows($result) > 0) {
            $message = "Error: ISBN already exists!";
            $message_type = "danger";
        } else {
            // Insert book
            $insert_query = "INSERT INTO books (title, author_id, category_id, location_id, isbn, 
                              publication_year, publisher, total_copies, available_copies, description) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "siiisssiis", 
                $title, $author_id, $category_id, $location_id, $isbn,
                $publication_year, $publisher, $total_copies, $available_copies, $description);
            
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to manage_books.php with success message
                header("Location: manage.php?message=Book added successfully!&type=success");
                exit;
            } else {
                $message = "Error adding book: " . mysqli_error($conn);
                $message_type = "danger";
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
    <title>Add New Book - Knowledge Haven</title>
    
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            padding: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            border: none;
            padding: 10px 30px;
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 10px 30px;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 95, 180, 0.25);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-book-medical me-2"></i>Add New Book
                            </h4>
                            <a href="manage.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Books
                            </a>
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
                                           placeholder="Enter book title" required>
                                </div>
                                
                                <!-- Author -->
                                <div class="col-md-6 mb-4">
                                    <label for="author_id" class="form-label required">Author</label>
                                    <select class="form-select" id="author_id" name="author_id" required>
                                        <option value="">Select Author</option>
                                        <?php while($author = mysqli_fetch_assoc($authors_result)): ?>
                                            <option value="<?php echo $author['author_id']; ?>">
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
                                            <option value="<?php echo $category['category_id']; ?>">
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
                                            <option value="<?php echo $location['location_id']; ?>">
                                                <?php echo htmlspecialchars($location['name'] . ' (' . $location['location_code'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <!-- ISBN -->
                                <div class="col-md-6 mb-4">
                                    <label for="isbn" class="form-label required">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" 
                                           placeholder="Enter ISBN number" required>
                                </div>
                                
                                <!-- Publication Year -->
                                <div class="col-md-4 mb-4">
                                    <label for="publication_year" class="form-label">Publication Year</label>
                                    <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                           min="1000" max="<?php echo date('Y'); ?>" 
                                           placeholder="e.g., 2023">
                                </div>
                                
                                <!-- Publisher -->
                                <div class="col-md-8 mb-4">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher" 
                                           placeholder="Enter publisher name">
                                </div>
                                
                                <!-- Total Copies -->
                                <div class="col-md-6 mb-4">
                                    <label for="total_copies" class="form-label required">Total Copies</label>
                                    <input type="number" class="form-control" id="total_copies" name="total_copies" 
                                           min="1" value="1" required>
                                </div>
                                
                                <!-- Available Copies -->
                                <div class="col-md-6 mb-4">
                                    <label for="available_copies" class="form-label required">Available Copies</label>
                                    <input type="number" class="form-control" id="available_copies" name="available_copies" 
                                           min="0" value="1" required>
                                    <div class="form-text">Must be less than or equal to total copies</div>
                                </div>
                                
                                <!-- Description -->
                                <div class="col-12 mb-4">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="4" placeholder="Enter book description"></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Book
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