<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../includes/db_connect.php');
include('../includes/functions.php');

markExpiredDonations($conn);

// Admin summary numbers give a quick health view of the whole platform.
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users3")->fetch_assoc()['total'];
$totalDonors = $conn->query("SELECT COUNT(*) AS total FROM users3 WHERE role='donor'")->fetch_assoc()['total'];
$totalVolunteers = $conn->query("SELECT COUNT(*) AS total FROM users3 WHERE role='volunteer'")->fetch_assoc()['total'];
$totalDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2")->fetch_assoc()['total'];
$completedDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE status='Delivered'")->fetch_assoc()['total'];
$expiredDonations = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE status='Expired'")->fetch_assoc()['total'];
$recentActivity = $conn->query("
    SELECT d.*, donor.name AS donor_name, volunteer.name AS volunteer_name
    FROM donations2 d
    JOIN users3 donor ON d.donor_id = donor.id
    LEFT JOIN users3 volunteer ON d.volunteer_id = volunteer.id
    ORDER BY d.created_at DESC
    LIMIT 8
");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">GreenBite - Admin</a>
    <div class="d-flex ms-auto">
  <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
</div>

  </div>
</nav>

<div class="container mt-5">
  <h3>Welcome, <?php echo $_SESSION['user_name']; ?> 🧑‍💼</h3>
  <p class="text-muted">You can manage users and monitor donations.</p>

  <div class="row g-3 mt-4">
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Users</span><h3><?php echo $totalUsers; ?></h3></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Donors</span><h3><?php echo $totalDonors; ?></h3></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Volunteers</span><h3><?php echo $totalVolunteers; ?></h3></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Donations</span><h3><?php echo $totalDonations; ?></h3></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Completed</span><h3 class="text-success"><?php echo $completedDonations; ?></h3></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted">Expired</span><h3 class="text-secondary"><?php echo $expiredDonations; ?></h3></div></div></div>
  </div>

  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white"><strong><i class="bi bi-activity text-success"></i> Recent Activity</strong></div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-success">
          <tr>
            <th>ID</th>
            <th>Food</th>
            <th>Donor</th>
            <th>Volunteer</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $recentActivity->fetch_assoc()) { ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['food_type']); ?></td>
              <td><?php echo htmlspecialchars($row['donor_name']); ?></td>
              <td><?php echo htmlspecialchars($row['volunteer_name'] ?? 'Not assigned'); ?></td>
              <td><?php echo renderStatusBadge($row['status']); ?></td>
              <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card text-center shadow p-3">
        <?php
          $result = $conn->query("SELECT COUNT(*) AS total FROM users3 WHERE role='donor'");
          $row = $result->fetch_assoc();
        ?>
        <h5>Donors</h5>
        <h3><?php echo $row['total']; ?></h3>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card text-center shadow p-3">
        <?php
          $result = $conn->query("SELECT COUNT(*) AS total FROM users3 WHERE role='volunteer'");
          $row = $result->fetch_assoc();
        ?>
        <h5>Volunteers</h5>
        <h3><?php echo $row['total']; ?></h3>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card text-center shadow p-3">
        <?php
          $result = $conn->query("SELECT COUNT(*) AS total FROM donations2");
          if ($result) {
            $row = $result->fetch_assoc();
            $count = $row['total'];
          } else {
            $count = 0;
          }
        ?>
        <h5>Total Donations</h5>
        <h3><?php echo $count; ?></h3>
      </div>
    </div>
  </div>

  <div class="card p-3 mt-4">
    <h5>Admin Controls:</h5>
    <ul>
  <li><a href="view_donations.php">Manage Donations</a></li>
  <li><a href="view_users.php">Manage Users</a></li>
</ul>
  </div>
</div>
</body>
</html>
