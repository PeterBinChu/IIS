<?php
include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = $_POST['email'] ?? '';
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT) ?? '';

    if (empty($name)) {
        echo "<p style='color:red; text-align:center;'>Name cannot be empty or just spaces.</p>";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<p style='color:red; text-align:center;'>Invalid email format. Email must follow the format: example@example.com</p>";
        exit;
    }

    if (strlen($password) < 4) {
        echo "<p style='color:red; text-align:center;'>Password must be at least 4 characters long.</p>";
        exit;
    }

    if ($name === 'Admin IIS') {
        $role = 'administrator';
    } else {
        $role = 'registered_user';
    }

    $check_query = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $check_query->execute([':email' => $email]);

    if ($check_query->rowCount() > 0) {
        echo "<p style='color:red; text-align: center;'>User already exists!</p>";
    } else {
        $sql = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
        $result = $sql->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $password,
            ':role' => $role
        ]);

        if ($result) {
            header("Location: login.php");
        } else {
            echo "<p style='color:red; text-align: center;'>Error: Unable to register user.</p>";
        }
    }

    $conn = null;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Register</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #e8f5e9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .register-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 350px;
        }

        .register-container h2 {
            color: #388e3c;
            text-align: center;
            margin-bottom: 20px;
        }

        .register-container .btn-success {
            background-color: #388e3c;
            border-color: #388e3c;
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h2>Register</h2>
        <form method="post" action="register.php" onsubmit="return validateForm();">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" class="form-control" name="password" required>
            </div>
            <button type="submit" class="btn btn-success btn-block">Register</button>
        </form>
    </div>
    <script>
        function validateForm() {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();

            if (name.length === 0) {
                alert("Name cannot be empty or just spaces.");
                return false;
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert("Invalid email format. Email must follow the format: example@example.example");
                return false;
            }

            if (password.length < 4) {
                alert("Password must be at least 4 characters long.");
                return false;
            }

            return true;
        }
    </script>
</body>
</html>