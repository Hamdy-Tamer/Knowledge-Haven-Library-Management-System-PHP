<?php
// admin/authors/delete.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if author ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Author ID is required!&type=danger");
    exit;
}

$author_id = intval($_GET['id']);

// Get author name for message
$query = "SELECT name FROM authors WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $author_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$author = mysqli_fetch_assoc($result);

if (!$author) {
    header("Location: manage.php?message=Author not found!&type=danger");
    exit;
}

$author_name = $author['name'];

// Check if any books are associated with this author
$check_books = "SELECT COUNT(*) as count FROM books 
                WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $check_books);
mysqli_stmt_bind_param($stmt, "i", $author_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    header("Location: manage.php?message=Cannot delete author '" . htmlspecialchars($author_name) . "'. There are " . $row['count'] . " books currently assigned to this author.&type=danger");
    exit;
}

// Delete the author
$delete_query = "DELETE FROM authors WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($stmt, "i", $author_id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: manage.php?message=Author '" . htmlspecialchars($author_name) . "' deleted successfully!&type=success");
    exit;
} else {
    header("Location: manage.php?message=Error deleting author!&type=danger");
    exit;
}
?>