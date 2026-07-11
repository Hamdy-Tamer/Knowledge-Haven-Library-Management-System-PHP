<?php
// admin/books/manage.php
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

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$available_only = isset($_GET['available_only']) && $_GET['available_only'] == 'on';

// Get categories for dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Build books query with search filters
$books_query = "SELECT b.*, a.name as author_name, c.name as category_name, 
                       l.name as location_name, l.location_code
                FROM books b
                LEFT JOIN authors a ON b.author_id = a.author_id
                LEFT JOIN categories c ON b.category_id = c.category_id
                LEFT JOIN locations l ON b.location_id = l.location_id
                WHERE 1=1";

$params = [];
$types = '';

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

if ($available_only) {
    $books_query .= " AND b.available_copies > 0";
}

$books_query .= " ORDER BY b.book_id DESC";

// Prepare and execute the query
if (empty($params)) {
    $books_result = mysqli_query($conn, $books_query);
} else {
    $stmt = mysqli_prepare($conn, $books_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $books_result = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Manage Books</title>
    
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
        
        .status-available {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-unavailable {
            color: #dc3545;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .available-badge {
            background-color: #d4edda;
            color: #155724;
        }
        
        .unavailable-badge {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .search-icon {
            color: var(--primary-color);
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
        }
        
        /* Animation for search results */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .table tbody tr {
            animation: fadeIn 0.3s ease-out;
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
                    <a class="nav-link active" href="#">
                        <i class="fas fa-book me-2"></i>Manage Books
                    </a>
                    <a class="nav-link" href="../authors/manage.php">
                        <i class="fas fa-user-edit me-2"></i>Authors
                    </a>
                    <a class="nav-link" href="../categories/manage.php">
                        <i class="fas fa-tags me-2"></i>Categories
                    </a>
                    <a class="nav-link" href="../locations/manage.php">
                        <i class="fas fa-map-marker-alt me-2"></i>Locations
                    </a>
                    <a class="nav-link" href="../reports/index.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 main-content">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-book me-2"></i>Manage Books</h4>
                            <a href="add.php" class="btn btn-add">
                                <i class="fas fa-plus me-1"></i>Add New Book
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Message Display -->
                        <?php if (isset($_GET['message'])): ?>
                            <div class="alert alert-<?php echo $_GET['type'] ?? 'success'; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_GET['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- SEARCH CARD - ADDED ABOVE THE TABLE -->
                        <div class="card search-card mb-4">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="fas fa-search search-icon me-2"></i>Search Books</h6>
                                
                                <form method="GET" action="manage.php">
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
                                                <select class="form-select" name="category_id">
                                                    <option value="0">All Categories</option>
                                                    <?php while($cat = mysqli_fetch_assoc($categories_result)): ?>
                                                        <option value="<?php echo $cat['category_id']; ?>"
                                                            <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                    <?php mysqli_data_seek($categories_result, 0); // Reset pointer for later use ?>
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
                                    
                                    <!-- Additional Filters Row -->
                                    <div class="row mt-3">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="available_only" 
                                                       id="availableOnly"
                                                       <?php echo $available_only ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="availableOnly">
                                                    <i class="fas fa-check-circle me-1"></i>Show available only
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-9 text-end">
                                            <!-- Active Filters Display -->
                                            <div class="d-inline-block me-3">
                                                <?php if ($search || $isbn || $category_id > 0 || $available_only): ?>
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
                                                    
                                                    <?php if ($available_only): ?>
                                                        <span class="filter-badge">
                                                            <i class="fas fa-check-circle"></i> Available only
                                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['available_only' => ''])); ?>" 
                                                               class="ms-1 text-danger">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Clear All Button -->
                                            <?php if ($search || $isbn || $category_id > 0 || $available_only): ?>
                                                <a href="manage.php" class="btn btn-clear btn-sm">
                                                    <i class="fas fa-times me-1"></i>Clear All
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                                
                                <!-- Results Count -->
                                <?php 
                                    $total_books = mysqli_num_rows($books_result);
                                    mysqli_data_seek($books_result, 0); // Reset pointer for table display
                                ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-book me-1"></i>
                                        <?php if ($search || $isbn || $category_id > 0 || $available_only): ?>
                                            Found <strong><?php echo $total_books; ?></strong> book(s) matching your search
                                        <?php else: ?>
                                            Total books in library: <strong><?php echo $total_books; ?></strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <!-- END SEARCH CARD -->
                        
                        <!-- Books Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th>ISBN</th>
                                        <th>Copies</th>
                                        <th>Available</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($total_books > 0): ?>
                                        <?php while($book = mysqli_fetch_assoc($books_result)): ?>
                                            <tr>
                                                <td><?php echo $book['book_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                                    <?php if ($book['description']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars(substr($book['description'], 0, 50)); ?>
                                                            <?php if (strlen($book['description']) > 50): ?>...<?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($book['author_name']); ?></td>
                                                <td><?php echo htmlspecialchars($book['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                                <td><?php echo $book['total_copies']; ?></td>
                                                <td>
                                                    <?php if ($book['available_copies'] > 0): ?>
                                                        <span class="status-available"><?php echo $book['available_copies']; ?></span>
                                                        <span class="status-badge available-badge">Available</span>
                                                    <?php else: ?>
                                                        <span class="status-unavailable">0</span>
                                                        <span class="status-badge unavailable-badge">Unavailable</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($book['location_name']): ?>
                                                        <?php echo htmlspecialchars($book['location_name']); ?>
                                                        <br>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($book['location_code']); ?>)</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="view.php?id=<?php echo $book['book_id']; ?>" 
                                                           class="btn btn-view btn-sm" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $book['book_id']; ?>" 
                                                           class="btn btn-edit btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="delete.php?id=<?php echo $book['book_id']; ?>" 
                                                           class="btn btn-delete btn-sm" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this book?\n\nBook: <?php echo addslashes($book['title']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <?php if ($search || $isbn || $category_id > 0 || $available_only): ?>
                                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No books found</h5>
                                                    <p class="text-muted">Try different search criteria</p>
                                                    <a href="manage.php" class="btn btn-primary btn-sm mt-2">
                                                        <i class="fas fa-times me-1"></i>Clear Search
                                                    </a>
                                                <?php else: ?>
                                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No books found</h5>
                                                    <p class="text-muted">Click "Add New Book" to add your first book</p>
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
        
        // Auto-focus search input on page load if there's a search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }
        });
    </script>
</body>
</html>