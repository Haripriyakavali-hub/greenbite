# GreenBite

GreenBite is a simple PHP and MySQL food donation and waste-reduction platform. It connects food donors with nearby volunteers so surplus food can be collected before it goes to waste.

## Problem Solved

Many homes, hostels, and events have extra food, while nearby people may need it. GreenBite provides a basic workflow for posting donations, allowing volunteers to accept them, and tracking donation status through pickup and delivery.

## Main Features

- User registration and login
- Role-based dashboards for donors, volunteers, and admins
- Donors can create and manage food donations
- Volunteers can view nearby available donations using stored latitude and longitude values
- Volunteers can accept available donations
- Donation statuses: Available, Reserved, Picked Up, Delivered, Expired, Cancelled
- Automatic expiry handling based on donation expiry time
- Donation search and filters by food type, status, location, and date
- In-app notifications with unread highlighting
- Simulated SMS logging for nearby volunteer alerts
- Admin views for users and donations

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- Bootstrap
- JavaScript for browser geolocation on registration

## Local XAMPP Setup

1. Install and start XAMPP.
2. Start Apache.
3. Start MySQL.
4. Place the project folder at:

```text
C:\xamppp\htdocs\greenbite
```

5. Open the app in a browser:

```text
http://localhost/greenbite/login.php
```

## Database Setup

Create a MySQL database named:

```sql
greenbite_db
```

Run the SQL script:

```text
greenbite_feature_updates.sql
```

The project expects these main tables:

- `users3`
- `donations2`
- `notifications`

## Database Configuration

Local defaults are stored in `includes/db_connect.php` for XAMPP:

- Host: `localhost`
- Port: `3307`
- Database: `greenbite_db`
- User: `root`
- Password: empty

For future deployment, the same file can read these environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

Do not commit real production passwords or cloud credentials.

## Project Structure

```text
greenbite/
  includes/
    db_connect.php
    functions.php
  pages/
    admin_dashboard.php
    donation_form.php
    donor_dashboard.php
    notifications.php
    view_donations.php
    view_users.php
    volunteer_dashboard.php
  index.html
  login.php
  logout.php
  register.php
  greenbite_feature_updates.sql
```

## Future Deployment

AWS deployment is planned for a future task. This project is not deployed to AWS in the current version.
