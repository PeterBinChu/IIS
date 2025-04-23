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

function is_atelier_borrowed($atelier_id, $conn) {
    $query = $conn->prepare("
        SELECT COUNT(*) AS borrowed_count 
        FROM equipment e
        JOIN loans l ON e.equipment_id = l.equipment_id
        WHERE e.atelier_id = :atelier_id AND l.loan_status = 'active'
    ");
    $query->execute([':atelier_id' => $atelier_id]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    return $result['borrowed_count'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atelier_name'])) {
    $atelier_name = $_POST['atelier_name'];
    $manager_id = $_POST['manager_id'] ?? null;

    try {
        $stmt = $conn->prepare("INSERT INTO atelier (atelier_name, manager_id) VALUES (:atelier_name, :manager_id)");
        $stmt->execute([':atelier_name' => $atelier_name, ':manager_id' => $manager_id]);

        echo "<p style='color:green; text-align:center;'>Studio added successfully!</p>";
    } catch (Exception $e) {
        echo "<p style='color:red; text-align:center;'>Error adding studio: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_studio_id'])) {
    $atelier_id = intval($_POST['delete_studio_id']);

    if (is_atelier_borrowed($atelier_id, $conn)) {
        echo "<p style='color:red; text-align:center;'>Cannot delete this atelier as it has borrowed equipment associated with it.</p>";
    } else {
        try {
            $conn->beginTransaction();
    
            // Step 1: Delete rows from `studio_user_permissions` referencing this atelier
            $delete_permissions_query = $conn->prepare("DELETE FROM studio_user_permissions WHERE atelier_id = :atelier_id");
            $delete_permissions_query->execute([':atelier_id' => $atelier_id]);
    
            // Step 2: Fetch all equipment IDs associated with the studio
            $fetch_equipment_query = $conn->prepare("SELECT equipment_id FROM equipment WHERE atelier_id = :atelier_id");
            $fetch_equipment_query->execute([':atelier_id' => $atelier_id]);
            $equipment_ids = $fetch_equipment_query->fetchAll(PDO::FETCH_COLUMN);
    
            // Step 3: Delete reservations associated with the equipment
            if (!empty($equipment_ids)) {
                $delete_reservations_query = $conn->prepare("DELETE FROM reservations WHERE equipment_id IN (" . implode(',', array_map('intval', $equipment_ids)) . ")");
                $delete_reservations_query->execute();
            }
    
            // Step 4: Delete loans associated with the equipment
            if (!empty($equipment_ids)) {
                $delete_loans_query = $conn->prepare("DELETE FROM loans WHERE equipment_id IN (" . implode(',', array_map('intval', $equipment_ids)) . ")");
                $delete_loans_query->execute();
            }
    
            // Step 5: Delete equipment linked to the studio
            $delete_equipment_query = $conn->prepare("DELETE FROM equipment WHERE atelier_id = :atelier_id");
            $delete_equipment_query->execute([':atelier_id' => $atelier_id]);
    
            // Step 6: Delete the studio itself
            $delete_studio_query = $conn->prepare("DELETE FROM atelier WHERE atelier_id = :atelier_id");
            $delete_studio_query->execute([':atelier_id' => $atelier_id]);
    
            $conn->commit();
    
            echo "<p style='color:green; text-align:center;'>Studio and associated data deleted successfully!</p>";
        } catch (Exception $e) {
            $conn->rollBack();
            echo "<p style='color:red; text-align:center;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

$query = $conn->prepare("SELECT a.atelier_id, a.atelier_name, u.name AS manager_name FROM atelier a LEFT JOIN users u ON a.manager_id = u.user_id");
$query->execute();
$ateliers = $query->fetchAll(PDO::FETCH_ASSOC);

$user_query = $conn->prepare("SELECT user_id, name FROM users WHERE role = 'studio_manager'");
$user_query->execute();
$users = $user_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Studios</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f1f1; padding: 20px; }
        .container { background-color: #fff; border-radius: 8px; box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1); padding: 20px; }
        .form-container { margin-bottom: 30px; }
        .back-btn { position: absolute; top: 20px; right: 20px; background-color: #6c757d; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 5px; transition: background-color 0.3s ease; }
        .back-btn:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <a href="main_page.php" class="back-btn"><i class="fa fa-home"></i></a>
    <div class="container">
        <h2>Manage Studios (Ateliers)</h2>
        <p>Create a new atelier or manage existing ones.</p>

        <div class="form-container">
            <h4>Create New Atelier</h4>
            <form method="post" action="manage_studios.php">
                <div class="form-group">
                    <label for="atelier_name">Atelier Name:</label>
                    <input type="text" class="form-control" name="atelier_name" required>
                </div>
                <div class="form-group">
                    <label for="manager_id">Assign Manager:</label>
                    <select name="manager_id" class="form-control">
                        <option value="">Select Manager (optional)</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create Atelier</button>
            </form>
        </div>

        <h4>Existing Ateliers</h4>
        <?php if ($ateliers): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Atelier Name</th>
                        <th>Manager</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ateliers as $atelier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($atelier['atelier_name']); ?></td>
                            <td><?php echo htmlspecialchars($atelier['manager_name'] ?? 'None'); ?></td>
                            <td>
                                <a href="edit_studio.php?id=<?php echo $atelier['atelier_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this atelier?');">
                                    <input type="hidden" name="delete_studio_id" value="<?php echo $atelier['atelier_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No ateliers found.</p>
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
