<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireAdmin();

$page_title = 'Manage Users';

// Handle user operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'toggle_status') {
        $user_id = intval($_POST['user_id']);
        $is_active = intval($_POST['is_active']);
        
        $stmt = $conn->prepare("UPDATE USER SET is_active = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $is_active, $user_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'User status updated successfully!');
        } else {
            setFlashMessage('danger', 'Failed to update user status');
        }
        header("Location: users.php");
        exit();
    }
}

// Get all users with their booking statistics
$users = $conn->query("SELECT u.*, 
                       (SELECT COUNT(*) FROM BOOKING WHERE user_id = u.user_id) as total_bookings,
                       (SELECT COUNT(*) FROM BOOKING WHERE user_id = u.user_id AND status = 'confirmed') as confirmed_bookings,
                       (SELECT COALESCE(SUM(total_amount), 0) FROM BOOKING WHERE user_id = u.user_id) as total_spent
                       FROM USER u 
                       WHERE is_admin = 0 
                       ORDER BY u.created_at DESC");

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Manage Users</h2>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Users</h6>
                <h3><?php echo $users->num_rows; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Active Users</h6>
                <h3><?php 
                    $active = $conn->query("SELECT COUNT(*) as count FROM USER WHERE is_admin = 0 AND is_active = 1")->fetch_assoc()['count'];
                    echo $active;
                ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>Inactive Users</h6>
                <h3><?php 
                    $inactive = $conn->query("SELECT COUNT(*) as count FROM USER WHERE is_admin = 0 AND is_active = 0")->fetch_assoc()['count'];
                    echo $inactive;
                ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>Total Bookings</h6>
                <h3><?php 
                    $bookings = $conn->query("SELECT COUNT(*) as count FROM BOOKING")->fetch_assoc()['count'];
                    echo $bookings;
                ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bookings</th>
                        <th>Confirmed</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><strong><?php echo $user['username']; ?></strong></td>
                            <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['phone_number'] ?: 'N/A'; ?></td>
                            <td><?php echo $user['total_bookings']; ?></td>
                            <td><?php echo $user['confirmed_bookings']; ?></td>
                            <td>৳<?php echo number_format($user['total_spent'], 2); ?></td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($user['is_active']): ?>
                                    <button class="btn btn-sm btn-warning" onclick="toggleStatus(<?php echo $user['user_id']; ?>, 0)">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleStatus(<?php echo $user['user_id']; ?>, 1)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleStatus(userId, status) {
    const action = status == 1 ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this user?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="is_active" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewUser(userId) {
    alert('User details view will be implemented. User ID: ' + userId);
}
</script>

<?php include '../includes/footer.php'; ?>