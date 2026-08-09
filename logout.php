<?php
// logout.php - ออกจากระบบ และลบผู้เล่นออกจากห้อง
require_once 'config.php';

if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // ลบผู้เล่นออกจากห้องที่กำลังเล่นอยู่
    if (isset($_SESSION['room_id'])) {
        removePlayerFromRoom($pdo, $user_id, $_SESSION['room_id']);
    }
    
    // เคลียร์ session ทั้งหมด
    session_destroy();
}

redirect('login.php');
?>