<?php

session_start();

require_once 'Auth.php';   // your auth function
require_once 'DB.php';

/* ===== Protect page (role check) ===== */
auth('student');


/* ===== 15-Minute Inactivity Timer ===== */
$timeout = 900; // 900 seconds = 15 minutes

if (isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {

    session_unset();
    session_destroy();

    header("Location: Register-login.php?timeout=1");
    exit;
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();


/* ===== Extra safety check (optional) ===== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: Register-login.php");
    exit;
}

$userId = $_SESSION['user_id'];



$userStmt = $pdo->prepare("SELECT first_name, last_name, student_id, email, profile_photo, last_login, password_reset FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$resultsStmt = $pdo->prepare("
    SELECT a.title, r.score, r.passed, r.completed_at
    FROM results r
    JOIN assessments a ON r.assessment_id = a.id
    WHERE r.student_id = ?
    ORDER BY r.completed_at DESC
");
$resultsStmt->execute([$userId]);
$results = $resultsStmt->fetchAll();
?>


<!DOCTYPE html>
<html>
<head>
<title>Student Dashboard | Quiz-me</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',Arial,sans-serif;
}

body{
background:
radial-gradient(circle at top left,#60a5fa 0%,transparent 25%),
radial-gradient(circle at bottom right,#2563eb 0%,transparent 30%),
linear-gradient(135deg,#020617,#0f172a,#1e3a8a);

min-height:100vh;
padding:40px 20px;
color:white;
overflow-x:hidden;
}

/* floating glow */

body::before{
content:"";
position:fixed;
width:500px;
height:500px;
background:rgba(59,130,246,.18);
border-radius:50%;
filter:blur(120px);
top:-150px;
left:-120px;
z-index:0;
}

body::after{
content:"";
position:fixed;
width:450px;
height:450px;
background:rgba(96,165,250,.12);
border-radius:50%;
filter:blur(120px);
bottom:-180px;
right:-100px;
z-index:0;
}

/* MAIN CONTAINER */

.container{
position:relative;
z-index:2;

max-width:1100px;
margin:auto;

padding:35px;

border-radius:28px;

background:rgba(255,255,255,.08);

backdrop-filter:blur(20px);
-webkit-backdrop-filter:blur(20px);

border:1px solid rgba(255,255,255,.12);

box-shadow:
0 25px 60px rgba(0,0,0,.35),
inset 0 1px 1px rgba(255,255,255,.12);

overflow:hidden;
}

/* glossy shine */

.container::before{
content:"";
position:absolute;
top:0;
left:0;
width:100%;
height:45%;

background:linear-gradient(
to bottom,
rgba(255,255,255,.12),
transparent
);

pointer-events:none;
}

/* HEADER */

.topbar{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:30px;
gap:20px;
flex-wrap:wrap;
}

.profile-area{
display:flex;
align-items:center;
gap:15px;
}

.profile-area img{
width:60px;
height:60px;
border-radius:50%;
object-fit:cover;
cursor:pointer;

border:3px solid rgba(255,255,255,.18);

box-shadow:
0 0 20px rgba(59,130,246,.25);

transition:.3s;
}

.profile-area img:hover{
transform:scale(1.08);
}

h2{
font-size:34px;
font-weight:700;

background:linear-gradient(90deg,#fff,#93c5fd);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

h3{
margin:20px 0 15px;
font-size:24px;
color:#dbeafe;
}

/* BUTTONS */

.logout,
.course-link{
display:inline-flex;
align-items:center;
justify-content:center;

padding:12px 22px;

border-radius:14px;

text-decoration:none;
font-weight:700;

transition:.3s;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.12);

color:white;

backdrop-filter:blur(10px);

box-shadow:
0 8px 18px rgba(0,0,0,.15);
}

.logout:hover,
.course-link:hover{
    
    
transform:translateY(-3px);

background:rgba(59,130,246,.22);

box-shadow:
0 12px 24px rgba(37,99,235,.25);

}
.logout{
float:right;
}
/* TABLE */

table{
width:100%;
border-collapse:collapse;
margin-top:25px;
overflow:hidden;

border-radius:18px;

background:rgba(255,255,255,.05);

backdrop-filter:blur(10px);

overflow:hidden;
}

th{
background:rgba(59,130,246,.22);

padding:16px;
text-align:left;

font-size:15px;
font-weight:700;

color:#fff;
}

td{
padding:16px;
border-bottom:1px solid rgba(255,255,255,.08);

color:#e2e8f0;
}

tr{
transition:.3s;
}

tr:hover{
background:rgba(255,255,255,.05);
}

/* PASS FAIL */

.pass{
color:#4ade80;
font-weight:bold;
}

.fail{
color:#f87171;
font-weight:bold;
}

/* MODALS */

.modal{
display:none;
position:fixed;
z-index:999;
left:0;
top:0;
width:100%;
height:100%;

background:rgba(0,0,0,.55);

backdrop-filter:blur(8px);

overflow:auto;
padding:30px 15px;
}

/* MODAL CONTENT */

.modal-content{
position:relative;

background:rgba(15,23,42,.88);

backdrop-filter:blur(20px);

border:1px solid rgba(255,255,255,.08);

margin:auto;

padding:30px;

width:100%;
max-width:500px;

border-radius:24px;

box-shadow:
0 25px 60px rgba(0,0,0,.45);

color:white;

animation:pop .35s ease;
}

@keyframes pop{
from{
transform:scale(.9);
opacity:0;
}

to{
transform:scale(1);
opacity:1;
}
}

/* CLOSE BUTTONS */

.close,
#profileClose{
position:absolute;
right:18px;
top:12px;

font-size:30px;
cursor:pointer;

color:#93c5fd;

transition:.3s;
}

.close:hover,
#profileClose:hover{
transform:rotate(90deg);
color:white;
}

/* INPUTS */

input{
width:100%;

padding:14px;
margin-top:8px;
margin-bottom:18px;

border:none;
outline:none;

border-radius:14px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.08);

color:white;

font-size:14px;

transition:.3s;
}

input:focus{
border:1px solid rgba(96,165,250,.55);

box-shadow:
0 0 18px rgba(59,130,246,.22);
}

input[readonly]{
opacity:.85;
}

label{
font-size:14px;
color:#cbd5e1;
}

/* MODAL IMAGE */

.modal-content img{
border-radius:50%;
border:4px solid rgba(255,255,255,.12);

box-shadow:
0 0 20px rgba(59,130,246,.25);
}

/* BUTTON */

button{
width:100%;
padding:14px;

border:none;
border-radius:14px;

font-weight:700;
font-size:15px;

cursor:pointer;

background:
linear-gradient(
145deg,
#60a5fa,
#2563eb
);

color:white;

transition:.3s;

box-shadow:
0 10px 25px rgba(37,99,235,.3);
}

button:hover{
transform:translateY(-3px);

box-shadow:
0 18px 35px rgba(37,99,235,.45);
}

/* DIVIDERS */

hr{
border:none;
height:1px;
background:rgba(255,255,255,.08);
margin:20px 0;
}

/* RESPONSIVE */

@media(max-width:768px){

.container{
padding:22px;
}

.topbar{
flex-direction:column;
align-items:flex-start;
}

h2{
font-size:28px;
}

table{
display:block;
overflow-x:auto;
}

th,td{
min-width:160px;
}

.modal-content{
padding:22px;
}

}

</style>
</head>

<body>

<div class="container">

<a href="logout.php" class="logout">Logout</a>

<?php
$photo = $user['profile_photo'] ?? 'default.png';
?>

<div style="display:flex; align-items:center; gap:10px;">

<img src="uploads/<?php echo $photo; ?>"
     id="profileBtn"
     style="width:40px;height:40px;border-radius:50%;object-fit:cover;cursor:pointer;">

<h2>Welcome, <?= htmlspecialchars($user['first_name']) ?></h2>

</div>

<h3>Your Assessments</h3>

<a href="Student-courses.php" class="course-link">
📚 View Assigned Exams
</a>

<table>

<tr>
<th>Assessment</th>
<th>Score</th>
<th>Status</th>
<th>Date</th>
</tr>

<?php if ($results): foreach ($results as $r): ?>

<tr>
<td><?= htmlspecialchars($r['title']) ?></td>
<td><?= $r['score'] ?>%</td>
<td class="<?= $r['passed'] ? 'pass' : 'fail' ?>">
<?= $r['passed'] ? 'Passed' : 'Failed' ?>
</td>
<td><?= date("M d, Y", strtotime($r['completed_at'])) ?></td>
</tr>

<?php endforeach; else: ?>

<tr>
<td colspan="4">No assessments taken yet.</td>
</tr>

<?php endif; ?>

</table>

</div>


<!-- PROFILE MODAL -->

<div id="profileModal" class="modal">

    <div class="modal-content">

        <span id="profileClose">&times;</span>

        <h2>My Profile</h2>

        <form action="Update-Profile.php" method="POST" enctype="multipart/form-data">

            <label>Profile Photo</label><br>

            <img src="uploads/<?php echo $user['profile_photo'] ?? 'default.png'; ?>" 
                 width="80"
                 style="border-radius:50%;object-fit:cover;"><br><br>

            <input type="file" name="profile_photo">

            <br><br>

            <label>First Name</label><br>
            <input type="text"
                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                   readonly>

            <br><br>

            <label>Last Name</label><br>
            <input type="text"
                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                   readonly>

            <br><br>

            <label>Email</label><br>
            <input type="email"
                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                   readonly>

            <br><br>

            <label>Student ID</label><br>
            <input type="text"
                   value="<?= htmlspecialchars($user['student_id'] ?? '') ?>" 
                   readonly>

            <hr>

            <h3>Security</h3>

            <label>New Password</label><br>
            <input type="password" name="new_password">

            <br><br>

            <label>Confirm Password</label><br>
            <input type="password" name="confirm_password">

            <hr>

            <h3>Account Activity</h3>

            <p>Last Login: <?= $user['last_login'] ?? '—' ?></p>
            <p>Last Password Reset: <?= $user['password_reset'] ?? '—' ?></p>

            <br>

            <button type="submit">Update Profile</button>

        </form>

    </div>

</div>

<div id="courseModal" class="modal">

  <div class="modal-content" style="width:600px;">

    <span class="close" onclick="closeCourse()">&times;</span>

    <h2 id="courseTitle"></h2>

    <div id="courseContent" style="margin-top:15px;"></div>

    <br>

    <button id="completeBtn" style="display:none;" onclick="completeCourse()">
        Mark as Completed
    </button>

  </div>

</div>
</form>

</div>
</div>


<script>

var modal = document.getElementById("profileModal");
var btn = document.getElementById("profileBtn");
var span = document.getElementById("profileClose");

if(btn){
    btn.onclick = function () {
        modal.style.display = "block";
    };
}

if(span){
    span.onclick = function () {
        modal.style.display = "none";
    };
}

window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
};

</script>

</body>
</html>