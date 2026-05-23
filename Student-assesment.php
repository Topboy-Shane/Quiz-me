<?php

session_start();
require_once 'Auth.php';
require_once 'DB.php';

auth('student');

$assessmentId = $_GET['id'] ?? 0;

/* ===============================
   ACCESS + GET ASSESSMENT
================================ */

$stmt = $pdo->prepare("
    SELECT a.*, ca.status
    FROM assessments a
    JOIN course_assignments ca
        ON ca.course_id = a.course_id
    WHERE ca.user_id = ? 
    AND a.id = ?
    AND ca.status = 'completed'
");

$stmt->execute([$_SESSION['user_id'], $assessmentId]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    die("Access denied or assessment not found.");
}
/* ===============================
   ATTEMPT VALIDATION
================================ */

$studentId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
SELECT 
    a.max_attempts,
    COALESCE(attempt.attempts_used, 0) AS used,
    COALESCE(attempt.extra_attempts, 0) AS extra
FROM assessments a
LEFT JOIN assessment_attempts attempt
    ON attempt.assessment_id = a.id
    AND attempt.student_id = ?
WHERE a.id = ?
");

$stmt->execute([$studentId, $assessmentId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

$base   = (int)($attempt['max_attempts'] ?? 0);
$extra  = (int)($attempt['extra'] ?? 0);
$used   = (int)($attempt['used'] ?? 0);

$totalAllowed = $base + $extra;
$remaining    = max(0, $totalAllowed - $used);

if ($remaining <= 0) {
    die("No attempts remaining for this assessment.");
}


/* ===============================
   TIMER (PERSISTENT)
================================ */

$timeLimit = (int)$assessment['time_limit']; 
$deadline = null;

if ($timeLimit > 0) {

    $stmt = $pdo->prepare("
        SELECT start_time, deadline
        FROM assessment_attempts
        WHERE student_id = ? AND assessment_id = ?
    ");
    $stmt->execute([$studentId, $assessmentId]);
    $timer = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = time();

    // FIRST TIME OPENING ASSESSMENT OR DEADLINE IS NULL
    if (!$timer || !$timer['deadline']) {

        $start = date('Y-m-d H:i:s');
        $deadline = date('Y-m-d H:i:s', strtotime("+{$timeLimit} minutes"));

        if (!$timer) {

            // create attempt timer
            $stmt = $pdo->prepare("
                INSERT INTO assessment_attempts
                (student_id, assessment_id, start_time, deadline)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $studentId,
                $assessmentId,
                $start,
                $deadline
            ]);

        } else {

            // update timer if row exists but deadline missing
            $stmt = $pdo->prepare("
                UPDATE assessment_attempts
                SET start_time = ?, deadline = ?
                WHERE student_id = ? AND assessment_id = ?
            ");
            $stmt->execute([
                $start,
                $deadline,
                $studentId,
                $assessmentId
            ]);
        }

    } else {

        // TIMER EXISTS
        $deadline = $timer['deadline'];
        $deadlineTime = strtotime($deadline);

        if ($deadlineTime <= $now) {

    // mark attempt as used
    $attemptUpdate = $pdo->prepare("
        UPDATE assessment_attempts
        SET attempts_used = attempts_used + 1,
            start_time = NULL,
            deadline = NULL
        WHERE assessment_id = ?
        AND student_id = ?
    ");

    $attemptUpdate->execute([$assessmentId, $studentId]);

    // reload page to allow next attempt
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
}
    }
}

/* ===============================
   GET QUESTIONS
================================ */

$questions = $pdo->prepare("
    SELECT * FROM questions
    WHERE assessment_id = ?
");
$questions->execute([$assessmentId]);
?>



<!DOCTYPE html>

<html>
<head>
    
<title><?= htmlspecialchars($assessment['title']) ?></title>
</head>
<style>
    *{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',Arial,sans-serif;
}

body{
min-height:100vh;
padding:40px 20px;
color:white;
overflow-x:hidden;

background:
radial-gradient(circle at top right,#2563eb 0%,transparent 25%),
radial-gradient(circle at bottom left,#7c3aed 0%,transparent 30%),
linear-gradient(135deg,#0f172a,#111827,#1e293b);

position:relative;
}

/* ambient glow */

body::before{
content:"";
position:fixed;
top:-150px;
right:-120px;

width:420px;
height:420px;

background:rgba(37,99,235,.14);

border-radius:50%;

filter:blur(120px);

z-index:0;
pointer-events:none;
}

body::after{
content:"";
position:fixed;
bottom:-180px;
left:-120px;

width:420px;
height:420px;

background:rgba(124,58,237,.14);

border-radius:50%;

filter:blur(120px);

z-index:0;
pointer-events:none;
}

/* MAIN CONTAINER */

.container{
position:relative;
z-index:2;

max-width:950px;
margin:auto;

padding:35px;

border-radius:30px;

background:rgba(255,255,255,.06);

backdrop-filter:blur(20px);
-webkit-backdrop-filter:blur(20px);

border:1px solid rgba(255,255,255,.08);

box-shadow:
0 25px 60px rgba(0,0,0,.45),
inset 0 1px 1px rgba(255,255,255,.08);

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
rgba(255,255,255,.08),
transparent
);

pointer-events:none;
}

/* TITLE */

h2{
font-size:34px;
font-weight:800;

margin-bottom:12px;

background:linear-gradient(90deg,#fff,#60a5fa);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

/* TIMER */

.timer-box{
display:inline-block;

padding:12px 18px;

margin-bottom:22px;

border-radius:16px;

background:rgba(239,68,68,.15);

border:1px solid rgba(239,68,68,.25);

font-size:18px;
font-weight:700;

color:#fca5a5;

box-shadow:
0 10px 25px rgba(239,68,68,.18);
}

/* ATTEMPTS */

.attempts{
margin-bottom:25px;

font-size:15px;
font-weight:600;

color:#cbd5e1;
}

/* QUESTION CARD */

.question-card{
position:relative;

background:rgba(255,255,255,.06);

border:1px solid rgba(255,255,255,.08);

border-radius:22px;

padding:25px;

margin-bottom:25px;

backdrop-filter:blur(14px);

-webkit-backdrop-filter:blur(14px);

box-shadow:
0 15px 35px rgba(0,0,0,.25);

overflow:hidden;
}

/* FIXED GLOW LAYER */

.question-card::before{
content:"";

position:absolute;

top:0;
left:0;

width:100%;
height:100%;

background:linear-gradient(
180deg,
rgba(255,255,255,.06),
transparent
);

pointer-events:none;

z-index:0;
}

/* CONTENT ABOVE GLOW */

.question-card *{
position:relative;
z-index:2;
}

/* QUESTION TEXT */

.question-card strong{
display:block;

font-size:18px;
line-height:1.5;

margin-bottom:18px;

color:white;
}

/* ANSWER OPTIONS */

.answer-option{
display:flex;
align-items:center;
gap:12px;

padding:14px 16px;

margin-top:12px;

border-radius:14px;

background:rgba(255,255,255,.05);

border:1px solid rgba(255,255,255,.08);

cursor:pointer;

transition:
transform .3s ease,
background .3s ease,
border .3s ease;
}

.answer-option:hover{
background:rgba(59,130,246,.16);

border-color:rgba(96,165,250,.35);

transform:translateX(4px);
}

/* RADIO BUTTON */

.answer-option input[type="radio"]{
width:18px;
height:18px;

cursor:pointer;

accent-color:#3b82f6;

flex-shrink:0;

position:relative;
z-index:5;
}

/* SUBMIT BUTTON */

.submit-btn{
margin-top:15px;

padding:15px 28px;

border:none;
border-radius:16px;

cursor:pointer;

font-size:15px;
font-weight:700;

color:white;

background:
linear-gradient(
145deg,
#2563eb,
#1d4ed8
);

transition:
transform .35s ease,
box-shadow .35s ease;

box-shadow:
0 15px 30px rgba(37,99,235,.28);
}

.submit-btn:hover{
transform:translateY(-4px);

box-shadow:
0 20px 40px rgba(37,99,235,.38);
}

/* EMPTY MESSAGE */

.empty{
padding:20px;

border-radius:18px;

background:rgba(239,68,68,.12);

border:1px solid rgba(239,68,68,.25);

color:#fecaca;

font-weight:700;
}

/* MOBILE */

@media(max-width:768px){

.container{
padding:24px;
}

h2{
font-size:28px;
}

.question-card{
padding:20px;
}

.answer-option{
padding:12px 14px;
}

.submit-btn{
width:100%;
}

}
</style>
<body>





<?php if ($timeLimit > 0 && $deadline): ?>

<div style="font-size:22px;font-weight:bold;color:#e74c3c;margin-bottom:20px;">
Time Remaining: <span id="countdown">Loading...</span>
</div>

<script>

const deadline = <?= strtotime($deadline) ?> * 1000;

const timer = setInterval(function () {

    const now = new Date().getTime();
    const distance = deadline - now;

    if (distance <= 0) {
        clearInterval(timer);
        document.getElementById("countdown").innerHTML = "0m 0s";
        alert("Time is up! Submitting assessment.");
        document.getElementById("assessmentForm").submit();
        return;
    }

    const minutes = Math.floor(distance / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    document.getElementById("countdown").innerHTML =
        minutes + "m " + seconds + "s";

}, 1000);

</script>


<?php endif; ?>

<p>
Attempts Remaining: <?= $remaining ?> / <?= $totalAllowed ?>
</p>
<h2><?= htmlspecialchars($assessment['title']) ?></h2>

<form id="assessmentForm" method="POST" action="Submit.php">

<input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">

<?php if ($questions->rowCount() > 0): ?>

<?php 
$i = 1;
while ($q = $questions->fetch(PDO::FETCH_ASSOC)): 
?>

<div style="margin-bottom:20px;">
<strong><?= $i++ ?>. <?= htmlspecialchars($q['question']) ?></strong><br>

<?php
$answers = $pdo->prepare("
    SELECT * FROM options
    WHERE question_id = ?
");
$answers->execute([$q['id']]);

while ($a = $answers->fetch(PDO::FETCH_ASSOC)):
?>

<label>
    <input type="radio"
           name="q<?= $q['id'] ?>"
           value="<?= $a['id'] ?>"
           required>
    <?= htmlspecialchars($a['option_text']) ?>
</label><br>

<?php endwhile; ?>

</div>

<?php endwhile; ?>

<button type="submit">Submit Assessment</button>

<?php else: ?>

<p style="color:red;font-weight:bold;">
No questions found for this assessment.
</p>

<?php endif; ?>

</form>

</body>
</html>