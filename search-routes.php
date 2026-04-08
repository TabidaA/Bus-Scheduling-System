<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = 'Search Routes';

$routes = null;
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $origin = sanitize($_POST['origin']);
    $destination = sanitize($_POST['destination']);
    $journey_date = $_POST['journey_date'];
    
    $search_performed = true;
    
    // Search for routes
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM BUS_ROUTE WHERE route_id = r.route_id) as available_buses
              FROM ROUTE r 
              WHERE r.is_active = 1";
    
    $params = [];
    $types = "";
    
    if (!empty($origin)) {
        $query .= " AND r.origin_city LIKE ?";
        $params[] = "%$origin%";
        $types .= "s";
    }
    
    if (!empty($destination)) {
        $query .= " AND r.destination_city LIKE ?";
        $params[] = "%$destination%";
        $types .= "s";
    }
    
    $query .= " ORDER BY r.departure_time";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $routes = $stmt->get_result();
    } else {
        $routes = $conn->query($query);
    }
    
    $_SESSION['search_data'] = [
        'origin' => $origin,
        'destination' => $destination,
        'journey_date' => $journey_date
    ];
}

// Get popular cities for suggestions
$popular_cities = $conn->query("SELECT DISTINCT origin_city as city FROM ROUTE 
                                UNION 
                                SELECT DISTINCT destination_city FROM ROUTE 
                                ORDER BY city");

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Search Bus Routes</h2>
        <p class="text-muted">Find the perfect bus for your journey</p>
    </div>
</div>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From (Origin)</label>
                    <input type="text" name="origin" class="form-control" 
                           placeholder="Enter city" 
                           value="<?php echo isset($_POST['origin']) ? $_POST['origin'] : ''; ?>"
                           list="originCities">
                    <datalist id="originCities">
                        <?php 
                        $popular_cities->data_seek(0);
                        while($city = $popular_cities->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $city['city']; ?>">
                        <?php endwhile; ?>
                    </datalist>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">To (Destination)</label>
                    <input type="text" name="destination" class="form-control" 
                           placeholder="Enter city"
                           value="<?php echo isset($_POST['destination']) ? $_POST['destination'] : ''; ?>"
                           list="destCities">
                    <datalist id="destCities">
                        <?php 
                        $popular_cities->data_seek(0);
                        while($city = $popular_cities->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $city['city']; ?>">
                        <?php endwhile; ?>
                    </datalist>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Journey Date</label>
                    <input type="date" name="journey_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo isset($_POST['journey_date']) ? $_POST['journey_date'] : date('Y-m-d'); ?>"
                           required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search Buses
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Search Results -->
<?php if ($search_performed): ?>
    <?php if ($routes && $routes->num_rows > 0): ?>
        <h4 class="mb-3">Available Routes (<?php echo $routes->num_rows; ?> found)</h4>
        <?php while($route = $routes->fetch_assoc()): ?>
            <!-- Get buses for this route -->
            <?php
            $bus_query = $conn->prepare("SELECT b.* FROM BUS b 
                                         JOIN BUS_ROUTE br ON b.bus_id = br.bus_id 
                                         WHERE br.route_id = ? AND b.is_active = 1");
            $bus_query->bind_param("i", $route['route_id']);
            $bus_query->execute();
            $buses = $bus_query->get_result();
            ?>
            
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-2"><?php echo $route['route_name']; ?></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt text-success"></i> 
                                        <strong>From:</strong> <?php echo $route['origin_city']; ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt text-danger"></i> 
                                        <strong>To:</strong> <?php echo $route['destination_city']; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-clock"></i> 
                                        <strong>Departure:</strong> <?php echo formatTime($route['departure_time']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-road"></i> 
                                        <strong>Distance:</strong> <?php echo $route['distance']; ?> km
                                    </p>
                                    <?php if ($route['travel_time']): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-hourglass-half"></i> 
                                        <strong>Duration:</strong> <?php echo $route['travel_time']; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($buses->num_rows > 0): ?>
                                <div class="mt-2">
                                    <strong>Available Buses:</strong>
                                    <?php while($bus = $buses->fetch_assoc()): ?>
                                        <span class="badge bg-info me-1"><?php echo $bus['bus_number']; ?></span>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <h3 class="text-primary mb-2">৳<?php echo number_format($route['fare'], 2); ?></h3>
                            <p class="text-muted small mb-3">Per person</p>
                            <a href="book-ticket.php?route_id=<?php echo $route['route_id']; ?>" 
                               class="btn btn-primary btn-lg">
                                <i class="fas fa-ticket-alt"></i> Book Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> No routes found</h5>
            <p class="mb-0">No buses available for your search criteria. Please try different cities or dates.</p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Popular Routes -->
    <h4 class="mb-3">Popular Routes</h4>
    <?php
    $popular_routes = $conn->query("SELECT * FROM ROUTE WHERE is_active = 1 ORDER BY route_id LIMIT 5");
    while($route = $popular_routes->fetch_assoc()):
    ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5><?php echo $route['route_name']; ?></h5>
                        <p class="mb-1">
                            <i class="fas fa-map-marker-alt text-success"></i> <?php echo $route['origin_city']; ?>
                            <i class="fas fa-arrow-right mx-2"></i>
                            <i class="fas fa-map-marker-alt text-danger"></i> <?php echo $route['destination_city']; ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock"></i> Departure: <?php echo formatTime($route['departure_time']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h4 class="text-primary">৳<?php echo number_format($route['fare'], 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>