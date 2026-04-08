<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireAdmin();

$page_title = 'Manage Buses';

// Handle bus operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $bus_number = sanitize($_POST['bus_number']);
            $bus_model = sanitize($_POST['bus_model']);
            $license_plate = sanitize($_POST['license_plate']);
            $capacity = intval($_POST['capacity']);
            
            $stmt = $conn->prepare("INSERT INTO BUS (bus_number, bus_model, license_plate, capacity) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $bus_number, $bus_model, $license_plate, $capacity);
            
            if ($stmt->execute()) {
                $bus_id = $conn->insert_id;
                
                // Generate seats for the bus
                for ($i = 1; $i <= $capacity; $i++) {
                    $seat_number = 'A' . $i;
                    $stmt2 = $conn->prepare("INSERT INTO SEAT (bus_id, seat_number) VALUES (?, ?)");
                    $stmt2->bind_param("is", $bus_id, $seat_number);
                    $stmt2->execute();
                }
                
                setFlashMessage('success', 'Bus added successfully with ' . $capacity . ' seats!');
            } else {
                setFlashMessage('danger', 'Failed to add bus');
            }
            header("Location: buses.php");
            exit();
        }
        
        if ($action == 'edit') {
            $bus_id = intval($_POST['bus_id']);
            $bus_number = sanitize($_POST['bus_number']);
            $bus_model = sanitize($_POST['bus_model']);
            $license_plate = sanitize($_POST['license_plate']);
            $capacity = intval($_POST['capacity']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE BUS SET bus_number = ?, bus_model = ?, license_plate = ?, capacity = ?, is_active = ? WHERE bus_id = ?");
            $stmt->bind_param("sssiii", $bus_number, $bus_model, $license_plate, $capacity, $is_active, $bus_id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Bus updated successfully!');
            } else {
                setFlashMessage('danger', 'Failed to update bus');
            }
            header("Location: buses.php");
            exit();
        }
        
        if ($action == 'delete') {
            $bus_id = intval($_POST['bus_id']);
            
            // Check if bus has bookings
            $check = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING WHERE bus_id = ?");
            $check->bind_param("i", $bus_id);
            $check->execute();
            $result = $check->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                setFlashMessage('danger', 'Cannot delete bus with existing bookings. Deactivate instead.');
            } else {
                $stmt = $conn->prepare("DELETE FROM BUS WHERE bus_id = ?");
                $stmt->bind_param("i", $bus_id);
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Bus deleted successfully!');
                }
            }
            header("Location: buses.php");
            exit();
        }
    }
}

// Get all buses
$buses_query = "SELECT b.*, 
                (SELECT COUNT(*) FROM SEAT WHERE bus_id = b.bus_id) as seat_count,
                (SELECT COUNT(*) FROM BUS_ROUTE WHERE bus_id = b.bus_id) as route_count
                FROM BUS b ORDER BY b.created_at DESC";
$buses = $conn->query($buses_query);

// Get bus for editing
$edit_bus = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM BUS WHERE bus_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_bus = $stmt->get_result()->fetch_assoc();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Manage Buses</h2>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBusModal">
            <i class="fas fa-plus"></i> Add New Bus
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
                        <th>Bus Number</th>
                        <th>Model</th>
                        <th>License Plate</th>
                        <th>Capacity</th>
                        <th>Seats</th>
                        <th>Routes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($buses->num_rows > 0): ?>
                        <?php while($bus = $buses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $bus['bus_id']; ?></td>
                            <td><strong><?php echo $bus['bus_number']; ?></strong></td>
                            <td><?php echo $bus['bus_model']; ?></td>
                            <td><?php echo $bus['license_plate']; ?></td>
                            <td><?php echo $bus['capacity']; ?></td>
                            <td><?php echo $bus['seat_count']; ?></td>
                            <td><?php echo $bus['route_count']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $bus['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $bus['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $bus['bus_id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteBus(<?php echo $bus['bus_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No buses found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Bus Modal -->
<div class="modal fade" id="addBusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Bus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Bus Number *</label>
                        <input type="text" name="bus_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bus Model</label>
                        <input type="text" name="bus_model" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">License Plate *</label>
                        <input type="text" name="license_plate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity (Number of Seats) *</label>
                        <input type="number" name="capacity" class="form-control" min="10" max="60" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bus Modal -->
<?php if ($edit_bus): ?>
<div class="modal fade show" id="editBusModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bus</h5>
                <a href="buses.php" class="btn-close"></a>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="bus_id" value="<?php echo $edit_bus['bus_id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Bus Number *</label>
                        <input type="text" name="bus_number" class="form-control" value="<?php echo $edit_bus['bus_number']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bus Model</label>
                        <input type="text" name="bus_model" class="form-control" value="<?php echo $edit_bus['bus_model']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">License Plate *</label>
                        <input type="text" name="license_plate" class="form-control" value="<?php echo $edit_bus['license_plate']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity *</label>
                        <input type="number" name="capacity" class="form-control" value="<?php echo $edit_bus['capacity']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" <?php echo $edit_bus['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="buses.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Bus</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function deleteBus(busId) {
    if (confirm('Are you sure you want to delete this bus?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="bus_id" value="${busId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>