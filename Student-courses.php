<?php

session_start();
require_once 'Auth.php';
require_once 'DB.php';

auth('student');

/* ✅ GET LOGGED-IN USER ID */
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];

/* ===============================
   GET ASSIGNED COURSES
================================ */

$stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.course_name, 
        c.title, 
        c.description,
        c.content,
        c.video_url,
        ca.assigned_at, 
        ca.status
    FROM course_assignments ca
    JOIN courses c ON ca.course_id = c.id
    WHERE ca.user_id = ?
");





$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
/* ===============================
   LOAD ASSESSMENTS FOR COURSES
================================ */



$assessmentsByCourse = [];

if (!empty($courses)) {

    // Get all course IDs
    $courseIds = array_column($courses, 'id');

    // Create placeholders (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id, course_id, max_attempts
        FROM assessments
        WHERE course_id IN ($placeholders)
    ");

    $stmt->execute($courseIds);
    $allAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by course_id
    foreach ($allAssessments as $a) {
        $assessmentsByCourse[$a['course_id']][] = $a;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    
<title>My Courses</title>
<a href="Student-dashboard.php" class="Home">Home</a>


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
overflow-x:hidden;
color:white;
padding-bottom:40px;
}

/* background glow */

body::before{
content:"";
position:fixed;
width:500px;
height:500px;
background:rgba(59,130,246,.16);
border-radius:50%;
filter:blur(120px);
top:-180px;
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
right:-120px;
z-index:0;
}

/* HEADER */

.header{
position:relative;
z-index:2;

display:flex;
justify-content:space-between;
align-items:center;
flex-wrap:wrap;

padding:25px 40px;

background:rgba(255,255,255,.08);

backdrop-filter:blur(16px);
-webkit-backdrop-filter:blur(16px);

border-bottom:1px solid rgba(255,255,255,.08);

box-shadow:
0 8px 30px rgba(0,0,0,.18);

font-size:28px;
font-weight:700;

color:white;
}

/* HOME BUTTON */

.Home{
text-decoration:none;

padding:12px 20px;

border-radius:14px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.12);

color:white;

font-weight:600;

backdrop-filter:blur(10px);

transition:.3s;

box-shadow:
0 8px 20px rgba(0,0,0,.18);

position:absolute;
top:22px;
right:30px;
}

.Home:hover{
transform:translateY(-3px);

background:rgba(59,130,246,.2);

box-shadow:
0 12px 24px rgba(37,99,235,.25);
}

/* CONTAINER */

.container{
position:relative;
z-index:2;

max-width:1200px;
margin:40px auto;

padding:20px;
}

/* COURSE CARD */

.course-card{
background:rgba(255,255,255,.08);

backdrop-filter:blur(18px);
-webkit-backdrop-filter:blur(18px);

border:1px solid rgba(255,255,255,.08);

border-radius:24px;

padding:28px;

margin-bottom:28px;

box-shadow:
0 20px 40px rgba(0,0,0,.25),
inset 0 1px 1px rgba(255,255,255,.08);

position:relative;
overflow:hidden;

transition:.35s;
}

/* glossy shine */

.course-card::before{
content:"";
position:absolute;
top:0;
left:0;
width:100%;
height:45%;

background:linear-gradient(
to bottom,
rgba(255,255,255,.08),
transparent
);

pointer-events:none;
}

.course-card:hover{
transform:translateY(-6px);

box-shadow:
0 25px 50px rgba(0,0,0,.35),
0 0 30px rgba(59,130,246,.12);
}

/* TITLES */

.course-title{
font-size:28px;
font-weight:700;

margin-bottom:10px;

background:linear-gradient(90deg,#fff,#93c5fd);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

small{
color:#cbd5e1;
font-size:13px;
}

/* STATUS */

.status{
margin-top:12px;
font-weight:700;
font-size:15px;
}

.status span{
padding:6px 12px;
border-radius:999px;
display:inline-block;
margin-left:8px;
}

.completed{
background:rgba(34,197,94,.18);
color:#4ade80;
}

.in-progress{
background:rgba(251,191,36,.18);
color:#facc15;
}

.assigned{
background:rgba(148,163,184,.18);
color:#cbd5e1;
}

/* BUTTONS */

.btn{
display:inline-flex;
align-items:center;
justify-content:center;

padding:13px 24px;

border:none;
border-radius:14px;

background:
linear-gradient(
145deg,
#60a5fa,
#2563eb
);

color:white;
text-decoration:none;
font-weight:700;

cursor:pointer;

transition:.3s;

box-shadow:
0 10px 25px rgba(37,99,235,.3);

margin-top:10px;
}

.btn:hover{
transform:translateY(-3px);

box-shadow:
0 18px 35px rgba(37,99,235,.45);
}

/* DISABLED */

.btn.disabled{
background:rgba(148,163,184,.25);
color:#cbd5e1;

pointer-events:none;

box-shadow:none;
}

/* ATTEMPTS */

.attempts{
margin:16px 0 8px;
font-weight:700;
font-size:15px;
}

.ok-attempts{
color:#4ade80;
}

.no-attempts{
color:#f87171;
}

/* EMPTY */

.empty{
background:rgba(255,255,255,.08);

backdrop-filter:blur(18px);

padding:60px 30px;

text-align:center;

border-radius:24px;

border:1px solid rgba(255,255,255,.08);

box-shadow:
0 20px 40px rgba(0,0,0,.25);
}

.empty h3{
font-size:28px;
margin-bottom:10px;
}

/* MODAL */

#courseModal{
backdrop-filter:blur(8px);
z-index:999;
}

/* MODAL BOX */

#courseModal > div{
background:rgba(15,23,42,.95) !important;

backdrop-filter:blur(20px);

border:1px solid rgba(255,255,255,.08);

border-radius:24px !important;

box-shadow:
0 30px 60px rgba(0,0,0,.45);

color:white;
}

/* MODAL TITLE */

#courseTitle{
font-size:30px;
margin-bottom:20px;

background:linear-gradient(90deg,#fff,#93c5fd);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

/* CONTENT */

#courseContent{
line-height:1.8;
color:#e2e8f0;
padding-right:6px;
}

/* VIDEO */

video,
iframe{
border-radius:18px;
margin-top:20px;
box-shadow:
0 10px 30px rgba(0,0,0,.35);
}

/* CLOSE BUTTON */

#courseModal span{
color:#93c5fd;
transition:.3s;
}

#courseModal span:hover{
color:white;
transform:rotate(90deg);
}

/* SCROLLBAR */

::-webkit-scrollbar{
width:10px;
}

::-webkit-scrollbar-thumb{
background:rgba(255,255,255,.15);
border-radius:20px;
}

/* MOBILE */

@media(max-width:768px){

.header{
padding:22px;
font-size:22px;
}

.Home{
position:static;
margin-top:15px;
display:inline-block;
}

.course-title{
font-size:24px;
}

.course-card{
padding:22px;
}

.btn{
width:100%;
}

}
.Home{
display:inline-flex;
align-items:center;
justify-content:center;

padding:8px 16px;

font-size:13px;
font-weight:600;

text-decoration:none;
color:white;

border-radius:12px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.12);

backdrop-filter:blur(10px);

transition:.3s;

box-shadow:
0 6px 18px rgba(0,0,0,.18);
}

.Home:hover{
transform:translateY(-2px);

background:rgba(59,130,246,.2);

box-shadow:
0 10px 22px rgba(37,99,235,.28);
}
</style>

</head>

<body>
    

<div class="header">

    <div>🎓 My Assigned Courses</div>

    <a href="Student-dashboard.php" class="Home">
        🏠 Home
    </a>

</div>

<div class="container">



<?php if (!empty($courses)): ?>

<?php foreach ($courses as $course): ?>
    

<?php
$courseStatus = $course['status'] ?? 'assigned';
$isCompleted = ($courseStatus === 'completed');

/* ✅ ONE SOURCE OF TRUTH */
$courseAssessments = $assessmentsByCourse[$course['id']] ?? [];
?>

<div class="course-card">

    <!-- ======================
         COURSE INFO
    ====================== -->
    
    
    <div class="course-title">
        <?= htmlspecialchars($course['title']) ?>
    </div>

    <small>
        Assigned: <?= date("F j, Y", strtotime($course['assigned_at'])) ?>
    </small>

    <div class="status">
    Status:

    <span class="
    <?= $courseStatus === 'completed'
        ? 'completed'
        : ($courseStatus === 'in_progress'
            ? 'in-progress'
            : 'assigned') ?>">
            
        <?= ucfirst($courseStatus) ?>

    </span>
</div>

    <br>

    <!-- ======================
         OPEN COURSE BUTTON
    ====================== -->
    
    
    <button class="btn"
    onclick='openCourse(
        <?= $course["id"] ?>,
        <?= json_encode($course["title"]) ?>,
        <?= json_encode($course["content"] ?? "") ?>,
        <?= json_encode($course["video_url"] ?? "") ?>
    )'>
        Open Course
    </button>

    <br><br>

    

    <!-- ======================
         QUIZ SECTION
    ====================== -->

    <?php if (!empty($courseAssessments)): ?>

        <?php foreach ($courseAssessments as $assessment): ?>
            

            <?php
            
            /* ======================
               ATTEMPT LOGIC
            ====================== */
            
            
            $att = $pdo->prepare("
                SELECT attempts_used, extra_attempts
                FROM assessment_attempts
                WHERE student_id = ?
                AND assessment_id = ?
            ");
            $att->execute([$userId, $assessment['id']]);
            $data = $att->fetch();

            $base  = (int)($assessment['max_attempts'] ?? 0);
            $extra = (int)($data['extra_attempts'] ?? 0);
            $used  = (int)($data['attempts_used'] ?? 0);

            $totalAllowed = $base + $extra;
            $remaining = max(0, $totalAllowed - $used);
            ?>

            <!-- Attempts Display -->
            <div class="attempts <?= $remaining > 0 ? 'ok-attempts' : 'no-attempts' ?>">
                Attempts: <?= $remaining ?> / <?= $totalAllowed ?>
            </div>

            <!-- Button Logic -->
            <?php if (!$isCompleted): ?>

                <a class="btn disabled">
                    Quiz unlocks after course completion
                </a>

            <?php elseif ($remaining > 0): ?>

                <a 
    href="<?= $isCompleted && $remaining > 0 ? 'Student-assesment.php?id='.$assessment['id'] : '#' ?>" 
    class="btn <?= (!$isCompleted || $remaining <= 0) ? 'disabled' : '' ?>"
>
    <?php if (!$isCompleted): ?>
        Complete course to unlock quiz
    <?php elseif ($remaining <= 0): ?>
        No Attempts Remaining
    <?php else: ?>
        Start Quiz
    <?php endif; ?>
</a>

            <?php endif; ?>

            <br><br>

        <?php endforeach; ?>

    <?php else: ?>

        <!-- ✅ NO QUIZ YET -->
        <a class="btn disabled">
            Quiz will be assigned after course completion
        </a>

    <?php endif; ?>

</div> <!-- END COURSE CARD -->

<?php endforeach; ?>

<?php else: ?>

<div class="empty">
    <h3>No courses assigned yet</h3>
    <p>Please check back later.</p>
</div>

<?php endif; ?>



<div id="courseModal" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.6);
    overflow-y:auto;
">

    <div style="
        background:white;
        width:90%;
        max-width:800px;
        height:90vh;
        margin:3% auto;
        padding:20px;
        border-radius:10px;
        position:relative;
        display:flex;
        flex-direction:column;
    ">

        <!-- CLOSE BUTTON -->
        <span onclick="closeCourse()" 
        style="position:absolute; right:15px; top:10px; cursor:pointer; font-size:20px;">
        &times;
        </span>

        <!-- TITLE -->
        <h2 id="courseTitle"></h2>

        <!-- SCROLLABLE CONTENT -->
        <div id="courseContent" style="
            flex:1;
            overflow-y:auto;
            margin-bottom:15px;
        "></div>

        <!-- VIDEO -->
        <div id="videoContainer"></div>

        <!-- BUTTON -->
        <div style="margin-top:10px;">
            <button id="completeBtn" class="btn" onclick="completeCourse()" style="display:none;">
                Complete Course
            </button>
        </div>

    </div>

</div>



<script>



let currentCourseId = null;

/* ================= CLOSE MODAL ================= */
function closeCourse() {
    let modal = document.getElementById("courseModal");
    let video = document.getElementById("courseVideo");

    modal.style.display = "none";

    if (video) {
        video.pause();
    }
}

/* ================= OPEN COURSE ================= */
function openCourse(id, title, content, videoPath = "") {

    currentCourseId = id;
    

    document.getElementById("courseTitle").innerText = title;
    document.getElementById("courseContent").innerHTML = content;

   let container = document.getElementById("videoContainer");

if (videoPath && videoPath.trim() !== "") {

    // Uploaded video
    if (videoPath.includes("uploads/")) {

        container.innerHTML = `
            <video width="100%" controls style="max-height:250px;">
                <source src="${videoPath}" type="video/mp4">
                Your browser does not support video.
            </video>
        `;

    } else {
        // External video (YouTube, etc.)
        container.innerHTML = `
            <iframe width="100%" height="250"
                src="${videoPath}"
                frameborder="0"
                allowfullscreen>
            </iframe>
        `;
    }

} else {
    container.innerHTML = "";
}

    document.getElementById("completeBtn").style.display = "block";

    document.getElementById("courseModal").style.display = "block";
}
/* ================= COMPLETE COURSE ================= */
function completeCourse() {

    if (!currentCourseId) return;

    fetch("Complete-course.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `course_id=${currentCourseId}&action=complete`
    })
    .then(res => res.text())
    .then(() => {
        alert("Course completed!");
        location.reload();
        
    });
}


</script>

</body>
</html>

