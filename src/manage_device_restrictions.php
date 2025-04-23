<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
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

$instructor_id = $_SESSION['user_id'];
$query_studios = $conn->prepare("
    SELECT DISTINCT a.atelier_id, a.atelier_name
    FROM studio_user_permissions sup
    JOIN atelier a ON sup.atelier_id = a.atelier_id
    WHERE sup.user_id = :instructor_id AND sup.access_level = 'instructor'
");
$query_studios->execute([':instructor_id' => $instructor_id]);
$studios = $query_studios->fetchAll(PDO::FETCH_ASSOC);

$selected_studio_id = isset($_POST['studio_id']) ? intval($_POST['studio_id']) : (isset($studios[0]['atelier_id']) ? $studios[0]['atelier_id'] : null);

if (!$selected_studio_id) {
    die("No studios found for this instructor.");
}

$query_equipment = $conn->prepare("
    SELECT * FROM equipment
    WHERE owner_id = :owner_id AND atelier_id = :atelier_id
");
$query_equipment->execute([':owner_id' => $instructor_id, ':atelier_id' => $selected_studio_id]);
$equipment = $query_equipment->fetchAll(PDO::FETCH_ASSOC);

$query_users = $conn->prepare("
    SELECT DISTINCT u.user_id, u.name, u.email
    FROM users u
    JOIN studio_user_permissions sup ON u.user_id = sup.user_id
    WHERE sup.atelier_id = :atelier_id AND sup.access_level = 'registered_user'
");
$query_users->execute([':atelier_id' => $selected_studio_id]);
$users = $query_users->fetchAll(PDO::FETCH_ASSOC);

$query_restrictions = $conn->prepare("
    SELECT r.restriction_id, r.equipment_id, r.user_id, u.name AS user_name, e.name AS equipment_name
    FROM device_user_restrictions r
    JOIN users u ON r.user_id = u.user_id
    JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE e.owner_id = :owner_id AND e.atelier_id = :atelier_id
");
$query_restrictions->execute([':owner_id' => $instructor_id, ':atelier_id' => $selected_studio_id]);
$restrictions = $query_restrictions->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'], $_POST['user_id'])) {
    $equipment_id = $_POST['equipment_id'];
    $user_id = $_POST['user_id'];

    $insert_restriction = $conn->prepare("
        INSERT INTO device_user_restrictions (equipment_id, user_id)
        VALUES (:equipment_id, :user_id)
    ");
    $insert_restriction->execute([':equipment_id' => $equipment_id, ':user_id' => $user_id]);

    //echo "<p style='color:green; text-align:center;'>Restriction added successfully!</p>";
    header("Refresh:0");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_restriction_id'])) {
    $restriction_id = $_POST['remove_restriction_id'];

    $delete_restriction = $conn->prepare("
        DELETE FROM device_user_restrictions WHERE restriction_id = :restriction_id
    ");
    $delete_restriction->execute([':restriction_id' => $restriction_id]);

    //echo "<p style='color:green; text-align:center;'>Restriction removed successfully!</p>";
    header("Refresh:0");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Device Restrictions</title>
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
    <div class="container mt-4">
        <h2>Manage Device Restrictions</h2>
        <p>Restrict device loans to specific users for devices you own.</p>

        <form method="post" action="manage_device_restrictions.php">
            <label for="studio_id">Select Studio:</label>
            <select name="studio_id" id="studio_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($studios as $studio): ?>
                    <option value="<?php echo $studio['atelier_id']; ?>"
                        <?php echo ($selected_studio_id == $studio['atelier_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($studio['atelier_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <h4 class="mt-4">Add Restrictions</h4>
        <?php if (empty($equipment) || empty($users)): ?>
            <p class="text-danger">No devices or users available for restrictions in the selected studio.</p>
        <?php else: ?>
            <form method="post" action="manage_device_restrictions.php">
                <div class="form-group">
                    <label for="equipment_id">Select Equipment:</label>
                    <select name="equipment_id" class="form-control" required>
                        <option value="">Select Equipment</option>
                        <?php foreach ($equipment as $item): ?>
                            <option value="<?php echo $item['equipment_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="user_id">Select User:</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Restriction</button>
            </form>
        <?php endif; ?>

        <h4 class="mt-4">Current Restrictions</h4>
        <?php if (empty($restrictions)): ?>
            <p class="text-danger">No restrictions have been added yet.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Equipment Name</th>
                        <th>User Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($restrictions as $restriction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($restriction['equipment_name']); ?></td>
                            <td><?php echo htmlspecialchars($restriction['user_name']); ?></td>
                            <td>
                                <form method="post" action="manage_device_restrictions.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to remove this restriction?');">
                                    <input type="hidden" name="remove_restriction_id" value="<?php echo $restriction['restriction_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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