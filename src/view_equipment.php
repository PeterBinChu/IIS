<?php
session_start();
include 'db_connection.php';
include 'session_timeout.php';

$timeout_duration = 600;

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

$equipment_id = isset($_GET['equipment_id']) ? intval($_GET['equipment_id']) : null;

if (!$equipment_id) {
    echo "Equipment not specified.";
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "User not logged in.";
    exit;
}

$query = $conn->prepare("
    SELECT 
        e.*, 
        et.type_name, 
        u.name AS owner_name, 
        a.atelier_name, 
        a.atelier_id
    FROM 
        equipment e
    LEFT JOIN 
        equipment_types et ON e.type_id = et.type_id
    LEFT JOIN 
        users u ON e.owner_id = u.user_id
    LEFT JOIN 
        atelier a ON e.atelier_id = a.atelier_id
    WHERE 
        e.equipment_id = :equipment_id
");
$query->execute([':equipment_id' => $equipment_id]);
$equipment = $query->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    echo "Equipment not found.";
    exit;
}

$check_reservation_query = $conn->prepare("
    SELECT * FROM reservations 
    WHERE equipment_id = :equipment_id AND user_id = :user_id
");
$check_reservation_query->execute([':equipment_id' => $equipment_id, ':user_id' => $user_id]);
$user_has_reservation = $check_reservation_query->fetch(PDO::FETCH_ASSOC) ? true : false;

$check_loan_query = $conn->prepare("
    SELECT * FROM loans 
    WHERE equipment_id = :equipment_id AND loan_status = 'active'
");
$check_loan_query->execute([':equipment_id' => $equipment_id]);
$equipment_is_borrowed = $check_loan_query->fetch(PDO::FETCH_ASSOC);

$user_atelier_query = $conn->prepare("
    SELECT * FROM studio_user_permissions 
    WHERE atelier_id = :atelier_id AND user_id = :user_id
");
$user_atelier_query->execute([
    ':atelier_id' => $equipment['atelier_id'],
    ':user_id' => $user_id
]);
$user_in_atelier = $user_atelier_query->fetch(PDO::FETCH_ASSOC) ? true : false;

$restriction_query = $conn->prepare("
    SELECT * FROM device_user_restrictions 
    WHERE equipment_id = :equipment_id AND user_id = :user_id
");
$restriction_query->execute([':equipment_id' => $equipment_id, ':user_id' => $user_id]);
$user_restricted = $restriction_query->fetch(PDO::FETCH_ASSOC) ? true : false;

$reservation_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservate']) && !$user_has_reservation && !$equipment_is_borrowed) {
    $insert_query = $conn->prepare("
        INSERT INTO reservations (user_id, equipment_id, start_date, end_date, status)
        VALUES (:user_id, :equipment_id, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending')
    ");
    $insert_query->execute([
        ':user_id' => $user_id,
        ':equipment_id' => $equipment_id
    ]);
    $reservation_message = "Reservation successful!";
    $user_has_reservation = true;
}

$disable_button = false;
$disable_message = '';
if ($user_has_reservation) {
    $disable_button = true;
    $disable_message = "You already have a reservation for this equipment.";
} elseif ($equipment_is_borrowed) {
    $disable_button = true;
    $disable_message = "This equipment is currently borrowed.";
} elseif ($equipment['status'] === 'prohibited') {
    $disable_button = true;
    $disable_message = "This equipment is prohibited.";
} elseif ($user_id == $equipment['owner_id']) {
    $disable_button = true;
    $disable_message = "You cannot reserve your own equipment.";
} elseif (!$user_in_atelier) {
    $disable_button = true;
    $disable_message = "You must belong to the same atelier to reserve this equipment.";
} elseif ($user_restricted) {
    $disable_button = true;
    $disable_message = "You are restricted from reserving this equipment.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($equipment['name']); ?> Details</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            margin-top: 50px;
            max-width: 700px;
        }

        .equipment-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .equipment-card .image-container {
            width: 100%;
            height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .equipment-card img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        .equipment-card h1 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #343a40;
        }

        .equipment-card p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .equipment-card .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-reservate.disabled {
            background-color: red;
            color: white;
            cursor: not-allowed;
            border: 1px solid red;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="equipment-card">
            <div class="image-container">
                <img src="data:image/png;base64,<?php echo htmlspecialchars($equipment['image_data']); ?>" alt="<?php echo htmlspecialchars($equipment['name']); ?>">
            </div>
            <h1><?php echo htmlspecialchars($equipment['name']); ?></h1>
            <p><strong>Type:</strong> <?php echo htmlspecialchars($equipment['type_name']); ?></p>
            <p><strong>Owner:</strong> <?php echo htmlspecialchars($equipment['owner_name']); ?></p>
            <p><strong>Atelier:</strong> <?php echo htmlspecialchars($equipment['atelier_name']); ?></p>
            <p><strong>Year of Manufacture:</strong> <?php echo htmlspecialchars($equipment['year_of_manufacture']); ?></p>
            <p><strong>Max Borrow Duration:</strong> <?php echo htmlspecialchars($equipment['max_borrow_duration']); ?> days</p>
            <p><strong>Pickup Location:</strong> <?php echo htmlspecialchars($equipment['pickup_location']); ?></p>
            <p><strong>Available Hours:</strong> <?php echo htmlspecialchars($equipment['available_hours']); ?></p>
            <?php if ($reservation_message): ?>
                <div class="alert alert-info mt-3"><?php echo htmlspecialchars($reservation_message); ?></div>
            <?php elseif ($disable_message): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($disable_message); ?></div>
            <?php endif; ?>
            <div class="btn-group">
                <a href="equipment_page.php?type_id=<?php echo htmlspecialchars($equipment['type_id']); ?>" class="btn btn-secondary">Back to Equipment List</a>
                <form method="post" style="margin: 0;">
                    <button type="submit" name="reservate" class="btn btn-primary btn-reservate <?php echo $disable_button ? 'disabled' : ''; ?>" <?php echo $disable_button ? 'disabled' : ''; ?>>
                        <?php echo $disable_button ? 'Unavailable' : 'Reservate'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You have been logged out due to inactivity.");
                window.location.href = "login.php";
            }, 600000);
        }

        document.addEventListener("mousemove", resetTimer);
        document.addEventListener("keypress", resetTimer);
        document.addEventListener("click", resetTimer);
        document.addEventListener("scroll", resetTimer);

        resetTimer();
    </script>
</body>

</html>