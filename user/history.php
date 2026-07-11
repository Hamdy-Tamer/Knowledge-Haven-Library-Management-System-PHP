<?php
// user/history.php
session_start();
include "../config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user is employee (should redirect to employee dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    header("Location: ../admin/dashboard.php");
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, m.member_id, m.member_code 
          FROM users u 
          LEFT JOIN members m ON u.user_id = m.user_id 
          WHERE u.user_id = ? AND u.role = 'user'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !$user['member_id']) {
    header("Location: dashboard.php?message=Member account not found!&type=danger");
    exit;
}

// Get search/filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Build query for transaction history
$history_query = "SELECT t.*, b.title, b.isbn, a.name as author_name,
                         f.amount as fine_amount, f.status as fine_status
                  FROM transactions t
                  JOIN books b ON t.book_id = b.book_id
                  JOIN authors a ON b.author_id = a.author_id
                  LEFT JOIN fines f ON t.transaction_id = f.transaction_id
                  WHERE t.member_id = ?";
$params = [$user['member_id']];
$types = 'i';

// Add filters
if (!empty($search)) {
    $history_query .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR a.name LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($status !== 'all') {
    $history_query .= " AND t.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($type !== 'all') {
    $history_query .= " AND t.transaction_type = ?";
    $params[] = $type;
    $types .= 's';
}

if ($month > 0) {
    $history_query .= " AND MONTH(t.created_at) = ?";
    $params[] = $month;
    $types .= 'i';
}

$history_query .= " AND YEAR(t.created_at) = ?";
$params[] = $year;
$types .= 'i';

$history_query .= " ORDER BY t.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN transaction_type = 'borrow' THEN 1 ELSE 0 END) as borrows,
                SUM(CASE WHEN transaction_type = 'return' THEN 1 ELSE 0 END) as returns,
                SUM(CASE WHEN transaction_type = 'renew' THEN 1 ELSE 0 END) as renews
                FROM transactions 
                WHERE member_id = ?";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $user['member_id']);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get total fines
$fines_query = "SELECT SUM(amount) as total_fines FROM fines WHERE member_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $fines_query);
mysqli_stmt_bind_param($stmt, "i", $user['member_id']);
mysqli_stmt_execute($stmt);
$fines_result = mysqli_stmt_get_result($stmt);
$fines = mysqli_fetch_assoc($fines_result);

// Function to get transaction years
function getTransactionYears($conn, $member_id, $mode = 'all') {
    $current_year = date('Y');
    
    $years_query = "SELECT DISTINCT YEAR(created_at) as year FROM transactions WHERE member_id = ? ORDER BY year DESC";
    $stmt = mysqli_prepare($conn, $years_query);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    mysqli_stmt_execute($stmt);
    $years_result = mysqli_stmt_get_result($stmt);
    
    $available_years = [];
    while ($row = mysqli_fetch_assoc($years_result)) {
        $available_years[] = $row['year'];
    }
    
    if (!in_array($current_year, $available_years)) {
        $available_years[] = $current_year;
        sort($available_years);
        $available_years = array_reverse($available_years);
    }
    
    if (empty($available_years)) {
        $available_years[] = $current_year;
    }
    
    if ($mode === 'last5') {
        if (count($available_years) >= 5) {
            return array_slice($available_years, 0, 5);
        } else {
            $last_five = [];
            for ($i = 0; $i < 5; $i++) {
                $year_to_add = $current_year - $i;
                if (!in_array($year_to_add, $last_five)) {
                    $last_five[] = $year_to_add;
                }
            }
            sort($last_five);
            return array_reverse($last_five);
        }
    }
    
    return $available_years;
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$available_years = getTransactionYears($conn, $user['member_id'], 'all');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Transaction History</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="../assets/images/logo-library.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
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
            padding-top: 80px;
        }
        
        .navbar-custom {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 24px;
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--dark-color) !important;
            margin: 0 10px;
        }
        
        .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        .user-welcome {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .main-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        
        .section-title {
            color: var(--primary-color);
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 10px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), #2d7dd2);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        .transaction-card {
            border-left: 4px solid var(--primary-color);
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .transaction-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .transaction-borrow {
            border-left-color: var(--primary-color);
        }
        
        .transaction-return {
            border-left-color: var(--secondary-color);
        }
        
        .transaction-renew {
            border-left-color: var(--accent-color);
        }
        
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-active {
            background-color: #d1e7ff;
            color: #0a58ca;
        }
        
        .badge-completed {
            background-color: #d1f2eb;
            color: #0d8a6c;
        }
        
        .badge-overdue {
            background-color: #f8d7da;
            color: #b02a37;
        }
        
        .filter-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .book-cover {
            width: 60px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            background: linear-gradient(45deg, #e3f2fd, #bbdefb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 24px;
        }
        
        .pagination .page-link {
            color: var(--primary-color);
            border: 1px solid #dee2e6;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .footer {
            background-color: var(--dark-color);
            color: white;
            padding: 40px 0;
            margin-top: 50px;
        }
        
        .year-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .main-content, .sidebar {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">
                    <div class="d-flex align-items-center">
                        <img src="../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                        <span style="font-family: 'Playfair Display', serif; font-weight: 700; color: var(--primary-color);">
                            Knowledge <span style="color: var(--accent-color);">Haven</span>
                        </span>
                    </div>
                </a>
            
                <div class="d-flex align-items-center">
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                    <span class="me-3 d-none d-md-block text-muted">
                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                    </span>
                </div>
            </div>
        </nav>
        
        <div class="row">
            <!-- Sidebar with Statistics -->
            <div class="col-lg-4 mt-5">
                <div class="sidebar">
                    <h4 class="section-title">Transaction Statistics</h4>
                    
                    <div class="stat-card">
                        <i class="fas fa-exchange-alt"></i>
                        <h3><?php echo $stats['total_transactions'] ?? 0; ?></h3>
                        <p>Total Transactions</p>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #26a269, #2ecc71);">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                        <p>Completed</p>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #1a5fb4, #2980b9);">
                        <i class="fas fa-clock"></i>
                        <h3><?php echo $stats['active'] ?? 0; ?></h3>
                        <p>Active</p>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #c13c3c, #e74c3c);">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3><?php echo $stats['overdue'] ?? 0; ?></h3>
                        <p>Overdue</p>
                    </div>
                    
                    <?php if ($fines['total_fines'] > 0): ?>
                    <div class="stat-card" style="background: linear-gradient(135deg, #e5a50a, #f39c12);">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3>$<?php echo number_format($fines['total_fines'], 2); ?></h3>
                        <p>Pending Fines</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <h5 class="mb-3">Member Information</h5>
                        <p><strong>Member Code:</strong> <?php echo htmlspecialchars($user['member_code']); ?></p>
                        <p><strong>Total Borrows:</strong> <?php echo $stats['borrows'] ?? 0; ?></p>
                        <p><strong>Total Returns:</strong> <?php echo $stats['returns'] ?? 0; ?></p>
                        <p><strong>Total Renews:</strong> <?php echo $stats['renews'] ?? 0; ?></p>
                        <p><strong>Years Active:</strong> <?php echo count($available_years); ?> year(s)</p>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-8 mt-5">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="section-title mb-0">Transaction History</h3>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="yearModeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-calendar-alt me-1"></i>Year Display
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="yearModeDropdown">
                                <li><a class="dropdown-item" href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&status=<?php echo $status; ?>&type=<?php echo $type; ?>&search=<?php echo urlencode($search); ?>">
                                    All Years (<?php echo count(getTransactionYears($conn, $user['member_id'], 'all')); ?>)
                                </a></li>
                                <li><a class="dropdown-item" href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&status=<?php echo $status; ?>&type=<?php echo $type; ?>&search=<?php echo urlencode($search); ?>&mode=last5">
                                    Last 5 Years
                                </a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-card">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search Books</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Search by title, ISBN or author..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="borrow" <?php echo $type === 'borrow' ? 'selected' : ''; ?>>Borrow</option>
                                    <option value="return" <?php echo $type === 'return' ? 'selected' : ''; ?>>Return</option>
                                    <option value="renew" <?php echo $type === 'renew' ? 'selected' : ''; ?>>Renew</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Month</label>
                                <select class="form-select" name="month">
                                    <option value="0" <?php echo $month === 0 ? 'selected' : ''; ?>>All Months</option>
                                    <?php foreach ($months as $key => $name): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $month === $key ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Year</label>
                                <select class="form-select" name="year">
                                    <?php foreach ($available_years as $available_year): ?>
                                        <option value="<?php echo $available_year; ?>" <?php echo $year == $available_year ? 'selected' : ''; ?>>
                                            <?php echo $available_year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                            </div>
                            
                            <?php if (isset($_GET['mode'])): ?>
                                <input type="hidden" name="mode" value="<?php echo htmlspecialchars($_GET['mode']); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Transaction History List -->
                    <div class="table-responsive">
                        <?php if (mysqli_num_rows($history_result) > 0): ?>
                            <?php while ($transaction = mysqli_fetch_assoc($history_result)): ?>
                                <div class="transaction-card p-3 mb-3 transaction-<?php echo $transaction['transaction_type']; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="book-cover">
                                                <i class="fas fa-book"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($transaction['title']); ?></h6>
                                                    <p class="text-muted small mb-1">
                                                        <i class="fas fa-user-pen me-1"></i> <?php echo htmlspecialchars($transaction['author_name']); ?> |
                                                        <i class="fas fa-barcode me-1"></i> <?php echo htmlspecialchars($transaction['isbn']); ?>
                                                    </p>
                                                    <div class="d-flex gap-3">
                                                        <span class="badge badge-status badge-<?php echo $transaction['status']; ?>">
                                                            <?php echo ucfirst($transaction['status']); ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-<?php echo $transaction['transaction_type'] == 'borrow' ? 'arrow-down' : ($transaction['transaction_type'] == 'return' ? 'arrow-up' : 'refresh'); ?> me-1"></i>
                                                            <?php echo ucfirst($transaction['transaction_type']); ?>
                                                        </span>
                                                        <?php if ($transaction['fine_amount'] && $transaction['fine_status'] == 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                Fine: $<?php echo number_format($transaction['fine_amount'], 2); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted d-block">Transaction Date</small>
                                                    <strong><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('h:i A', strtotime($transaction['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <?php if ($transaction['transaction_type'] == 'borrow' || $transaction['transaction_type'] == 'renew'): ?>
                                                <div class="mt-2 pt-2 border-top">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <small class="text-muted">Borrowed Date:</small>
                                                            <div><strong><?php echo date('M d, Y', strtotime($transaction['borrow_date'])); ?></strong></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small class="text-muted">Due Date:</small>
                                                            <div class="<?php echo $transaction['status'] == 'overdue' ? 'text-danger' : ''; ?>">
                                                                <strong><?php echo date('M d, Y', strtotime($transaction['due_date'])); ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($transaction['transaction_type'] == 'return' && $transaction['return_date']): ?>
                                                <div class="mt-2 pt-2 border-top">
                                                    <small class="text-muted">Returned Date:</small>
                                                    <div><strong><?php echo date('M d, Y', strtotime($transaction['return_date'])); ?></strong></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5>No transactions found</h5>
                                <p class="text-muted"><?php echo empty($search) && $status === 'all' && $type === 'all' && $month === 0 ? 'You have no transaction history yet.' : 'No transactions match your filters.'; ?></p>
                                <?php if (!empty($search) || $status !== 'all' || $type !== 'all' || $month > 0): ?>
                                    <a href="history.php" class="btn btn-primary">
                                        <i class="fas fa-times me-1"></i> Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function printHistory() {
            window.open('print_transaction_history.php', '_blank');
        }
        
        // Tooltip initialization
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Update year mode based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const mode = urlParams.get('mode');
            if (mode === 'last5') {
                const dropdownButton = document.getElementById('yearModeDropdown');
                if (dropdownButton) {
                    dropdownButton.innerHTML = '<i class="fas fa-calendar-alt me-1"></i>Last 5 Years';
                }
            }
        });
        
        // Auto-refresh page every 5 minutes for status updates
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>