<?php
function sendSMS($phone, $message) {
    // Simulated SMS sender. This keeps the old project behavior without using a real SMS API.
    $log = "SMS to $phone: $message\n";
    file_put_contents('../sms_log.txt', $log, FILE_APPEND);
}

function getDonationStatuses() {
    return ['Available', 'Reserved', 'Picked Up', 'Delivered', 'Expired', 'Cancelled'];
}

function getValidStatusTransitions() {
    // Business workflow: a donation should move forward through collection and delivery.
    return [
        'Available' => ['Reserved', 'Cancelled', 'Expired'],
        'Reserved' => ['Picked Up', 'Cancelled', 'Expired'],
        'Picked Up' => ['Delivered', 'Cancelled'],
        'Delivered' => [],
        'Expired' => [],
        'Cancelled' => []
    ];
}

function canChangeDonationStatus($currentStatus, $newStatus) {
    if ($currentStatus === $newStatus) {
        return true;
    }

    $transitions = getValidStatusTransitions();
    return isset($transitions[$currentStatus]) && in_array($newStatus, $transitions[$currentStatus]);
}

function getStatusBadgeClass($status) {
    $classes = [
        'Available' => 'success',
        'Reserved' => 'warning text-dark',
        'Picked Up' => 'primary',
        'Delivered' => 'dark',
        'Expired' => 'secondary',
        'Cancelled' => 'danger'
    ];

    return $classes[$status] ?? 'secondary';
}

function renderStatusBadge($status) {
    $safeStatus = htmlspecialchars($status);
    return "<span class='badge bg-" . getStatusBadgeClass($status) . "'>$safeStatus</span>";
}

function createNotification($conn, $userId, $message, $donationId = null, $type = 'general') {
    if (empty($userId)) {
        return;
    }

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, donation_id, type, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $donationId, $type, $message);
    $stmt->execute();
    $stmt->close();
}

function getUnreadNotificationCount($conn, $userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result['total'] ?? 0;
}

function markNotificationsRead($conn, $userId) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

function getRecentNotifications($conn, $userId, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT *
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function markExpiredDonations($conn) {
    // Expiry is automatic for donations that are still active and not completed/cancelled.
    $sql = "
        UPDATE donations2
        SET status = 'Expired'
        WHERE expiry_time < NOW()
          AND status IN ('Available', 'Reserved')
    ";
    $conn->query($sql);
}

?>
