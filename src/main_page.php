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

$query_types = $conn->prepare("
    SELECT et.type_id, et.type_name, p.base64_data 
    FROM equipment_types et
    LEFT JOIN equipment_pictures p ON et.type_id = p.id
");
$query_types->execute();
$equipment_types = $query_types->fetchAll(PDO::FETCH_ASSOC);

$selected_type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : null;
$equipment = [];

if ($selected_type_id) {
    $query_equipment = $conn->prepare("SELECT * FROM equipment WHERE type_id = :type_id AND available = 1");
    $query_equipment->execute([':type_id' => $selected_type_id]);
    $equipment = $query_equipment->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <style>
        .sidebar {
            width: 300px;
            background-color: #343a40;
            color: #fff;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
        }

        .sidebar-title {
            margin-top: 10px;
            margin-bottom: 40px;
            color: #fff;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
        }

        .sidebar-link {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            font-size: 16px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .sidebar-link i {
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar-link:hover {
            background-color: #495057;
            text-decoration: none;
            color: #f8f9fa;
        }

        .logout-link {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #555;
            font-size: 16px;
        }

        .logout-link i {
            margin-right: 10px;
        }

        .content {
            margin-left: 300px;
            padding: 20px;
        }

        .equipment-card {
            height: 200px;
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
        }

        .equipment-card img {
            width: 100px;
            height: 100px;
            margin-right: 15px;
            border-radius: 8px;
            object-fit: cover;
        }

        .equipment-card .btn {
            align-items: center;
        }
    </style>
</head>

<body>
    <div class="sidebar d-flex flex-column">
        <h3 class="sidebar-title">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h3>

        <a href="reservations.php" class="sidebar-link">
            <i class="fas fa-book"></i> My Reservations
        </a>

        <a href="borrowings.php" class="sidebar-link">
            <i class="fas fa-hands"></i> My Borrowings
        </a>

        <?php if ($_SESSION['role'] === 'administrator'): ?>
            <a href="my_equipment.php" class="sidebar-link">
                <i class="fas fa-cubes"></i> Equipment
            </a>
            <a href="manage_studio_users.php" class="sidebar-link">
                <i class="fas fa-users-cog"></i> Manage User
            </a>
            <a href="manage_studios.php" class="sidebar-link">
                <i class="fas fa-warehouse"></i> Manage Studio
            </a>
            <a href="manage_equipment_types.php" class="sidebar-link">
                <i class="fas fa-tools"></i> Manage Equipment Types
            </a>
        <?php elseif ($_SESSION['role'] === 'studio_manager'): ?>
            <a href="my_equipment.php" class="sidebar-link">
                <i class="fas fa-cubes"></i> Studio Equipment
            </a>
            <a href="manage_studio_users.php" class="sidebar-link">
                <i class="fas fa-users-cog"></i> Manage User
            </a>
            <a href="assign_users_to_studio.php" class="sidebar-link">
                <i class="far fa-address-book"></i> Add User
            </a>
            <a href="manage_equipment_types.php" class="sidebar-link">
                <i class="fas fa-tools"></i> Manage Equipment Types
            </a>
        <?php elseif ($_SESSION['role'] === 'instructor'): ?>
            <a href="edit_reservations.php?id=<?php echo $_SESSION['user_id']; ?>" class="sidebar-link">
                <i class="fas fa-folder"></i> Edit reservations
            </a>
            <a href="my_equipment.php" class="sidebar-link">
                <i class="fas fa-cubes"></i> My Equipment
            </a>
            <a href="manage_device_restrictions.php" class="sidebar-link">
                <i class="fas fa-user-lock"></i> Restrict
            </a>
            <a href="create_equipment.php?id=<?php echo $_SESSION['user_id']; ?>" class="sidebar-link">
                <i class="fas fa-download"></i> Add equipment
            </a>
        <?php endif; ?>

        <a href="edit_profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="sidebar-link">
            <i class="fas fa-pen"></i> Edit Profile
        </a>

        <a href="logout.php" class="text-danger mt-auto logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>


    <div class="content">
        <h2>Available Equipment</h2>
        <?php if (!$selected_type_id): ?>
            <p>Select an equipment type to view available equipment:</p>
            <div class="row">
                <?php foreach ($equipment_types as $type): ?>
                    <div class="col-md-4">
                        <div class="card equipment-card">
                            <?php if ($type['base64_data']): ?>
                                <img src="data:image/png;base64,<?php echo htmlspecialchars($type['base64_data']); ?>" alt="<?php echo htmlspecialchars($type['type_name']); ?>">
                            <?php else: ?>
                                <img src="placeholder.png" alt="No Image">
                            <?php endif; ?>
                            <h5><?php echo htmlspecialchars($type['type_name']); ?></h5>
                            <a href="equipment_page.php?type_id=<?php echo urlencode($type['type_id']); ?>" class="btn btn-primary">View Equipments</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p><a href="dashboard.php" class="btn btn-secondary">Back to Equipment Types</a></p>
            <h4>Equipment in <?php echo htmlspecialchars($equipment_types[array_search($selected_type_id, array_column($equipment_types, 'type_id'))]['type_name']); ?></h4>

            <?php if ($equipment): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Equipment Name</th>
                            <th>Max Borrow Duration</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipment as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['max_borrow_duration']); ?> days</td>
                                <td>
                                    <a href="reserve_equipment.php?id=<?php echo $item['equipment_id']; ?>" class="btn btn-primary btn-sm">Reserve</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No equipment available in this category.</p>
            <?php endif; ?>
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