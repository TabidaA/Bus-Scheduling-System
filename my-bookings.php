<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = 'My Bookings';

$user_id = $_SESSION['user_id'];

// Get all bookings for the user
$query = "SELECT b.*, r.route_name, r.origin_city, r.destination_city, r.departure_time,
          bus.bus_number, bus.bus_model, p.payment_status, p.payment_method, p.transaction_id
          FROM BOOKING b 
          JOIN ROUTE r ON b.route_id = r.route_id 
          JOIN BUS bus ON b.bus_id = bus.bus_id 
          LEFT JOIN PAYMENT p ON b.booking_id = p.booking_id
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();

// Get specific booking details if viewing
$view_booking = null;
$booked_seats = [];
if (isset($_GET['booking_id'])) {
    $view_id = intval($_GET['booking_id']);
    $stmt = $conn->prepare("SELECT b.*, r.*, bus.bus_number, bus.bus_model, p.payment_status, p.payment_method, p.transaction_id
                            FROM BOOKING b 
                            JOIN ROUTE r ON b.route_id = r.route_id 
                            JOIN BUS bus ON b.bus_id = bus.bus_id
                            LEFT JOIN PAYMENT p ON b.booking_id = p.booking_id
                            WHERE b.booking_id = ? AND b.user_id = ?");
    $stmt->bind_param("ii", $view_id, $user_id);
    $stmt->execute();
    $view_booking = $stmt->get_result()->fetch_assoc();
    
    if ($view_booking) {
        // Get booked seats
        $stmt2 = $conn->prepare("SELECT s.seat_number FROM BOOKING_SEAT bs 
                                 JOIN SEAT s ON bs.seat_id = s.seat_id 
                                 WHERE bs.booking_id = ?");
        $stmt2->bind_param("i", $view_id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        while ($row = $result->fetch_assoc()) {
            $booked_seats[] = $row['seat_number'];
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>My Bookings</h2>
        <p class="text-muted">View and manage your bus ticket bookings</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="search-routes.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Booking
        </a>
    </div>
</div>

<!-- Statistics -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Bookings</h6>
                <h3><?php echo $bookings->num_rows; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Confirmed</h6>
                <h3><?php 
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING WHERE user_id = ? AND status = 'confirmed'");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>Pending</h6>
                <h3><?php 
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING WHERE user_id = ? AND status = 'pending'");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>Total Spent</h6>
                <h3>৳<?php 
                    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM BOOKING WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo number_format($stmt->get_result()->fetch_assoc()['total'], 0);
                ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Bookings List -->
<?php if ($bookings->num_rows > 0): ?>
    <?php while($booking = $bookings->fetch_assoc()): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">
                                    <?php echo $booking['route_name']; ?>
                                    <span class="badge bg-<?php 
                                        echo $booking['status'] == 'confirmed' ? 'success' : 
                                            ($booking['status'] == 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </h5>
                                <p class="text-muted mb-0">Booking ID: #<?php echo $booking['booking_id']; ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <i class="fas fa-map-marker-alt text-success"></i> 
                                    <strong>From:</strong> <?php echo $booking['origin_city']; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-map-marker-alt text-danger"></i> 
                                    <strong>To:</strong> <?php echo $booking['destination_city']; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-calendar"></i> 
                                    <strong>Date:</strong> <?php echo formatDate($booking['journey_date']); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <i class="fas fa-bus"></i> 
                                    <strong>Bus:</strong> <?php echo $booking['bus_number']; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-clock"></i> 
                                    <strong>Departure:</strong> <?php echo formatTime($booking['departure_time']); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-users"></i> 
                                    <strong>Passengers:</strong> <?php echo $booking['total_passengers']; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($booking['payment_status']): ?>
                            <p class="mb-0 mt-2">
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i> Paid via <?php echo $booking['payment_method']; ?>
                                </span>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        <h3 class="text-primary mb-3">৳<?php echo number_format($booking['total_amount'], 2); ?></h3>
                        <a href="?booking_id=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-info mb-1">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($booking['status'] == 'pending'): ?>
                            <a href="payment.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-success mb-1">
                                <i class="fas fa-credit-card"></i> Complete Payment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
            <h4>No Bookings Yet</h4>
            <p class="text-muted">You haven't made any bookings. Start your journey today!</p>
            <a href="search-routes.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Search Routes
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- View Booking Modal -->
<?php if ($view_booking): ?>
<div class="modal fade show" id="viewBookingModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-ticket-alt"></i> Booking Details - #<?php echo $view_booking['booking_id']; ?>
                </h5>
                <a href="my-bookings.php" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
                <!-- Booking Status -->
                <div class="text-center mb-4">
                    <h3>
                        <span class="badge bg-<?php 
                            echo $view_booking['status'] == 'confirmed' ? 'success' : 
                                ($view_booking['status'] == 'pending' ? 'warning' : 'danger'); 
                        ?>" style="font-size: 1.5rem;">
                            <?php echo ucfirst($view_booking['status']); ?>
                        </span>
                    </h3>
                </div>
                
                <!-- Journey Details -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2"><i class="fas fa-route"></i> Journey Information</h6>
                        <p><strong>Route:</strong> <?php echo $view_booking['route_name']; ?></p>
                        <p><strong>From:</strong> <?php echo $view_booking['origin_city']; ?></p>
                        <p><strong>To:</strong> <?php echo $view_booking['destination_city']; ?></p>
                        <p><strong>Journey Date:</strong> <?php echo formatDate($view_booking['journey_date']); ?></p>
                        <p><strong>Departure Time:</strong> <?php echo formatTime($view_booking['departure_time']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2"><i class="fas fa-bus"></i> Bus Details</h6>
                        <p><strong>Bus Number:</strong> <?php echo $view_booking['bus_number']; ?></p>
                        <p><strong>Bus Model:</strong> <?php echo $view_booking['bus_model']; ?></p>
                        <p><strong>Seat Numbers:</strong> <?php echo implode(', ', $booked_seats); ?></p>
                        <p><strong>Total Seats:</strong> <?php echo $view_booking['seat_count']; ?></p>
                        <p><strong>Passengers:</strong> <?php echo $view_booking['total_passengers']; ?></p>
                    </div>
                </div>
                
                <!-- Passenger Details -->
                <?php if ($view_booking['passenger_names']): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2"><i class="fas fa-users"></i> Passenger Details</h6>
                        <p><strong>Names:</strong><br><?php echo nl2br($view_booking['passenger_names']); ?></p>
                        <?php if ($view_booking['passenger_phones']): ?>
                        <p><strong>Contact:</strong> <?php echo $view_booking['passenger_phones']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Payment Details -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2"><i class="fas fa-credit-card"></i> Payment Information</h6>
                        <p><strong>Total Amount:</strong> 
                            <span class="text-primary fs-4">৳<?php echo number_format($view_booking['total_amount'], 2); ?></span>
                        </p>
                        <?php if ($view_booking['payment_status']): ?>
                            <p><strong>Payment Status:</strong> 
                                <span class="badge bg-success">Paid</span>
                            </p>
                            <p><strong>Payment Method:</strong> <?php echo $view_booking['payment_method']; ?></p>
                            <p><strong>Transaction ID:</strong> <?php echo $view_booking['transaction_id']; ?></p>
                        <?php else: ?>
                            <p><strong>Payment Status:</strong> 
                                <span class="badge bg-warning">Pending</span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Booking Date -->
                <div class="text-muted small">
                    <p class="mb-0"><strong>Booked on:</strong> <?php echo formatDate($view_booking['booking_date']); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($view_booking['status'] == 'pending'): ?>
                    <a href="payment.php?booking_id=<?php echo $view_booking['booking_id']; ?>" class="btn btn-success">
                        <i class="fas fa-credit-card"></i> Complete Payment
                    </a>
                <?php endif; ?>
                <a href="my-bookings.php" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>