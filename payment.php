<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = 'Payment';

// Get booking details
if (!isset($_GET['booking_id'])) {
    header("Location: my-bookings.php");
    exit();
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT b.*, r.route_name, r.origin_city, r.destination_city, r.departure_time, 
                        bus.bus_number, bus.bus_model
                        FROM BOOKING b 
                        JOIN ROUTE r ON b.route_id = r.route_id 
                        JOIN BUS bus ON b.bus_id = bus.bus_id
                        WHERE b.booking_id = ? AND b.user_id = ?");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    setFlashMessage('danger', 'Booking not found');
    header("Location: my-bookings.php");
    exit();
}

// Get booked seats
$stmt2 = $conn->prepare("SELECT s.seat_number FROM BOOKING_SEAT bs 
                         JOIN SEAT s ON bs.seat_id = s.seat_id 
                         WHERE bs.booking_id = ?");
$stmt2->bind_param("i", $booking_id);
$stmt2->execute();
$result = $stmt2->get_result();
$seat_numbers = [];
while ($row = $result->fetch_assoc()) {
    $seat_numbers[] = $row['seat_number'];
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = sanitize($_POST['payment_method']);
    $transaction_id = 'TXN' . date('YmdHis') . rand(1000, 9999);
    
    // Create payment record
    $stmt = $conn->prepare("INSERT INTO PAYMENT (booking_id, user_id, amount, payment_method, transaction_id, payment_status) 
                           VALUES (?, ?, ?, ?, ?, 'completed')");
    $stmt->bind_param("iidss", $booking_id, $user_id, $booking['total_amount'], $payment_method, $transaction_id);
    
    if ($stmt->execute()) {
        // Update booking status
        $update = $conn->prepare("UPDATE BOOKING SET status = 'confirmed' WHERE booking_id = ?");
        $update->bind_param("i", $booking_id);
        $update->execute();
        
        setFlashMessage('success', 'Payment successful! Your booking is confirmed.');
        header("Location: my-bookings.php?booking_id=" . $booking_id);
        exit();
    } else {
        setFlashMessage('danger', 'Payment failed. Please try again.');
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-credit-card"></i> Complete Payment</h4>
            </div>
            <div class="card-body">
                <!-- Booking Summary -->
                <div class="alert alert-info">
                    <h5>Booking Details</h5>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Booking ID:</strong> #<?php echo $booking['booking_id']; ?></p>
                            <p><strong>Route:</strong> <?php echo $booking['route_name']; ?></p>
                            <p><strong>From:</strong> <?php echo $booking['origin_city']; ?></p>
                            <p><strong>To:</strong> <?php echo $booking['destination_city']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Bus:</strong> <?php echo $booking['bus_number']; ?></p>
                            <p><strong>Journey Date:</strong> <?php echo formatDate($booking['journey_date']); ?></p>
                            <p><strong>Departure:</strong> <?php echo formatTime($booking['departure_time']); ?></p>
                            <p><strong>Seats:</strong> <?php echo implode(', ', $seat_numbers); ?></p>
                        </div>
                    </div>
                    <hr>
                    <h4 class="text-center mb-0">
                        <strong>Total Amount: </strong>
                        <span class="text-primary">৳<?php echo number_format($booking['total_amount'], 2); ?></span>
                    </h4>
                </div>
                
                <!-- Payment Form -->
                <form method="POST" action="" id="paymentForm">
                    <h5 class="mb-3">Select Payment Method</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <input type="radio" name="payment_method" value="bKash" id="bkash" class="form-check-input" required>
                                    <label for="bkash" class="form-check-label w-100">
                                        <div class="py-3">
                                            <i class="fas fa-mobile-alt fa-3x text-danger mb-2"></i>
                                            <h5>bKash</h5>
                                            <p class="text-muted small">Pay using bKash mobile wallet</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <input type="radio" name="payment_method" value="Nagad" id="nagad" class="form-check-input" required>
                                    <label for="nagad" class="form-check-label w-100">
                                        <div class="py-3">
                                            <i class="fas fa-mobile-alt fa-3x text-warning mb-2"></i>
                                            <h5>Nagad</h5>
                                            <p class="text-muted small">Pay using Nagad mobile wallet</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <input type="radio" name="payment_method" value="Credit Card" id="card" class="form-check-input" required>
                                    <label for="card" class="form-check-label w-100">
                                        <div class="py-3">
                                            <i class="fas fa-credit-card fa-3x text-primary mb-2"></i>
                                            <h5>Credit/Debit Card</h5>
                                            <p class="text-muted small">Pay using your card</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <input type="radio" name="payment_method" value="Cash" id="cash" class="form-check-input" required>
                                    <label for="cash" class="form-check-label w-100">
                                        <div class="py-3">
                                            <i class="fas fa-money-bill-wave fa-3x text-success mb-2"></i>
                                            <h5>Cash</h5>
                                            <p class="text-muted small">Pay at counter</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-check-circle"></i> Complete Payment
                        </button>
                        <a href="my-bookings.php" class="btn btn-secondary btn-lg px-5">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
                
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> This is a demo payment system. In production, you would integrate actual payment gateways.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add click handlers to card labels
document.querySelectorAll('.card-body label').forEach(label => {
    label.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>