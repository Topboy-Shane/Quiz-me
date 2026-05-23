<?php

session_start();
require_once('DB.php');


function generateStudentID($first, $last) {
    return strtoupper(substr($first,0,2) . substr($last,0,2)) . rand(10,99);
}

$studentIDPopup = "";
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'Register-login.php';
}




/* ================= REGISTER ================= */
if (isset($_POST['register'])) {

    $allowedRoles = ['student', 'facilitator'];
    $role  = in_array($_POST['role'], $allowedRoles) ? $_POST['role'] : 'student';

    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $confirm = $_POST['confirm_password'] ?? $pass;

    if (!$first || !$last || !$email || !$pass) {
        $error = "All fields are required.";
    }
    elseif ($pass !== $confirm) {
        $error = "Passwords do not match.";
    }
    else {

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $error = "Email already registered.";
        }
        else {

            $password = password_hash($pass, PASSWORD_DEFAULT);

            $studentID = null;
            if ($role === 'student') {
                do {
                    $studentID = generateStudentID($first, $last);
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
                    $stmt->execute([$studentID]);
                } while ($stmt->rowCount() > 0);
            }

            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (student_id, role, first_name, last_name, email, password)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if ($stmt->execute([
                $studentID,
                $role,
                $first,
                $last,
                $email,
                $password,
            ])) {

                $verifyLink = "http://localhost/Quiz-me/verify.php?token=$token";

                $success = "Registration successful.";

                if ($role === 'student') {
                    $studentIDPopup = $studentID;
                }

            } else {
                $error = "Database insert failed.";
            }
        }
    }
}


/* ================= LOGIN ================= */

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $studentID = $_POST['student_id'] ?? null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        /* ===== 1. Check if account is locked ===== */
        if ($user['account_locked_until'] !== null &&
            strtotime($user['account_locked_until']) > time()) {

            $error = "Account locked. Try again later.";
        }

        /* ===== 2. Verify password ===== */
        elseif (password_verify($password, $user['password'])) {

    /* ===== Student validation only for students ===== */

    if ($user['role'] === 'student') {

        if (!$studentID) {
            $error = "Student ID is required.";
        }
        elseif ($user['student_id'] !== $studentID) {
            $error = "Invalid Student ID";
        }

    }

    /* ===== If no error → login ===== */

    if (!$error) {

    $reset = $pdo->prepare("
        UPDATE users 
        SET 
            failed_attempts = 0,
            account_locked_until = NULL,
            last_login = NOW()
        WHERE id = ?
    ");

    $reset->execute([$user['id']]);

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['LAST_ACTIVITY'] = time();

    if ($user['role'] === 'facilitator') {
        header("Location: Facilitator-dashboard.php");
    } else {
        header("Location: Student-dashboard.php");
    }

    exit;
}

} else {

    /* ===== Failed password → Increase attempts ===== */

    $failedAttempts = $user['failed_attempts'] + 1;

    if ($failedAttempts >= 4) {

        $lockTime = date("Y-m-d H:i:s", time() + (2 * 60 * 60));

        $lock = $pdo->prepare("
            UPDATE users
            SET 
                failed_attempts = ?,
                last_failed_login = NOW(),
                account_locked_until = ?
            WHERE id = ?
        ");

        $lock->execute([$failedAttempts, $lockTime, $user['id']]);

        $error = "Too many failed attempts. Account locked for 2 hours.";

    } else {

        $update = $pdo->prepare("
            UPDATE users 
            SET 
                failed_attempts = ?,
                last_failed_login = NOW()
            WHERE id = ?
        ");

        $update->execute([$failedAttempts, $user['id']]);

        $error = "Invalid login details, try again.";
    }
}
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>Quiz-me | Login</title>

<style>

*{
box-sizing:border-box;
font-family:Segoe UI, Arial, sans-serif;
}

body{
margin:0;
height:100vh;
overflow:hidden;
display:flex;
justify-content:center;
align-items:center;
position:relative;

/* futuristic layered background */
background:
radial-gradient(circle at top left,#60a5fa 0%,transparent 30%),
radial-gradient(circle at bottom right,#2563eb 0%,transparent 35%),
linear-gradient(135deg,#020617,#0f172a,#1e3a8a);
}

/* animated background glow */

body::before{
content:"";
position:absolute;
width:700px;
height:700px;
background:rgba(59,130,246,.18);
border-radius:50%;
filter:blur(120px);

top:-200px;
left:-150px;

animation:moveGlow 10s ease-in-out infinite alternate;
}

body::after{
content:"";
position:absolute;
width:600px;
height:600px;
background:rgba(96,165,250,.12);
border-radius:50%;
filter:blur(100px);

bottom:-180px;
right:-120px;

animation:moveGlow2 12s ease-in-out infinite alternate;
}

@keyframes moveGlow{
from{
transform:translate(0,0);
}
to{
transform:translate(60px,40px);
}
}

@keyframes moveGlow2{
from{
transform:translate(0,0);
}
to{
transform:translate(-50px,-30px);
}
}

/* floating particles */

.particle{
position:absolute;
width:7px;
height:7px;
background:rgba(255,255,255,.8);
border-radius:50%;
box-shadow:0 0 12px rgba(255,255,255,.9);
animation:float 20s linear infinite;
}

@keyframes float{
from{
transform:translateY(100vh) scale(.6);
opacity:0;
}

20%{
opacity:.8;
}

to{
transform:translateY(-120vh) scale(1.2);
opacity:0;
}
}

/* premium glass container */

.container{
position:relative;
z-index:2;
width:390px;
padding:42px;
border-radius:28px;

/* glass look */
background:rgba(255,255,255,.08);

backdrop-filter:blur(22px);
-webkit-backdrop-filter:blur(22px);

border:1px solid rgba(255,255,255,.12);

box-shadow:
0 25px 80px rgba(0,0,0,.45),
inset 0 1px 1px rgba(255,255,255,.12),
0 0 35px rgba(59,130,246,.12);

overflow:hidden;
color:white;
}

/* glossy shine */

.container::before{
content:"";
position:absolute;
top:0;
left:0;
width:100%;
height:50%;

background:linear-gradient(
to bottom,
rgba(255,255,255,.14),
transparent
);

pointer-events:none;
}

/* heading */

h2{
text-align:center;
margin-bottom:28px;
font-size:30px;
font-weight:700;
letter-spacing:.5px;

text-shadow:0 4px 10px rgba(0,0,0,.35);
}

/* inputs */

input,
select{
width:100%;
padding:14px;
margin-bottom:16px;

border:none;
outline:none;

border-radius:14px;

background:rgba(255,255,255,.10);

border:1px solid rgba(255,255,255,.08);

color:white;
font-size:15px;

backdrop-filter:blur(8px);

transition:.3s;
}

input:focus,
select:focus{
background:rgba(255,255,255,.14);

border:1px solid rgba(96,165,250,.6);

box-shadow:
0 0 15px rgba(59,130,246,.25);
}

input::placeholder{
color:#dbeafe;
}

/* buttons */

button{
width:100%;
padding:14px;

border:none;
border-radius:14px;

background:
linear-gradient(
145deg,
#60a5fa,
#2563eb
);

color:white;
font-weight:700;
font-size:15px;

cursor:pointer;
transition:.3s;

box-shadow:
0 10px 25px rgba(37,99,235,.35),
inset 0 1px 2px rgba(255,255,255,.25);

position:relative;
overflow:hidden;
}

/* moving shine */

button::before{
content:"";
position:absolute;
top:0;
left:-50%;
width:50%;
height:100%;

background:linear-gradient(
90deg,
transparent,
rgba(255,255,255,.35),
transparent
);

transform:skewX(-25deg);
transition:.6s;
}

button:hover::before{
left:130%;
}

button:hover{
transform:translateY(-3px);

box-shadow:
0 18px 35px rgba(37,99,235,.45);
}

/* switch */

.switch{
text-align:center;
margin-top:16px;
font-size:14px;
}

.switch span{
color:#93c5fd;
cursor:pointer;
font-weight:700;
transition:.3s;
}

.switch span:hover{
color:white;
}

/* hidden forms */

.hidden{
display:none;
}

/* floating graduation cap */

.cap{
position:absolute;
top:-120px;
left:50%;
transform:translateX(-50%);
width:130px;
height:130px;

opacity:.5;

filter:drop-shadow(0 10px 15px rgba(0,0,0,.4));

animation:spin 12s linear infinite;
}

.cap-top{
width:130px;
height:12px;

background:
linear-gradient(
145deg,
#111827,
#000
);

border-radius:4px;
}

.cap-base{
width:65px;
height:42px;

background:#111827;

margin:auto;
margin-top:-10px;

border-radius:8px;
}

@keyframes spin{
from{
transform:translateX(-50%) rotateY(0deg);
}

to{
transform:translateX(-50%) rotateY(360deg);
}
}

/* home button */

.home-btn{
display:block;
margin:14px auto 0 auto;

width:auto;
padding:8px 18px;

font-size:12px;
font-weight:600;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.15);

border-radius:10px;

color:white;

cursor:pointer;
transition:.3s;
}

.home-btn:hover{
background:rgba(255,255,255,.15);

transform:translateY(-2px);
}

/* password */

.password-box{
position:relative;
}

.password-box input{
width:100%;
padding-right:45px;
}

.toggle{
position:absolute;
right:14px;
top:50%;
transform:translateY(-50%);

cursor:pointer;
font-size:17px;

opacity:.7;
transition:.3s;
}

.toggle:hover{
opacity:1;
transform:translateY(-50%) scale(1.1);
}
</style>

</head>

<body>

<script>
for(let i=0;i<40;i++){
let p=document.createElement("div");
p.className="particle";
p.style.left=Math.random()*100+"%";
p.style.animationDuration=(10+Math.random()*15)+"s";
document.body.appendChild(p);
}
</script>

<div class="container">

<div class="cap">
<div class="cap-top"></div>
<div class="cap-base"></div>
</div>

<!-- LOGIN -->
<form method="POST" id="loginForm">

<h2>Quiz-me Login</h2>

<?php if(!empty($error)): ?>
<div style="
background:#ef4444;
padding:10px;
border-radius:6px;
margin-bottom:15px;
font-size:14px;
text-align:center;
color:white;
">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<input type="email" name="email" placeholder="Email" required>

<input type="text" name="student_id" placeholder="Student ID (students only)">

<div class="password-box">
<input type="password" name="password" id="loginPassword" placeholder="Password" required>
<span class="toggle" onclick="togglePassword('loginPassword', this)">👁</span>
</div>

<button name="login">Login</button>

<div class="switch">
No account? <span onclick="toggle()">Register</span>
</div>

</form>

<!-- REGISTER -->
<form method="POST" id="registerForm" class="hidden">

<h2>Create Account</h2>

<select name="role" required>
<option value="">Select Role</option>
<option value="student">Student</option>
<option value="facilitator">Facilitator</option>
</select>

<input type="text" name="first_name" placeholder="First Name" required>
<input type="text" name="last_name" placeholder="Last Name" required>
<input type="email" name="email" placeholder="Email" required>

<div class="password-box">
<input type="password" name="password" id="registerPassword" placeholder="Password" required>
<span class="toggle" onclick="togglePassword('registerPassword', this)">👁</span>
</div>

<div class="password-box">
<input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required>
<span class="toggle" onclick="togglePassword('confirmPassword', this)">👁</span>
</div>

<button name="register">Register</button>

<div class="switch">
Already registered? <span onclick="toggle()">Login</span>
</div>

</form>

<!-- SMALL HOME BUTTON -->
<button class="home-btn" onclick="location.href='Quiz-me landing page.html'">
Home
</button>

</div>

<script>

function toggle(){
document.getElementById("loginForm").classList.toggle("hidden");
document.getElementById("registerForm").classList.toggle("hidden");
}

function togglePassword(id, icon){

let input = document.getElementById(id);

if(input.type === "password"){
input.type = "text";
icon.textContent = "🙈";
}else{
input.type = "password";
icon.textContent = "👁";
}

}
</script>
<?php if (!empty($studentIDPopup)): ?>
<script>
    alert("Registration successful!\nYour Student ID is: <?= $studentIDPopup ?>");
</script>
<?php endif; ?>
</body>
</html>