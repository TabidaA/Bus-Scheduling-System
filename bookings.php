<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireAdmin();

$page_title = 'Manage Bookings';

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $booking_id = intval($_POST['booking_id']);
        $status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE BOOKING SET status = ? WHERE booking_id = ?");
        $stmt->bind_param("si", $status, $booking_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Booking status updated successfully!');
        } else {
            setFlashMessage('danger', 'Failed to update status');
        }
        header("Location: bookings.php");
        exit();
    }
}

// Filter bookings
$where = "1=1";
$params = [];
$types = "";

if (isset($_GET['status']) && $_GET['status'] != '') {
    $where .= " AND b.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

if (isset($_GET['date']) && $_GET['date'] != '') {
    $where .= " AND b.journey_date = ?";
    $params[] = $_GET['date'];
    $types .= "s";
}

// Get all bookings with details
$query = "SELECT b.*, u.username, u.email, r.route_name, r.origin_city, r.destination_city, 
          bus.bus_number, p.payment_status, p.payment_method, p.amount as payment_amount
          FROM BOOKING b 
          JOIN USER u ON b.user_id = u.user_id 
          JOIN ROUTE r ON b.route_id = r.route_id 
          JOIN BUS bus ON b.bus_id = bus.bus_id 
          LEFT JOIN PAYMENT p ON b.booking_id = p.booking_id
          WHERE $where
          ORDER BY b.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $bookings = $stmt->get_result();
} else {
    $bookings = $conn->query($query);
}

// Get booking details if viewing specific booking
$view_booking = null;
$booked_seats = [];
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $stmt = $conn->prepare("SELECT b.*, u.username, u.email, u.phone_number, r.*, bus.bus_number, bus.bus_model
                            FROM BOOKING b 
                            JOIN USER u ON b.user_id = u.user_id 
                            JOIN ROUTE r ON b.route_id = r.route_id 
                            JOIN BUS bus ON b.bus_id = bus.bus_id
                            WHERE b.booking_id = ?");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $view_booking = $stmt->get_result()->fetch_assoc();
    
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

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Manage Bookings</h2>
    </div>
</div>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo isset($_GET['status']) && $_GET['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Journey Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo isset($_GET['date']) ? $_GET['date'] : ''; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="bookings.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Route</th>
                        <th>Bus</th>
                        <th>Journey Date</th>
                        <th>Passengers</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bookings->num_rows > 0): ?>
                        <?php while($booking = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $booking['booking_id']; ?></strong></td>
                            <td>
                                <?php echo $booking['username']; ?><br>
                                <small class="text-muted"><?php echo $booking['email']; ?></small>
                            </td>
                            <td>
                                <?php echo $booking['route_name']; ?><br>
                                <small class="text-muted"><?php echo $booking['origin_city'] . ' → ' . $booking['destination_city']; ?></small>
                            </td>
                            <td><?php echo $booking['bus_number']; ?></td>
                            <td><?php echo formatDate($booking['journey_date']); ?></td>
                            <td><?php echo $booking['total_passengers']; ?></td>
                            <td>৳<?php echo number_format($booking['total_amount'], 2); ?></td>
                            <td>
                                <?php if ($booking['payment_status']): ?>
                                    <span class="badge bg-success"><?php echo ucfirst($booking['payment_status']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Not Paid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $booking['status'] == 'confirmed' ? 'success' : 
                                        ($booking['status'] == 'pending' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?view=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        Status
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $booking['booking_id']; ?>, 'pending')">Pending</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $booking['booking_id']; ?>, 'confirmed')">Confirmed</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $booking['booking_id']; ?>, 'cancelled')">Cancelled</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">No bookings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Booking Modal -->
<?php if ($view_booking): ?>
<div class="modal fade show" id="viewBookingModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details - #<?php echo $view_booking['booking_id']; ?></h5>
                <a href="bookings.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Customer Information</h6>
                        <p><strong>Name:</strong> <?php echo $view_booking['username']; ?></p>
                        <p><strong>Email:</strong> <?php echo $view_booking['email']; ?></p>
                        <p><strong>Phone:</strong> <?php echo $view_booking['phone_number']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Booking Information</h6>
                        <p><strong>Booking Date:</strong> <?php echo formatDate($view_booking['booking_date']); ?></p>
                        <p><strong>Journey Date:</strong> <?php echo formatDate($view_booking['journey_date']); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $view_booking['status'] == 'confirmed' ? 'success' : 
                                    ($view_booking['status'] == 'pending' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($view_booking['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">Route & Bus Details</h6>
                        <p><strong>Route:</strong> <?php echo $view_booking['route_name']; ?></p>
                        <p><strong>From:</strong> <?php echo $view_booking['origin_city']; ?> 
                           <strong>To:</strong> <?php echo $view_booking['destination_city']; ?></p>
                        <p><strong>Bus:</strong> <?php echo $view_booking['bus_number']; ?> (<?php echo $view_booking['bus_model']; ?>)</p>
                        <p><strong>Departure Time:</strong> <?php echo formatTime($view_booking['departure_time']); ?></p>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Passenger Details</h6>
                        <p><strong>Total Passengers:</strong> <?php echo $view_booking['total_passengers']; ?></p>
                        <?php if ($view_booking['passenger_names']): ?>
                        <p><strong>Names:</strong><br><?php echo nl2br($view_booking['passenger_names']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Seat Information</h6>
                        <p><strong>Number of Seats:</strong> <?php echo $view_booking['seat_count']; ?></p>
                        <p><strong>Seat Numbers:</strong> <?php echo implode(', ', $booked_seats); ?></p>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2">Payment Information</h6>
                        <p><strong>Total Amount:</strong> ৳<?php echo number_format($view_booking['total_amount'], 2); ?></p>
                        <p><strong>Base Fare:</strong> ৳<?php echo number_format($view_booking['fare'], 2); ?> per seat</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="bookings.php" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function updateStatus(bookingId, status) {
    if (confirm('Are you sure you want to update this booking status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="booking_id" value="${bookingId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>