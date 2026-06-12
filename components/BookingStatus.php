<?php

function getBlockingBookingStatuses() {
    return [
        'pending_provider',
        'pending_admin',
        'confirmed',
    ];
}

function getBookableDateOptions($count = 14, $max_days_ahead = 30) {
    $options = [];
    $today = new DateTime('today');
    $limit = min($count, $max_days_ahead + 1);

    for ($i = 0; $i < $limit; $i++) {
        $date = (clone $today)->modify("+{$i} days");
        $options[] = [
            'value' => $date->format('Y-m-d'),
            'weekday' => $date->format('D'),
            'day' => (int) $date->format('j'),
            'month' => $date->format('M'),
            'caption' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $date->format('l')),
        ];
    }

    return $options;
}

function getServiceTimeOptions($start_hour = 8, $end_hour = 18) {
    $options = [];

    for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
        $value = sprintf('%02d:00', $hour);
        $options[] = [
            'value' => $value,
            'label' => date('g:i A', strtotime($value)),
        ];
    }

    return $options;
}

function validateServiceDate($date, $max_days_ahead = 30) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $selected = DateTime::createFromFormat('Y-m-d', $date);
    $errors = DateTime::getLastErrors();
    if (
        !$selected
        || ($errors['warning_count'] ?? 0) > 0
        || ($errors['error_count'] ?? 0) > 0
    ) {
        return false;
    }

    $today = new DateTime('today');
    $max = (clone $today)->modify("+{$max_days_ahead} days");

    return $selected >= $today && $selected <= $max;
}

function validateServiceTime($time, $start_hour = 8, $end_hour = 18) {
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    $hour = (int) substr($time, 0, 2);
    $minute = (int) substr($time, 3, 2);

    return $minute === 0 && $hour >= $start_hour && $hour <= $end_hour;
}

function formatServiceDateTime($date, $time) {
    $date_time = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    if (!$date_time) {
        return trim($date . ' ' . $time);
    }

    return $date_time->format('l, j M Y') . ' at ' . $date_time->format('g:i A');
}

function isProviderTimeSlotAvailable($conn, $provider_id, $service_date, $service_time) {
    $statuses = getBlockingBookingStatuses();
    $placeholders = implode(', ', array_fill(0, count($statuses), '?'));

    $sql = "SELECT id FROM bookings
            WHERE provider_id = ?
              AND service_date = ?
              AND service_time = ?
              AND status IN ($placeholders)
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $types = 'iss' . str_repeat('s', count($statuses));
    $params = array_merge([$provider_id, $service_date, $service_time], $statuses);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->store_result();
    $available = $stmt->num_rows === 0;
    $stmt->close();

    return $available;
}

function getBookingStatusLabel($status) {
    switch ($status) {
        case 'pending_provider':
            return 'Waiting for Provider';
        case 'pending_admin':
            return 'Waiting for Admin';
        case 'confirmed':
            return 'Confirmed';
        case 'completed':
            return 'Completed';
        case 'denied':
            return 'Denied';
        case 'rejected_by_provider':
            return 'Rejected by Provider';
        case 'rejected_by_admin':
            return 'Rejected by Admin';
        default:
            return ucfirst(str_replace('_', ' ', $status));
    }
}

function updateProviderBookingCompletion($conn, $booking_id, $provider_id, $completed) {
    $check = $conn->prepare("SELECT status FROM bookings WHERE id = ? AND provider_id = ?");
    if (!$check) {
        return ['success' => false, 'message' => 'Could not verify booking.'];
    }

    $check->bind_param('ii', $booking_id, $provider_id);
    $check->execute();
    $check->bind_result($current_status);
    if (!$check->fetch()) {
        $check->close();
        return ['success' => false, 'message' => 'Booking not found for this provider.'];
    }
    $check->close();

    if ($current_status !== 'confirmed') {
        return ['success' => false, 'message' => 'Only confirmed bookings can be marked done or not done.'];
    }

    $status = $completed ? 'completed' : 'denied';
    $served = $completed ? 1 : 0;

    $stmt = $conn->prepare(
        "UPDATE bookings SET status = ?, served = ? WHERE id = ? AND provider_id = ?"
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not prepare booking update.'];
    }

    $stmt->bind_param('siii', $status, $served, $booking_id, $provider_id);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Could not update booking: ' . $error];
    }

    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return ['success' => false, 'message' => 'Booking could not be updated.'];
    }

    return [
        'success' => true,
        'message' => $completed ? 'Booking marked as completed.' : 'Booking marked as denied.',
        'status' => $status,
    ];
}

function getBookingStatusBadgeClass($status) {
    switch ($status) {
        case 'pending_provider':
            return 'status-badge status-pending-provider';
        case 'pending_admin':
            return 'status-badge status-pending-admin';
        case 'confirmed':
            return 'status-badge status-confirmed';
        case 'completed':
            return 'status-badge status-completed';
        case 'denied':
        case 'rejected_by_provider':
        case 'rejected_by_admin':
            return 'status-badge status-rejected';
        default:
            return 'status-badge';
    }
}
