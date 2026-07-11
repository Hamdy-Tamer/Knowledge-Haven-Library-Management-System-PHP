<?php
// admin/authors/add.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
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
        $check_name_query = "SELECT author_id FROM authors WHERE name = ?";
        $stmt = mysqli_prepare($conn, $check_name_query);
        mysqli_stmt_bind_param($stmt, "s", $name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $message = "Error: Author Name already exists!";
            $message_type = "danger";
        } else {
            $insert_query = "INSERT INTO authors (name, birth_year, death_year, nationality) 
                             VALUES (?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssss", 
                $name, $birth_year_sql, $death_year_sql, $nationality_sql);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: manage.php?message=Author added successfully!&type=success");
                exit;
            } else {
                $message = "Error adding author: " . mysqli_error($conn);
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
    <title>Add New Author - Knowledge Haven</title>
    
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
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-user-plus me-2"></i>Add New Author
                            </h4>
                            <a href="manage.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Authors
                            </a>
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
                                           placeholder="e.g., Jane Austen" required 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="birth_year" class="form-label">Birth Year</label>
                                    <input type="number" class="form-control" id="birth_year" name="birth_year" 
                                           min="1000" max="<?php echo date('Y'); ?>" placeholder="e.g., 1980"
                                           value="<?php echo htmlspecialchars($_POST['birth_year'] ?? ''); ?>">
                                    <div class="form-text">Leave blank if unknown.</div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="death_year" class="form-label">Death Year</label>
                                    <input type="number" class="form-control" id="death_year" name="death_year" 
                                           min="1000" max="<?php echo date('Y'); ?>" placeholder="e.g., 2010"
                                           value="<?php echo htmlspecialchars($_POST['death_year'] ?? ''); ?>">
                                    <div class="form-text">Leave blank if alive or unknown.</div>
                                </div>
                                
                                <div class="col-md-12 mb-4">
                                    <label for="nationality" class="form-label">Nationality</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" 
                                           placeholder="e.g., British, Nigerian"
                                           value="<?php echo htmlspecialchars($_POST['nationality'] ?? ''); ?>">
                                    <div class="form-text">Optional field.</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Author
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