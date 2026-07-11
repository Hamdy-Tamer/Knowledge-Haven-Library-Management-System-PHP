<?php
// admin/reports/index.php
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

// Get date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Get library statistics for the date range
function getLibraryStats($conn, $start_date, $end_date) {
    $stats = [];
    
    // Total books
    $query = "SELECT COUNT(*) as total_books FROM books";
    $result = mysqli_query($conn, $query);
    $stats['total_books'] = mysqli_fetch_assoc($result)['total_books'];
    
    // Total users
    $query = "SELECT COUNT(*) as total_users FROM users WHERE role = 'user'";
    $result = mysqli_query($conn, $query);
    $stats['total_users'] = mysqli_fetch_assoc($result)['total_users'];
    
    // Total transactions in date range
    $query = "SELECT COUNT(*) as total_transactions FROM transactions 
              WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $result = mysqli_query($conn, $query);
    $stats['total_transactions'] = mysqli_fetch_assoc($result)['total_transactions'];
    
    // Total borrowed books currently
    $query = "SELECT COUNT(*) as active_loans FROM transactions 
              WHERE status = 'active' AND transaction_type = 'borrow'";
    $result = mysqli_query($conn, $query);
    $stats['active_loans'] = mysqli_fetch_assoc($result)['active_loans'];
    
    // Total overdue books
    $query = "SELECT COUNT(*) as overdue_books FROM transactions 
              WHERE status = 'overdue' AND due_date < CURDATE()";
    $result = mysqli_query($conn, $query);
    $stats['overdue_books'] = mysqli_fetch_assoc($result)['overdue_books'];
    
    // Total fines collected
    $query = "SELECT COALESCE(SUM(amount), 0) as total_fines FROM fines 
              WHERE status = 'paid' AND DATE(fine_date) BETWEEN '$start_date' AND '$end_date'";
    $result = mysqli_query($conn, $query);
    $stats['total_fines'] = mysqli_fetch_assoc($result)['total_fines'];
    
    // Total pending fines
    $query = "SELECT COALESCE(SUM(amount), 0) as pending_fines FROM fines WHERE status = 'pending'";
    $result = mysqli_query($conn, $query);
    $stats['pending_fines'] = mysqli_fetch_assoc($result)['pending_fines'];
    
    // Most borrowed books
    $query = "SELECT b.title, a.name as author, COUNT(t.book_id) as borrow_count
              FROM transactions t
              JOIN books b ON t.book_id = b.book_id
              JOIN authors a ON b.author_id = a.author_id
              WHERE t.transaction_type = 'borrow' 
              AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
              GROUP BY t.book_id
              ORDER BY borrow_count DESC
              LIMIT 10";
    $result = mysqli_query($conn, $query);
    $stats['popular_books'] = $result;
    
    // Most active users
    $query = "SELECT u.username, COUNT(t.member_id) as borrow_count
              FROM transactions t
              JOIN members m ON t.member_id = m.member_id
              JOIN users u ON m.user_id = u.user_id
              WHERE t.transaction_type = 'borrow' 
              AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
              GROUP BY t.member_id
              ORDER BY borrow_count DESC
              LIMIT 10";
    $result = mysqli_query($conn, $query);
    $stats['active_users'] = $result;
    
    // Transactions by day
    $query = "SELECT DATE(created_at) as date, COUNT(*) as count
              FROM transactions
              WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
              GROUP BY DATE(created_at)
              ORDER BY date";
    $result = mysqli_query($conn, $query);
    $stats['daily_transactions'] = $result;
    
    // Category distribution
    $query = "SELECT c.name as category, COUNT(b.book_id) as book_count
              FROM books b
              JOIN categories c ON b.category_id = c.category_id
              GROUP BY b.category_id
              ORDER BY book_count DESC";
    $result = mysqli_query($conn, $query);
    $stats['category_distribution'] = $result;
    
    // Books by availability
    $query = "SELECT 
                SUM(CASE WHEN available_copies > 0 THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN available_copies = 0 THEN 1 ELSE 0 END) as unavailable
              FROM books";
    $result = mysqli_query($conn, $query);
    $stats['availability'] = mysqli_fetch_assoc($result);
    
    return $stats;
}

// Get all reports
$stats = getLibraryStats($conn, $start_date, $end_date);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Reports & Analytics</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../../assets/images/logo-library.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .card-header-accent {
            background: linear-gradient(135deg, var(--accent-color) 0%, #d19408 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .report-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .report-table {
            font-size: 0.9rem;
        }
        
        .report-table th {
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        
        .date-range-card {
            background: linear-gradient(to right, rgba(26, 95, 180, 0.05), rgba(38, 162, 105, 0.05));
            border: 1px solid rgba(26, 95, 180, 0.1);
        }
        
        .btn-export {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }
        
        .btn-export:hover {
            background-color: #1e7b4d;
            color: white;
        }
        
        .btn-refresh {
            background-color: var(--accent-color);
            color: white;
            border: none;
        }
        
        .btn-refresh:hover {
            background-color: #d19408;
            color: white;
        }
        
        .nav-tabs .nav-link {
            color: var(--dark-color);
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
        }
        
        .badge-stat {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .trend-up {
            color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .trend-down {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .trend-neutral {
            color: #6c757d;
            background-color: rgba(108, 117, 125, 0.1);
        }
        
        .report-section {
            margin-bottom: 30px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .report-table {
                font-size: 0.85rem;
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
                        <i class="fas fa-chart-bar me-1"></i>Reports Dashboard
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
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    <a class="nav-link" href="../settings/index.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 main-content">
                <!-- Date Range Filter -->
                <div class="card date-range-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-alt me-2"></i>Report Period</h5>
                        <form method="GET" action="index.php" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-select" name="report_type">
                                    <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                                    <option value="books" <?php echo $report_type == 'books' ? 'selected' : ''; ?>>Books Analysis</option>
                                    <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Analysis</option>
                                    <option value="financial" <?php echo $report_type == 'financial' ? 'selected' : ''; ?>>Financial</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sync-alt me-1"></i>Update Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Report Tabs -->
                <ul class="nav nav-tabs mb-4" id="reportTabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'overview' ? 'active' : ''; ?>" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['report_type' => 'overview'])); ?>">
                            <i class="fas fa-home me-1"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'books' ? 'active' : ''; ?>" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['report_type' => 'books'])); ?>">
                            <i class="fas fa-book me-1"></i>Books Analysis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'users' ? 'active' : ''; ?>" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['report_type' => 'users'])); ?>">
                            <i class="fas fa-users me-1"></i>User Analysis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'financial' ? 'active' : ''; ?>" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['report_type' => 'financial'])); ?>">
                            <i class="fas fa-money-bill-wave me-1"></i>Financial
                        </a>
                    </li>
                </ul>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-end mb-4">
                    <button class="btn btn-refresh" onclick="window.location.reload()">
                        <i class="fas fa-redo me-1"></i>Refresh Data
                    </button>
                </div>
                
                <?php if ($report_type == 'overview'): ?>
                    <!-- OVERVIEW REPORT -->
                    <div class="report-section">
                        <h4 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Library Overview</h4>
                        
                        <!-- Key Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(26, 95, 180, 0.1); color: var(--primary-color);">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo number_format($stats['total_books']); ?></h2>
                                        <p class="card-text text-muted">Total Books</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(38, 162, 105, 0.1); color: var(--secondary-color);">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo number_format($stats['total_users']); ?></h2>
                                        <p class="card-text text-muted">Total Users</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(229, 165, 10, 0.1); color: var(--accent-color);">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo number_format($stats['total_transactions']); ?></h2>
                                        <p class="card-text text-muted">Transactions</p>
                                        <small class="badge badge-stat trend-up">
                                            <i class="fas fa-arrow-up me-1"></i>
                                            Period: <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo number_format($stats['overdue_books']); ?></h2>
                                        <p class="card-text text-muted">Overdue Books</p>
                                        <?php if ($stats['overdue_books'] > 0): ?>
                                            <small class="badge badge-stat trend-down">
                                                <i class="fas fa-exclamation-circle me-1"></i>Needs Attention
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Charts Row -->
                        <div class="row mb-4">
                            <div class="col-lg-6 mb-4">
                                <div class="card report-card">
                                    <div class="card-header card-header-custom">
                                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Transactions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="dailyTransactionsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6 mb-4">
                                <div class="card report-card">
                                    <div class="card-header card-header-secondary">
                                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Books by Category</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="categoryDistributionChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tables Row -->
                        <div class="row">
                            <div class="col-lg-6 mb-4">
                                <div class="card report-card">
                                    <div class="card-header card-header-custom">
                                        <h5 class="mb-0"><i class="fas fa-fire me-2"></i>Most Popular Books</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table report-table">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Book Title</th>
                                                        <th>Author</th>
                                                        <th>Borrow Count</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (mysqli_num_rows($stats['popular_books']) > 0): 
                                                        $counter = 1;
                                                        while($book = mysqli_fetch_assoc($stats['popular_books'])): ?>
                                                            <tr>
                                                                <td><?php echo $counter++; ?></td>
                                                                <td><?php echo htmlspecialchars(substr($book['title'], 0, 30)); ?><?php echo strlen($book['title']) > 30 ? '...' : ''; ?></td>
                                                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                                <td><span class="badge bg-primary"><?php echo $book['borrow_count']; ?></span></td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center py-3 text-muted">
                                                                No borrowing data available for this period
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6 mb-4">
                                <div class="card report-card">
                                    <div class="card-header card-header-secondary">
                                        <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Most Active Users</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table report-table">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Username</th>
                                                        <th>Borrow Count</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (mysqli_num_rows($stats['active_users']) > 0): 
                                                        $counter = 1;
                                                        while($user = mysqli_fetch_assoc($stats['active_users'])): ?>
                                                            <tr>
                                                                <td><?php echo $counter++; ?></td>
                                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                <td><span class="badge bg-success"><?php echo $user['borrow_count']; ?></span></td>
                                                                <td>
                                                                    <span class="badge bg-info">Active Reader</span>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center py-3 text-muted">
                                                                No user activity data available for this period
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
                    
                <?php elseif ($report_type == 'books'): ?>
                    <!-- BOOKS ANALYSIS REPORT -->
                    <div class="report-section">
                        <h4 class="mb-4"><i class="fas fa-book me-2"></i>Books Analysis</h4>
                        
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo $stats['availability']['available']; ?></h2>
                                        <p class="card-text text-muted">Available Books</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo $stats['availability']['unavailable']; ?></h2>
                                        <p class="card-text text-muted">Unavailable Books</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h2 class="card-title"><?php echo $stats['active_loans']; ?></h2>
                                        <p class="card-text text-muted">Currently Borrowed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Distribution Table -->
                        <div class="card report-card mb-4">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Category Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table report-table">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Number of Books</th>
                                                <th>Percentage</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_books = $stats['total_books'];
                                            if (mysqli_num_rows($stats['category_distribution']) > 0): 
                                                while($category = mysqli_fetch_assoc($stats['category_distribution'])): 
                                                    $percentage = $total_books > 0 ? round(($category['book_count'] / $total_books) * 100, 1) : 0;
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($category['category']); ?></td>
                                                        <td><?php echo $category['book_count']; ?></td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar" role="progressbar" 
                                                                     style="width: <?php echo $percentage; ?>%; background-color: var(--primary-color);" 
                                                                     aria-valuenow="<?php echo $percentage; ?>" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                    <?php echo $percentage; ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($percentage > 30): ?>
                                                                <span class="badge bg-primary">Major Category</span>
                                                            <?php elseif ($percentage > 10): ?>
                                                                <span class="badge bg-success">Medium</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Small</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">
                                                        No category data available
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                <?php elseif ($report_type == 'users'): ?>
                    <!-- USER ANALYSIS REPORT -->
                    <div class="report-section">
                        <h4 class="mb-4"><i class="fas fa-users me-2"></i>User Analysis</h4>
                        
                        <!-- Active Users Table -->
                        <div class="card report-card mb-4">
                            <div class="card-header card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Top Active Users</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table report-table">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Username</th>
                                                <th>Borrow Count</th>
                                                <th>Last Activity</th>
                                                <th>Reading Level</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($stats['active_users']) > 0): 
                                                $rank = 1;
                                                while($user = mysqli_fetch_assoc($stats['active_users'])): 
                                                    $level = $user['borrow_count'] > 20 ? 'Heavy Reader' : ($user['borrow_count'] > 10 ? 'Regular' : 'Casual');
                                                    $badge_color = $user['borrow_count'] > 20 ? 'bg-warning' : ($user['borrow_count'] > 10 ? 'bg-success' : 'bg-info');
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($rank == 1): ?>
                                                                <span class="badge bg-danger">#1</span>
                                                            <?php elseif ($rank == 2): ?>
                                                                <span class="badge bg-secondary">#2</span>
                                                            <?php elseif ($rank == 3): ?>
                                                                <span class="badge bg-warning text-dark">#3</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-light text-dark">#<?php echo $rank; ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                        <td><span class="badge bg-primary"><?php echo $user['borrow_count']; ?></span></td>
                                                        <td><?php echo date('M j, Y', strtotime($end_date)); ?></td>
                                                        <td><span class="badge <?php echo $badge_color; ?>"><?php echo $level; ?></span></td>
                                                    </tr>
                                                <?php 
                                                $rank++;
                                                endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-3 text-muted">
                                                        No user activity data available for this period
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                <?php elseif ($report_type == 'financial'): ?>
                    <!-- FINANCIAL REPORT -->
                    <div class="report-section">
                        <h4 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i>Financial Analysis</h4>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;">
                                            <i class="fas fa-money-check-alt"></i>
                                        </div>
                                        <h2 class="card-title">$<?php echo number_format($stats['total_fines'], 2); ?></h2>
                                        <p class="card-text text-muted">Fines Collected</p>
                                        <small class="badge badge-stat trend-up">
                                            <i class="fas fa-calendar me-1"></i>
                                            Period: <?php echo date('M Y', strtotime($start_date)); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <div class="stat-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h2 class="card-title">$<?php echo number_format($stats['pending_fines'], 2); ?></h2>
                                        <p class="card-text text-muted">Pending Fines</p>
                                        <?php if ($stats['pending_fines'] > 0): ?>
                                            <small class="badge badge-stat trend-down">
                                                <i class="fas fa-exclamation-circle me-1"></i>Requires Collection
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fines Breakdown -->
                        <div class="card report-card mb-4">
                            <div class="card-header card-header-accent">
                                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Fines Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-info-circle me-2"></i>Collection Status</h6>
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Collected</span>
                                                <span class="fw-bold text-success">$<?php echo number_format($stats['total_fines'], 2); ?></span>
                                            </div>
                                            <div class="progress mb-3" style="height: 10px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo ($stats['total_fines'] + $stats['pending_fines']) > 0 ? ($stats['total_fines'] / ($stats['total_fines'] + $stats['pending_fines']) * 100) : 0; ?>%">
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Pending</span>
                                                <span class="fw-bold text-warning">$<?php echo number_format($stats['pending_fines'], 2); ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-warning" role="progressbar" 
                                                     style="width: <?php echo ($stats['total_fines'] + $stats['pending_fines']) > 0 ? ($stats['pending_fines'] / ($stats['total_fines'] + $stats['pending_fines']) * 100) : 0; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-chart-bar me-2"></i>Financial Health</h6>
                                        <div class="mt-3">
                                            <?php 
                                            $collection_rate = ($stats['total_fines'] + $stats['pending_fines']) > 0 ? 
                                                ($stats['total_fines'] / ($stats['total_fines'] + $stats['pending_fines']) * 100) : 0;
                                            ?>
                                            <div class="text-center">
                                                <div class="display-4 fw-bold <?php echo $collection_rate >= 80 ? 'text-success' : ($collection_rate >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                                    <?php echo number_format($collection_rate, 1); ?>%
                                                </div>
                                                <p class="text-muted">Collection Rate</p>
                                            </div>
                                            
                                            <?php if ($collection_rate < 50): ?>
                                                <div class="alert alert-warning mt-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    Low collection rate. Consider following up on pending fines.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                <?php endif; ?>
                
                <!-- Report Footer -->
                <div class="card mt-4">
                    <div class="card-body text-center text-muted">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Report generated on <?php echo date('F j, Y \a\t g:i A'); ?> | 
                            Period: <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>       
        // Initialize charts when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type == 'overview'): ?>
                // Daily Transactions Chart
                <?php
                $dates = [];
                $counts = [];
                if (mysqli_num_rows($stats['daily_transactions']) > 0) {
                    while($row = mysqli_fetch_assoc($stats['daily_transactions'])) {
                        $dates[] = date('M j', strtotime($row['date']));
                        $counts[] = $row['count'];
                    }
                }
                mysqli_data_seek($stats['daily_transactions'], 0);
                ?>
                
                const dailyCtx = document.getElementById('dailyTransactionsChart').getContext('2d');
                new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($dates); ?>,
                        datasets: [{
                            label: 'Daily Transactions',
                            data: <?php echo json_encode($counts); ?>,
                            backgroundColor: 'rgba(26, 95, 180, 0.1)',
                            borderColor: 'rgba(26, 95, 180, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
                
                // Category Distribution Chart
                <?php
                $categories = [];
                $category_counts = [];
                if (mysqli_num_rows($stats['category_distribution']) > 0) {
                    while($row = mysqli_fetch_assoc($stats['category_distribution'])) {
                        $categories[] = $row['category'];
                        $category_counts[] = $row['book_count'];
                    }
                }
                ?>
                
                const categoryCtx = document.getElementById('categoryDistributionChart').getContext('2d');
                new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($categories); ?>,
                        datasets: [{
                            data: <?php echo json_encode($category_counts); ?>,
                            backgroundColor: [
                                '#1a5fb4', '#26a269', '#e5a50a', '#dc3545', '#6f42c1',
                                '#20c997', '#fd7e14', '#e83e8c', '#6c757d', '#17a2b8'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 20
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
        
        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // Check if user is still active on the page
            if (!document.hidden) {
                console.log('Auto-refreshing report data...');
                // You could implement AJAX refresh here instead of full page reload
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>