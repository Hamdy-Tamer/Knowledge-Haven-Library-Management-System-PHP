<?php
// admin/categories/manage.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Get employee details for navigation
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employee = mysqli_fetch_assoc($result);

// Get all categories
$categories_query = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Manage Categories</title>
    
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1a5fb4;
            --secondary-color: #26a269;
            --accent-color: #e5a50a;
            --dark-color: #2d2d2d;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .sidebar {
            background-color: white;
            min-height: calc(100vh - 70px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
        }
        
        .sidebar .nav-link {
            color: var(--dark-color);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(26, 95, 180, 0.1);
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .action-buttons .btn {
            padding: 5px 10px;
            margin: 0 3px;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
            border: none;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
            border: none;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        .btn-add {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 20px;
        }
        
        .btn-add:hover {
            background-color: #1e7b4d;
            color: white;
        }
        
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700;">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto align-items-center">
                    <span class="nav-item me-3 text-white d-none d-md-block">
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo htmlspecialchars($employee['position'] ?? 'Employee'); ?>
                    </span>
                    <a href="../../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="margin-top: 70px;">
        <div class="row">
            <div class="col-lg-2 col-md-3 sidebar">
                <div class="text-center py-4">
                    <img src="<?php echo htmlspecialchars($employee['profile_image'] ?? '../../assets/images/user_image.jpg'); ?>" 
                         alt="Profile" 
                         class="rounded-circle mb-3"
                         style="width: 80px; height: 80px; object-fit: cover; border: 3px solid var(--primary-color);"
                         onerror="this.src='../../assets/images/user_image.jpg'">
                    <h6 class="mb-1"><?php echo htmlspecialchars($employee['username'] ?? 'Employee'); ?></h6>
                    <p class="text-muted mb-3">
                        <small><?php echo htmlspecialchars($employee['employee_code'] ?? 'EMP000'); ?></small>
                    </p>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="../books/manage.php"><i class="fas fa-book me-2"></i>Manage Books</a>
                    <a class="nav-link" href="../authors/manage.php"><i class="fas fa-user-edit me-2"></i>Authors</a>
                    <a class="nav-link active" href="#"><i class="fas fa-tags me-2"></i>Categories</a>
                    <a class="nav-link" href="../locations/manage.php"><i class="fas fa-map-marker-alt me-2"></i>Locations</a>
                    <a class="nav-link" href="../reports/index.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
                </nav>
            </div>
            
            <div class="col-lg-10 col-md-9 main-content">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-tags me-2"></i>Manage Categories</h4>
                            <a href="add.php" class="btn btn-add">
                                <i class="fas fa-plus me-1"></i>Add New Category
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['message'])): ?>
                            <div class="alert alert-<?php echo $_GET['type'] ?? 'success'; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_GET['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($categories_result) > 0): ?>
                                        <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                                            <tr>
                                                <td><?php echo $category['category_id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                                <td><?php echo date('Y-m-d', strtotime($category['created_at'])); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="view.php?id=<?php echo $category['category_id']; ?>" 
                                                           class="btn btn-view btn-sm" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $category['category_id']; ?>" 
                                                           class="btn btn-edit btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="delete.php?id=<?php echo $category['category_id']; ?>" 
                                                           class="btn btn-delete btn-sm" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this category?\n\nCategory: <?php echo addslashes($category['name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                                <h5 class="text-muted">No categories found</h5>
                                                <p class="text-muted">Click "Add New Category" to start organizing your books.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>