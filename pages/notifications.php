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

markExpiredDonations($conn);

if (isset($_POST['mark_read'])) {
    markNotificationsRead($conn, $user_id);
    header("Location: notifications.php");
    exit();
}

$notifications = $conn->query("
    SELECT *
    FROM notifications
    WHERE user_id = '$user_id'
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Notifications - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-bell text-success"></i> Notifications</h3>
    <a href="<?php echo $role; ?>_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
  </div>

  <form method="POST" class="mb-3">
    <button type="submit" name="mark_read" class="btn btn-success btn-sm"><i class="bi bi-check2-all"></i> Mark All Read</button>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
      <?php if ($notifications->num_rows === 0) { ?>
        <div class="list-group-item text-muted">No notifications yet.</div>
      <?php } ?>

      <?php while ($row = $notifications->fetch_assoc()) { ?>
        <div class="list-group-item <?php if ($row['is_read'] == 0) echo 'list-group-item-warning'; ?>">
          <div class="d-flex justify-content-between">
            <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['type']))); ?></strong>
            <small class="text-muted"><?php echo date('d M Y h:i A', strtotime($row['created_at'])); ?></small>
          </div>
          <p class="mb-0"><?php echo htmlspecialchars($row['message']); ?></p>
        </div>
      <?php } ?>
    </div>
  </div>
</div>
</body>
</html>
