<?php
// user/books/available.php
session_start();
include "../../config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Check if user is employee (should redirect to employee dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    header("Location: ../../admin/dashboard.php");
    exit;
}

// Get user details from users table where role = 'user' (member)
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, m.member_id, m.member_code, m.total_books_borrowed, m.current_fines 
          FROM users u 
          LEFT JOIN members m ON u.user_id = m.user_id 
          WHERE u.user_id = ? AND u.role = 'user'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header("Location: ../../logout.php");
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$availability = isset($_GET['availability']) ? $_GET['availability'] : 'all';

// Get categories for dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Build books query with search filters
$books_query = "SELECT b.*, a.name as author_name, c.name as category_name, 
                       l.name as location_name, l.location_code,
                       t.due_date as user_due_date, t.status as user_transaction_status
                FROM books b
                LEFT JOIN authors a ON b.author_id = a.author_id
                LEFT JOIN categories c ON b.category_id = c.category_id
                LEFT JOIN locations l ON b.location_id = l.location_id
                LEFT JOIN transactions t ON b.book_id = t.book_id 
                    AND t.member_id = ? 
                    AND t.status = 'active' 
                    AND t.transaction_type = 'borrow'
                WHERE 1=1";

$params = [$user['member_id'] ?? 0];
$types = 'i';

// Add search filters
if (!empty($search)) {
    $books_query .= " AND (b.title LIKE ? OR a.name LIKE ? OR b.description LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($isbn)) {
    $books_query .= " AND b.isbn LIKE ?";
    $params[] = "%" . $isbn . "%";
    $types .= 's';
}

if ($category_id > 0) {
    $books_query .= " AND b.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

// Add availability filter
if ($availability === 'available') {
    $books_query .= " AND b.available_copies > 0";
} elseif ($availability === 'borrowed') {
    $books_query .= " AND b.available_copies = 0 AND b.total_copies > 0";
}

$books_query .= " ORDER BY b.title ASC";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $books_query);

if ($stmt) {
    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bindParams));
    mysqli_stmt_execute($stmt);
    $books_result = mysqli_stmt_get_result($stmt);
} else {
    echo "Error preparing statement: " . mysqli_error($conn);
    $books_result = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Browse Books</title>
    
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
            padding-top: 80px;
        }
        
        .navbar-custom {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
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
        
        .btn-borrow {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            font-size: 0.8rem;
            padding: 4px 10px;
        }
        
        .btn-borrow:hover:not(:disabled) {
            background-color: #1e7b4d;
        }
        
        .btn-borrow:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-borrowed {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-borrowed-by-you {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        
        .book-title {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .book-author {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .availability-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .available-indicator {
            background-color: #28a745;
        }
        
        .borrowed-indicator {
            background-color: #007bff;
        }
        
        .unavailable-indicator {
            background-color: #dc3545;
        }
        
        .borrowed-by-you-indicator {
            background-color: #ffc107;
        }
        
        .user-info-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d4a9e 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .member-badge {
            background-color: var(--accent-color);
            color: var(--dark-color);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .search-card .col-md-3, 
            .search-card .col-md-2 {
                margin-bottom: 10px;
            }
            
            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
            
            .btn-borrow {
                font-size: 0.75rem;
                padding: 3px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/logo-library.png" alt="Logo" width="40" height="40" class="me-2">
                    <span style="font-family: 'Playfair Display', serif; font-weight: 700; color: var(--primary-color);">
                        Knowledge <span style="color: var(--accent-color);">Haven</span>
                    </span>
                </div>
            </a>
            
            <div class="d-flex align-items-center">
                <!-- User Info -->
                <div class="d-flex align-items-center me-3">
                    <img src="<?php echo htmlspecialchars($user['profile_image'] ?? '../../assets/images/user_image.jpg'); ?>" 
                         alt="Profile" 
                         class="rounded-circle"
                         style="width: 35px; height: 35px; object-fit: cover; margin-right: 8px;"
                         onerror="this.src='../../assets/images/user_image.jpg'">
                    <div>
                        <div class="small fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="small text-muted">Member</div>
                    </div>
                </div>
                
                <a href="../dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                
                <!-- Main Card -->
                <div class="card">
                    <div class="card-header card-header-custom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-book me-2"></i>Browse Library Books</h4>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-book-open me-1"></i>
                                <?php echo $books_result ? mysqli_num_rows($books_result) : 0; ?> Books
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Search Card -->
                        <div class="card search-card mb-4">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="fas fa-search text-primary me-2"></i>Search Books</h6>
                                
                                <form method="GET" action="available.php" id="searchForm">
                                    <div class="row g-3">
                                        <!-- Search by Title/Author/Description -->
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                                <input type="text" 
                                                       class="form-control" 
                                                       name="search" 
                                                       id="search"
                                                       placeholder="Search title, author, or description..."
                                                       value="<?php echo htmlspecialchars($search); ?>">
                                            </div>
                                        </div>
                                        
                                        <!-- Search by ISBN -->
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-barcode"></i>
                                                </span>
                                                <input type="text" 
                                                       class="form-control" 
                                                       name="isbn" 
                                                       id="isbn"
                                                       placeholder="ISBN..."
                                                       value="<?php echo htmlspecialchars($isbn); ?>">
                                            </div>
                                        </div>
                                        
                                        <!-- Category Filter -->
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-tag"></i>
                                                </span>
                                                <select class="form-select" name="category_id" id="category_id">
                                                    <option value="0">All Categories</option>
                                                    <?php while($cat = mysqli_fetch_assoc($categories_result)): ?>
                                                        <option value="<?php echo $cat['category_id']; ?>"
                                                            <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                    <?php mysqli_data_seek($categories_result, 0); ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Search Button -->
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-search w-100" id="searchButton">
                                                <i class="fas fa-search me-1"></i>Search
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Filters Row -->
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Availability:</label>
                                            <div class="btn-group" role="group">
                                                <input type="radio" class="btn-check" name="availability" id="all" 
                                                       value="all" <?php echo $availability === 'all' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-primary btn-sm" for="all">All Books</label>
                                                
                                                <input type="radio" class="btn-check" name="availability" id="available" 
                                                       value="available" <?php echo $availability === 'available' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-success btn-sm" for="available">Available</label>
                                                
                                                <input type="radio" class="btn-check" name="availability" id="borrowed" 
                                                       value="borrowed" <?php echo $availability === 'borrowed' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-info btn-sm" for="borrowed">Borrowed</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-8 text-end">
                                            <!-- Active Filters Display -->
                                            <div class="d-inline-block me-3">
                                                <?php if ($search || $isbn || $category_id > 0 || $availability !== 'all'): ?>
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
                                                    
                                                    <?php if ($isbn): ?>
                                                        <span class="filter-badge">
                                                            <i class="fas fa-barcode"></i> ISBN: <?php echo htmlspecialchars($isbn); ?>
                                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['isbn' => ''])); ?>" 
                                                               class="ms-1 text-danger">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($category_id > 0): 
                                                        $cat_name = '';
                                                        mysqli_data_seek($categories_result, 0);
                                                        while($cat = mysqli_fetch_assoc($categories_result)) {
                                                            if ($cat['category_id'] == $category_id) {
                                                                $cat_name = $cat['name'];
                                                                break;
                                                            }
                                                        }
                                                    ?>
                                                        <span class="filter-badge">
                                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat_name); ?>
                                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category_id' => '0'])); ?>" 
                                                               class="ms-1 text-danger">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($availability !== 'all'): ?>
                                                        <span class="filter-badge">
                                                            <i class="fas fa-filter"></i> 
                                                            <?php echo $availability === 'available' ? 'Available Only' : 'Borrowed Only'; ?>
                                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['availability' => 'all'])); ?>" 
                                                               class="ms-1 text-danger">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Clear All Button -->
                                            <?php if ($search || $isbn || $category_id > 0 || $availability !== 'all'): ?>
                                                <a href="available.php" class="btn btn-clear btn-sm">
                                                    <i class="fas fa-times me-1"></i>Clear All
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Books Table -->
                        <div class="table-responsive table-container">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th>ISBN</th>
                                        <th>Total Copies</th>
                                        <th>Available</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Get user's currently borrowed books count
                                    $borrowed_count_query = "SELECT COUNT(*) as count FROM transactions 
                                                           WHERE member_id = ? AND status = 'active' AND transaction_type = 'borrow'";
                                    $stmt_count = mysqli_prepare($conn, $borrowed_count_query);
                                    mysqli_stmt_bind_param($stmt_count, "i", $user['member_id']);
                                    mysqli_stmt_execute($stmt_count);
                                    $result_count = mysqli_stmt_get_result($stmt_count);
                                    $borrow_count_row = mysqli_fetch_assoc($result_count);
                                    $current_borrows = $borrow_count_row['count'] ?? 0;
                                    
                                    if ($books_result && mysqli_num_rows($books_result) > 0): 
                                        $count = 1;
                                        while($book = mysqli_fetch_assoc($books_result)): 
                                            // Determine book status
                                            if ($book['user_due_date']) {
                                                $status = 'borrowed_by_you';
                                                $status_text = 'Borrowed by You';
                                                $return_date = date('M d, Y', strtotime($book['user_due_date']));
                                                $can_borrow = false;
                                            } elseif ($book['available_copies'] > 0) {
                                                $status = 'available';
                                                $status_text = 'Available';
                                                $return_date = null;
                                                $can_borrow = ($current_borrows < 5); // Max 5 books
                                            } else {
                                                $status = 'borrowed';
                                                $status_text = 'Borrowed';
                                                $return_date = null;
                                                $can_borrow = false;
                                            }
                                    ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td>
                                                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                                <?php if ($book['description']): ?>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars(substr($book['description'], 0, 60)); ?>
                                                        <?php if (strlen($book['description']) > 60): ?>...<?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="book-author">
                                                    <i class="fas fa-user-edit me-1"></i>
                                                    <?php echo htmlspecialchars($book['author_name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($book['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                            <td><?php echo $book['total_copies']; ?></td>
                                            <td>
                                                <?php echo $book['available_copies']; ?>
                                                <small class="text-muted">/<?php echo $book['total_copies']; ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="availability-indicator 
                                                        <?php echo $status === 'available' ? 'available-indicator' : 
                                                               ($status === 'borrowed_by_you' ? 'borrowed-by-you-indicator' : 
                                                               ($status === 'borrowed' ? 'borrowed-indicator' : 'unavailable-indicator')); ?>">
                                                    </span>
                                                    <div>
                                                        <span class="status-badge 
                                                            <?php echo $status === 'available' ? 'status-available' : 
                                                                   ($status === 'borrowed_by_you' ? 'status-borrowed-by-you' : 
                                                                   ($status === 'borrowed' ? 'status-borrowed' : 'status-unavailable')); ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                        <?php if ($status === 'borrowed_by_you' && $return_date): ?>
                                                            <div class="small text-muted mt-1">
                                                                <i class="fas fa-calendar-alt me-1"></i>
                                                                Return by: <?php echo $return_date; ?>
                                                            </div>
                                                        <?php elseif ($book['location_name']): ?>
                                                            <div class="small text-muted mt-1">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                <?php echo htmlspecialchars($book['location_name']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($can_borrow): ?>
                                                    <form action="borrow.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                        <button type="submit" class="btn btn-borrow btn-sm" 
                                                                onclick="return confirm('Borrow <?php echo addslashes($book['title']); ?>?')">
                                                            <i class="fas fa-bookmark me-1"></i>Borrow
                                                        </button>
                                                    </form>
                                                <?php elseif ($status === 'borrowed_by_you'): ?>
                                                    <span class="badge bg-secondary">Already Borrowed</span>
                                                <?php elseif ($status === 'available' && !$can_borrow): ?>
                                                    <span class="badge bg-warning text-dark">Limit Reached</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Unavailable</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <?php if ($search || $isbn || $category_id > 0 || $availability !== 'all'): ?>
                                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No books found</h5>
                                                    <p class="text-muted">Try different search criteria</p>
                                                    <a href="available.php" class="btn btn-primary btn-sm mt-2">
                                                        <i class="fas fa-times me-1"></i>Clear Search
                                                    </a>
                                                <?php else: ?>
                                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No books available</h5>
                                                    <p class="text-muted">Check back later for new arrivals</p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Legend -->
                        <div class="mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Status Legend:</h6>
                            <div class="d-flex flex-wrap gap-3 mt-2">
                                <div class="d-flex align-items-center">
                                    <span class="availability-indicator available-indicator me-2"></span>
                                    <span class="status-badge status-available me-2">Available</span>
                                    <small class="text-muted">- Can be borrowed (max 5 books)</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="availability-indicator borrowed-by-you-indicator me-2"></span>
                                    <span class="status-badge status-borrowed-by-you me-2">Borrowed by You</span>
                                    <small class="text-muted">- You have this book</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="availability-indicator borrowed-indicator me-2"></span>
                                    <span class="status-badge status-borrowed me-2">Borrowed</span>
                                    <small class="text-muted">- Currently borrowed by others</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form submission handler
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            const searchButton = document.getElementById('searchButton');
            const originalText = searchButton.innerHTML;
            searchButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Searching...';
            searchButton.disabled = true;
            
            setTimeout(() => {
                if (searchButton.disabled) {
                    searchButton.innerHTML = originalText;
                    searchButton.disabled = false;
                }
            }, 3000);
        });
        
        // Clear individual filter when X is clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.filter-badge a')) {
                e.preventDefault();
                const url = e.target.closest('.filter-badge a').getAttribute('href');
                window.location.href = url;
            }
        });
        
        // Auto-focus search input on page load if there's a search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }
        });
        
        // Borrow button confirmation
        document.querySelectorAll('form[action="borrow.php"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to borrow this book?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>