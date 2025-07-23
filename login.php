<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: index.php");
    } else if ($_SESSION['role'] === 'supervisor') {
        header("Location: supervisordashboard.php");
    }
    exit();
}

require_once 'includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // First, let's check if the user exists
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Add debugging (temporarily)
    if ($user) {
        // User found, now verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: " . ($user['role'] === 'admin' ? 'index.php' : 'supervisordashboard.php'));
            exit();
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'Username not found';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PureFarm Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f0f2f5 0%, #e0e5ec 100%);
        }

        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            transform: translateY(20px);
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            position: relative;
            z-index: 1;
            background-color: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }

        @keyframes fadeInUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
            text-align: center;
        }

        .logo-image {
            width: 150px;
            height: auto;
            margin-bottom: 0;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .logo-text {
            font-size: 24px;
            color: #333;
            font-weight: bold;
            line-height: 1.2;
            margin-top: 10px;
            animation: fadeIn 0.6s ease forwards;
            animation-delay: 0.3s;
            opacity: 0;
        }

        .logo-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 2px;
            letter-spacing: 0.5px;
            animation: fadeIn 0.6s ease forwards;
            animation-delay: 0.4s;
            opacity: 0;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            animation: slideIn 0.6s ease forwards;
            opacity: 0;
        }

        .form-group:nth-child(1) { animation-delay: 0.5s; }
        .form-group:nth-child(2) { animation-delay: 0.6s; }

        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }

        .form-group .input-container {
            position: relative;
        }

        .form-group .input-container i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }

        .error-message {
            background-color: #ffe5e5;
            color: #dc3545;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .login-button {
            background-color: #4CAF50;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s ease forwards;
            animation-delay: 0.7s;
            opacity: 0;
        }

        .login-button:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .loading-spinner {
            display: none;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 3px solid #ffffff;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        

        .bubbles-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            background: linear-gradient(135deg, #f0f2f5 0%, #e0e5ec 100%);
        }

        .bubble {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(76, 175, 80, 0.1);
            background: rgba(76, 175, 80, 0.05);
            pointer-events: none;
        }


    </style>
</head>
<body>
    <div class="bubbles-container"></div>
    <div class="login-container">
        <div class="logo-container">
            <img src="images/pure-logo.png" alt="PureFarm Logo" class="logo-image">
            <div class="logo-text">PureFarm</div>
            <div class="logo-subtitle">FOSTERING SMARTER FARM</div>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required>
                </div>
            </div>
            <button type="submit" class="login-button">
                <span>Login</span>
                <div class="loading-spinner"></div>
            </button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = this.querySelector('.login-button');
            const buttonText = button.querySelector('span');
            const spinner = button.querySelector('.loading-spinner');
            
            buttonText.style.opacity = '0';
            spinner.style.display = 'block';
        });

        document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.bubbles-container');
        
        function createBubble() {
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            
            // Random size between 20 and 80 pixels
            const size = Math.random() * 60 + 20;
            bubble.style.width = `${size}px`;
            bubble.style.height = `${size}px`;
            
            // Start from random position at bottom
            bubble.style.left = `${Math.random() * 100}%`;
            bubble.style.top = '100%';
            
            // Random animation duration between 10-20 seconds
            const duration = Math.random() * 10 + 10;
            
            // Add to container
            container.appendChild(bubble);
            
            // Animate bubble
            bubble.animate([
                { 
                    transform: 'translateY(0) translateX(0)', 
                    opacity: 0 
                },
                { 
                    transform: `translateY(-${window.innerHeight + size}px) translateX(${(Math.random() - 0.5) * 200}px)`,
                    opacity: 1,
                    offset: 0.4
                },
                { 
                    transform: `translateY(-${window.innerHeight + size}px) translateX(${(Math.random() - 0.5) * 400}px)`,
                    opacity: 0 
                }
            ], {
                duration: duration * 1000,
                easing: 'ease-out'
            }).onfinish = () => bubble.remove();
        }
        
        // Create new bubble every 500ms
        setInterval(createBubble, 500);
        
        // Create initial bubbles
        for(let i = 0; i < 15; i++) {
            setTimeout(createBubble, i * 300);
        }
    });
    </script>
</body>
</html>