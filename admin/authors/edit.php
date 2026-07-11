<?php
// admin/authors/edit.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if author ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Author ID is required!&type=danger");
    exit;
}

$author_id = intval($_GET['id']);

// Get author details for initial form population
$query = "SELECT * FROM authors WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $author_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt); 
$author = mysqli_fetch_assoc($result);

if (!$author) {
    header("Location: manage.php?message=Author not found!&type=danger");
    exit;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    
    $birth_year_sql = empty(trim($_POST['birth_year'])) ? NULL : trim($_POST['birth_year']);
    $death_year_sql = empty(trim($_POST['death_year'])) ? NULL : trim($_POST['death_year']);
    $nationality_sql = empty(trim($_POST['nationality'])) ? NULL : trim($_POST['nationality']);

    if (empty($name)) {
        $message = "Error: Author Name is required!";
        $message_type = "danger";
    } else {
        $check_name_query = "SELECT author_id FROM authors WHERE name = ? AND author_id != ?";
        $stmt = mysqli_prepare($conn, $check_name_query);
        mysqli_stmt_bind_param($stmt, "si", $name, $author_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $message = "Error: Author Name already exists for another author!";
            $message_type = "danger";
        } else {
            $update_query = "UPDATE authors SET 
                            name = ?, 
                            birth_year = ?, 
                            death_year = ?, 
                            nationality = ?
                            WHERE author_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ssssi", 
                $name, $birth_year_sql, $death_year_sql, $nationality_sql, $author_id);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: manage.php?message=Author updated successfully!&type=success");
                exit;
            } else {
                $message = "Error updating author: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
    
    // If update fails or validation error, re-fetch author data for form display
    $query = "SELECT * FROM authors WHERE author_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $author_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $author = mysqli_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Author - Knowledge Haven</title>
    
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
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
                <a href="manage.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-edit me-2"></i>Edit Author
                            </h4>
                            <span class="badge bg-info">Author ID: <?php echo $author_id; ?></span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label for="name" class="form-label required">Author Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($author['name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="birth_year" class="form-label">Birth Year</label>
                                    <input type="number" class="form-control" id="birth_year" name="birth_year" 
                                           min="1000" max="<?php echo date('Y'); ?>" placeholder="e.g., 1980"
                                           value="<?php echo htmlspecialchars($author['birth_year']); ?>">
                                    <div class="form-text">Leave blank if unknown.</div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="death_year" class="form-label">Death Year</label>
                                    <input type="number" class="form-control" id="death_year" name="death_year" 
                                           min="1000" max="<?php echo date('Y'); ?>" placeholder="e.g., 2010"
                                           value="<?php echo htmlspecialchars($author['death_year']); ?>">
                                    <div class="form-text">Leave blank if alive or unknown.</div>
                                </div>
                                
                                <div class="col-md-12 mb-4">
                                    <label for="nationality" class="form-label">Nationality</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" 
                                           placeholder="e.g., British, Nigerian"
                                           value="<?php echo htmlspecialchars($author['nationality']); ?>">
                                    <div class="form-text">Optional field.</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Author
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>