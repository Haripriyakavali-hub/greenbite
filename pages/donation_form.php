<?php
session_start();
include('../includes/db_connect.php');
include('../includes/functions.php'); // include once at top

// Restrict access to donors only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'donor') {
    header("Location: ../login.php");
    exit();
}

// Keep statuses correct each time a donor opens the donation page.
markExpiredDonations($conn);

if (isset($_POST['submit'])) {
    // Step 1: Collect form data
    $food_type = mysqli_real_escape_string($conn, $_POST['food_type']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);
    $expiry_time = mysqli_real_escape_string($conn, $_POST['expiry_time']);
    $donor_id = $_SESSION['user_id'];

    if (strtotime($expiry_time) <= time()) {
        // A donor should not create a new donation that is already expired.
        echo "<div class='alert alert-danger text-center'>Expiry time must be in the future.</div>";
    } else {
    // Step 2: Insert donation once. New donations start as Available.
    $query = "INSERT INTO donations2 (donor_id, food_type, quantity, location, phone, latitude, longitude, expiry_time, status)
              VALUES ('$donor_id', '$food_type', '$quantity', '$location', '$phone', '$latitude', '$longitude', '$expiry_time', 'Available')";

    if (mysqli_query($conn, $query)) {
        echo "<div class='alert alert-success text-center'>✅ Donation added successfully!</div>";

        // Step 3: Get new donation info
        $donation_id = mysqli_insert_id($conn);
        $donation_lat = $latitude;
        $donation_lng = $longitude;

        // Step 4: Find volunteers within 10 km radius
        $sql = "SELECT id, phone FROM users3 
                WHERE role='volunteer' AND 
                (6371 * acos(
                    cos(radians($donation_lat)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians($donation_lng)) + 
                    sin(radians($donation_lat)) * 
                    sin(radians(latitude))
                )) <= 10";

        $result = mysqli_query($conn, $sql);

        // Step 5: Notify volunteers
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $v_phone = $row['phone'];
                $message = "📦 New donation available near you! Check GreenBite.";
                sendSMS($v_phone, $message);
                createNotification($conn, $row['id'], "New donation available near you. Food type: $food_type", $donation_id, 'new_donation');
            }
            echo "<div class='alert alert-info text-center mt-2'>📢 Nearby volunteers notified successfully!</div>";
        } else {
            echo "<div class='alert alert-warning text-center mt-2'>No volunteers found nearby (within 10 km).</div>";
        }
    } else {
        echo "<div class='alert alert-danger text-center'>❌ Error: " . mysqli_error($conn) . "</div>";
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Donate Food - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="card shadow p-4">
    <h3 class="text-success mb-4 text-center">🍲 Donate Food</h3>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Food Type</label>
        <input type="text" name="food_type" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Quantity</label>
        <input type="text" name="quantity" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Pickup Address</label>
        <textarea name="location" class="form-control" required></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-control" required>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Latitude</label>
          <input type="text" name="latitude" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Longitude</label>
          <input type="text" name="longitude" class="form-control" required>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Expiry Time</label>
        <input type="datetime-local" name="expiry_time" class="form-control" required>
      </div>

      <div class="text-center">
        <button type="submit" name="submit" class="btn btn-success px-4"><i class="bi bi-check-circle"></i> Submit Donation</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
