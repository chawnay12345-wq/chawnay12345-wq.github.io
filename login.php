<?php
// login.php - ธีมดำเทาทอง
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            redirect('index.php');
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เข้าสู่ระบบ - โป๊กเกอร์</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        body {
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-box {
            background: #1a1a1a;
            padding: 40px 35px;
            border-radius: 20px;
            box-shadow: 0 0 60px rgba(212, 175, 55, 0.15), inset 0 0 60px rgba(212, 175, 55, 0.03);
            border: 1px solid #333;
            width: 100%;
            max-width: 400px;
        }
        .login-box h1 {
            color: #d4af37;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.15);
        }
        .login-box input {
            width: 100%;
            padding: 14px 18px;
            margin: 8px 0;
            border-radius: 12px;
            border: 1px solid #333;
            background: #111;
            color: #ccc;
            font-size: 1rem;
            transition: 0.3s;
        }
        .login-box input:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
        }
        .login-box input::placeholder { color: #555; }
        .login-box button {
            width: 100%;
            padding: 14px;
            margin-top: 15px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .login-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
        }
        .login-box button:active { transform: translateY(0); }
        .error {
            color: #ff6b6b;
            text-align: center;
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 0, 0, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 0, 0, 0.2);
        }
        .link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .link a:hover { text-shadow: 0 0 20px rgba(212, 175, 55, 0.3); }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>♠️ เข้าสู่ระบบ</h1>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
            <input type="password" name="password" placeholder="รหัสผ่าน" required>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
        <div class="link">
            <a href="register.php">สมัครสมาชิก</a>
        </div>
    </div>
</body>
</html>