<?php
// admin/authors/view.php
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

// Get author details
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

// Get the count of books assigned to this author
$book_count_query = "SELECT COUNT(*) as book_count FROM books WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $book_count_query);
mysqli_stmt_bind_param($stmt, "i", $author_id);
mysqli_stmt_execute($stmt);
$book_count_result = mysqli_stmt_get_result($stmt);
$book_count_row = mysqli_fetch_assoc($book_count_result);
$book_count = $book_count_row['book_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Author - Knowledge Haven</title>
    
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
        
        .detail-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
            border: none;
        }
        
        .btn-back {
            background-color: #6c757d;
            border: none;
            color: white;
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
                        <h4 class="mb-0">
                            <i class="fas fa-user-tie me-2"></i>Author Details
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <h2 class="mb-4"><?php echo htmlspecialchars($author['name']); ?></h2>
                        
                        <div class="row detail-row">
                            <div class="col-4 detail-label">Author ID:</div>
                            <div class="col-8"><?php echo $author['author_id']; ?></div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-4 detail-label">Birth Year:</div>
                            <div class="col-8"><?php echo htmlspecialchars($author['birth_year'] ?: 'N/A'); ?></div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-4 detail-label">Death Year:</div>
                            <div class="col-8"><?php echo htmlspecialchars($author['death_year'] ?: 'N/A'); ?></div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-4 detail-label">Nationality:</div>
                            <div class="col-8"><?php echo htmlspecialchars($author['nationality'] ?: 'N/A'); ?></div>
                        </div>

                        <div class="row detail-row">
                            <div class="col-4 detail-label">Books Published:</div>
                            <div class="col-8"><?php echo $book_count; ?></div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-4 detail-label">Created At:</div>
                            <div class="col-8"><?php echo $author['created_at']; ?></div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-4 detail-label">Last Updated:</div>
                            <div class="col-8"><?php echo $author['updated_at']; ?></div>
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="manage.php" class="btn btn-back">
                                <i class="fas fa-arrow-left me-1"></i>Back to List
                            </a>
                            <a href="edit.php?id=<?php echo $author['author_id']; ?>" class="btn btn-edit">
                                <i class="fas fa-edit me-1"></i>Edit Author
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>