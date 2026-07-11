<?php
// admin/categories/delete.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if category ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Category ID is required!&type=danger");
    exit;
}

$category_id = intval($_GET['id']);

// Get category name for message
$query = "SELECT name FROM categories WHERE category_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $category_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$category = mysqli_fetch_assoc($result);

if (!$category) {
    header("Location: manage.php?message=Category not found!&type=danger");
    exit;
}

$category_name = $category['name'];

// Check if any books are associated with this category
$check_books = "SELECT COUNT(*) as count FROM books 
                WHERE category_id = ?";
$stmt = mysqli_prepare($conn, $check_books);
mysqli_stmt_bind_param($stmt, "i", $category_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    header("Location: manage.php?message=Cannot delete category '" . htmlspecialchars($category_name) . "'. There are " . $row['count'] . " books currently assigned to this category.&type=danger");
    exit;
}

// Delete the category
$delete_query = "DELETE FROM categories WHERE category_id = ?";
$stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($stmt, "i", $category_id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: manage.php?message=Category '" . htmlspecialchars($category_name) . "' deleted successfully!&type=success");
    exit;
} else {
    header("Location: manage.php?message=Error deleting category!&type=danger");
    exit;
}
?>