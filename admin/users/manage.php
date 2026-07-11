<?php
// admin/users/manage.php
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

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id_param = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user_status'])) {
    $user_id_update = intval($_POST['user_id']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $update_query = "UPDATE users SET is_active = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ii", $is_active, $user_id_update);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "User status updated successfully!";
    } else {
        $error_msg = "Failed to update user status.";
    }
    mysqli_stmt_close($stmt);
}

// Handle user deletion
if ($action == 'delete' && $user_id_param > 0) {
    // Check if user has any transactions
    $check_transactions = "SELECT COUNT(*) as transaction_count FROM transactions t 
                           JOIN members m ON t.member_id = m.member_id 
                           WHERE m.user_id = ?";
    $stmt = mysqli_prepare($conn, $check_transactions);
    mysqli_stmt_bind_param($stmt, "i", $user_id_param);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $transaction_count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if ($transaction_count > 0) {
        $error_msg = "Cannot delete user with active transactions. Deactivate instead.";
    } else {
        $delete_query = "DELETE FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id_param);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "User deleted successfully!";
        } else {
            $error_msg = "Failed to delete user.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
    $bulk_action = $_POST['bulk_action'];
    
    if (!empty($selected_users)) {
        if ($bulk_action == 'activate') {
            $ids = implode(',', array_map('intval', $selected_users));
            $bulk_query = "UPDATE users SET is_active = 1 WHERE user_id IN ($ids)";
            mysqli_query($conn, $bulk_query);
            $success_msg = "Selected users activated successfully!";
        } elseif ($bulk_action == 'deactivate') {
            $ids = implode(',', array_map('intval', $selected_users));
            $bulk_query = "UPDATE users SET is_active = 0 WHERE user_id IN ($ids)";
            mysqli_query($conn, $bulk_query);
            $success_msg = "Selected users deactivated successfully!";
        } elseif ($bulk_action == 'delete') {
            // Check for transactions before deletion
            $ids = implode(',', array_map('intval', $selected_users));
            $check_bulk = "SELECT u.user_id FROM users u 
                           JOIN members m ON u.user_id = m.user_id 
                           JOIN transactions t ON m.member_id = t.member_id 
                           WHERE u.user_id IN ($ids) 
                           GROUP BY u.user_id 
                           HAVING COUNT(t.transaction_id) > 0";
            $result = mysqli_query($conn, $check_bulk);
            
            if (mysqli_num_rows($result) > 0) {
                $error_msg = "Some users have transactions and cannot be deleted.";
            } else {
                $delete_query = "DELETE FROM users WHERE user_id IN ($ids)";
                mysqli_query($conn, $delete_query);
                $success_msg = "Selected users deleted successfully!";
            }
        }
    } else {
        $error_msg = "No users selected for bulk action.";
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'user_id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Build users query with filters
$users_query = "SELECT u.*, m.member_id, m.member_code, m.total_books_borrowed, m.current_fines,
                (SELECT COUNT(*) FROM transactions t 
                 JOIN members m2 ON t.member_id = m2.member_id 
                 WHERE m2.user_id = u.user_id AND t.status = 'active') as active_loans
                FROM users u 
                LEFT JOIN members m ON u.user_id = m.user_id 
                WHERE role = 'user'";

$params = [];
$types = '';

// Add search filters
if (!empty($search)) {
    $users_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($role_filter)) {
    $users_query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($status_filter !== '') {
    $users_query .= " AND u.is_active = ?";
    $params[] = intval($status_filter);
    $types .= 'i';
}

// Add sorting
$valid_sort_columns = ['user_id', 'username', 'email', 'registration_date', 'total_books_borrowed', 'current_fines'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'user_id';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

$users_query .= " ORDER BY $sort_by $sort_order";

// Prepare and execute the query
if (empty($params)) {
    $users_result = mysqli_query($conn, $users_query);
} else {
    $stmt = mysqli_prepare($conn, $users_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $users_result = mysqli_stmt_get_result($stmt);
}

// Get total counts for stats
$total_users_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
    (SELECT COUNT(*) FROM transactions WHERE status = 'active') as total_active_loans,
    (SELECT SUM(current_fines) FROM members) as total_fines
    FROM users WHERE role = 'user'";
$total_stats_result = mysqli_query($conn, $total_users_query);
$total_stats = mysqli_fetch_assoc($total_stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Manage Users</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
        
        .card-header-secondary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #1e7b4d 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
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
        
        .btn-activate {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-deactivate {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-search {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-search:hover {
            background-color: #0d4a9e;
        }
        
        .btn-clear {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-clear:hover {
            background-color: #5a6268;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-member {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-no-member {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .table th.sortable:hover {
            background-color: #0d4a9e;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(26, 95, 180, 0.05);
        }
        
        .search-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            background: linear-gradient(to right, rgba(26, 95, 180, 0.05), rgba(38, 162, 105, 0.05));
        }
        
        .bulk-actions {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        
        .filter-badge {
            background-color: rgba(26, 95, 180, 0.1);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        
        .filter-badge i {
            font-size: 0.8rem;
            margin-right: 3px;
        }
        
        .checkbox-column {
            width: 40px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin: 2px 0;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .search-card .col-md-3, 
            .search-card .col-md-2 {
                margin-bottom: 10px;
            }
            
            .bulk-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
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
            <!-- Sidebar -->
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
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="../books/manage.php">
                        <i class="fas fa-book me-2"></i>Manage Books
                    </a>
                    <a class="nav-link active" href="#">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a class="nav-link" href="../transactions/manage.php">
                        <i class="fas fa-exchange-alt me-2"></i>Transactions
                    </a>
                    <a class="nav-link" href="../reports/index.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    <a class="nav-link" href="../settings/index.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-users me-2"></i>Manage Library Users</h3>
                </div>
                
                <!-- Messages -->
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo number_format($total_stats['total_users']); ?></h2>
                                        <p class="text-muted mb-0">Total Users</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(26, 95, 180, 0.1); color: var(--primary-color);">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo number_format($total_stats['active_users']); ?></h2>
                                        <p class="text-muted mb-0">Active Users</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo number_format($total_stats['total_active_loans']); ?></h2>
                                        <p class="text-muted mb-0">Active Loans</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                        <i class="fas fa-book"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0">$<?php echo number_format($total_stats['total_fines'], 2); ?></h2>
                                        <p class="text-muted mb-0">Total Fines</p>
                                    </div>
                                    <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search Card -->
                <div class="card search-card mb-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-search me-2"></i>Search & Filter Users</h6>
                        
                        <form method="GET" action="manage.php">
                            <div class="row g-3">
                                <!-- Search by Username/Email/Phone -->
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Search username, email, or phone..."
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                
                                <!-- Status Filter -->
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-filter"></i>
                                        </span>
                                        <select class="form-select" name="status_filter">
                                            <option value="">All Status</option>
                                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Sort By -->
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-sort"></i>
                                        </span>
                                        <select class="form-select" name="sort_by">
                                            <option value="user_id" <?php echo $sort_by == 'user_id' ? 'selected' : ''; ?>>ID</option>
                                            <option value="username" <?php echo $sort_by == 'username' ? 'selected' : ''; ?>>Username</option>
                                            <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Email</option>
                                            <option value="registration_date" <?php echo $sort_by == 'registration_date' ? 'selected' : ''; ?>>Registration Date</option>
                                            <option value="total_books_borrowed" <?php echo $sort_by == 'total_books_borrowed' ? 'selected' : ''; ?>>Books Borrowed</option>
                                        </select>
                                        <select class="form-select" name="sort_order" style="max-width: 100px;">
                                            <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>DESC</option>
                                            <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>ASC</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Search Button -->
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-search w-100">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Active Filters Display -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <?php if ($search || $status_filter !== ''): ?>
                                        <small class="text-muted me-2">Active filters:</small>
                                        <?php if ($search): ?>
                                            <span class="filter-badge">
                                                <i class="fas fa-search"></i> "<?php echo htmlspecialchars(substr($search, 0, 20)); ?>"
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" 
                                                   class="ms-1 text-danger">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($status_filter !== ''): ?>
                                            <span class="filter-badge">
                                                <i class="fas fa-filter"></i> 
                                                <?php echo $status_filter === '1' ? 'Active' : 'Inactive'; ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => ''])); ?>" 
                                                   class="ms-1 text-danger">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <a href="manage.php" class="btn btn-clear btn-sm float-end">
                                            <i class="fas fa-times me-1"></i>Clear All
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <form method="POST" action="" id="bulkForm">
                    <div class="bulk-actions d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">
                                Select All
                            </label>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <select class="form-select" name="bulk_action" style="width: auto;">
                                <option value="activate">Activate Selected</option>
                                <option value="deactivate">Deactivate Selected</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction()">
                                <i class="fas fa-play me-1"></i>Apply
                            </button>
                        </div>
                    </div>
                
                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header card-header-custom">
                            <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Library Users</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $total_users = mysqli_num_rows($users_result);
                            mysqli_data_seek($users_result, 0); // Reset pointer for table display
                            ?>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th class="checkbox-column">
                                                <input type="checkbox" id="selectAllTable">
                                            </th>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Contact</th>
                                            <th>Member Info</th>
                                            <th>Books</th>
                                            <th>Fines</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($total_users > 0): ?>
                                            <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="user-checkbox" name="selected_users[]" value="<?php echo $user['user_id']; ?>">
                                                    </td>
                                                    <td><?php echo $user['user_id']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo htmlspecialchars($user['profile_image'] ?? '../../assets/images/user_image.jpg'); ?>" 
                                                                 alt="Avatar" 
                                                                 class="user-avatar me-2"
                                                                 onerror="this.src='../../assets/images/user_image.jpg'">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($user['gender']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <i class="fas fa-envelope me-1 text-muted"></i>
                                                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                        </div>
                                                        <div class="mt-1">
                                                            <i class="fas fa-phone me-1 text-muted"></i>
                                                            <small><?php echo htmlspecialchars($user['phone_number']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['member_id']): ?>
                                                            <span class="badge badge-member">
                                                                <i class="fas fa-id-card me-1"></i>
                                                                <?php echo htmlspecialchars($user['member_code']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-no-member">
                                                                <i class="fas fa-times me-1"></i>
                                                                Not a member
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="text-center">
                                                            <div class="fw-bold"><?php echo $user['total_books_borrowed'] ?? 0; ?></div>
                                                            <small class="text-muted">
                                                                <?php echo $user['active_loans'] ?? 0; ?> active
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['current_fines'] > 0): ?>
                                                            <span class="text-danger fw-bold">
                                                                $<?php echo number_format($user['current_fines'], 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-success">$0.00</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['is_active']): ?>
                                                            <span class="status-badge badge-active">
                                                                <i class="fas fa-check-circle me-1"></i>Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-badge badge-inactive">
                                                                <i class="fas fa-times-circle me-1"></i>Inactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($user['registration_date'])); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('g:i A', strtotime($user['registration_date'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">                                                        
                                                            <!-- Status Toggle Form -->
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? '0' : '1'; ?>">
                                                                <?php if ($user['is_active']): ?>
                                                                    <button type="submit" name="update_user_status" 
                                                                            class="btn btn-deactivate btn-sm" title="Deactivate"
                                                                            onclick="return confirm('Deactivate this user?')">
                                                                        <i class="fas fa-user-slash"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="submit" name="update_user_status" 
                                                                            class="btn btn-activate btn-sm" title="Activate"
                                                                            onclick="return confirm('Activate this user?')">
                                                                        <i class="fas fa-user-check"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </form>
                                                            
                                                            <a href="manage.php?action=delete&user_id=<?php echo $user['user_id']; ?>" 
                                                               class="btn btn-delete btn-sm" 
                                                               title="Delete"
                                                               onclick="return confirmDelete('<?php echo addslashes($user['username']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <?php if ($search || $status_filter !== ''): ?>
                                                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                        <h5 class="text-muted">No users found</h5>
                                                        <p class="text-muted">Try different search criteria</p>
                                                        <a href="manage.php" class="btn btn-primary btn-sm mt-2">
                                                            <i class="fas fa-times me-1"></i>Clear Search
                                                        </a>
                                                    <?php else: ?>
                                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                        <h5 class="text-muted">No users found</h5>
                                                        <p class="text-muted">Click "Add New User" to add your first user</p>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        document.getElementById('selectAllTable').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            document.getElementById('selectAll').checked = this.checked;
        });
        
        // Confirm delete action
        function confirmDelete(username) {
            return confirm('Are you sure you want to delete user "' + username + '"?\n\nThis action cannot be undone!');
        }
        
        // Confirm bulk action
        function confirmBulkAction() {
            const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
            const action = document.querySelector('select[name="bulk_action"]').value;
            
            if (selectedCount === 0) {
                alert('Please select at least one user.');
                return false;
            }
            
            if (!action) {
                alert('Please select a bulk action.');
                return false;
            }
            
            let message = '';
            switch(action) {
                case 'activate':
                    message = 'Activate ' + selectedCount + ' selected user(s)?';
                    break;
                case 'deactivate':
                    message = 'Deactivate ' + selectedCount + ' selected user(s)?';
                    break;
                case 'delete':
                    message = 'DELETE ' + selectedCount + ' selected user(s)?\n\nThis action cannot be undone!';
                    break;
            }
            
            return confirm(message);
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Clear individual filter when X is clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.filter-badge a')) {
                e.preventDefault();
                const url = e.target.closest('.filter-badge a').getAttribute('href');
                window.location.href = url;
            }
        });
        
        // Sort table headers
        document.addEventListener('DOMContentLoaded', function() {
            const sortableHeaders = document.querySelectorAll('.sortable');
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const currentUrl = new URL(window.location.href);
                    const currentSort = currentUrl.searchParams.get('sort_by');
                    const currentOrder = currentUrl.searchParams.get('sort_order');
                    
                    let newSort = this.dataset.sort;
                    let newOrder = 'ASC';
                    
                    if (currentSort === newSort) {
                        newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                    }
                    
                    currentUrl.searchParams.set('sort_by', newSort);
                    currentUrl.searchParams.set('sort_order', newOrder);
                    window.location.href = currentUrl.toString();
                });
            });
        });
    </script>
</body>
</html>