<?php

session_start();
require_once 'DB.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$userId = $_SESSION['user_id'];
$courseId = $_POST['course_id'] ?? null;
$progressTime = $_POST['progress_time'] ?? null;
$action = $_POST['action'] ?? null;

if (!$courseId) {
    die("No course ID received");
}

/* ================= SAVE PROGRESS ================= */
if ($action === "progress" && $progressTime !== null) {

    $stmt = $pdo->prepare("
        UPDATE course_assignments
        SET progress_time = ?
        WHERE user_id = ? AND course_id = ?
    ");

    $stmt->execute([$progressTime, $userId, $courseId]);

    echo "progress_saved";
    exit;
}

/* ================= COMPLETE COURSE ================= */
if ($action === "complete") {

    $stmt = $pdo->prepare("
        UPDATE course_assignments
        SET status = 'completed',
            completed_at = NOW()
        WHERE user_id = ? AND course_id = ?
    ");

    $stmt->execute([$userId, $courseId]);

    // 🔥 THIS LINE WILL EXPOSE THE PROBLEM
    if ($stmt->rowCount() === 0) {
        die("Update failed: No matching row. user_id=$userId course_id=$courseId");
    }

    echo "completed";
    exit;
}