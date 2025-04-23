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

$type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : null;

if (!$type_id) {
    echo "Type not specified.";
    exit;
}

$query_category = $conn->prepare("SELECT type_name FROM equipment_types WHERE type_id = :type_id");
$query_category->execute([':type_id' => $type_id]);
$category = $query_category->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    echo "Category not found.";
    exit;
}

$query = $conn->prepare("
     SELECT e.equipment_id, e.name AS equipment_name, e.image_data, u.name AS owner_name
    FROM equipment e
    LEFT JOIN users u ON e.owner_id = u.user_id
    WHERE e.type_id = :type_id
");
$query->execute([':type_id' => $type_id]);
$equipment_list = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['type_name']); ?> Equipment</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .equipment-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .equipment-card img {
            width: 200px;
            height: 200px;
            object-fit: cover; 
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        .category-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .category-title h1 {
            font-size: 36px;
            color: #343a40;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="category-title">
            <h1>Equipment in <?php echo htmlspecialchars($category['type_name']); ?></h1>
            <a href="main_page.php" class="btn btn-secondary mt-3">Back to Types</a>
        </div>
        <div class="row">
            <?php if ($equipment_list): ?>
                <?php foreach ($equipment_list as $equipment): ?>
                    <div class="col-md-4">
                        <div class="equipment-card">
                        <a href="view_equipment.php?equipment_id=<?php echo $equipment['equipment_id']; ?>">
                                <img src="data:image/png;base64,<?php echo htmlspecialchars($equipment['image_data']); ?>" alt="<?php echo htmlspecialchars($equipment['equipment_name']); ?>">
                            </a>
                            <h5><?php echo htmlspecialchars($equipment['equipment_name']); ?></h5>
                            <p><strong>Owner:</strong> <?php echo htmlspecialchars($equipment['owner_name']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center">No equipment found in this category.</p>
                </div>
            <?php endif; ?>
        </div>
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
