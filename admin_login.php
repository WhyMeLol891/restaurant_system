<?php 

include 'db.php';

$error = '';
$success = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check if the username and password are correct
        $sql = "SELECT * FROM admin_account WHERE username=? AND password=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['username'] = $username;
            // Login successful, redirect to admin page
            header("Location: admin.php");
            exit();
        } else {
            // Login failed, show an error message
            $error = '❌ Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Restaurant System</title> 
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E8E 50%, #4ECDC4 100%);
            padding: 1rem;
        }

        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .login-header h1 {
            font-size: 2rem;
            color: #2C3E50;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #5A6C7D;
            font-size: 0.95rem;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: #2C3E50;
            font-size: 0.9rem;
        }

        .form-group input {
            padding: 1rem;
            border: 2px solid #E0E6ED;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4ECDC4;
            box-shadow: 0 0 0 4px rgba(78, 205, 196, 0.1);
        }

        .form-group input::placeholder {
            color: #A8B5C7;
        }

        .login-button {
            padding: 1rem;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E8E 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 107, 107, 0.4);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .error-message {
            background: rgba(255, 107, 107, 0.15);
            border-left: 4px solid #FF6B6B;
            color: #842029;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .success-message {
            background: rgba(93, 255, 183, 0.15);
            border-left: 4px solid #5DFFB7;
            color: #155E3B;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #E0E6ED;
            color: #5A6C7D;
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-header .logo {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="logo">🍽️</div>
                <h1>Restaurant Admin</h1>
                <p>Secure Login Access</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">👤 Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">🔐 Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="login-button">🔓 Login to Dashboard</button>
            </form>

            <div class="login-footer">
                <p>© 2026 Restaurant System. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>