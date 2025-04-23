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

function encode_image_to_base64($file_path) {
    $image_data = file_get_contents($file_path);
    return base64_encode($image_data);
}

function is_equipment_type_borrowed($type_id, $conn) {
    $query = $conn->prepare("
        SELECT COUNT(*) AS borrowed_count 
        FROM equipment e
        JOIN loans l ON e.equipment_id = l.equipment_id
        WHERE e.type_id = :type_id AND l.loan_status = 'active'
    ");
    $query->execute([':type_id' => $type_id]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    return $result['borrowed_count'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $type_name = $_POST['type_name'];
    $base64_data = null;

    if (isset($_FILES['type_image']) && $_FILES['type_image']['error'] === UPLOAD_ERR_OK) {
        $base64_data = encode_image_to_base64($_FILES['type_image']['tmp_name']);

        $stmt = $conn->prepare("INSERT INTO equipment_pictures (name, base64_data) VALUES (:name, :base64_data)");
        $stmt->execute([':name' => $_FILES['type_image']['name'], ':base64_data' => $base64_data]);

        $picture_id = $conn->lastInsertId();
    }

    $stmt = $conn->prepare("INSERT INTO equipment_types (type_name, type_id) VALUES (:type_name, :picture_id)");
    $stmt->execute([':type_name' => $type_name, ':picture_id' => $picture_id ?? null]);

    echo "<p style='color:green; text-align:center;'>Equipment type added successfully!</p>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_type'])) {
    $type_id = intval($_POST['type_id']);
    $new_name = trim($_POST['type_name']) !== '' ? $_POST['type_name'] : null;
    $new_image = null;

    if (isset($_FILES['type_image']) && $_FILES['type_image']['error'] === UPLOAD_ERR_OK) {
        $new_image = encode_image_to_base64($_FILES['type_image']['tmp_name']);

        $update_image_query = $conn->prepare("
            UPDATE equipment_pictures 
            SET name = :name, base64_data = :base64_data 
            WHERE id = :type_id
        ");
        $update_image_query->execute([':name' => $_FILES['type_image']['name'], ':base64_data' => $new_image, ':type_id' => $type_id]);
    }

    if ($new_name !== null) {
        $update_name_query = $conn->prepare("UPDATE equipment_types SET type_name = :type_name WHERE type_id = :type_id");
        $update_name_query->execute([':type_name' => $new_name, ':type_id' => $type_id]);
    }

    echo "<p style='color:green; text-align:center;'>Equipment type updated successfully!</p>";
}

if (isset($_GET['delete_id'])) {
    $type_id = intval($_GET['delete_id']);

    if (is_equipment_type_borrowed($type_id, $conn)) {
        echo "<p style='color:red; text-align:center;'>Cannot delete this equipment type as it has borrowed equipment associated with it.</p>";
    } else {
        try {
            $conn->beginTransaction();

            $fetch_equipment_query = $conn->prepare("SELECT equipment_id FROM equipment WHERE type_id = :type_id");
            $fetch_equipment_query->execute([':type_id' => $type_id]);
            $equipment_ids = $fetch_equipment_query->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($equipment_ids)) {
                $delete_reservations_query = $conn->prepare("DELETE FROM reservations WHERE equipment_id IN (" . implode(',', array_map('intval', $equipment_ids)) . ")");
                $delete_reservations_query->execute();
            }

            $delete_equipment_query = $conn->prepare("DELETE FROM equipment WHERE type_id = :type_id");
            $delete_equipment_query->execute([':type_id' => $type_id]);

            $fetch_picture_query = $conn->prepare("SELECT p.id AS picture_id FROM equipment_types et LEFT JOIN equipment_pictures p ON et.type_id = p.id WHERE et.type_id = :type_id");
            $fetch_picture_query->execute([':type_id' => $type_id]);
            $result = $fetch_picture_query->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['picture_id']) {
                $delete_picture_query = $conn->prepare("DELETE FROM equipment_pictures WHERE id = :picture_id");
                $delete_picture_query->execute([':picture_id' => $result['picture_id']]);
            }

            $delete_type_query = $conn->prepare("DELETE FROM equipment_types WHERE type_id = :type_id");
            $delete_type_query->execute([':type_id' => $type_id]);

            $conn->commit();

            echo "<p style='color:green; text-align:center;'>Equipment type and associated data deleted successfully!</p>";
        } catch (Exception $e) {
            $conn->rollBack();
            echo "<p style='color:red; text-align:center;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

$query = $conn->prepare("
    SELECT et.type_id, et.type_name, p.base64_data
    FROM equipment_types et
    LEFT JOIN equipment_pictures p ON et.type_id = p.id
");
$query->execute();
$equipment_types = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Equipment Types</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
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
        <h2>Manage Equipment Types</h2>
        
        <form method="post" action="manage_equipment_types.php" enctype="multipart/form-data">
            <input type="hidden" name="add_type" value="1">
            <div class="form-group">
                <label for="type_name">Equipment Type Name:</label>
                <input type="text" class="form-control" name="type_name" required>
            </div>
            <div class="form-group">
                <label for="type_image">Upload Image (Optional):</label>
                <input type="file" class="form-control" name="type_image" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary">Add Equipment Type</button>
        </form>

        <h4 class="mt-4">Existing Equipment Types</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Type Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipment_types as $type): ?>
                    <tr>
                        <td>
                            <?php if ($type['base64_data']): ?>
                                <img src="data:image/png;base64,<?php echo htmlspecialchars($type['base64_data']); ?>" 
                                     alt="<?php echo htmlspecialchars($type['type_name']); ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover;">
                            <?php else: ?>
                                <span>No Image</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                        <td>
                            <form method="post" action="manage_equipment_types.php" enctype="multipart/form-data" style="display:inline-block;">
                                <input type="hidden" name="edit_type" value="1">
                                <input type="hidden" name="type_id" value="<?php echo $type['type_id']; ?>">
                                <div class="form-group">
                                    <input type="text" name="type_name" class="form-control mb-1" placeholder="New Name (Optional)">
                                    <input type="file" name="type_image" class="form-control" accept="image/*">
                                </div>
                                <button type="submit" class="btn btn-sm btn-warning">Edit</button>
                            </form>
                            <a href="manage_equipment_types.php?delete_id=<?php echo $type['type_id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this equipment type?');">Delete</a>
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
