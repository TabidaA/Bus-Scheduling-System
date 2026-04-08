<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$bus_id = intval($_GET['bus_id']);
$journey_date = $_GET['journey_date'];
$route_id = intval($_GET['route_id']);

// Get all seats for the bus
$stmt = $conn->prepare("SELECT seat_id, seat_number FROM SEAT WHERE bus_id = ? ORDER BY seat_number");
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$result = $stmt->get_result();

$seats = [];

while ($seat = $result->fetch_assoc()) {
    // Check if seat is already booked for this date and route
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING_SEAT bs
                                   JOIN BOOKING b ON bs.booking_id = b.booking_id
                                   WHERE bs.seat_id = ? 
                                   AND b.journey_date = ? 
                                   AND b.route_id = ?
                                   AND b.status != 'cancelled'");
    $check_stmt->bind_param("isi", $seat['seat_id'], $journey_date, $route_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    
    $seats[] = [
        'seat_id' => $seat['seat_id'],
        'seat_number' => $seat['seat_number'],
        'is_booked' => $check_result['count'] > 0
    ];
}

echo json_encode($seats);
?>