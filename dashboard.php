<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireAdmin();

$page_title = 'Admin Dashboard';

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM USER WHERE is_admin = 0")->fetch_assoc()['count'];
$total_buses = $conn->query("SELECT COUNT(*) as count FROM BUS")->fetch_assoc()['count'];
$total_routes = $conn->query("SELECT COUNT(*) as count FROM ROUTE")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM BOOKING")->fetch_assoc()['count'];
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM BOOKING WHERE status = 'pending'")->fetch_assoc()['count'];
$confirmed_bookings = $conn->query("SELECT COUNT(*) as count FROM BOOKING WHERE status = 'confirmed'")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM PAYMENT")->fetch_assoc()['total'];

// Recent bookings
$recent_bookings = $conn->query("SELECT b.*, u.username, r.route_name, bus.bus_number 
                                 FROM BOOKING b 
                                 JOIN USER u ON b.user_id = u.user_id 
                                 JOIN ROUTE r ON b.route_id = r.route_id 
                                 JOIN BUS bus ON b.bus_id = bus.bus_id 
                                 ORDER BY b.created_at DESC LIMIT 5");

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Admin Dashboard</h2>
        <p class="text-muted">Overview of bus schedule system</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Total Users</h6>
                        <h3 class="mb-0"><?php echo $total_users; ?></h3>
                    </div>
                    <i class="fas fa-users fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Total Buses</h6>
                        <h3 class="mb-0"><?php echo $total_buses; ?></h3>
                    </div>
                    <i class="fas fa-bus fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Total Routes</h6>
                        <h3 class="mb-0"><?php echo $total_routes; ?></h3>
                    </div>
                    <i class="fas fa-route fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Total Bookings</h6>
                        <h3 class="mb-0"><?php echo $total_bookings; ?></h3>
                    </div>
                    <i class="fas fa-ticket-alt fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Pending Bookings</h6>
                <h2 class="text-warning"><?php echo $pending_bookings; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Confirmed Bookings</h6>
                <h2 class="text-success"><?php echo $confirmed_bookings; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Total Revenue</h6>
                <h2 class="text-primary">৳<?php echo number_format($total_revenue, 2); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Bookings</h5>
                <a href="bookings.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Route</th>
                                <th>Bus</th>
                                <th>Journey Date</th>
                                <th>Passengers</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_bookings->num_rows > 0): ?>
                                <?php while($booking = $recent_bookings->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $booking['booking_id']; ?></td>
                                    <td><?php echo $booking['username']; ?></td>
                                    <td><?php echo $booking['route_name']; ?></td>
                                    <td><?php echo $booking['bus_number']; ?></td>
                                    <td><?php echo formatDate($booking['journey_date']); ?></td>
                                    <td><?php echo $booking['total_passengers']; ?></td>
                                    <td>৳<?php echo number_format($booking['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $booking['status'] == 'confirmed' ? 'success' : 
                                                ($booking['status'] == 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="bookings.php?id=<?php echo $booking['booking_id']; ?>" 
                                           class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No bookings found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>