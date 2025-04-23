<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

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

try {
    $query = $conn->prepare("SELECT type_id, type_name FROM equipment_types");
    $query->execute();
    $equipment_types = $query->fetchAll(PDO::FETCH_ASSOC);
    $equipment_types_empty = empty($equipment_types);

    $query_user_ateliers = $conn->prepare("
        SELECT a.atelier_id, a.atelier_name 
        FROM studio_user_permissions sup
        JOIN atelier a ON sup.atelier_id = a.atelier_id
        WHERE sup.user_id = :user_id
    ");
    $query_user_ateliers->execute([':user_id' => $_SESSION['user_id']]);
    $user_ateliers = $query_user_ateliers->fetchAll(PDO::FETCH_ASSOC);
    $user_ateliers_empty = empty($user_ateliers);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = $_POST['name'];
        $year_of_manufacture = $_POST['year_of_manufacture'];
        $max_borrow_duration = $_POST['max_borrow_duration'];
        $pickup_location = $_POST['pickup_location'];
        $available_hours = $_POST['available_hours'];
        $type_id = $_POST['equipment_type'];
        $atelier_id = $_POST['atelier_id'];
        $owner_id = $_SESSION['user_id'];

        if (isset($_FILES['image_data']) && $_FILES['image_data']['error'] == UPLOAD_ERR_OK) {
            $image_data = base64_encode(file_get_contents($_FILES['image_data']['tmp_name']));
        } else {
            $image_data = null;
        }

        if ($name && $year_of_manufacture && $max_borrow_duration && $pickup_location && $available_hours && $type_id && $atelier_id && $image_data) {
            $insert_query = $conn->prepare("
                INSERT INTO equipment (name, year_of_manufacture, max_borrow_duration, pickup_location, available_hours, owner_id, type_id, atelier_id, image_data, status)
                VALUES (:name, :year_of_manufacture, :max_borrow_duration, :pickup_location, :available_hours, :owner_id, :type_id, :atelier_id, :image_data, 'available')
            ");
            $insert_query->execute([
                ':name' => $name,
                ':year_of_manufacture' => $year_of_manufacture,
                ':max_borrow_duration' => $max_borrow_duration,
                ':pickup_location' => $pickup_location,
                ':available_hours' => $available_hours,
                ':owner_id' => $owner_id,
                ':type_id' => $type_id,
                ':atelier_id' => $atelier_id,
                ':image_data' => $image_data,
            ]);
            echo "<p>Equipment added successfully!</p>";
        } else {
            echo "<p>Please fill out all fields and upload an image.</p>";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <title>Create Equipment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        h1 {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        form input,
        form select,
        form button {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        form button {
            background-color: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        form button:hover {
            background-color: #0056b3;
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
        <h1>Create Equipment</h1>
        <form method="POST" enctype="multipart/form-data">
            <label for="name">Equipment Name:</label>
            <input type="text" id="name" name="name" required>

            <label for="image_data">Image:</label>
            <input type="file" id="image_data" name="image_data" accept="image/*" required>

            <label for="year_of_manufacture">Year of Manufacture:</label>
            <input type="number" id="year_of_manufacture" name="year_of_manufacture" min="1900" max="2100" required>

            <label for="max_borrow_duration">Max Borrow Duration (days):</label>
            <input type="number" id="max_borrow_duration" name="max_borrow_duration" min="1" required>

            <label for="pickup_location">Pickup Location:</label>
            <input type="text" id="pickup_location" name="pickup_location" required>

            <label for="available_hours">Available Hours:</label>
            <input type="text" id="available_hours" name="available_hours" placeholder="e.g., 9:00 AM - 5:00 PM" required>

            <label for="equipment_type">Equipment Type:</label>
            <select id="equipment_type" name="equipment_type" required>
                <option value="" disabled selected>Select Type</option>
                <?php foreach ($equipment_types as $type): ?>
                    <option value="<?= htmlspecialchars($type['type_id']) ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="atelier_id">Atelier:</label>
            <select id="atelier_id" name="atelier_id" required>
                <option value="" disabled selected>Select Atelier</option>
                <?php foreach ($user_ateliers as $atelier): ?>
                    <option value="<?= htmlspecialchars($atelier['atelier_id']) ?>"><?= htmlspecialchars($atelier['atelier_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" <?= ($user_ateliers_empty || $equipment_types_empty) ? 'disabled' : '' ?>>
                <?= ($user_ateliers_empty || $equipment_types_empty) ? 'Cannot Create Equipment' : 'Add Equipment' ?>
            </button>
        </form>
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