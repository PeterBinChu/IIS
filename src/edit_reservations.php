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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $equipment_id_query = $conn->prepare("
            SELECT equipment_id FROM reservations WHERE reservation_id = :reservation_id
        ");
        $equipment_id_query->execute([':reservation_id' => $reservation_id]);
        $equipment = $equipment_id_query->fetch(PDO::FETCH_ASSOC);

        if ($equipment) {
            $equipment_id = $equipment['equipment_id'];

            $approve_query = $conn->prepare("
                UPDATE reservations 
                SET status = 'approved' 
                WHERE reservation_id = :reservation_id
            ");
            $approve_query->execute([':reservation_id' => $reservation_id]);

            $cancel_other_reservations_query = $conn->prepare("
                UPDATE reservations 
                SET status = 'canceled' 
                WHERE equipment_id = :equipment_id AND reservation_id != :reservation_id
            ");
            $cancel_other_reservations_query->execute([
                ':equipment_id' => $equipment_id,
                ':reservation_id' => $reservation_id
            ]);
        }
    } elseif ($action === 'save_loan') {
        $pickup_date = $_POST['pickup_date'] ?? null;
        $return_date = $_POST['return_date'] ?? null;

        $reservation_query = $conn->prepare("
            SELECT user_id, equipment_id FROM reservations WHERE reservation_id = :reservation_id
        ");
        $reservation_query->execute([':reservation_id' => $reservation_id]);
        $reservation = $reservation_query->fetch(PDO::FETCH_ASSOC);

        if ($reservation && $pickup_date && $return_date) {
            $insert_loan_query = $conn->prepare("
                INSERT INTO loans (reservation_id, user_id, equipment_id, pickup_date, return_date, loan_status)
                VALUES (:reservation_id, :user_id, :equipment_id, :pickup_date, :return_date, 'active')
            ");
            $insert_loan_query->execute([
                ':reservation_id' => $reservation_id,
                ':user_id' => $reservation['user_id'],
                ':equipment_id' => $reservation['equipment_id'],
                ':pickup_date' => $pickup_date,
                ':return_date' => $return_date,
            ]);
        }
    } elseif ($action === 'returned_earlier') {
        $loan_id = intval($_POST['loan_id']);

        $reservation_query = $conn->prepare("
        SELECT reservation_id FROM loans WHERE loan_id = :loan_id
    ");
        $reservation_query->execute([':loan_id' => $loan_id]);
        $reservation = $reservation_query->fetch(PDO::FETCH_ASSOC);

        if ($reservation) {
            $reservation_id = $reservation['reservation_id'];

            $delete_loan_query = $conn->prepare("
            DELETE FROM loans WHERE loan_id = :loan_id
        ");
            $delete_loan_query->execute([':loan_id' => $loan_id]);

            $delete_reservation_query = $conn->prepare("
            DELETE FROM reservations WHERE reservation_id = :reservation_id
        ");
            $delete_reservation_query->execute([':reservation_id' => $reservation_id]);
        }
    } elseif ($action === 'cancel') {
        $cancel_reservation_query = $conn->prepare("
            UPDATE reservations 
            SET status = 'canceled' 
            WHERE reservation_id = :reservation_id
        ");
        $cancel_reservation_query->execute([':reservation_id' => $reservation_id]);
    }
}

$reservations_query = $conn->prepare("
    SELECT 
        r.reservation_id, 
        r.status, 
        e.name AS equipment_name, 
        e.equipment_id, 
        u.name AS user_name, 
        u.email AS user_email, 
        l.loan_id, 
        l.pickup_date, 
        l.return_date
    FROM 
        reservations r
    LEFT JOIN 
        equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN 
        users u ON r.user_id = u.user_id
    LEFT JOIN 
        loans l ON r.reservation_id = l.reservation_id
    WHERE 
        e.owner_id = :owner_id AND r.status NOT IN ('canceled')
");
$reservations_query->execute([':owner_id' => $user_id]);
$reservations = $reservations_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reservations</title>
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
            flex-direction: column;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
        <h1 class="mb-4">Edit Reservations</h1>
        <?php if ($reservations): ?>
            <?php foreach ($reservations as $reservation): ?>
                <div class="reservation-card">
                    <h5><?php echo htmlspecialchars($reservation['equipment_name']); ?></h5>
                    <p><strong>Reserved by:</strong> <?php echo htmlspecialchars($reservation['user_name']); ?> (<?php echo htmlspecialchars($reservation['user_email']); ?>)</p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($reservation['status']); ?></p>
                    <?php if ($reservation['status'] === 'approved' && !$reservation['loan_id']): ?>
                        <form method="post">
                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['reservation_id']); ?>">
                            <div>
                                <label for="pickup_date">Pickup Date:</label>
                                <input type="date" id="pickup_date" name="pickup_date" required>
                            </div>
                            <div>
                                <label for="return_date">Return Date:</label>
                                <input type="date" id="return_date" name="return_date" required>
                            </div>
                            <button type="submit" name="action" value="save_loan" class="btn btn-success mt-3">Save</button>
                        </form>
                    <?php elseif ($reservation['loan_id']): ?>
                        <p><strong>Pickup Date:</strong> <?php echo htmlspecialchars($reservation['pickup_date']); ?></p>
                        <p><strong>Return Date:</strong> <?php echo htmlspecialchars($reservation['return_date']); ?></p>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="loan_id" value="<?php echo htmlspecialchars($reservation['loan_id']); ?>">
                            <button type="submit" name="action" value="returned_earlier" class="btn btn-danger">Returned Earlier</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['reservation_id']); ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['reservation_id']); ?>">
                            <button type="submit" name="action" value="cancel" class="btn btn-danger">Cancel</button>
                        </form>
                    <?php endif; ?>
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
