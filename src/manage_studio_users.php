<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['administrator', 'studio_manager'])) {
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

function can_delete_user($user_id, $conn)
{
    $owns_borrowed_query = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM equipment e
        JOIN loans l ON e.equipment_id = l.equipment_id
        WHERE e.owner_id = :user_id AND l.loan_status = 'active'
    ");
    $owns_borrowed_query->execute([':user_id' => $user_id]);
    $owns_borrowed = $owns_borrowed_query->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    $is_borrowing_query = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM loans 
        WHERE user_id = :user_id AND loan_status = 'active'
    ");
    $is_borrowing_query->execute([':user_id' => $user_id]);
    $is_borrowing = $is_borrowing_query->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    $is_manager_query = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM atelier 
        WHERE manager_id = :user_id
    ");
    $is_manager_query->execute([':user_id' => $user_id]);
    $is_manager = $is_manager_query->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    return !$owns_borrowed && !$is_borrowing && !$is_manager;
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'administrator';

if ($is_admin) {
    $query_studios = $conn->prepare("SELECT atelier_id, atelier_name FROM atelier");
    $query_studios->execute();
} else {
    $query_studios = $conn->prepare("SELECT atelier_id, atelier_name FROM atelier WHERE manager_id = :manager_id");
    $query_studios->execute([':manager_id' => $user_id]);
}

$studios = $query_studios->fetchAll(PDO::FETCH_ASSOC);

$selected_studio_id = isset($_POST['atelier_id']) && $_POST['atelier_id'] !== "" ? intval($_POST['atelier_id']) : NULL;

if ($is_admin) {
    $query_users = $conn->prepare("
        SELECT u.user_id, 
               u.name, 
               u.email, 
               u.role AS user_role, 
               COALESCE(p.access_level, u.role) AS access_level, 
               a.atelier_name, 
               p.atelier_id
        FROM users u
        LEFT JOIN studio_user_permissions p ON u.user_id = p.user_id
        LEFT JOIN atelier a ON p.atelier_id = a.atelier_id
        WHERE (:atelier_id IS NULL AND p.atelier_id IS NULL) OR (:atelier_id IS NOT NULL AND p.atelier_id = :atelier_id)
    ");
    $query_users->execute([':atelier_id' => $selected_studio_id]);
} else {
    $query_users = $conn->prepare("
    SELECT 
        u.user_id, 
        u.name, 
        u.email, 
        u.role AS user_role, 
        u.role AS access_level, 
        a.atelier_name, 
        p.atelier_id
    FROM users u
    LEFT JOIN studio_user_permissions p ON u.user_id = p.user_id
    LEFT JOIN atelier a ON p.atelier_id = a.atelier_id
    WHERE a.manager_id = :manager_id 
      AND (:atelier_id IS NULL OR p.atelier_id = :atelier_id)
");
    $query_users->execute([':manager_id' => $user_id, ':atelier_id' => $selected_studio_id]);
}

$users = $query_users->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_id'], $_POST['new_role'])) {
        $user_id = $_POST['user_id'];
        $new_role = $_POST['new_role'];

        $update_user_role = $conn->prepare("UPDATE users SET role = :new_role WHERE user_id = :user_id");
        $update_user_role->execute([':new_role' => $new_role, ':user_id' => $user_id]);

        $update_access_level = $conn->prepare("
            UPDATE studio_user_permissions 
            SET access_level = :new_role 
            WHERE user_id = :user_id AND atelier_id = :atelier_id
        ");
        $update_access_level->execute([':new_role' => $new_role, ':user_id' => $user_id, ':atelier_id' => $selected_studio_id]);

        //echo "<p style='color:green; text-align:center;'>User role updated successfully!</p>";
        header("Refresh:0");
        exit;
    }

    if (isset($_POST['move_user_id'], $_POST['target_atelier_id'], $_POST['current_role'])) {
        $move_user_id = $_POST['move_user_id'];
        $target_atelier_id = $_POST['target_atelier_id'];
        $current_role = $_POST['current_role'];

        $remove_user = $conn->prepare("
            DELETE FROM studio_user_permissions 
            WHERE user_id = :user_id AND atelier_id = :atelier_id
        ");
        $remove_user->execute([':user_id' => $move_user_id, ':atelier_id' => $selected_studio_id]);

        $assign_user = $conn->prepare("
            INSERT INTO studio_user_permissions (user_id, atelier_id, access_level)
            VALUES (:user_id, :atelier_id, :access_level)
        ");
        $assign_user->execute([
            ':user_id' => $move_user_id,
            ':atelier_id' => $target_atelier_id,
            ':access_level' => $current_role
        ]);

        //echo "<p style='color:green; text-align:center;'>User moved to the new studio successfully!</p>";
        header("Refresh:0");
        exit;
    }

    if (isset($_POST['delete_user_id'], $_POST['current_atelier_id'])) {
        $delete_user_id = $_POST['delete_user_id'];
        $current_atelier_id = $_POST['current_atelier_id'];

        $delete_query = $conn->prepare("
            DELETE FROM studio_user_permissions 
            WHERE user_id = :user_id AND atelier_id = :atelier_id
        ");
        $delete_query->execute([':user_id' => $delete_user_id, ':atelier_id' => $current_atelier_id]);

        //echo "<p style='color:green; text-align:center;'>User removed from studio successfully!</p>";
        header("Refresh:0");
        exit;
    }

    if (isset($_POST['delete_user_completely_id'])) {
        $delete_user_completely_id = intval($_POST['delete_user_completely_id']);

        if (!can_delete_user($delete_user_completely_id, $conn)) {
            echo "<p style='color:red; text-align:center;'>Cannot delete this user as they are connected to borrowing activity or are a studio manager.</p>";
        } else {
            try {
                $conn->beginTransaction();

                $fetch_equipment_query = $conn->prepare("SELECT equipment_id FROM equipment WHERE owner_id = :owner_id");
                $fetch_equipment_query->execute([':owner_id' => $delete_user_completely_id]);
                $equipment_ids = $fetch_equipment_query->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($equipment_ids)) {
                    $delete_reservations_query = $conn->prepare("
                        DELETE FROM reservations 
                        WHERE equipment_id IN (" . implode(',', array_map('intval', $equipment_ids)) . ")
                    ");
                    $delete_reservations_query->execute();
                }

                $delete_equipment_query = $conn->prepare("DELETE FROM equipment WHERE owner_id = :owner_id");
                $delete_equipment_query->execute([':owner_id' => $delete_user_completely_id]);

                $delete_loans_query = $conn->prepare("DELETE FROM loans WHERE user_id = :user_id");
                $delete_loans_query->execute([':user_id' => $delete_user_completely_id]);

                $delete_permissions_query = $conn->prepare("DELETE FROM studio_user_permissions WHERE user_id = :user_id");
                $delete_permissions_query->execute([':user_id' => $delete_user_completely_id]);

                $delete_user_query = $conn->prepare("DELETE FROM users WHERE user_id = :user_id");
                $delete_user_query->execute([':user_id' => $delete_user_completely_id]);

                $conn->commit();

                //echo "<p style='color:green; text-align:center;'>User and all associated data deleted successfully!</p>";
                header("Refresh:0");
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                echo "<p style='color:red; text-align:center;'>Error deleting user: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Studio Users</title>
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

        .table-container {
            margin-top: 50px;
        }

        table {
            text-align: center;
            width: 100%;
        }

        th,
        td {
            vertical-align: middle;
        }

        select,
        button {
            display: inline-block;
            text-align: center;
        }
    </style>
</head>

<body>
    <a href="main_page.php" class="back-btn"><i class="fa fa-home"></i></a>

    <div class="container mt-4">
        <h2 class="text-center">Manage Studio Users</h2>

        <form method="post" action="" class="text-center">
            <label for="atelier_id">Select Studio:</label>
            <select name="atelier_id" id="atelier_id" class="form-control w-auto d-inline-block" onchange="this.form.submit()">
                <?php if ($is_admin): ?>
                    <option value="" <?php echo ($selected_studio_id === NULL) ? 'selected' : ''; ?>>Without Studios</option>
                <?php endif; ?>
                <?php foreach ($studios as $studio): ?>
                    <option value="<?php echo $studio['atelier_id']; ?>" <?php echo ($selected_studio_id == $studio['atelier_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($studio['atelier_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="table-container">
            <table class="table table-bordered mt-4">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Current Studio</th>
                        <th>Role</th>
                        <th>New Role</th>
                        <th>Move to Studio</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['atelier_name'] ?? 'No Studio'); ?></td>
                            <td><?php echo htmlspecialchars($user['access_level'] ?? 'None'); ?></td>
                            <td>
                                <form method="post" action="">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="new_role" class="form-control">
                                        <?php if ($is_admin): ?>
                                            <option value="registered_user" <?php echo $user['user_role'] === 'registered_user' ? 'selected' : ''; ?>>Registered User</option>
                                            <option value="instructor" <?php echo $user['user_role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                            <option value="studio_manager" <?php echo $user['user_role'] === 'studio_manager' ? 'selected' : ''; ?>>Studio Manager</option>
                                            <option value="administrator" <?php echo $user['user_role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                                        <?php else: ?>
                                            <option value="registered_user" <?php echo $user['user_role'] === 'registered_user' ? 'selected' : ''; ?>>Registered User</option>
                                            <option value="instructor" <?php echo $user['user_role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm mt-2">Update Role</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="">
                                    <input type="hidden" name="move_user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="current_role" value="<?php echo $user['access_level']; ?>">
                                    <select name="target_atelier_id" class="form-control">
                                        <?php foreach ($studios as $studio): ?>
                                            <?php if ($studio['atelier_id'] != $selected_studio_id): ?>
                                                <option value="<?php echo $studio['atelier_id']; ?>">
                                                    <?php echo htmlspecialchars($studio['atelier_name']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm mt-2">Move</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to remove this user from the studio?');">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="current_atelier_id" value="<?php echo $user['atelier_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove from Studio</button>
                                </form>

                                <?php if ($is_admin): ?>
                                    <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this user completely?');" class="mt-2">
                                        <input type="hidden" name="delete_user_completely_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete User</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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