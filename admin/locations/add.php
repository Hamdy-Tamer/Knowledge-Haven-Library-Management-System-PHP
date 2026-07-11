<?php
// admin/locations/add.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Get employee details (for sidebar/context)
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employee = mysqli_fetch_assoc($result);

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $location_code = mysqli_real_escape_string($conn, $_POST['location_code']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $floor = intval($_POST['floor']);
    $shelf = mysqli_real_escape_string($conn, $_POST['shelf']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate floor range
    if ($floor < 1 || $floor > 4) {
        $message = "Error: Floor must be between 1 and 4!";
        $message_type = "danger";
    } else {
        // Check if location_code already exists
        $check_code_query = "SELECT location_id FROM locations WHERE location_code = ?";
        $stmt = mysqli_prepare($conn, $check_code_query);
        mysqli_stmt_bind_param($stmt, "s", $location_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $message = "Error: Location Code already exists!";
            $message_type = "danger";
        } else {
            // Insert location
            $insert_query = "INSERT INTO locations (location_code, name, floor, shelf, is_active) 
                             VALUES (?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssisi", 
                $location_code, $name, $floor, $shelf, $is_active);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: manage.php?message=Location added successfully!&type=success");
                exit;
            } else {
                $message = "Error adding location: " . mysqli_error($conn);
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
    <title>Add New Location - Knowledge Haven</title>
    
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
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 95, 180, 0.25);
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
                                <i class="fas fa-map-pin me-2"></i>Add New Location
                            </h4>
                            <a href="manage.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Locations
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
                                <div class="col-md-6 mb-4">
                                    <label for="location_code" class="form-label required">Location Code</label>
                                    <input type="text" class="form-control" id="location_code" name="location_code" 
                                           placeholder="e.g., A-345, FICT-SH01" required 
                                           value="<?php echo htmlspecialchars($_POST['location_code'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="name" class="form-label required">Name / Description</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           placeholder="e.g., Main Shelf, Reference Section" required
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="floor" class="form-label required">Floor</label>
                                    <input type="number" class="form-control" id="floor" name="floor" 
                                           min="1" max="4" required value="<?php echo htmlspecialchars($_POST['floor'] ?? '1'); ?>">
                                    <div class="form-text">Must be between 1 and 4</div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="shelf" class="form-label required">Shelf</label>
                                    <input type="text" class="form-control" id="shelf" name="shelf" 
                                           placeholder="e.g., Aisle 5, Shelf B" required
                                           value="<?php echo htmlspecialchars($_POST['shelf'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12 mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" checked>
                                        <label class="form-check-label" for="is_active">Location is Active (Can be assigned to books)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Location
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