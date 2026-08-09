<?php
// game.php - เพิ่มการลบผู้เล่นเมื่อออกจากห้อง
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$room_code = $_GET['room'] ?? '';
if (empty($room_code)) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];

// ตรวจสอบว่าผู้เล่นนี้อยู่ในห้องนี้แล้วหรือยัง
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = ?");
$stmt->execute([$room_code]);
$room = $stmt->fetch();

if (!$room) {
    redirect('index.php');
}

$_SESSION['room_id'] = $room['id'];

// ตรวจสอบว่าผู้เล่นนี้มีอยู่ในห้องแล้วหรือยัง
$stmt = $pdo->prepare("SELECT * FROM players WHERE user_id = ? AND room_id = ?");
$stmt->execute([$user_id, $room['id']]);
$existing_player = $stmt->fetch();

if (!$existing_player) {
    // ถ้าไม่มีให้เพิ่มใหม่
    $chips = convertMoneyToChips($room['initial_money']);
    $stmt = $pdo->prepare("INSERT INTO players (room_id, user_id, player_name, chips_50, chips_25, chips_10, chips_5, chips_1) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $room['id'],
        $user_id,
        $_SESSION['username'],
        $chips[50] ?? 0,
        $chips[25] ?? 0,
        $chips[10] ?? 0,
        $chips[5] ?? 0,
        $chips[1] ?? 0
    ]);
}

$is_host = ($room['host_id'] == $user_id);

$stmt = $pdo->prepare("SELECT * FROM players WHERE room_id = ? ORDER BY id");
$stmt->execute([$room['id']]);
$players = $stmt->fetchAll();

$current_player = null;
foreach ($players as $p) {
    if ($p['user_id'] == $user_id) {
        $current_player = $p;
        break;
    }
}

if (!$current_player) {
    redirect('index.php');
}

$chip_types = [50, 25, 10, 5, 1];
$players_data = [];
foreach ($players as $p) {
    $total_money = 0;
    $chips_data = [];
    foreach ($chip_types as $value) {
        $qty = $p['chips_' . $value] ?? 0;
        $chips_data[$value] = $qty;
        $total_money += $value * $qty;
    }
    $players_data[] = [
        'id' => $p['id'],
        'user_id' => $p['user_id'],
        'name' => $p['player_name'],
        'chips' => $chips_data,
        'total_money' => $total_money,
        'is_current' => $p['user_id'] == $user_id
    ];
}

// จัดการ AJAX (เหมือนเดิม)
// ... โค้ด AJAX เหมือนเดิม ...

$stmt = $pdo->prepare("SELECT pot, current_bet FROM rooms WHERE id = ?");
$stmt->execute([$room['id']]);
$room_data = $stmt->fetch();
$pot = $room_data['pot'] ?? 0;
$current_bet = $room_data['current_bet'] ?? 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>♠️ โป๊กเกอร์ - <?php echo htmlspecialchars($room['room_name']); ?></title>
    <style>
        /* CSS เหมือนเดิม */
        * { box-sizing: border-box; font-family: 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; }
        body {
            background: #0a0a0a;
            min-height: 100vh;
            padding: 12px;
        }
        .game-board {
            max-width: 1200px;
            margin: 0 auto;
            background: #1a1a1a;
            border-radius: 20px;
            padding: 25px 20px;
            box-shadow: 0 0 60px rgba(212, 175, 55, 0.08), inset 0 0 60px rgba(212, 175, 55, 0.02);
            border: 1px solid #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
            gap: 8px;
        }
        .header h1 {
            color: #d4af37;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.15);
            font-size: 1.4rem;
        }
        .header .info {
            color: #888;
            background: #111;
            padding: 6px 16px;
            border-radius: 12px;
            border: 1px solid #333;
            font-size: 0.85rem;
        }
        .header .info a {
            color: #d4af37;
            text-decoration: none;
            margin-left: 10px;
            transition: 0.3s;
        }
        .header .info a:hover { text-shadow: 0 0 20px rgba(212, 175, 55, 0.3); }
        .header .host-badge {
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 6px;
        }
        .room-info {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            background: #111;
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid #333;
            font-size: 0.85rem;
        }
        .room-info span {
            color: #888;
            font-weight: 600;
        }
        .room-info .code {
            color: #d4af37;
            font-size: 1.1rem;
        }

        .chip-bet-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            background: #111;
            padding: 12px 15px;
            border-radius: 16px;
            margin-bottom: 15px;
            border: 1px solid #333;
        }
        .chip-bet-area .chip-label {
            color: #888;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            margin-right: 5px;
        }
        .chip-bet-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 12px;
            background: #0a0a0a;
            color: #ccc;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            border: 1px solid #333;
        }
        .chip-bet-btn:hover { border-color: #555; }
        .chip-bet-btn:active { transform: scale(0.95); }
        .chip-bet-btn .chip-circle {
            width: 28px;
            height: 28px;
            line-height: 28px;
            font-size: 0.65rem;
        }
        .chip-bet-btn .chip-count {
            background: #111;
            padding: 0 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #666;
        }
        .chip-bet-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .chip-bet-btn:disabled:hover { border-color: #333; }

        .chip-circle {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #444;
            box-shadow: inset 0 -4px 0 rgba(0,0,0,0.3), 0 3px 6px rgba(0,0,0,0.6);
            text-align: center;
            line-height: 30px;
            font-weight: 700;
            font-size: 0.7rem;
            color: #111;
        }
        .chip-50 { background: radial-gradient(circle at 35% 35%, #f5d742, #b8860b); color: #3d2b00; }
        .chip-25 { background: radial-gradient(circle at 35% 35%, #7ac7f5, #1f6e9e); color: #021c2e; }
        .chip-10 { background: radial-gradient(circle at 35% 35%, #f5a3a3, #b33a3a); color: #3d0505; }
        .chip-5  { background: radial-gradient(circle at 35% 35%, #b3d9a8, #2e7d32); color: #022b04; }
        .chip-1  { background: radial-gradient(circle at 35% 35%, #e0d6c0, #8f7a5a); color: #3d2b1a; }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin: 12px 0;
        }
        .player-card {
            background: #111;
            border-radius: 16px;
            padding: 15px 12px;
            box-shadow: 0 0 30px rgba(0,0,0,0.3);
            border-bottom: 3px solid #333;
            text-align: center;
            border: 1px solid #333;
            position: relative;
        }
        .player-card.current-player {
            border-color: #d4af37;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.05), inset 0 0 30px rgba(212, 175, 55, 0.02);
        }
        .player-card .winner-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.3);
            display: none;
        }
        .player-card.winner .winner-badge {
            display: block;
        }
        .player-card.winner {
            border-color: #d4af37;
            box-shadow: 0 0 40px rgba(212, 175, 55, 0.1), inset 0 0 40px rgba(212, 175, 55, 0.05);
        }
        .player-card .player-name {
            font-size: 1rem;
            font-weight: 700;
            color: #ccc;
            margin-bottom: 4px;
        }
        .player-card .player-name .current-badge {
            color: #d4af37;
            font-size: 0.7rem;
            margin-left: 4px;
        }
        .player-card .player-total-label {
            color: #666;
            font-size: 0.75rem;
        }
        .player-card .player-total {
            font-size: 1.6rem;
            font-weight: 700;
            color: #d4af37;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.15);
            margin: 2px 0 6px 0;
        }
        .player-card .chip-detail {
            font-size: 0.7rem;
            color: #888;
            margin-top: 4px;
            background: #0a0a0a;
            padding: 6px 8px;
            border-radius: 10px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px 12px;
            border: 1px solid #222;
        }
        .player-card .chip-detail .chip-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .player-card .chip-detail .mini-chip {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid #444;
            font-size: 0.5rem;
            text-align: center;
            line-height: 18px;
            font-weight: 700;
            color: #111;
        }
        .player-card .chip-detail .chip-count {
            color: #aaa;
            font-weight: 600;
        }
        .player-card .remove-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #ff6b6b;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.7rem;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        .player-card .remove-btn:hover {
            background: rgba(255, 0, 0, 0.4);
        }
        .player-card .remove-btn.show {
            display: flex;
        }
        .player-card.other-player {
            opacity: 0.7;
            cursor: default;
        }

        .action-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 15px 0 12px;
        }
        .action-btn {
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            flex: 1;
            min-width: 80px;
            text-align: center;
        }
        .action-btn:hover { transform: translateY(-2px); }
        .action-btn:active { transform: translateY(0); }
        .action-btn.fold {
            background: #222;
            color: #888;
            border: 1px solid #333;
        }
        .action-btn.fold:hover { border-color: #555; }
        .action-btn.bet {
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
        }
        .action-btn.bet:hover { box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2); }
        .action-btn.call {
            background: linear-gradient(145deg, #444, #333);
            color: #ccc;
            border: 1px solid #555;
        }
        .action-btn.call:hover { border-color: #777; }
        .action-btn.reset {
            background: #111;
            color: #666;
            border: 1px solid #333;
        }
        .action-btn.reset:hover { border-color: #555; }
        .action-btn.winner {
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
        }
        .action-btn.winner:hover { box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2); }

        .pot-display {
            background: #111;
            padding: 10px 20px;
            border-radius: 12px;
            color: #d4af37;
            font-weight: 700;
            font-size: 1.1rem;
            border: 1px solid #333;
            min-width: 120px;
            text-align: center;
        }
        .log-area {
            background: #111;
            border-radius: 12px;
            padding: 10px 15px;
            margin-top: 12px;
            border: 1px solid #333;
            max-height: 100px;
            overflow-y: auto;
            color: #666;
            font-size: 0.8rem;
        }
        .log-area p { margin: 3px 0; border-bottom: 1px solid #222; padding: 3px 0; }
        .log-area::-webkit-scrollbar { width: 4px; }
        .log-area::-webkit-scrollbar-thumb { background: #333; border-radius: 20px; }

        .reset-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 8px 0;
        }
        .reset-row .action-btn {
            flex: 0 1 auto;
            min-width: 100px;
            padding: 10px 18px;
            font-size: 0.9rem;
        }

        .winner-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
            padding: 20px;
        }
        .winner-modal.show { display: flex; }
        .winner-modal-content {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 30px 25px;
            max-width: 400px;
            width: 100%;
            border: 1px solid #d4af37;
            box-shadow: 0 0 60px rgba(212, 175, 55, 0.1);
        }
        .winner-modal-content h2 {
            color: #d4af37;
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        .winner-modal-content h2 span { color: #d4af37; }
        .winner-modal-content .pot-amount {
            text-align: center;
            color: #d4af37;
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0 20px;
        }
        .winner-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0;
        }
        .winner-option {
            background: #111;
            padding: 14px 18px;
            border-radius: 12px;
            color: #ccc;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-align: center;
            border: 1px solid #333;
        }
        .winner-option:hover { border-color: #555; }
        .winner-option.selected {
            border-color: #d4af37;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.05);
        }
        .winner-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .winner-modal-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .winner-modal-actions button:hover { transform: translateY(-2px); }
        .winner-modal-actions button:active { transform: translateY(0); }
        .winner-modal-actions .confirm-btn {
            background: linear-gradient(145deg, #d4af37, #b8962e);
            color: #0a0a0a;
        }
        .winner-modal-actions .confirm-btn:hover { box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2); }
        .winner-modal-actions .cancel-btn {
            background: #222;
            color: #888;
            border: 1px solid #333;
        }
        .winner-modal-actions .cancel-btn:hover { border-color: #555; }

        @media (max-width: 600px) {
            .game-board { padding: 15px 12px; border-radius: 16px; }
            .header h1 { font-size: 1.2rem; }
            .header .info { font-size: 0.75rem; padding: 4px 12px; }
            .room-info { font-size: 0.75rem; gap: 8px; padding: 6px 12px; }
            .room-info .code { font-size: 0.95rem; }
            .players-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .player-card { padding: 10px 8px; border-radius: 12px; }
            .player-card .player-name { font-size: 0.85rem; }
            .player-card .player-total { font-size: 1.3rem; }
            .player-card .chip-detail { font-size: 0.6rem; gap: 4px 8px; }
            .player-card .chip-detail .mini-chip { width: 14px; height: 14px; line-height: 14px; font-size: 0.4rem; }
            .action-btn { padding: 10px 12px; font-size: 0.85rem; min-width: 60px; }
            .pot-display { font-size: 0.95rem; padding: 8px 14px; min-width: 90px; }
            .log-area { font-size: 0.7rem; max-height: 80px; padding: 8px 12px; }
            .reset-row .action-btn { font-size: 0.8rem; padding: 8px 14px; min-width: 80px; }
            .chip-bet-area { padding: 8px 10px; gap: 6px; }
            .chip-bet-btn { padding: 6px 12px; font-size: 0.8rem; }
            .chip-bet-btn .chip-circle { width: 22px; height: 22px; line-height: 22px; font-size: 0.55rem; }
        }
        @media (max-width: 400px) {
            .players-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .player-card .player-total { font-size: 1.1rem; }
            .action-bar { gap: 6px; }
            .action-btn { padding: 8px 10px; font-size: 0.75rem; min-width: 50px; }
        }
        @media (max-width: 350px) {
            .players-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="game-board">
        <div class="header">
            <h1>
                ♠️ <?php echo htmlspecialchars($room['room_name']); ?>
                <?php if ($is_host): ?>
                <span class="host-badge">👑 เจ้าของห้อง</span>
                <?php endif; ?>
            </h1>
            <div class="info">
                👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
                <a href="index.php" onclick="return confirm('ออกจากห้อง? ข้อมูลจะถูกบันทึก')">ออก</a>
            </div>
        </div>
        
        <div class="room-info">
            <span>🏷️ <span class="code"><?php echo $room['room_code']; ?></span></span>
            <span>👥 <?php echo count($players); ?>/<?php echo $room['player_count']; ?></span>
            <span>💰 <?php echo number_format($room['initial_money']); ?></span>
        </div>

        <!-- ปุ่มชิปสำหรับเดิมพัน -->
        <div class="chip-bet-area">
            <span class="chip-label">🎯 เลือกชิปเดิมพัน:</span>
            <?php 
            $current_chips = [];
            foreach ($chip_types as $value) {
                $current_chips[$value] = $current_player['chips_' . $value] ?? 0;
            }
            foreach ($chip_types as $value): 
                $has_chip = ($current_chips[$value] ?? 0) > 0;
            ?>
            <button class="chip-bet-btn" data-chip-value="<?php echo $value; ?>" <?php echo !$has_chip ? 'disabled' : ''; ?>>
                <span class="chip-circle chip-<?php echo $value; ?>"><?php echo $value; ?></span>
                <span class="chip-count">×<?php echo $current_chips[$value] ?? 0; ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="players-grid" id="playersContainer">
            <?php foreach ($players_data as $index => $p): 
                $is_current = $p['is_current'];
            ?>
            <div class="player-card <?php echo $is_current ? 'current-player' : 'other-player'; ?>" data-player-id="<?php echo $p['id']; ?>" data-player-name="<?php echo htmlspecialchars($p['name']); ?>" data-is-current="<?php echo $is_current ? 'true' : 'false'; ?>">
                <span class="winner-badge">👑 ชนะ</span>
                <?php if ($is_host && !$is_current): ?>
                <button class="remove-btn show" onclick="removePlayer(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name']); ?>')">✕</button>
                <?php endif; ?>
                <div class="player-name">
                    👤 P<?php echo $index + 1; ?>: <?php echo htmlspecialchars($p['name']); ?>
                    <?php if ($is_current): ?>
                    <span class="current-badge">(คุณ)</span>
                    <?php endif; ?>
                </div>
                <div class="player-total-label">💰 เงินรวม</div>
                <div class="player-total" id="total-<?php echo $p['id']; ?>"><?php echo number_format($p['total_money']); ?></div>
                <div class="chip-detail">
                    <?php foreach ($chip_types as $value): ?>
                    <span class="chip-item">
                        <span class="mini-chip chip-<?php echo $value; ?>"><?php echo $value; ?></span>
                        <span class="chip-count">×<?php echo $p['chips'][$value] ?? 0; ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="action-bar">
            <button class="action-btn fold" id="foldBtn">🙅 Fold</button>
            <button class="action-btn bet" id="betBtn">📈 Bet</button>
            <button class="action-btn call" id="callBtn">📞 Call</button>
            <span class="pot-display" id="potDisplay">💰 Pot: <?php echo $pot; ?></span>
        </div>

        <div class="reset-row">
            <?php if ($is_host): ?>
            <button class="action-btn winner" id="winnerBtn">👑 เลือกผู้ชนะ</button>
            <?php endif; ?>
            <button class="action-btn reset" id="resetPotBtn">🧹 เคลียร์ Pot</button>
        </div>

        <div class="log-area" id="logArea">
            <p>🟢 พร้อมเล่นแล้ว - คลิกชิปเพื่อเลือกเดิมพัน</p>
        </div>
    </div>

    <!-- Modal เลือกผู้ชนะ -->
    <div class="winner-modal" id="winnerModal">
        <div class="winner-modal-content">
            <h2>👑 <span>เลือกผู้ชนะ</span></h2>
            <div class="pot-amount" id="modalPotAmount">💰 Pot: 0</div>
            <p style="color: #888; text-align: center; font-size: 0.9rem;">เลือกผู้เล่นที่ชนะในรอบนี้</p>
            <div class="winner-list" id="winnerList"></div>
            <div class="winner-modal-actions">
                <button class="cancel-btn" id="cancelWinnerBtn">ยกเลิก</button>
                <button class="confirm-btn" id="confirmWinnerBtn">✅ ยืนยัน</button>
            </div>
        </div>
    </div>

    <script>
    let currentPlayerId = <?php echo $current_player['id']; ?>;
    let selectedWinnerId = null;
    let selectedChipValue = 50;

    document.addEventListener('DOMContentLoaded', function() {
        const firstChipBtn = document.querySelector('.chip-bet-btn:not(:disabled)');
        if (firstChipBtn) {
            firstChipBtn.style.borderColor = '#d4af37';
            selectedChipValue = parseInt(firstChipBtn.dataset.chipValue);
        }
    });

    document.querySelectorAll('.chip-bet-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.disabled) return;
            
            document.querySelectorAll('.chip-bet-btn').forEach(b => b.style.borderColor = 'transparent');
            this.style.borderColor = '#d4af37';
            selectedChipValue = parseInt(this.dataset.chipValue);
            addLog('🎯 เลือกชิป ' + selectedChipValue);
        });
    });

    document.getElementById('betBtn').addEventListener('click', function() {
        fetch('game.php?room=<?php echo $room_code; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=bet&player_id=' + currentPlayerId + '&chip_value=' + selectedChipValue
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                addLog('✅ ' + data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                addLog('❌ ' + data.message);
            }
        });
    });

    document.getElementById('callBtn').addEventListener('click', function() {
        fetch('game.php?room=<?php echo $room_code; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=call&player_id=' + currentPlayerId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                addLog('✅ ' + data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                addLog('❌ ' + data.message);
            }
        });
    });

    document.getElementById('foldBtn').addEventListener('click', function() {
        fetch('game.php?room=<?php echo $room_code; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=fold&player_id=' + currentPlayerId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                addLog('✅ Fold แล้ว');
            }
        });
    });

    document.getElementById('resetPotBtn').addEventListener('click', function() {
        if (!confirm('เคลียร์ Pot?')) return;
        fetch('game.php?room=<?php echo $room_code; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=reset_pot'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('potDisplay').textContent = '💰 Pot: 0';
                addLog('🧹 เคลียร์ Pot แล้ว');
                location.reload();
            } else {
                addLog('❌ ' + data.message);
            }
        });
    });

    function removePlayer(playerId, playerName) {
        if (!confirm('ต้องการลบ ' + playerName + ' ออกจากห้อง?')) return;
        
        fetch('game.php?room=<?php echo $room_code; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=remove_player&player_id=' + playerId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                addLog('✅ ' + data.message);
                location.reload();
            } else {
                addLog('❌ ' + data.message);
            }
        });
    }

    <?php if ($is_host): ?>
    const winnerBtn = document.getElementById('winnerBtn');
    const winnerModal = document.getElementById('winnerModal');
    const winnerList = document.getElementById('winnerList');
    const modalPotAmount = document.getElementById('modalPotAmount');
    const cancelWinnerBtn = document.getElementById('cancelWinnerBtn');
    const confirmWinnerBtn = document.getElementById('confirmWinnerBtn');

    winnerBtn.addEventListener('click', function() {
        const potText = document.getElementById('potDisplay').textContent;
        const potAmount = parseInt(potText.replace(/[^0-9]/g, '')) || 0;
        
        if (potAmount === 0) {
            addLog('❌ ไม่มีเงินใน Pot ให้แจก');
            alert('ไม่มีเงินใน Pot ให้แจก');
            return;
        }
        
        modalPotAmount.textContent = '💰 Pot: ' + potAmount.toLocaleString();
        
        winnerList.innerHTML = '';
        document.querySelectorAll('.player-card').forEach(card => {
            const playerId = card.dataset.playerId;
            const playerName = card.dataset.playerName;
            const playerIndex = Array.from(document.querySelectorAll('.player-card')).indexOf(card) + 1;
            
            const option = document.createElement('div');
            option.className = 'winner-option';
            option.dataset.playerId = playerId;
            option.textContent = '👤 P' + playerIndex + ': ' + playerName;
            
            option.addEventListener('click', function() {
                document.querySelectorAll('.winner-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                selectedWinnerId = parseInt(this.dataset.playerId);
            });
            
            winnerList.appendChild(option);
        });
        
        selectedWinnerId = null;
        winnerModal.classList.add('show');
    });

    cancelWinnerBtn.addEventListener('click', function() {
        winnerModal.classList.remove('show');
        selectedWinnerId = null;
    });

    confirmWinnerBtn.addEventListener('click', function() {
        if (!selectedWinnerId) {
            alert('กรุณาเลือกผู้ชนะ');
            return;
        }
        
        if (!confirm('ยืนยันให้ผู้เล่นนี้เป็นผู้ชนะ?')) return;
        
        fetch('game.php?room=<?php echo $room_code; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=declare_winner&winner_id=' + selectedWinnerId
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const potAmount = data.pot || 0;
                const winnerName = document.querySelector(`.player-card[data-player-id="${selectedWinnerId}"]`)?.dataset.playerName || '';
                addLog('👑 ' + winnerName + ' ชนะ! ได้รับ ' + potAmount.toLocaleString() + ' บาท');
                
                document.querySelectorAll('.player-card').forEach(c => c.classList.remove('winner'));
                const winnerCard = document.querySelector(`.player-card[data-player-id="${selectedWinnerId}"]`);
                if (winnerCard) {
                    winnerCard.classList.add('winner');
                }
                
                winnerModal.classList.remove('show');
                setTimeout(() => location.reload(), 500);
            } else {
                addLog('❌ ' + data.message);
                alert(data.message);
            }
        });
    });

    winnerModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            selectedWinnerId = null;
        }
    });
    <?php endif; ?>

    function addLog(msg) {
        const logArea = document.getElementById('logArea');
        const p = document.createElement('p');
        p.textContent = '🕐 ' + new Date().toLocaleTimeString() + ' - ' + msg;
        logArea.appendChild(p);
        logArea.scrollTop = logArea.scrollHeight;
        if (logArea.children.length > 50) {
            logArea.removeChild(logArea.firstChild);
        }
    }
    </script>
</body>
</html>