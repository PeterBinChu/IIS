<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'studio_manager') {
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

$studio_manager_id = $_SESSION['user_id'];
$query_studios = $conn->prepare("
    SELECT atelier_id, atelier_name 
    FROM atelier 
    WHERE manager_id = :manager_id
");
$query_studios->execute([':manager_id' => $studio_manager_id]);
$studios = $query_studios->fetchAll(PDO::FETCH_ASSOC);

if (!$studios) {
    die("Error: No studios found for this manager.");
}

$selected_studio_id = isset($_POST['atelier_id']) ? intval($_POST['atelier_id']) : (isset($studios[0]['atelier_id']) ? $studios[0]['atelier_id'] : null);

if (!$selected_studio_id) {
    die("Error: Please select a valid studio.");
}

$query_users = $conn->prepare("
    SELECT u.user_id, u.name, u.email, u.role
    FROM users u
    WHERE u.user_id NOT IN (
        SELECT user_id 
        FROM studio_user_permissions 
        WHERE atelier_id = :atelier_id
    )
    AND u.role IN ('registered_user', 'instructor')
");
$query_users->execute([':atelier_id' => $selected_studio_id]);
$users = $query_users->fetchAll(PDO::FETCH_ASSOC);

if (!$users) {
    error_log("No users available for studio ID $selected_studio_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['atelier_id'])) {
    $user_id = intval($_POST['user_id']);
    $atelier_id = intval($_POST['atelier_id']);

    $query_user_role = $conn->prepare("SELECT role FROM users WHERE user_id = :user_id");
    $query_user_role->execute([':user_id' => $user_id]);
    $user_role = $query_user_role->fetchColumn();

    if (!$user_role) {
        die("Error: User role not found.");
    }

    $assign_user = $conn->prepare("
        INSERT INTO studio_user_permissions (user_id, atelier_id, access_level) 
        VALUES (:user_id, :atelier_id, :access_level)
    ");
    $assign_user->execute([
        ':user_id' => $user_id,
        ':atelier_id' => $atelier_id,
        ':access_level' => $user_role
    ]);

    //echo "<p style='color:green; text-align:center;'>User assigned to the studio successfully!</p>";
    header("Location: assign_users_to_studio.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Users to Studio</title>
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
        .studio-selector {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <a href="main_page.php" class="back-btn"><i class="fa fa-home"></i></a>
    <div class="container mt-4">
        <h2>Assign Users to Studio</h2>
        <p>Select users to assign to your studio.</p>

        <form method="post" action="assign_users_to_studio.php" class="studio-selector">
            <label for="atelier_id">Select Studio:</label>
            <select name="atelier_id" id="atelier_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($studios as $studio): ?>
                    <option value="<?php echo $studio['atelier_id']; ?>" 
                        <?php echo ($selected_studio_id == $studio['atelier_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($studio['atelier_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($users): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                            <td>
                                <form method="post" action="assign_users_to_studio.php">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="atelier_id" value="<?php echo $selected_studio_id; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Assign to Studio</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No users available to assign to this studio.</p>
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
