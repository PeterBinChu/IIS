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

$user_id = intval($_GET['id'] ?? $_SESSION['user_id']);

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p style='color:red; text-align:center;'>User not found!</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Name validation
    if (empty($name)) {
        echo "<p style='color:red; text-align:center;'>Name cannot be empty or just spaces!</p>";
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<p style='color:red; text-align:center;'>Invalid email format!</p>";
    } 
    else {
        $email_check_stmt = $conn->prepare("SELECT * FROM users WHERE email = :email AND user_id != :user_id");
        $email_check_stmt->execute([':email' => $email, ':user_id' => $user_id]);
        if ($email_check_stmt->fetch()) {
            echo "<p style='color:red; text-align:center;'>Email is already taken by another user!</p>";
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET name = :name, email = :email WHERE user_id = :user_id");
            if ($update_stmt->execute([':name' => $name, ':email' => $email, ':user_id' => $user_id])) {
                $_SESSION['name'] = $name;
                header("Location: main_page.php");
                exit;
            } else {
                echo "<p style='color:red; text-align:center;'>Error updating profile. Please try again.</p>";
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
    <title>Edit User</title>
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
        <h2>Edit Profile</h2>
        <form method="post" action="edit_profile.php?id=<?php echo $user_id; ?>">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="main_page.php" class="btn btn-secondary">Cancel</a>
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

