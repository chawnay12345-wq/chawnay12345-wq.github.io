<?php
// index.php - เพิ่มการลบผู้เล่นเมื่อออก
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ถ้ามี room_id ใน session ให้ลบผู้เล่นออกจากห้องก่อน
if (isset($_SESSION['room_id'])) {
    removePlayerFromRoom($pdo, $user_id, $_SESSION['room_id']);
    unset($_SESSION['room_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_room'])) {
    $room_name = trim($_POST['room_name'] ?? '');
    $initial_money = intval($_POST['initial_money'] ?? 0);
    $player_count = intval($_POST['player_count'] ?? 2);
    
    if ($initial_money < 100) {
        $error = 'เงินเริ่มต้นต้องอย่างน้อย 100';
    } else {
        $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
        $stmt = $pdo->prepare("INSERT INTO rooms (room_code, room_name, host_id, player_count, initial_money, status) VALUES (?, ?, ?, ?, ?, 'waiting')");
        if ($stmt->execute([$room_code, $room_name, $user_id, $player_count, $initial_money])) {
            $room_id = $pdo->lastInsertId();
            
            $chips = convertMoneyToChips($initial_money);
            $stmt = $pdo->prepare("INSERT INTO players (room_id, user_id, player_name, chips_50, chips_25, chips_10, chips_5, chips_1) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $room_id, 
                $user_id, 
                $username, 
                $chips[50] ?? 0,
                $chips[25] ?? 0,
                $chips[10] ?? 0,
                $chips[5] ?? 0,
                $chips[1] ?? 0
            ]);
            
            $_SESSION['room_id'] = $room_id;
            redirect('game.php?room=' . $room_code);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_room'])) {
    $room_code = trim($_POST['room_code'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = ? AND status = 'waiting'");
    $stmt->execute([$room_code]);
    $room = $stmt->fetch();
    
    if ($room) {
        // ตรวจสอบว่าผู้เล่นนี้อยู่ในห้องนี้แล้วหรือไม่
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? AND room_id = ?");
        $stmt->execute([$user_id, $room['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // ถ้ามีอยู่แล้ว ให้เข้าห้องเลย
            $_SESSION['room_id'] = $room['id'];
            redirect('game.php?room=' . $room_code);
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ?");
        $stmt->execute([$room['id']]);
        $current_players = $stmt->fetchColumn();
        
        if ($current_players >= $room['player_count']) {
            $error = 'ห้องนี้เต็มแล้ว';
        } else {
            $chips = convertMoneyToChips($room['initial_money']);
            $stmt = $pdo->prepare("INSERT INTO players (room_id, user_id, player_name, chips_50, chips_25, chips_10, chips_5, chips_1) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $room['id'],
                $user_id,
                $username,
                $chips[50] ?? 0,
                $chips[25] ?? 0,
                $chips[10] ?? 0,
                $chips[5] ?? 0,
                $chips[1] ?? 0
            ]);
            
            $_SESSION['room_id'] = $room['id'];
            redirect('game.php?room=' . $room_code);
        }
    } else {
        $error = 'ไม่พบห้องนี้ หรือห้องเริ่มเกมแล้ว';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>♠️ โป๊กเกอร์ - สร้าง/เข้าร่วมห้อง</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        body {
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
        }
        .container {
            max-width: 1000px;
            width: 100%;
            background: #1a1a1a;
            border-radius: 20px;
            padding: 30px 25px;
            box-shadow: 0 0 60px rgba(212, 175, 55, 0.1), inset 0 0 60px rgba(212, 175, 55, 0.02);
            border: 1px solid #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
            gap: 10px;
        }
        .header h1 {
            color: #d4af37;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.15);
            font-size: 1.8rem;
        }
        .user-info {
            color: #888;
            background: #111;
            padding: 8px 18px;
            border-radius: 12px;
            border: 1px solid #333;
            font-size: 0.9rem;
        }
        .user-info a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 600;
            margin-left: 10px;
            transition: 0.3s;
        }
        .user-info a:hover { text-shadow: 0 0 20px rgba(212, 175, 55, 0.3); }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .card {
            background: #111;
            padding: 25px 20px;
            border-radius: 16px;
            border: 1px solid #333;
            transition: 0.3s;
        }
        .card:hover { border-color: #444; }
        .card h2 {
            color: #d4af37;
            margin-bottom: 18px;
            text-align: center;
            font-size: 1.3rem;
        }
        .form-group {
            margin-bottom: 12px;
        }
        .form-group label {
            color: #888;
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #333;
            background: #0a0a0a;
            color: #ccc;
            font-size: 1rem;
            transition: 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.05);
        }
        .form-group input::placeholder { color: #444; }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 8px;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:active { transform: translateY(0); }
        .btn-create {
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
        }
        .btn-create:hover { box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2); }
        .btn-join {
            background: linear-gradient(145deg, #666, #444);
            color: #fff;
        }
        .btn-join:hover { box-shadow: 0 10px 30px rgba(255, 255, 255, 0.05); }
        .error {
            color: #ff6b6b;
            text-align: center;
            margin: 10px 0;
            padding: 12px;
            background: rgba(255, 0, 0, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 0, 0, 0.1);
        }
        .chip-preview {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            padding: 10px;
            background: #0a0a0a;
            border-radius: 12px;
            border: 1px solid #222;
        }
        .chip-preview span {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #888;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .mini-chip {
            display: inline-block;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1px solid #444;
            text-align: center;
            line-height: 22px;
            font-size: 0.55rem;
            font-weight: 700;
        }
        .chip-50 { background: radial-gradient(circle at 35% 35%, #f5d742, #b8860b); color: #3d2b00; }
        .chip-25 { background: radial-gradient(circle at 35% 35%, #7ac7f5, #1f6e9e); color: #021c2e; }
        .chip-10 { background: radial-gradient(circle at 35% 35%, #f5a3a3, #b33a3a); color: #3d0505; }
        .chip-5  { background: radial-gradient(circle at 35% 35%, #b3d9a8, #2e7d32); color: #022b04; }
        .chip-1  { background: radial-gradient(circle at 35% 35%, #e0d6c0, #8f7a5a); color: #3d2b1a; }
        .info-text {
            margin-top: 15px;
            padding: 12px;
            background: #0a0a0a;
            border-radius: 12px;
            border: 1px solid #222;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }

        @media (max-width: 700px) {
            .grid-2 { grid-template-columns: 1fr; gap: 15px; }
            .container { padding: 20px 15px; }
            .header h1 { font-size: 1.4rem; }
        }
        @media (max-width: 400px) {
            .header { flex-direction: column; align-items: stretch; text-align: center; }
            .user-info { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>♠️ โป๊กเกอร์</h1>
            <div class="user-info">
                👤 <?php echo htmlspecialchars($username); ?>
                <a href="logout.php">ออก</a>
            </div>
        </div>
        
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <div class="grid-2">
            <div class="card">
                <h2>🏠 สร้างห้อง</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>ชื่อห้อง</label>
                        <input type="text" name="room_name" placeholder="ชื่อห้อง" required>
                    </div>
                    <div class="form-group">
                        <label>เงินเริ่มต้น (บาท)</label>
                        <input type="number" name="initial_money" id="initialMoney" value="500" min="100" step="100" required oninput="updateChipPreview(this.value)">
                    </div>
                    <div class="form-group">
                        <label>จำนวนผู้เล่น</label>
                        <select name="player_count">
                            <option value="2">2 คน</option>
                            <option value="3">3 คน</option>
                            <option value="4" selected>4 คน</option>
                            <option value="5">5 คน</option>
                            <option value="6">6 คน</option>
                        </select>
                    </div>
                    
                    <div style="margin: 10px 0;">
                        <label style="color: #666; font-size: 0.8rem;">📊 ตัวอย่างการแจกชิป:</label>
                        <div class="chip-preview" id="chipPreview">
                            <span><span class="mini-chip chip-50">50</span> ×2</span>
                            <span><span class="mini-chip chip-25">25</span> ×2</span>
                            <span><span class="mini-chip chip-10">10</span> ×3</span>
                            <span><span class="mini-chip chip-5">5</span> ×4</span>
                            <span><span class="mini-chip chip-1">1</span> ×5</span>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_room" class="btn btn-create">🚀 สร้างห้อง</button>
                </form>
            </div>
            
            <div class="card">
                <h2>🔑 เข้าร่วมห้อง</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>รหัสห้อง</label>
                        <input type="text" name="room_code" placeholder="เช่น ABC123" required>
                    </div>
                    <button type="submit" name="join_room" class="btn btn-join">📥 เข้าร่วม</button>
                </form>
                <div class="info-text">
                    💡 เงินเริ่มต้นจะถูกแปลงเป็นชิปแบบกระจายทุกชนิด
                </div>
            </div>
        </div>
    </div>

    <script>
    function updateChipPreview(amount) {
        const preview = document.getElementById('chipPreview');
        const money = parseInt(amount) || 0;
        let remaining = money;
        const chips = {};
        const min_chips = {50: 2, 25: 2, 10: 3, 5: 4, 1: 5};
        const values = [50, 25, 10, 5, 1];
        
        values.forEach(v => {
            const min = min_chips[v];
            const cost = min * v;
            if (remaining >= cost) {
                chips[v] = min;
                remaining -= cost;
            } else {
                chips[v] = 0;
            }
        });
        
        values.forEach(v => {
            if (remaining <= 0) return;
            const use = Math.floor(remaining / v);
            if (use > 0) {
                chips[v] = (chips[v] || 0) + use;
                remaining -= use * v;
            }
        });
        
        if (remaining > 0) {
            chips[1] = (chips[1] || 0) + remaining;
        }
        
        let html = '';
        values.forEach(v => {
            const count = chips[v] || 0;
            const chipClass = 'chip-' + v;
            html += `<span><span class="mini-chip ${chipClass}">${v}</span> ×${count}</span>`;
        });
        preview.innerHTML = html;
    }
    </script>
</body>
</html>