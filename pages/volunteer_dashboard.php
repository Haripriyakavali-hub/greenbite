<?php
session_start();
include('../includes/db_connect.php');
include('../includes/functions.php');

// Make sure only volunteers can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'volunteer') {
    header("Location: ../login.php");
    exit();
}

// Get volunteer’s latitude & longitude from session
$user_id = $_SESSION['user_id'];
$volunteer_lat = $_SESSION['latitude'];
$volunteer_lon = $_SESSION['longitude'];
markExpiredDonations($conn);

// Query to find nearby donations within 10 km
$query = "
    SELECT d.*, u.name AS donor_name, u.phone, 
    (6371 * acos(
        cos(radians($volunteer_lat)) * cos(radians(d.latitude)) * 
        cos(radians(d.longitude) - radians($volunteer_lon)) + 
        sin(radians($volunteer_lat)) * sin(radians(d.latitude))
    )) AS distance
    FROM donations2 d
    JOIN users3 u ON d.donor_id = u.id
    WHERE d.status = 'Available'
    HAVING distance <= 10
    ORDER BY distance ASC;
";

$result = mysqli_query($conn, $query);
$nearbyAvailable = $result;
$acceptedDonations = $conn->query("
    SELECT d.*, donor.name AS donor_name
    FROM donations2 d
    JOIN users3 donor ON d.donor_id = donor.id
    WHERE d.volunteer_id = '$user_id'
      AND d.status IN ('Reserved', 'Picked Up')
    ORDER BY d.created_at DESC
");
$completedDeliveries = $conn->query("SELECT COUNT(*) AS total FROM donations2 WHERE volunteer_id = '$user_id' AND status = 'Delivered'")->fetch_assoc()['total'];
$deliveryHistory = $conn->query("
    SELECT d.*, donor.name AS donor_name
    FROM donations2 d
    JOIN users3 donor ON d.donor_id = donor.id
    WHERE d.volunteer_id = '$user_id'
      AND d.status IN ('Delivered', 'Cancelled')
    ORDER BY d.created_at DESC
    LIMIT 5
");
$unreadCount = getUnreadNotificationCount($conn, $user_id);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Volunteer Dashboard - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">GreenBite - Volunteer</a>
    <div>
      <a href="notifications.php" class="btn btn-outline-light btn-sm me-2">
        <i class="bi bi-bell"></i> Notifications
        <?php if ($unreadCount > 0) { ?><span class="badge bg-danger"><?php echo $unreadCount; ?></span><?php } ?>
      </a>
      <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container mt-5">
  <h3>Welcome, <?php echo $_SESSION['user_name']; ?> 🙌</h3>
  <p class="text-muted">You can view and pick available donations here.</p>

  <div class="row g-4 mt-2">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-geo-alt text-success"></i> Nearby Available Donations</strong>
          <a href="view_donations.php?status=Available" class="btn btn-sm btn-outline-success">View All</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-success">
              <tr>
                <th>Food</th>
                <th>Donor</th>
                <th>Location</th>
                <th>Distance</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $nearbyAvailable->fetch_assoc()) { ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['food_type']); ?></td>
                  <td><?php echo htmlspecialchars($row['donor_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['location']); ?></td>
                  <td><?php echo round($row['distance'], 2); ?> km</td>
                  <td><a href="view_donations.php?claim=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-hand-thumbs-up"></i> Accept</a></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong><i class="bi bi-truck text-success"></i> Accepted Donations</strong></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-success">
              <tr>
                <th>Food</th>
                <th>Donor</th>
                <th>Status</th>
                <th>Expiry</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $acceptedDonations->fetch_assoc()) { ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['food_type']); ?></td>
                  <td><?php echo htmlspecialchars($row['donor_name']); ?></td>
                  <td><?php echo renderStatusBadge($row['status']); ?></td>
                  <td><?php echo date('d M Y h:i A', strtotime($row['expiry_time'])); ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <span class="text-muted">Completed Deliveries</span>
          <h3 class="text-success"><?php echo $completedDeliveries; ?></h3>
        </div>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong><i class="bi bi-clock-history text-success"></i> Delivery History</strong></div>
        <div class="list-group list-group-flush">
          <?php while ($row = $deliveryHistory->fetch_assoc()) { ?>
            <a href="view_donations.php?status=<?php echo urlencode($row['status']); ?>" class="list-group-item list-group-item-action">
              <div class="d-flex justify-content-between">
                <strong><?php echo htmlspecialchars($row['food_type']); ?></strong>
                <?php echo renderStatusBadge($row['status']); ?>
              </div>
              <small class="text-muted">Donor: <?php echo htmlspecialchars($row['donor_name']); ?></small>
            </a>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card p-3 mt-4">
  <h5>Next Step:</h5>
  <ul>
    <li><a href="view_donations.php">View Nearby Donations</a></li>
  </ul>
</div>



</body>
</html>
