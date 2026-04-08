<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = 'Book Ticket';

// Get route details
if (!isset($_GET['route_id'])) {
    header("Location: search-routes.php");
    exit();
}

$route_id = intval($_GET['route_id']);
$stmt = $conn->prepare("SELECT * FROM ROUTE WHERE route_id = ? AND is_active = 1");
$stmt->bind_param("i", $route_id);
$stmt->execute();
$route = $stmt->get_result()->fetch_assoc();

if (!$route) {
    setFlashMessage('danger', 'Route not found or inactive');
    header("Location: search-routes.php");
    exit();
}

// Get buses for this route
$bus_query = $conn->prepare("SELECT b.* FROM BUS b 
                             JOIN BUS_ROUTE br ON b.bus_id = br.bus_id 
                             WHERE br.route_id = ? AND b.is_active = 1");
$bus_query->bind_param("i", $route_id);
$bus_query->execute();
$buses = $bus_query->get_result();

// Get journey date from session or default to today
$journey_date = isset($_SESSION['search_data']['journey_date']) ? $_SESSION['search_data']['journey_date'] : date('Y-m-d');

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bus_id = intval($_POST['bus_id']);
    $selected_seats = isset($_POST['seats']) ? $_POST['seats'] : [];
    $passenger_names = sanitize($_POST['passenger_names']);
    $passenger_phones = sanitize($_POST['passenger_phones']);
    $journey_date = $_POST['journey_date'];
    
    if (empty($selected_seats)) {
        setFlashMessage('danger', 'Please select at least one seat');
    } else {
        $seat_count = count($selected_seats);
        $total_amount = $route['fare'] * $seat_count;
        $user_id = $_SESSION['user_id'];
        $booking_date = date('Y-m-d');
        $status = 'pending';
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create booking
            $stmt = $conn->prepare("INSERT INTO BOOKING (user_id, route_id, bus_id, booking_date, journey_date, status, seat_count, total_passengers, total_amount, passenger_names, passenger_phones) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisssiddss", $user_id, $route_id, $bus_id, $booking_date, $journey_date, $status, $seat_count, $seat_count, $total_amount, $passenger_names, $passenger_phones);
            $stmt->execute();
            $booking_id = $conn->insert_id;
            
            // Link seats to booking
            foreach ($selected_seats as $seat_id) {
                $stmt2 = $conn->prepare("INSERT INTO BOOKING_SEAT (booking_id, seat_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $booking_id, $seat_id);
                $stmt2->execute();
            }
            
            $conn->commit();
            
            // Redirect to payment
            $_SESSION['pending_booking_id'] = $booking_id;
            header("Location: payment.php?booking_id=" . $booking_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('danger', 'Booking failed. Please try again.');
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <a href="search-routes.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back to Search
        </a>
        <h2>Book Your Ticket</h2>
    </div>
</div>

<!-- Route Details -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-route"></i> Route Details</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5><?php echo $route['route_name']; ?></h5>
                <p class="mb-1">
                    <i class="fas fa-map-marker-alt text-success"></i> 
                    <strong>From:</strong> <?php echo $route['origin_city']; ?>
                </p>
                <p class="mb-1">
                    <i class="fas fa-map-marker-alt text-danger"></i> 
                    <strong>To:</strong> <?php echo $route['destination_city']; ?>
                </p>
                <p class="mb-1">
                    <i class="fas fa-clock"></i> 
                    <strong>Departure:</strong> <?php echo formatTime($route['departure_time']); ?>
                </p>
            </div>
            <div class="col-md-6">
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
                <h4 class="text-primary mt-3">
                    <i class="fas fa-tag"></i> ৳<?php echo number_format($route['fare'], 2); ?> per seat
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Booking Form -->
<form method="POST" action="" id="bookingForm">
    <div class="row">
        <div class="col-md-8">
            <!-- Bus Selection -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bus"></i> Select Bus</h5>
                </div>
                <div class="card-body">
                    <?php if ($buses->num_rows > 0): ?>
                        <?php while($bus = $buses->fetch_assoc()): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="bus_id" 
                                       value="<?php echo $bus['bus_id']; ?>" 
                                       id="bus<?php echo $bus['bus_id']; ?>"
                                       required
                                       onchange="loadSeats(<?php echo $bus['bus_id']; ?>)">
                                <label class="form-check-label" for="bus<?php echo $bus['bus_id']; ?>">
                                    <strong><?php echo $bus['bus_number']; ?></strong> - <?php echo $bus['bus_model']; ?>
                                    <span class="badge bg-info"><?php echo $bus['capacity']; ?> seats</span>
                                </label>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-danger">No buses available for this route</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Seat Selection -->
            <div class="card mb-4" id="seatSection" style="display:none;">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chair"></i> Select Seats</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Journey Date</label>
                        <input type="date" name="journey_date" class="form-control" 
                               value="<?php echo $journey_date; ?>" 
                               min="<?php echo date('Y-m-d'); ?>"
                               required
                               onchange="loadSeats(document.querySelector('input[name=bus_id]:checked').value)">
                    </div>
                    
                    <div class="text-center mb-3">
                        <div class="d-inline-block p-2 border">
                            <i class="fas fa-steering-wheel"></i> Driver
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-4 mb-3">
                        <div><span class="seat seat-available" style="display:inline-block; width:30px; height:30px;"></span> Available</div>
                        <div><span class="seat seat-selected" style="display:inline-block; width:30px; height:30px;"></span> Selected</div>
                        <div><span class="seat seat-booked" style="display:inline-block; width:30px; height:30px;"></span> Booked</div>
                    </div>
                    
                    <div id="seatGrid" class="seat-grid">
                        <!-- Seats will be loaded here -->
                    </div>
                    
                    <div class="mt-3">
                        <strong>Selected Seats: </strong>
                        <span id="selectedSeatsDisplay">None</span>
                    </div>
                </div>
            </div>
            
            <!-- Passenger Details -->
            <div class="card mb-4" id="passengerSection" style="display:none;">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Passenger Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Passenger Names (One per line)</label>
                        <textarea name="passenger_names" class="form-control" rows="4" 
                                  placeholder="Enter passenger names&#10;Example:&#10;John Doe&#10;Jane Smith"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Phone Numbers (Comma separated)</label>
                        <input type="text" name="passenger_phones" class="form-control" 
                               placeholder="01712345678, 01823456789">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Summary -->
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt"></i> Booking Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Route:</strong><br>
                        <?php echo $route['route_name']; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Journey Date:</strong><br>
                        <span id="summaryDate"><?php echo formatDate($journey_date); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Selected Seats:</strong><br>
                        <span id="summarySeatCount">0</span> seat(s)
                    </div>
                    <div class="mb-3">
                        <strong>Fare per seat:</strong><br>
                        ৳<?php echo number_format($route['fare'], 2); ?>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <h5><strong>Total Amount:</strong></h5>
                        <h3 class="text-primary" id="totalAmount">৳0.00</h3>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="bookButton" disabled>
                        <i class="fas fa-credit-card"></i> Proceed to Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const baseFare = <?php echo $route['fare']; ?>;
let selectedSeats = [];

function loadSeats(busId) {
    const journeyDate = document.querySelector('input[name="journey_date"]').value;
    
    fetch(`get-seats.php?bus_id=${busId}&journey_date=${journeyDate}&route_id=<?php echo $route_id; ?>`)
        .then(response => response.json())
        .then(data => {
            const seatGrid = document.getElementById('seatGrid');
            seatGrid.innerHTML = '';
            selectedSeats = [];
            
            data.forEach(seat => {
                const seatDiv = document.createElement('div');
                seatDiv.className = 'seat ' + (seat.is_booked ? 'seat-booked' : 'seat-available');
                seatDiv.textContent = seat.seat_number;
                seatDiv.dataset.seatId = seat.seat_id;
                seatDiv.dataset.seatNumber = seat.seat_number;
                
                if (!seat.is_booked) {
                    seatDiv.onclick = function() {
                        toggleSeat(this);
                    };
                }
                
                seatGrid.appendChild(seatDiv);
            });
            
            document.getElementById('seatSection').style.display = 'block';
            updateSummary();
        });
}

function toggleSeat(seatElement) {
    const seatId = seatElement.dataset.seatId;
    const seatNumber = seatElement.dataset.seatNumber;
    
    if (seatElement.classList.contains('seat-selected')) {
        seatElement.classList.remove('seat-selected');
        seatElement.classList.add('seat-available');
        selectedSeats = selectedSeats.filter(s => s.id != seatId);
    } else {
        seatElement.classList.remove('seat-available');
        seatElement.classList.add('seat-selected');
        selectedSeats.push({id: seatId, number: seatNumber});
    }
    
    updateSummary();
}

function updateSummary() {
    const seatCount = selectedSeats.length;
    const totalAmount = baseFare * seatCount;
    
    document.getElementById('summarySeatCount').textContent = seatCount;
    document.getElementById('totalAmount').textContent = '৳' + totalAmount.toFixed(2);
    
    if (seatCount > 0) {
        document.getElementById('selectedSeatsDisplay').textContent = 
            selectedSeats.map(s => s.number).join(', ');
        document.getElementById('passengerSection').style.display = 'block';
        document.getElementById('bookButton').disabled = false;
        
        // Add hidden inputs for selected seats
        const oldInputs = document.querySelectorAll('input[name="seats[]"]');
        oldInputs.forEach(input => input.remove());
        
        selectedSeats.forEach(seat => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'seats[]';
            input.value = seat.id;
            document.getElementById('bookingForm').appendChild(input);
        });
    } else {
        document.getElementById('selectedSeatsDisplay').textContent = 'None';
        document.getElementById('passengerSection').style.display = 'none';
        document.getElementById('bookButton').disabled = true;
    }
}
</script>

<?php include '../includes/footer.php'; ?>