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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_earlier'])) {
    $loan_id = intval($_POST['loan_id']);

    $reservation_query = $conn->prepare("
        SELECT reservation_id FROM loans WHERE loan_id = :loan_id
    ");
    $reservation_query->execute([':loan_id' => $loan_id]);
    $reservation_data = $reservation_query->fetch(PDO::FETCH_ASSOC);

    if ($reservation_data) {
        $reservation_id = $reservation_data['reservation_id'];

        $delete_loan_query = $conn->prepare("
            DELETE FROM loans WHERE loan_id = :loan_id
        ");
        $delete_loan_query->execute([':loan_id' => $loan_id]);

        $delete_reservation_query = $conn->prepare("
            DELETE FROM reservations WHERE reservation_id = :reservation_id
        ");
        $delete_reservation_query->execute([':reservation_id' => $reservation_id]);
    }
}

$loans_query = $conn->prepare("
    SELECT 
        l.loan_id, 
        l.pickup_date, 
        l.return_date, 
        e.name AS equipment_name, 
        e.year_of_manufacture, 
        e.pickup_location, 
        e.available_hours, 
        e.image_data, 
        a.atelier_name
    FROM 
        loans l
    LEFT JOIN 
        equipment e ON l.equipment_id = e.equipment_id
    LEFT JOIN 
        atelier a ON e.atelier_id = a.atelier_id
    WHERE 
        l.user_id = :user_id
");
$loans_query->execute([':user_id' => $user_id]);
$loans = $loans_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowings</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            margin-top: 50px;
        }

        .borrowing-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .borrowing-card img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
            margin-right: 20px;
        }

        .borrowing-card h5 {
            margin: 0;
            font-size: 18px;
            color: #343a40;
        }

        .borrowing-card p {
            margin: 0;
            font-size: 16px;
            color: #6c757d;
        }

        .return-button {
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
        <h1 class="mb-4">My Borrowings</h1>
        <?php if ($loans): ?>
            <?php foreach ($loans as $loan): ?>
                <div class="borrowing-card">
                    <div>
                        <img src="data:image/png;base64,<?php echo htmlspecialchars($loan['image_data']); ?>" alt="<?php echo htmlspecialchars($loan['equipment_name']); ?>">
                        <div>
                            <h5><?php echo htmlspecialchars($loan['equipment_name']); ?></h5>
                            <p><strong>Year:</strong> <?php echo htmlspecialchars($loan['year_of_manufacture']); ?></p>
                            <p><strong>Atelier:</strong> <?php echo htmlspecialchars($loan['atelier_name']); ?></p>
                            <p><strong>Pickup Date:</strong> <?php echo htmlspecialchars($loan['pickup_date']); ?></p>
                            <p><strong>Return Date:</strong> <?php echo htmlspecialchars($loan['return_date']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($loan['pickup_location']); ?></p>
                            <p><strong>Available Hours:</strong> <?php echo htmlspecialchars($loan['available_hours']); ?></p>
                        </div>
                    </div>
                    <form method="post" class="return-button">
                        <input type="hidden" name="loan_id" value="<?php echo htmlspecialchars($loan['loan_id']); ?>">
                        <button type="submit" name="return_earlier" class="btn btn-danger">Return Earlier</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No borrowings to display.</p>
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