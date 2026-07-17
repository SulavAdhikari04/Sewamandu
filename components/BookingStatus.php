<?php

function ensureBookingLocationColumns($conn) {
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL");
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS location_label VARCHAR(255) NULL");
}

function ensureBookingGroupColumn($conn) {
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_group_id VARCHAR(36) NULL");
}

function createBookingGroupId() {
    if (function_exists('random_bytes')) {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
    return uniqid('grp_', true);
}

/**
 * Confirm a pending booking for this provider and release sibling requests
 * in the same booking group so they leave other providers' dashboards.
 */
function acceptBookingRequest($conn, $booking_id, $provider_id) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT status, booking_group_id FROM bookings WHERE id = ? AND provider_id = ? FOR UPDATE"
        );
        if (!$stmt) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Could not verify booking.'];
        }
        $stmt->bind_param('ii', $booking_id, $provider_id);
        $stmt->execute();
        $stmt->bind_result($current_status, $group_id);
        if (!$stmt->fetch()) {
            $stmt->close();
            $conn->rollback();
            return ['success' => false, 'message' => 'Booking not found.'];
        }
        $stmt->close();

        if ($current_status !== 'pending_provider') {
            $conn->rollback();
            if ($current_status === 'taken_by_other') {
                return ['success' => false, 'message' => 'Another provider already accepted this request.'];
            }
            return ['success' => false, 'message' => 'This booking is no longer pending.'];
        }

        $confirm = $conn->prepare(
            "UPDATE bookings SET status = 'confirmed' WHERE id = ? AND provider_id = ? AND status = 'pending_provider'"
        );
        if (!$confirm) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Could not confirm booking.'];
        }
        $confirm->bind_param('ii', $booking_id, $provider_id);
        $confirm->execute();
        $confirmed = $confirm->affected_rows > 0;
        $confirm->close();

        if (!$confirmed) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Another provider already accepted this request.'];
        }

        if ($group_id) {
            $release = $conn->prepare(
                "UPDATE bookings SET status = 'taken_by_other'
                 WHERE booking_group_id = ? AND id != ? AND status = 'pending_provider'"
            );
            if (!$release) {
                $conn->rollback();
                return ['success' => false, 'message' => 'Could not update other providers\' requests.'];
            }
            $release->bind_param('si', $group_id, $booking_id);
            $release->execute();
            $release->close();
        }

        $conn->commit();
        return ['success' => true, 'message' => 'Booking approved and confirmed.'];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Could not accept booking.'];
    }
}

function parseBookingCoordinates($latitude, $longitude) {
    if ($latitude === '' || $latitude === null || $longitude === '' || $longitude === null) {
        return null;
    }
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return null;
    }
    $lat = (float) $latitude;
    $lng = (float) $longitude;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return null;
    }
    return ['latitude' => $lat, 'longitude' => $lng];
}

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
            return 'Confirmed';
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
        case 'taken_by_other':
            return 'Accepted by Another Provider';
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

    if (!in_array($current_status, ['confirmed', 'pending_admin'], true)) {
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
            return 'status-badge status-confirmed';
        case 'confirmed':
            return 'status-badge status-confirmed';
        case 'completed':
            return 'status-badge status-completed';
        case 'denied':
        case 'rejected_by_provider':
        case 'rejected_by_admin':
        case 'taken_by_other':
            return 'status-badge status-rejected';
        default:
            return 'status-badge';
    }
}
