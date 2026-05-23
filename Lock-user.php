<?php
session_start();
require_once 'Auth.php';
require_once 'DB.php';

auth('facilitator');

$userId = $_POST['user_id'];
$action = $_POST['action'];

if ($action === 'lock') {
    $stmt = $pdo->prepare("UPDATE users SET is_locked = 1 WHERE id = ?");
} else {
    $stmt = $pdo->prepare("UPDATE users SET is_locked = 0 WHERE id = ?");
}

$stmt->execute([$userId]);

header("Location: facilitator_dashboard.php");
exit;