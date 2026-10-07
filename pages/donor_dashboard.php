<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'donor') {
    header("Location: ../login.php");
    exit();
}
include('../includes/db_connect.php');
include('../includes/functions.php');

$user_id = $_SESSION['user_id'];
markExpiredDonations($conn);

// Dashboard counts are grouped in simple queries so students can explain each number.
$totalDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE donor_id = '$user_id'")->fetch_assoc()['total'];
$availableDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE donor_id = '$user_id' AND status = 'Available'")->fetch_assoc()['total'];
$reservedDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE donor_id = '$user_id' AND status = 'Reserved'")->fetch_assoc()['total'];
$completedDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE donor_id = '$user_id' AND status = 'Delivered'")->fetch_assoc()['total'];
$expiredDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE donor_id = '$user_id' AND status = 'Expired'")->fetch_assoc()['total'];
$recentDonations = $conn->query("
    SELECT d.*, volunteer.name AS volunteer_name
    FROM donations2 d
    LEFT JOIN users3 volunteer ON d.volunteer_id = volunteer.id
    WHERE d.donor_id = '$user_id'
    ORDER BY d.created_at DESC
    LIMIT 5
");
$notifications = getRecentNotifications($conn, $user_id, 5);
$unreadCount = getUnreadNotificationCount($conn, $user_id);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Donor Dashboard - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">GreenBite - Donor</a>
<div class="d-flex ms-auto">
  <a href="notifications.php" class="btn btn-outline-light btn-sm me-2">
    <i class="bi bi-bell"></i> Notifications
    <?php if ($unreadCount > 0) { ?><span class="badge bg-danger"><?php echo $unreadCount; ?></span><?php } ?>
  </a>
  <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
</div>

  </div>
</nav>

<div class="container mt-5">
  <h3>Welcome, <?php echo $_SESSION['user_name']; ?> 👋</h3>
  <p class="text-muted">You can add and view your food donations here.</p>

  <div class="row g-3 mt-4">
    <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Total Donations</span><h3><?php echo $totalDonations; ?></h3></div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Available</span><h3 class="text-success"><?php echo $availableDonations; ?></h3></div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Reserved</span><h3 class="text-warning"><?php echo $reservedDonations; ?></h3></div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Completed</span><h3 class="text-dark"><?php echo $completedDonations; ?></h3></div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Expired</span><h3 class="text-secondary"><?php echo $expiredDonations; ?></h3></div></div></div>
  </div>

  <div class="row g-4 mt-2">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-clock-history text-success"></i> Recent Donations</strong>
          <a href="view_donations.php" class="btn btn-sm btn-outline-success">View All</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-success">
              <tr>
                <th>Food</th>
                <th>Status</th>
                <th>Volunteer</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $recentDonations->fetch_assoc()) { ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['food_type']); ?></td>
                  <td><?php echo renderStatusBadge($row['status']); ?></td>
                  <td><?php echo htmlspecialchars($row['volunteer_name'] ?? 'Not assigned'); ?></td>
                  <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong><i class="bi bi-lightning-charge text-success"></i> Quick Actions</strong></div>
        <div class="list-group list-group-flush">
          <a href="donation_form.php" class="list-group-item list-group-item-action"><i class="bi bi-plus-circle"></i> Add a Food Donation</a>
          <a href="view_donations.php" class="list-group-item list-group-item-action"><i class="bi bi-table"></i> View Donation History</a>
          <a href="notifications.php" class="list-group-item list-group-item-action"><i class="bi bi-bell"></i> View Notifications</a>
        </div>
      </div>
    </div>
  </div>

  <div class="card p-3 mt-4">
    <h5>Next Step:</h5>
    <ul>
      <li><a href="donation_form.php">Add a Food Donation</a></li>
      <li><a href="view_donations.php">View My Donations</a></li>
    </ul>
  </div>
</div>
</body>
</html>
