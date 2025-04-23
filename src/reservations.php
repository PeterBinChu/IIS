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

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "User not logged in.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);

    $delete_reservation_query = $conn->prepare("
        DELETE FROM reservations 
        WHERE reservation_id = :reservation_id AND status IN ('pending', 'canceled')
    ");
    $delete_reservation_query->execute([':reservation_id' => $reservation_id]);
}

$reservations_query = $conn->prepare("
    SELECT 
        r.reservation_id, 
        r.status, 
        e.name AS equipment_name, 
        e.equipment_id, 
        e.image_data, 
        e.year_of_manufacture, 
        e.pickup_location, 
        e.available_hours, 
        a.atelier_name, 
        l.loan_id 
    FROM 
        reservations r
    LEFT JOIN 
        equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN 
        atelier a ON e.atelier_id = a.atelier_id
    LEFT JOIN 
        loans l ON r.reservation_id = l.reservation_id
    WHERE 
        r.user_id = :user_id
");
$reservations_query->execute([':user_id' => $user_id]);
$reservations = $reservations_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            margin-top: 50px;
        }

        .reservation-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .reservation-card img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
            margin-right: 20px;
        }

        .reservation-card h5 {
            margin: 0;
            font-size: 18px;
            color: #343a40;
        }

        .reservation-card p {
            margin: 0;
            font-size: 16px;
            color: #6c757d;
        }

        .reservation-card .btn-group {
            margin-left: auto;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #6c757d;
            color: #fff;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .back-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <a href="main_page.php" class="back-btn"><i class="fa fa-home"></i></a>
    <div class="container">
        <h1 class="mb-4">My Reservations</h1>
        <?php if ($reservations): ?>
            <?php foreach ($reservations as $reservation): ?>
                <div class="reservation-card">
                    <img src="data:image/png;base64,<?php echo htmlspecialchars($reservation['image_data']); ?>" alt="<?php echo htmlspecialchars($reservation['equipment_name']); ?>">
                    <div>
                        <h5><?php echo htmlspecialchars($reservation['equipment_name']); ?></h5>
                        <p><strong>Year:</strong> <?php echo htmlspecialchars($reservation['year_of_manufacture']); ?></p>
                        <p><strong>Atelier:</strong> <?php echo htmlspecialchars($reservation['atelier_name']); ?></p>
                        <p><strong>Pickup Location:</strong> <?php echo htmlspecialchars($reservation['pickup_location']); ?></p>
                        <p><strong>Available Hours:</strong> <?php echo htmlspecialchars($reservation['available_hours']); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($reservation['status']); ?></p>
                    </div>
                    <div class="btn-group">
                        <?php if ($reservation['status'] === 'pending' || $reservation['status'] === 'canceled'): ?>
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['reservation_id']); ?>">
                                <button type="submit" name="cancel_reservation" class="btn btn-danger">Cancel Reservation</button>
                            </form>
                        <?php elseif ($reservation['status'] === 'approved'): ?>
                            <a href="borrowings.php" class="btn btn-primary">Go to Borrowing</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No reservations to display.</p>
        <?php endif; ?>
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