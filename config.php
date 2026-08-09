<?php
// config.php - เพิ่มฟังก์ชันลบผู้เล่นออกจากห้อง
session_start();

$host = 'localhost';
$dbname = 'poker_chips';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// ฟังก์ชันลบผู้เล่นออกจากห้อง
function removePlayerFromRoom($pdo, $user_id, $room_id = null) {
    if ($room_id === null && isset($_SESSION['room_id'])) {
        $room_id = $_SESSION['room_id'];
    }
    
    if ($room_id) {
        $stmt = $pdo->prepare("DELETE FROM players WHERE user_id = ? AND room_id = ?");
        $stmt->execute([$user_id, $room_id]);
        
        // ตรวจสอบว่าหมดผู้เล่นหรือยัง
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            // ถ้าไม่มีผู้เล่นเหลือ ให้ลบห้องทิ้ง
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$room_id]);
        }
    }
}

function convertMoneyToChips($amount) {
    $chip_values = [50, 25, 10, 5, 1];
    $chips = [];
    $remaining = $amount;
    
    $min_chips = [
        50 => 2,
        25 => 2,
        10 => 3,
        5 => 4,
        1 => 5
    ];
    
    foreach ($chip_values as $value) {
        $min = $min_chips[$value];
        $cost = $min * $value;
        if ($remaining >= $cost) {
            $chips[$value] = $min;
            $remaining -= $cost;
        } else {
            $chips[$value] = 0;
        }
    }
    
    foreach ($chip_values as $value) {
        if ($remaining <= 0) break;
        $max_use = floor($remaining / $value);
        $use = min($max_use, floor($remaining * 0.3 / $value) + 1);
        if ($use > 0) {
            $chips[$value] = ($chips[$value] ?? 0) + $use;
            $remaining -= $use * $value;
        }
    }
    
    $chip_values_asc = [1, 5, 10, 25, 50];
    foreach ($chip_values_asc as $value) {
        if ($remaining <= 0) break;
        $use = floor($remaining / $value);
        if ($use > 0) {
            $chips[$value] = ($chips[$value] ?? 0) + $use;
            $remaining -= $use * $value;
        }
    }
    
    if ($remaining > 0) {
        $chips[1] = ($chips[1] ?? 0) + $remaining;
    }
    
    foreach ($chip_values as $value) {
        if (!isset($chips[$value]) || $chips[$value] == 0) {
            if ($amount >= $value * 2) {
                $chips[$value] = 1;
                foreach ($chip_values as $v) {
                    if ($v != $value && ($chips[$v] ?? 0) > 0) {
                        $chips[$v] = ($chips[$v] ?? 0) - 1;
                        break;
                    }
                }
            }
        }
    }
    
    krsort($chips);
    return $chips;
}

function calculateTotalFromChips($chips) {
    $total = 0;
    foreach ($chips as $value => $count) {
        $total += $value * $count;
    }
    return $total;
}

function smartConvertMoneyToChips($amount, $available_chips) {
    $chip_values = [50, 25, 10, 5, 1];
    $result = [];
    $remaining = $amount;
    $total_available = 0;
    
    foreach ($chip_values as $value) {
        $total_available += $value * ($available_chips[$value] ?? 0);
    }
    
    if ($amount > $total_available) {
        return ['success' => false, 'message' => 'เงินไม่พอ กรุณาเพิ่มชิป'];
    }
    
    foreach ($chip_values as $value) {
        $available = $available_chips[$value] ?? 0;
        $needed = floor($remaining / $value);
        $use = min($needed, $available);
        
        if ($use > 0) {
            $result[$value] = $use;
            $remaining -= $use * $value;
        } else {
            $result[$value] = 0;
        }
    }
    
    if ($remaining > 0) {
        $available_1 = $available_chips[1] ?? 0;
        $used_1 = ($result[1] ?? 0) + $remaining;
        if ($used_1 <= $available_1) {
            $result[1] = $used_1;
            $remaining = 0;
        } else {
            return ['success' => false, 'message' => 'เงินไม่พอแม้แต่ชิป 1'];
        }
    }
    
    return ['success' => true, 'chips' => $result];
}
?>