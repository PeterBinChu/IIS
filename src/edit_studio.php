<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
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

if (!isset($_GET['id'])) {
    header("Location: manage_studios.php");
    exit;
}

$atelier_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM atelier WHERE atelier_id = :atelier_id");
$stmt->execute([':atelier_id' => $atelier_id]);
$atelier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atelier) {
    echo "<p style='color:red; text-align:center;'>Atelier not found!</p>";
    exit;
}

$user_query = $conn->prepare("SELECT user_id, name FROM users WHERE role = 'studio_manager'");
$user_query->execute();
$users = $user_query->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atelier_name = $_POST['atelier_name'];
    $manager_id = $_POST['manager_id'] ?? null;

    $update_stmt = $conn->prepare("UPDATE atelier SET atelier_name = :atelier_name, manager_id = :manager_id WHERE atelier_id = :atelier_id");
    if ($update_stmt->execute([':atelier_name' => $atelier_name, ':manager_id' => $manager_id, ':atelier_id' => $atelier_id])) {
        //echo "<p style='color:green; text-align:center;'>Atelier updated successfully!</p>";
        header("Location: manage_studios.php");
        exit;
    } else {
        echo "<p style='color:red; text-align:center;'>Error: Unable to update atelier.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Atelier</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f1f1f1;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Atelier</h2>
        <form method="post" action="edit_studio.php?id=<?php echo $atelier_id; ?>">
            <div class="form-group">
                <label for="atelier_name">Atelier Name:</label>
                <input type="text" class="form-control" name="atelier_name" value="<?php echo htmlspecialchars($atelier['atelier_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="manager_id">Assign Manager:</label>
                <select name="manager_id" class="form-control">
                    <option value="">No Manager</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo ($user['user_id'] == $atelier['manager_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update Atelier</button>
            <a href="manage_studios.php" class="btn btn-secondary">Cancel</a>
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

