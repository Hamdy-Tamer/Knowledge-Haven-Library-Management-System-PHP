<?php
// admin/books/delete.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Book ID is required!&type=danger");
    exit;
}

$book_id = intval($_GET['id']);

// Get book title for message
$query = "SELECT title FROM books WHERE book_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $book_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$book = mysqli_fetch_assoc($result);

if (!$book) {
    header("Location: manage.php?message=Book not found!&type=danger");
    exit;
}

$book_title = $book['title'];

// Check if book has active transactions
$check_transactions = "SELECT COUNT(*) as count FROM transactions 
                      WHERE book_id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $check_transactions);
mysqli_stmt_bind_param($stmt, "i", $book_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    header("Location: manage.php?message=Cannot delete book with active transactions!&type=danger");
    exit;
}

// Delete the book
$delete_query = "DELETE FROM books WHERE book_id = ?";
$stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($stmt, "i", $book_id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: manage.php?message=Book '" . htmlspecialchars($book_title) . "' deleted successfully!&type=success");
    exit;
} else {
    header("Location: manage.php?message=Error deleting book!&type=danger");
    exit;
}
?>