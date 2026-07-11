<?php
// admin/locations/delete.php
session_start();
include "../../config.php";

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login.php");
    exit;
}

// Check if location ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php?message=Location ID is required!&type=danger");
    exit;
}

$location_id = intval($_GET['id']);

// Get location name for message
$query = "SELECT name FROM locations WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$location = mysqli_fetch_assoc($result);

if (!$location) {
    header("Location: manage.php?message=Location not found!&type=danger");
    exit;
}

$location_name = $location['name'];

// Check if any books are associated with this location
$check_books = "SELECT COUNT(*) as count FROM books 
                WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $check_books);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    header("Location: manage.php?message=Cannot delete location! There are " . $row['count'] . " books currently assigned here.&type=danger");
    exit;
}

// Delete the location
$delete_query = "DELETE FROM locations WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: manage.php?message=Location '" . htmlspecialchars($location_name) . "' deleted successfully!&type=success");
    exit;
} else {
    header("Location: manage.php?message=Error deleting location!&type=danger");
    exit;
}
?>