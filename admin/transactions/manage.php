<?php
// admin/transactions/manage.php
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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$transaction_type = isset($_GET['transaction_type']) ? $_GET['transaction_type'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'transaction_id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Build transactions query with filters
$transactions_query = "SELECT t.*, 
                       u.username, u.email, u.phone_number,
                       m.member_code,
                       b.title as book_title, b.isbn,
                       a.name as author_name,
                       DATEDIFF(CURDATE(), t.due_date) as days_overdue,
                       CASE 
                           WHEN t.status = 'overdue' AND t.due_date < CURDATE() THEN 'overdue'
                           WHEN t.status = 'active' AND t.due_date < CURDATE() THEN 'overdue'
                           ELSE t.status
                       END as calculated_status
                       FROM transactions t
                       JOIN members m ON t.member_id = m.member_id
                       JOIN users u ON m.user_id = u.user_id
                       JOIN books b ON t.book_id = b.book_id
                       JOIN authors a ON b.author_id = a.author_id
                       WHERE 1=1";

$params = [];
$types = '';

// Add search filters
if (!empty($search)) {
    $transactions_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($transaction_type)) {
    $transactions_query .= " AND t.transaction_type = ?";
    $params[] = $transaction_type;
    $types .= 's';
}

if (!empty($status_filter)) {
    $transactions_query .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $transactions_query .= " AND DATE(t.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $transactions_query .= " AND DATE(t.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Add sorting
$valid_sort_columns = ['transaction_id', 'username', 'book_title', 'transaction_type', 'borrow_date', 'due_date', 'status'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'transaction_id';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

$transactions_query .= " ORDER BY $sort_by $sort_order";

// Prepare and execute the query
if (empty($params)) {
    $transactions_result = mysqli_query($conn, $transactions_query);
} else {
    $stmt = mysqli_prepare($conn, $transactions_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $transactions_result = mysqli_stmt_get_result($stmt);
}

// Get statistics for dashboard
$stats_query = "SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status = 'active' AND transaction_type = 'borrow' THEN 1 ELSE 0 END) as active_borrows,
    SUM(CASE WHEN status = 'overdue' OR (status = 'active' AND due_date < CURDATE()) THEN 1 ELSE 0 END) as overdue_books,
    SUM(CASE WHEN transaction_type = 'return' THEN 1 ELSE 0 END) as total_returns,
    SUM(CASE WHEN transaction_type = 'renew' THEN 1 ELSE 0 END) as total_renews
    FROM transactions";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent fines
$recent_fines_query = "SELECT f.*, u.username, b.title 
                       FROM fines f
                       JOIN transactions t ON f.transaction_id = t.transaction_id
                       JOIN members m ON f.member_id = m.member_id
                       JOIN users u ON m.user_id = u.user_id
                       JOIN books b ON t.book_id = b.book_id
                       WHERE f.status = 'pending'
                       ORDER BY f.fine_date DESC
                       LIMIT 5";
$recent_fines_result = mysqli_query($conn, $recent_fines_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Manage Transactions</title>
    
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
        
        .search-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            background: linear-gradient(to right, rgba(26, 95, 180, 0.05), rgba(38, 162, 105, 0.05));
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
        
        .badge-borrow {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-return {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-renew {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-active {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .badge-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-fine-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-fine-paid {
            background-color: #d4edda;
            color: #155724;
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
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(26, 95, 180, 0.05);
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
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        
        .fines-card {
            border-left: 4px solid #dc3545;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .search-card .col-md-2,
            .search-card .col-md-3 {
                margin-bottom: 10px;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .stats-card {
                margin-bottom: 15px;
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
                        <i class="fas fa-exchange-alt me-1"></i>Transactions
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
                    <a class="nav-link" href="../users/manage.php">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a class="nav-link active" href="#">
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
                    <h3><i class="fas fa-exchange-alt me-2"></i>Library Transactions</h3>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(26, 95, 180, 0.1); color: var(--primary-color);">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <h2 class="card-title"><?php echo number_format($stats['total_transactions']); ?></h2>
                                <p class="card-text text-muted">Total Transactions</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <h2 class="card-title"><?php echo number_format($stats['active_borrows']); ?></h2>
                                <p class="card-text text-muted">Active Borrows</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h2 class="card-title"><?php echo number_format($stats['overdue_books']); ?></h2>
                                <p class="card-text text-muted">Overdue Books</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stat-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                    <i class="fas fa-redo"></i>
                                </div>
                                <h2 class="card-title"><?php echo number_format($stats['total_renews']); ?></h2>
                                <p class="card-text text-muted">Total Renews</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search Card -->
                <div class="card search-card mb-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-search me-2"></i>Search & Filter Transactions</h6>
                        
                        <form method="GET" action="manage.php">
                            <div class="row g-3">
                                <!-- Search -->
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               placeholder="Search user, book, or ISBN..."
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                
                                <!-- Transaction Type -->
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-filter"></i>
                                        </span>
                                        <select class="form-select" name="transaction_type">
                                            <option value="">All Types</option>
                                            <option value="borrow" <?php echo $transaction_type == 'borrow' ? 'selected' : ''; ?>>Borrow</option>
                                            <option value="return" <?php echo $transaction_type == 'return' ? 'selected' : ''; ?>>Return</option>
                                            <option value="renew" <?php echo $transaction_type == 'renew' ? 'selected' : ''; ?>>Renew</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                        <select class="form-select" name="status_filter">
                                            <option value="">All Status</option>
                                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
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
                            
                            <!-- Date Range -->
                            <div class="row g-3 mt-3">
                                <div class="col-md-3">
                                    <label class="form-label small">Date From</label>
                                    <input type="date" 
                                           class="form-control" 
                                           name="date_from" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label small">Date To</label>
                                    <input type="date" 
                                           class="form-control" 
                                           name="date_to" 
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label small">Sort By</label>
                                    <select class="form-select" name="sort_by">
                                        <option value="transaction_id" <?php echo $sort_by == 'transaction_id' ? 'selected' : ''; ?>>Transaction ID</option>
                                        <option value="username" <?php echo $sort_by == 'username' ? 'selected' : ''; ?>>User Name</option>
                                        <option value="book_title" <?php echo $sort_by == 'book_title' ? 'selected' : ''; ?>>Book Title</option>
                                        <option value="borrow_date" <?php echo $sort_by == 'borrow_date' ? 'selected' : ''; ?>>Borrow Date</option>
                                        <option value="due_date" <?php echo $sort_by == 'due_date' ? 'selected' : ''; ?>>Due Date</option>
                                        <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Status</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label small">Order</label>
                                    <select class="form-select" name="sort_order">
                                        <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Active Filters Display -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <?php if ($search || $transaction_type || $status_filter || $date_from || $date_to): ?>
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
                                        
                                        <?php if ($transaction_type): ?>
                                            <span class="filter-badge">
                                                <i class="fas fa-filter"></i> Type: <?php echo ucfirst($transaction_type); ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['transaction_type' => ''])); ?>" 
                                                   class="ms-1 text-danger">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($status_filter): ?>
                                            <span class="filter-badge">
                                                <i class="fas fa-info-circle"></i> Status: <?php echo ucfirst($status_filter); ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['status_filter' => ''])); ?>" 
                                                   class="ms-1 text-danger">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($date_from || $date_to): ?>
                                            <span class="filter-badge">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo $date_from ? date('M d', strtotime($date_from)) : 'Any'; ?> 
                                                to 
                                                <?php echo $date_to ? date('M d, Y', strtotime($date_to)) : 'Any'; ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => '', 'date_to' => ''])); ?>" 
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
                
                <!-- Pending Fines Card -->
                <?php if (mysqli_num_rows($recent_fines_result) > 0): ?>
                <div class="card fines-card mb-4">
                    <div class="card-header" style="background-color: #f8d7da; color: #721c24; border-bottom: 1px solid #f5c6cb;">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pending Fines Requiring Attention</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Book</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                        <th>Days Overdue</th>
                                        <th>Fine Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($fine = mysqli_fetch_assoc($recent_fines_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fine['username']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($fine['title'], 0, 30)); ?><?php echo strlen($fine['title']) > 30 ? '...' : ''; ?></td>
                                        <td><span class="fw-bold text-danger">$<?php echo number_format($fine['amount'], 2); ?></span></td>
                                        <td><span class="badge badge-fine-pending"><?php echo ucfirst($fine['reason']); ?></span></td>
                                        <td><?php echo $fine['days_overdue']; ?> days</td>
                                        <td><?php echo date('M d, Y', strtotime($fine['fine_date'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-header card-header-custom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Transaction History</h5>
                            <span class="badge bg-light text-dark">
                                <?php 
                                $total_transactions = mysqli_num_rows($transactions_result);
                                mysqli_data_seek($transactions_result, 0);
                                echo $total_transactions . ' transaction' . ($total_transactions != 1 ? 's' : '');
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Book Details</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($total_transactions > 0): ?>
                                        <?php while($transaction = mysqli_fetch_assoc($transactions_result)): 
                                            $is_overdue = $transaction['calculated_status'] == 'overdue';
                                            $actual_status = $is_overdue ? 'overdue' : $transaction['status'];
                                        ?>
                                            <tr class="<?php echo $is_overdue ? 'table-warning' : ''; ?>">
                                                <td>#<?php echo $transaction['transaction_id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2">
                                                            <i class="fas fa-user-circle fa-lg text-muted"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($transaction['username']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($transaction['member_code']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars(substr($transaction['book_title'], 0, 40)); ?><?php echo strlen($transaction['book_title']) > 40 ? '...' : ''; ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-edit me-1"></i><?php echo htmlspecialchars($transaction['author_name']); ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-barcode me-1"></i><?php echo htmlspecialchars($transaction['isbn']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['transaction_type'] == 'borrow'): ?>
                                                        <span class="status-badge badge-borrow">
                                                            <i class="fas fa-bookmark me-1"></i>Borrow
                                                        </span>
                                                    <?php elseif ($transaction['transaction_type'] == 'return'): ?>
                                                        <span class="status-badge badge-return">
                                                            <i class="fas fa-book me-1"></i>Return
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-renew">
                                                            <i class="fas fa-redo me-1"></i>Renew
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['transaction_type'] == 'borrow'): ?>
                                                        <div>
                                                            <small class="text-muted">Borrowed:</small><br>
                                                            <?php echo date('M d, Y', strtotime($transaction['borrow_date'])); ?>
                                                        </div>
                                                        <div class="mt-1">
                                                            <small class="text-muted">Due:</small><br>
                                                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                                <?php echo date('M d, Y', strtotime($transaction['due_date'])); ?>
                                                            </span>
                                                            <?php if ($is_overdue && $transaction['days_overdue'] > 0): ?>
                                                                <br>
                                                                <small class="text-danger">
                                                                    <i class="fas fa-clock me-1"></i><?php echo $transaction['days_overdue']; ?> days overdue
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif ($transaction['transaction_type'] == 'return'): ?>
                                                        <div>
                                                            <small class="text-muted">Returned:</small><br>
                                                            <?php echo date('M d, Y', strtotime($transaction['return_date'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div>
                                                            <small class="text-muted">Renewed:</small><br>
                                                            <?php echo date('M d, Y', strtotime($transaction['borrow_date'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($actual_status == 'active'): ?>
                                                        <span class="status-badge badge-active">
                                                            <i class="fas fa-clock me-1"></i>Active
                                                        </span>
                                                    <?php elseif ($actual_status == 'completed'): ?>
                                                        <span class="status-badge badge-completed">
                                                            <i class="fas fa-check-circle me-1"></i>Completed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-overdue">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($transaction['renew_count'] > 0): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-redo me-1"></i>Renewed <?php echo $transaction['renew_count']; ?> time<?php echo $transaction['renew_count'] > 1 ? 's' : ''; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <?php if ($search || $transaction_type || $status_filter || $date_from || $date_to): ?>
                                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No transactions found</h5>
                                                    <p class="text-muted">Try different search criteria</p>
                                                    <a href="manage.php" class="btn btn-primary btn-sm mt-2">
                                                        <i class="fas fa-times me-1"></i>Clear Search
                                                    </a>
                                                <?php else: ?>
                                                    <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No transactions found</h5>
                                                    <p class="text-muted">All transactions will appear here</p>
                                                <?php endif; ?>
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

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // View transaction details
        function viewTransactionDetails(transactionId) {
            alert('Transaction Details for ID: ' + transactionId + '\n\nIn a complete system, this would show:\n• Full transaction details\n• Fine calculations\n• Renewal history\n• User contact information\n• Book availability status');
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Auto-hide alerts
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
        
        // Highlight overdue rows
        document.addEventListener('DOMContentLoaded', function() {
            const overdueRows = document.querySelectorAll('.table-warning');
            overdueRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(255, 193, 7, 0.1)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
        
        // Set default date range to last 30 days if not set
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            if (!dateFrom.value && !dateTo.value) {
                const today = new Date();
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(today.getDate() - 30);
                
                dateFrom.valueAsDate = thirtyDaysAgo;
                dateTo.valueAsDate = today;
            }
        });
    </script>
</body>
</html>