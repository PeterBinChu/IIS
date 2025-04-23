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

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$query = $conn->prepare("
    SELECT 
        e.equipment_id,
        e.name AS equipment_name,
        e.year_of_manufacture,
        e.status,
        u.name AS owner_name,
        a.atelier_name,
        (
            SELECT COUNT(*) 
            FROM loans 
            WHERE loans.equipment_id = e.equipment_id AND loans.loan_status = 'active'
        ) AS is_borrowed
    FROM equipment e
    LEFT JOIN users u ON e.owner_id = u.user_id
    LEFT JOIN atelier a ON e.atelier_id = a.atelier_id
    WHERE 
        (:user_role = 'administrator') OR
        (:user_role = 'studio_manager' AND a.manager_id = :user_id) OR
        (:user_role = 'instructor' AND e.owner_id = :user_id);
");
$query->execute([':user_role' => $user_role, ':user_id' => $user_id]);
$equipment = $query->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment_id'])) {
    $delete_id = intval($_POST['delete_equipment_id']);

    $borrow_check_query = $conn->prepare("
        SELECT COUNT(*) AS is_borrowed 
        FROM loans 
        WHERE equipment_id = :equipment_id AND loan_status = 'active'
    ");
    $borrow_check_query->execute([':equipment_id' => $delete_id]);
    $borrow_check = $borrow_check_query->fetch(PDO::FETCH_ASSOC);

    if ($borrow_check['is_borrowed'] > 0) {
        echo "<p style='color:red; text-align:center;'>This equipment is currently borrowed and cannot be deleted.</p>";
    } else {
        try {
            $conn->beginTransaction();

            $delete_reservations_query = $conn->prepare("DELETE FROM reservations WHERE equipment_id = :equipment_id");
            $delete_reservations_query->execute([':equipment_id' => $delete_id]);

            $delete_equipment_query = $conn->prepare("DELETE FROM equipment WHERE equipment_id = :equipment_id");
            $delete_equipment_query->execute([':equipment_id' => $delete_id]);

            $conn->commit();

            //echo "<p style='color:green; text-align:center;'>Equipment and associated reservations deleted successfully!</p>";
            header("Refresh:0");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            echo "<p style='color:red; text-align:center;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restrict_equipment_id'], $_POST['status'])) {
    $equipment_id = intval($_POST['restrict_equipment_id']);
    $status = $_POST['status'];

    $borrow_check_query = $conn->prepare("
        SELECT COUNT(*) AS is_borrowed 
        FROM loans 
        WHERE equipment_id = :equipment_id AND loan_status = 'active'
    ");
    $borrow_check_query->execute([':equipment_id' => $equipment_id]);
    $borrow_check = $borrow_check_query->fetch(PDO::FETCH_ASSOC);

    if ($borrow_check['is_borrowed'] > 0) {
        echo "<p style='color:red; text-align:center;'>This equipment is currently borrowed and its status cannot be changed.</p>";
    } else {
        try {
            $conn->beginTransaction();

            $update_status_query = $conn->prepare("UPDATE equipment SET status = :status WHERE equipment_id = :equipment_id");
            $update_status_query->execute([':status' => $status, ':equipment_id' => $equipment_id]);

            if ($status === 'prohibited') {
                $delete_reservations_query = $conn->prepare("DELETE FROM reservations WHERE equipment_id = :equipment_id");
                $delete_reservations_query->execute([':equipment_id' => $equipment_id]);

                //echo "<p style='color:green; text-align:center;'>All reservations for this equipment have been deleted due to status change to prohibited.</p>";
            }

            $conn->commit();

            //echo "<p style='color:green; text-align:center;'>Equipment status updated successfully!</p>";
            header("Refresh:0");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            echo "<p style='color:red; text-align:center;'>Error updating status: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Equipment</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .btn-custom {
            background-color: #6c757d;
            color: #fff;
            border: none;
            padding: 8px 15px;
            font-size: 14px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            background-color: #5a6268;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
            color: #f8f9fa;
        }

        .btn-update {
            background-color: #007bff;
        }

        .btn-update:hover {
            background-color: #0056b3;
        }

        .btn-remove {
            background-color: #dc3545;
        }

        .btn-remove:hover {
            background-color: #c82333;
        }

        .btn-custom i {
            margin-right: 5px;
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
    <div class="container mt-4">
        <h2>My Equipment</h2>
        <p>Manage your equipment: edit details, delete devices, or restrict borrowing.</p>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Year</th>
                    <th>Owner</th>
                    <th>Studio</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipment as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['year_of_manufacture']); ?></td>
                        <td><?php echo htmlspecialchars($item['owner_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['atelier_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($item['status']); ?>
                            <?php if ($item['is_borrowed'] == 0 && ($user_role === 'instructor' || $user_role === 'administrator')): ?>
                            <form method="post" action="my_equipment.php" style="display:inline-block;">
                                <input type="hidden" name="restrict_equipment_id" value="<?php echo $item['equipment_id']; ?>">
                                <select name="status" class="form-control" style="display:inline-block; width:auto;">
                                    <option value="available" <?php echo $item['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="prohibited" <?php echo $item['status'] === 'prohibited' ? 'selected' : ''; ?>>Prohibit</option>
                                </select>
                                <button type="submit" class="btn btn-custom btn-update">
                                    <i class="fa fa-sync"></i> Update
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['is_borrowed'] == 0 && ($user_role === 'instructor' || $user_role === 'administrator')): ?>
                                <a href="edit_equipment.php?id=<?php echo $item['equipment_id']; ?>" class="btn btn-custom btn-update">
                                    <i class="fa fa-edit"></i> Edit
                                </a>
                                <form method="post" action="my_equipment.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this equipment?');">
                                    <input type="hidden" name="delete_equipment_id" value="<?php echo $item['equipment_id']; ?>">
                                    <button type="submit" class="btn btn-custom btn-remove">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">No actions available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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
