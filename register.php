<?php include('includes/db_connect.php'); ?>

<!DOCTYPE html>
<html>

<head>
  <title>Register - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script>
    // Auto-fill latitude & longitude using browser geolocation
    window.onload = function() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
          document.getElementById("latitude").value = position.coords.latitude;
          document.getElementById("longitude").value = position.coords.longitude;
        }, function(error) {
          alert("Unable to get your location. Please allow location access or enter manually.");
        });
      } else {
        alert("Geolocation is not supported by your browser. Please enter coordinates manually.");
      }
    }
  </script>
</head>

<body class="bg-light">
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card p-4 shadow">
          <h3 class="text-center mb-3">User Registration</h3>

          <?php
          if (isset($_POST['register'])) {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'];
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];

            $sql = "INSERT INTO users3 (name, email, password, role, phone, address, latitude, longitude)
        VALUES ('$name', '$email', '$password', '$role', '$phone', '$address', '$latitude', '$longitude')";



            if ($conn->query($sql) === TRUE) {
              echo "<div class='alert alert-success'>Registration successful! <a href='login.php'>Login here</a>.</div>";
            } else {
              echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
            }
          }
          ?>

          <form method="POST">
            <div class="mb-3">
              <label>Name</label>
              <input type="text" class="form-control" name="name" required>
            </div>

            <div class="mb-3">
              <label>Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>

            <div class="mb-3">
              <label>Password</label>
              <input type="password" class="form-control" name="password" required>
            </div>

            <div class="mb-3">
              <label>Role</label>
              <select name="role" class="form-control" required>
                <option value="donor">Donor</option>
                <option value="volunteer">Volunteer</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            <div class="mb-3">
              <label>Phone</label>
              <input type="text" class="form-control" name="phone">
            </div>

            <div class="mb-3">
              <label>Address</label>
              <textarea class="form-control" name="address"></textarea>
            </div>

            <!-- 🌍 New Location Fields -->
            <div class="row">
              <div class="col-md-6 mb-3">
                <label>Latitude</label>
                <input type="text" class="form-control" id="latitude" name="latitude" placeholder="e.g., 17.3850" required>
              </div>
              <div class="col-md-6 mb-3">
                <label>Longitude</label>
                <input type="text" class="form-control" id="longitude" name="longitude" placeholder="e.g., 78.4867" required>
              </div>
            </div>

            <button type="submit" name="register" class="btn btn-success w-100">Register</button>
            <p class="text-center mt-3">Already have an account? <a href="login.php">Login</a></p>

          </form>
        </div>
      </div>
    </div>
  </div>
</body>

</html>