<?php
session_start();
require 'DB.php';





$userID = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(isset($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['name'] != ''){

    $filename = time() . "_" . $_FILES['profile_photo']['name'];
    $target = "uploads/" . $filename;

    move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target);

    $stmt = $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?");
    $stmt->execute([$filename,$userID]);
}


if(!empty($_POST['new_password'])){

    if($_POST['new_password'] === $_POST['confirm_password']){

        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE users 
            SET password=?, password_reset=NOW()
            WHERE id=?
        ");

        $stmt->execute([$hash,$userID]);

    }
}
header("Location: Student-dashboard.php");
exit();

?>
