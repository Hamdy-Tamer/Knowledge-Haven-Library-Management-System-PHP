<?php
// admin/locations/edit.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if location ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Location ID is required!&type=danger");
    exit;
}

$location_id = intval($_GET['id']);

// Get location details for initial form population
$query = "SELECT * FROM locations WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt); 
$location = mysqli_fetch_assoc($result);

if (!$location) {
    header("Location: manage.php?message=Location not found!&type=danger");
    exit;
}

// Get employee details (for context)
$user_id = $_SESSION['user_id'];
$emp_query = "SELECT u.*, e.* FROM users u 
              JOIN employees e ON u.user_id = e.user_id 
              WHERE u.user_id = ?";
$emp_stmt = mysqli_prepare($conn, $emp_query);
mysqli_stmt_bind_param($emp_stmt, "i", $user_id);
mysqli_stmt_execute($emp_stmt);
$emp_result = mysqli_stmt_get_result($emp_stmt);
$employee = mysqli_fetch_assoc($emp_result);

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $location_code = mysqli_real_escape_string($conn, $_POST['location_code']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $floor = intval($_POST['floor']);
    $shelf = mysqli_real_escape_string($conn, $_POST['shelf']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Server-side validation for floor range
    if ($floor < 1 || $floor > 4) {
        $message = "Error: Floor must be between 1 and 4!";
        $message_type = "danger";
    } else {
        // Check if location_code exists for another location
        $check_code_query = "SELECT location_id FROM locations WHERE location_code = ? AND location_id != ?";
        $stmt = mysqli_prepare($conn, $check_code_query);
        mysqli_stmt_bind_param($stmt, "si", $location_code, $location_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $message = "Error: Location Code already exists for another location!";
            $message_type = "danger";
        } else {
            // Update location
            $update_query = "UPDATE locations SET 
                            location_code = ?, 
                            name = ?, 
                            floor = ?, 
                            shelf = ?, 
                            is_active = ?
                            WHERE location_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ssisii", 
                $location_code, $name, $floor, $shelf, $is_active, $location_id);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: manage.php?message=Location updated successfully!&type=success");
                exit;
            } else {
                $message = "Error updating location: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
    
    // If update fails or validation error, re-fetch location data for form display
    $query = "SELECT * FROM locations WHERE location_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $location_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $location = mysqli_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Location - Knowledge Haven</title>
    
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
                                <i class="fas fa-edit me-2"></i>Edit Location
                            </h4>
                            <span class="badge bg-info">Location ID: <?php echo $location_id; ?></span>
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
                                <div class="col-md-6 mb-4">
                                    <label for="location_code" class="form-label required">Location Code</label>
                                    <input type="text" class="form-control" id="location_code" name="location_code" 
                                           value="<?php echo htmlspecialchars($location['location_code']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="name" class="form-label required">Name / Description</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($location['name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="floor" class="form-label required">Floor</label>
                                    <input type="number" class="form-control" id="floor" name="floor" 
                                           min="1" max="4" required value="<?php echo htmlspecialchars($location['floor']); ?>">
                                    <div class="form-text">Must be between 1 and 4</div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="shelf" class="form-label required">Shelf</label>
                                    <input type="text" class="form-control" id="shelf" name="shelf" 
                                           value="<?php echo htmlspecialchars($location['shelf']); ?>" required>
                                </div>
                                
                                <div class="col-12 mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" 
                                               <?php echo $location['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Location is Active (Can be assigned to books)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Location
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