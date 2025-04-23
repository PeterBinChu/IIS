<?php
include 'db_connection.php';

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $sql->execute([':email' => $email]);

    if ($sql->rowCount() == 1) {
        $row = $sql->fetch(PDO::FETCH_ASSOC);

        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['role'] = $row['role']; 
            header("Location: main_page.php");
        } else {
            echo "<p style='color:red; text-align: center; vertical-align: text-top'>Invalid password!</p>";
        }
    } else {
        echo "<p style='color:red; text-align: center;'>User not found!</p>";
    }

    $conn = null; 
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #e0f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 350px;
        }
        .login-container h2 {
            color: #00796b;
            text-align: center;
            margin-bottom: 20px;
        }
        .login-container .btn-primary {
            background-color: #00796b;
            border-color: #00796b;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" class="form-control" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
    </div>
</body>
</html>
