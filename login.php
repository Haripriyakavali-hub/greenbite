<?php
include('includes/db_connect.php');
session_start();
session_unset();
session_destroy();
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - GreenBite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-md-5">
        <div class="card p-4 shadow">
          <h3 class="text-center mb-3">User Login</h3>

          <?php
          if (isset($_POST['login'])) {
              // Sanitize inputs
              $email = trim($_POST['email']);
              $password = trim($_POST['password']);

              // Prepared statement (safer against SQL injection)
              $stmt = $conn->prepare("SELECT * FROM users3 WHERE email = ?");
              $stmt->bind_param("s", $email);
              $stmt->execute();
              $result = $stmt->get_result();

              if ($result->num_rows > 0) {
                  $row = $result->fetch_assoc();

                  if (password_verify($password, $row['password'])) {
                      // Store session data
                      $_SESSION['user_id'] = $row['id'];
                      $_SESSION['user_name'] = $row['name'];
                      $_SESSION['user_role'] = $row['role'];
                      $_SESSION['latitude'] = $row['latitude'];
                      $_SESSION['longitude'] = $row['longitude'];

                      // Redirect based on role
                      if ($row['role'] == 'donor') {
                          header("Location: pages/donor_dashboard.php");
                      } elseif ($row['role'] == 'volunteer') {
                          header("Location: pages/volunteer_dashboard.php");
                      } else {
                          header("Location: pages/admin_dashboard.php");
                      }
                      exit();
                  } else {
                      echo "<div class='alert alert-danger'>❌ Invalid password.</div>";
                  }
              } else {
                  echo "<div class='alert alert-danger'>⚠️ No account found with this email.</div>";
              }

              $stmt->close();
          }
          ?>

          <form method="POST">
            <div class="mb-3">
              <label>Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>

            <div class="mb-3">
              <label>Password</label>
              <input type="password" class="form-control" name="password" required>
            </div>

            <button type="submit" name="login" class="btn btn-success w-100">Login</button>
            <p class="text-center mt-3">
              Don't have an account? <a href="register.php">Register</a>
            </p>
          </form>

        </div>
      </div>
    </div>
  </div>
</body>
</html>
