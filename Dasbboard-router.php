<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: Auth.php");
    exit;
}

if ($_SESSION['role'] === 'student') {
    header("Location: Student-dashboard.php");
} else {
    header("Location: Facilitator-dashboard.php");
}
exit;
