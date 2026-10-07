<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}
include('../includes/db_connect.php');
include('../includes/functions.php');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$message = '';
$error = '';

markExpiredDonations($conn);

// Search and filter values are reused in the form so the user can see active filters.
$food_filter = trim($_GET['food_type'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$location_filter = trim($_GET['location'] ?? '');
$date_filter = trim($_GET['date'] ?? '');

// ------------------------------------
// HANDLE VOLUNTEER CLAIM
// ------------------------------------
if ($role === 'volunteer' && isset($_GET['claim'])) {
  $donation_id = (int) $_GET['claim'];

  // Atomic claim: only one volunteer can reserve a donation while it is still Available.
  $claimStmt = $conn->prepare("UPDATE donations2 SET status='Reserved', volunteer_id=? WHERE id=? AND status='Available' AND volunteer_id IS NULL");
  $claimStmt->bind_param("ii", $user_id, $donation_id);
  $claimStmt->execute();

  if ($claimStmt->affected_rows === 1) {
    $donationResult = $conn->query("SELECT d.food_type, d.donor_id, u.name AS volunteer_name FROM donations2 d JOIN users3 u ON u.id = $user_id WHERE d.id = $donation_id");
    $donation = $donationResult->fetch_assoc();
    createNotification($conn, $donation['donor_id'], $donation['volunteer_name'] . " accepted your " . $donation['food_type'] . " donation.", $donation_id, 'accepted');
    header("Location: view_donations.php?success=claimed");
  } else {
    header("Location: view_donations.php?error=already_claimed");
  }
  $claimStmt->close();
  exit();
}

// ------------------------------------
// HANDLE STATUS UPDATE
// ------------------------------------
if (isset($_POST['status']) && isset($_POST['donation_id'])) {
  $donation_id = (int) $_POST['donation_id'];
  $new_status = $_POST['status'];

  $donationResult = $conn->query("
        SELECT d.*, donor.name AS donor_name, volunteer.name AS volunteer_name
        FROM donations2 d
        JOIN users3 donor ON d.donor_id = donor.id
        LEFT JOIN users3 volunteer ON d.volunteer_id = volunteer.id
        WHERE d.id = $donation_id
    ");
  $donation = $donationResult->fetch_assoc();

  $allowedUser = false;
  if ($donation) {
    $allowedUser = $role === 'admin'
      || ($role === 'donor' && $donation['donor_id'] == $user_id && $new_status === 'Cancelled')
      || ($role === 'volunteer' && $donation['volunteer_id'] == $user_id && in_array($new_status, ['Picked Up', 'Delivered']));
  }

  if ($donation && $allowedUser && canChangeDonationStatus($donation['status'], $new_status)) {
    $updateStmt = $conn->prepare("UPDATE donations2 SET status=? WHERE id=?");
    $updateStmt->bind_param("si", $new_status, $donation_id);
    $updateStmt->execute();
    $updateStmt->close();

    if ($new_status === 'Delivered') {
      createNotification($conn, $donation['donor_id'], "Your donation #" . $donation_id . " was delivered.", $donation_id, 'delivered');
    }

    if ($new_status === 'Cancelled') {
      createNotification($conn, $donation['donor_id'], "Donation #" . $donation_id . " was cancelled.", $donation_id, 'cancelled');
      if (!empty($donation['volunteer_id'])) {
        createNotification($conn, $donation['volunteer_id'], "Donation #" . $donation_id . " was cancelled.", $donation_id, 'cancelled');
      }
    }

    header("Location: view_donations.php?success=status");
  } else {
    header("Location: view_donations.php?error=invalid_transition");
  }
  exit();
}

// ------------------------------------
// HANDLE ADMIN DELETE
// ------------------------------------
if ($role === 'admin' && isset($_GET['delete'])) {
  $donation_id = (int) $_GET['delete'];
  $conn->query("DELETE FROM donations2 WHERE id='$donation_id'");
  header("Location: view_donations.php");
  exit();
}

if (isset($_GET['success'])) {
  $message = $_GET['success'] === 'claimed' ? 'Donation reserved successfully.' : 'Donation status updated.';
}
if (isset($_GET['error'])) {
  $error = $_GET['error'] === 'already_claimed'
    ? 'This donation was already claimed by another volunteer.'
    : 'Invalid status change for the current workflow.';
}

// ------------------------------------
// FETCH DONATIONS BASED ON ROLE + FILTERS
// ------------------------------------
$where = [];

if ($role === 'donor') {
  $where[] = "d.donor_id = '$user_id'";
} elseif ($role === 'volunteer') {
  $volunteer_lat = $_SESSION['latitude'];
  $volunteer_lon = $_SESSION['longitude'];
  $where[] = "(d.status = 'Available' OR d.volunteer_id = '$user_id')";
} else {
  $volunteer_lat = 0;
  $volunteer_lon = 0;
}

if ($food_filter !== '') {
  $safe_food = mysqli_real_escape_string($conn, $food_filter);
  $where[] = "d.food_type LIKE '%$safe_food%'";
}
if ($status_filter !== '' && in_array($status_filter, getDonationStatuses())) {
  $safe_status = mysqli_real_escape_string($conn, $status_filter);
  $where[] = "d.status = '$safe_status'";
}
if ($location_filter !== '') {
  $safe_location = mysqli_real_escape_string($conn, $location_filter);
  $where[] = "d.location LIKE '%$safe_location%'";
}
if ($date_filter !== '') {
  $safe_date = mysqli_real_escape_string($conn, $date_filter);
  $where[] = "DATE(d.created_at) = '$safe_date'";
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

if ($role === 'volunteer') {
  $sql = "
        SELECT d.*, d.volunteer_id AS assigned_volunteer_id, donor.name AS donor_name, donor.phone AS donor_phone, volunteer.name AS volunteer_name,
            (6371 * acos(
                cos(radians($volunteer_lat)) * cos(radians(d.latitude)) *
                cos(radians(d.longitude) - radians($volunteer_lon)) +
                sin(radians($volunteer_lat)) * sin(radians(d.latitude))
            )) AS distance
        FROM donations2 d
        JOIN users3 donor ON d.donor_id = donor.id
        LEFT JOIN users3 volunteer ON d.volunteer_id = volunteer.id
        $whereSql
        HAVING distance <= 10 OR assigned_volunteer_id = '$user_id'
        ORDER BY d.created_at DESC
    ";
} else {
  $sql = "
        SELECT d.*, donor.name AS donor_name, donor.phone AS donor_phone, volunteer.name AS volunteer_name
        FROM donations2 d
        JOIN users3 donor ON d.donor_id = donor.id
        LEFT JOIN users3 volunteer ON d.volunteer_id = volunteer.id
        $whereSql
        ORDER BY d.created_at DESC
    ";
}

$result = $conn->query($sql);
if (!$result) {
  die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>View Donations - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="mb-0"><i class="bi bi-list-check text-success"></i> Donations</h3>
      <a href="<?php echo $role; ?>_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>

    <?php if ($message) { ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>
    <?php if ($error) { ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php } ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="GET" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Food Type</label>
            <input type="text" name="food_type" class="form-control" value="<?php echo htmlspecialchars($food_filter); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All Statuses</option>
              <?php foreach (getDonationStatuses() as $statusOption) { ?>
                <option value="<?php echo $statusOption; ?>" <?php if ($status_filter === $statusOption) echo 'selected'; ?>>
                  <?php echo $statusOption; ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($location_filter); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-success"><i class="bi bi-search"></i> Search</button>
            <a href="view_donations.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Clear</a>
          </div>
        </form>
      </div>
    </div>

    <div class="table-responsive card border-0 shadow-sm">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-success">
          <tr>
            <th>ID</th>
            <th>Donor Name</th>
            <th>Phone</th>
            <th>Food Type</th>
            <th>Quantity</th>
            <th>Pickup Address</th>
            <th>Date</th>
            <th>Expiry Time</th>
            <th>Volunteer</th>
            <?php if ($role === 'volunteer') echo "<th>Distance (km)</th>"; ?>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>

        <tbody>
          <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['donor_name'] ?? 'You'); ?></td>
              <td><?php echo htmlspecialchars($row['donor_phone'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($row['food_type']); ?></td>
              <td><?php echo htmlspecialchars($row['quantity']); ?></td>
              <td><?php echo htmlspecialchars($row['location']); ?></td>
              <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
              <td><?php echo date('d M Y h:i A', strtotime($row['expiry_time'])); ?></td>
              <td><?php echo htmlspecialchars($row['volunteer_name'] ?? 'Not assigned'); ?></td>
              <?php if ($role === 'volunteer') echo "<td>" . round($row['distance'], 2) . "</td>"; ?>
              <td><?php echo renderStatusBadge($row['status']); ?></td>

              <td>
                <?php if ($role === 'volunteer') { ?>
                  <?php if ($row['status'] === 'Available') { ?>
                    <a href="?claim=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-hand-thumbs-up"></i> Accept</a>
                  <?php } elseif ($row['volunteer_id'] == $user_id && $row['status'] === 'Reserved') { ?>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="donation_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="status" value="Picked Up">
                      <button class="btn btn-sm btn-warning"><i class="bi bi-box-seam"></i> Picked Up</button>
                    </form>
                  <?php } elseif ($row['volunteer_id'] == $user_id && $row['status'] === 'Picked Up') { ?>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="donation_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="status" value="Delivered">
                      <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Delivered</button>
                    </form>
                  <?php } else { ?>
                    <span class="text-muted">No action</span>
                  <?php } ?>
                <?php } elseif ($role === 'donor') { ?>
                  <?php if (in_array($row['status'], ['Available', 'Reserved'])) { ?>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="donation_id" value="<?php echo $row['id']; ?>">
                      <input type="hidden" name="status" value="Cancelled">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Cancel</button>
                    </form>
                  <?php } else { ?>
                    <span class="text-muted">No action</span>
                  <?php } ?>
                <?php } elseif ($role === 'admin') { ?>
                  <form method="POST">
                    <input type="hidden" name="donation_id" value="<?php echo $row['id']; ?>">
                    <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                      <?php foreach (getDonationStatuses() as $statusOption) { ?>
                        <option value="<?php echo $statusOption; ?>" <?php if ($row['status'] == $statusOption) echo 'selected'; ?>>
                          <?php echo $statusOption; ?>
                        </option>
                      <?php } ?>
                    </select>
                  </form>
                  <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger mt-1"><i class="bi bi-trash"></i> Delete</a>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <?php
    switch ($role) {
      case 'donor':
        echo '<a href="donor_dashboard.php" class="btn btn-secondary mt-3">Back to Donor Dashboard</a>';
        break;
      case 'volunteer':
        echo '<a href="volunteer_dashboard.php" class="btn btn-secondary mt-3">Back to Volunteer Dashboard</a>';
        break;
      case 'admin':
        echo '<a href="admin_dashboard.php" class="btn btn-secondary mt-3">Back to Admin Dashboard</a>';
        break;
      default:
        echo '<a href="../login.php" class="btn btn-secondary mt-3">Go to Login</a>';
        break;
    }
    ?>

  </div>
</body>

</html>
