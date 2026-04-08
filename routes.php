<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireAdmin();

$page_title = 'Manage Routes';

// Handle route operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $route_name = sanitize($_POST['route_name']);
            $origin_city = sanitize($_POST['origin_city']);
            $destination_city = sanitize($_POST['destination_city']);
            $distance = intval($_POST['distance']);
            $fare = floatval($_POST['fare']);
            $travel_time = $_POST['travel_time'];
            $departure_time = $_POST['departure_time'];
            
            $stmt = $conn->prepare("INSERT INTO ROUTE (route_name, origin_city, destination_city, distance, fare, travel_time, departure_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssidss", $route_name, $origin_city, $destination_city, $distance, $fare, $travel_time, $departure_time);
            
            if ($stmt->execute()) {
                $route_id = $conn->insert_id;
                
                // Assign buses to route if selected
                if (isset($_POST['buses']) && is_array($_POST['buses'])) {
                    foreach ($_POST['buses'] as $bus_id) {
                        $stmt2 = $conn->prepare("INSERT INTO BUS_ROUTE (bus_id, route_id) VALUES (?, ?)");
                        $stmt2->bind_param("ii", $bus_id, $route_id);
                        $stmt2->execute();
                    }
                }
                
                setFlashMessage('success', 'Route added successfully!');
            } else {
                setFlashMessage('danger', 'Failed to add route');
            }
            header("Location: routes.php");
            exit();
        }
        
        if ($action == 'edit') {
            $route_id = intval($_POST['route_id']);
            $route_name = sanitize($_POST['route_name']);
            $origin_city = sanitize($_POST['origin_city']);
            $destination_city = sanitize($_POST['destination_city']);
            $distance = intval($_POST['distance']);
            $fare = floatval($_POST['fare']);
            $travel_time = $_POST['travel_time'];
            $departure_time = $_POST['departure_time'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE ROUTE SET route_name = ?, origin_city = ?, destination_city = ?, distance = ?, fare = ?, travel_time = ?, departure_time = ?, is_active = ? WHERE route_id = ?");
            $stmt->bind_param("sssidssii", $route_name, $origin_city, $destination_city, $distance, $fare, $travel_time, $departure_time, $is_active, $route_id);
            
            if ($stmt->execute()) {
                // Update bus assignments
                $conn->query("DELETE FROM BUS_ROUTE WHERE route_id = $route_id");
                if (isset($_POST['buses']) && is_array($_POST['buses'])) {
                    foreach ($_POST['buses'] as $bus_id) {
                        $stmt2 = $conn->prepare("INSERT INTO BUS_ROUTE (bus_id, route_id) VALUES (?, ?)");
                        $stmt2->bind_param("ii", $bus_id, $route_id);
                        $stmt2->execute();
                    }
                }
                
                setFlashMessage('success', 'Route updated successfully!');
            } else {
                setFlashMessage('danger', 'Failed to update route');
            }
            header("Location: routes.php");
            exit();
        }
        
        if ($action == 'delete') {
            $route_id = intval($_POST['route_id']);
            
            // Check if route has bookings
            $check = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING WHERE route_id = ?");
            $check->bind_param("i", $route_id);
            $check->execute();
            $result = $check->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                setFlashMessage('danger', 'Cannot delete route with existing bookings. Deactivate instead.');
            } else {
                $stmt = $conn->prepare("DELETE FROM ROUTE WHERE route_id = ?");
                $stmt->bind_param("i", $route_id);
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Route deleted successfully!');
                }
            }
            header("Location: routes.php");
            exit();
        }
    }
}

// Get all routes
$routes = $conn->query("SELECT r.*, 
                        (SELECT COUNT(*) FROM BUS_ROUTE WHERE route_id = r.route_id) as bus_count,
                        (SELECT COUNT(*) FROM BOOKING WHERE route_id = r.route_id) as booking_count
                        FROM ROUTE r ORDER BY r.created_at DESC");

// Get all buses for assignment
$all_buses = $conn->query("SELECT * FROM BUS WHERE is_active = 1");

// Get route for editing
$edit_route = null;
$assigned_buses = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM ROUTE WHERE route_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_route = $stmt->get_result()->fetch_assoc();
    
    // Get assigned buses
    $stmt2 = $conn->prepare("SELECT bus_id FROM BUS_ROUTE WHERE route_id = ?");
    $stmt2->bind_param("i", $edit_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    while ($row = $result->fetch_assoc()) {
        $assigned_buses[] = $row['bus_id'];
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Manage Routes</h2>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRouteModal">
            <i class="fas fa-plus"></i> Add New Route
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Route Name</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Distance (km)</th>
                        <th>Fare (৳)</th>
                        <th>Departure</th>
                        <th>Buses</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($routes->num_rows > 0): ?>
                        <?php while($route = $routes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $route['route_id']; ?></td>
                            <td><strong><?php echo $route['route_name']; ?></strong></td>
                            <td><?php echo $route['origin_city']; ?></td>
                            <td><?php echo $route['destination_city']; ?></td>
                            <td><?php echo $route['distance']; ?></td>
                            <td><?php echo number_format($route['fare'], 2); ?></td>
                            <td><?php echo formatTime($route['departure_time']); ?></td>
                            <td><?php echo $route['bus_count']; ?></td>
                            <td><?php echo $route['booking_count']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $route['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $route['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $route['route_id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteRoute(<?php echo $route['route_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No routes found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Route Modal -->
<div class="modal fade" id="addRouteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Route Name *</label>
                            <input type="text" name="route_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Origin City *</label>
                            <input type="text" name="origin_city" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Destination City *</label>
                            <input type="text" name="destination_city" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Distance (km)</label>
                            <input type="number" name="distance" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fare (৳) *</label>
                            <input type="number" step="0.01" name="fare" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Travel Time</label>
                            <input type="time" name="travel_time" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departure Time *</label>
                            <input type="time" name="departure_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Buses</label>
                        <div class="border p-3" style="max-height: 150px; overflow-y: auto;">
                            <?php 
                            $all_buses->data_seek(0);
                            while($bus = $all_buses->fetch_assoc()): 
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="buses[]" value="<?php echo $bus['bus_id']; ?>" id="bus<?php echo $bus['bus_id']; ?>">
                                    <label class="form-check-label" for="bus<?php echo $bus['bus_id']; ?>">
                                        <?php echo $bus['bus_number'] . ' - ' . $bus['bus_model']; ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Route Modal -->
<?php if ($edit_route): ?>
<div class="modal fade show" id="editRouteModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Route</h5>
                <a href="routes.php" class="btn-close"></a>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="route_id" value="<?php echo $edit_route['route_id']; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Route Name *</label>
                            <input type="text" name="route_name" class="form-control" value="<?php echo $edit_route['route_name']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Origin City *</label>
                            <input type="text" name="origin_city" class="form-control" value="<?php echo $edit_route['origin_city']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Destination City *</label>
                            <input type="text" name="destination_city" class="form-control" value="<?php echo $edit_route['destination_city']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Distance (km)</label>
                            <input type="number" name="distance" class="form-control" value="<?php echo $edit_route['distance']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fare (৳) *</label>
                            <input type="number" step="0.01" name="fare" class="form-control" value="<?php echo $edit_route['fare']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Travel Time</label>
                            <input type="time" name="travel_time" class="form-control" value="<?php echo $edit_route['travel_time']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departure Time *</label>
                            <input type="time" name="departure_time" class="form-control" value="<?php echo $edit_route['departure_time']; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Buses</label>
                        <div class="border p-3" style="max-height: 150px; overflow-y: auto;">
                            <?php 
                            $all_buses->data_seek(0);
                            while($bus = $all_buses->fetch_assoc()): 
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="buses[]" value="<?php echo $bus['bus_id']; ?>" 
                                           id="editbus<?php echo $bus['bus_id']; ?>"
                                           <?php echo in_array($bus['bus_id'], $assigned_buses) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="editbus<?php echo $bus['bus_id']; ?>">
                                        <?php echo $bus['bus_number'] . ' - ' . $bus['bus_model']; ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" <?php echo $edit_route['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="routes.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Route</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function deleteRoute(routeId) {
    if (confirm('Are you sure you want to delete this route?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="route_id" value="${routeId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>