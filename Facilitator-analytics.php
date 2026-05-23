<?php


session_start();
require_once 'Auth.php';
require_once 'DB.php';

auth('facilitator');


/* ===============================
   UNLOCK USER ACCOUNT
================================ */

if (isset($_POST['unlock_user_id'])) {

    $uid = (int)$_POST['unlock_user_id'];

    $pdo->prepare("
        UPDATE users
        SET 
            failed_attempts = 0,
            last_failed_login = NULL,
            account_locked_until = NULL
        WHERE id = ?
    ")->execute([$uid]);

    // Refresh page to show updated data
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


/* ===============================
   GRANT EXTRA ATTEMPTS
================================ */

if (isset($_POST['grant_attempts'])) {

    $studentId    = (int)($_POST['student_id'] ?? 0);
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);
    $extra        = (int)($_POST['extra_attempts'] ?? 0);

    if ($studentId > 0 && $assessmentId > 0 && $extra > 0) {

        // ✅ Add attempts + clear timer + reset usage
        $stmt = $pdo->prepare("
            INSERT INTO assessment_attempts
                (student_id, assessment_id, extra_attempts,
                 attempts_used, start_time, deadline)
            VALUES (?, ?, ?, 0, NULL, NULL)
            ON DUPLICATE KEY UPDATE
                extra_attempts = extra_attempts + VALUES(extra_attempts),
                attempts_used = 0,
                start_time = NULL,
                deadline = NULL
        ");


        


        $stmt->execute([$studentId, $assessmentId, $extra]);

        // ✅ OPTIONAL: allow retake even if results exist
        // Comment out if you want to keep history strict
        $pdo->prepare("
UPDATE assessment_attempts
SET
    start_time = NULL,
    deadline = NULL
WHERE student_id = ?
AND assessment_id = ?
")->execute([$studentId, $assessmentId]);

$success = "Attempts granted and test reopened.";
    }
}

/* ===============================
   RESET ATTEMPTS
================================ */

if (isset($_POST['reset_attempts'])) {

    $studentId    = (int)($_POST['student_id'] ?? 0);
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);

    if ($studentId > 0 && $assessmentId > 0) {

        // Reset usage + extra attempts + timer
        $stmt = $pdo->prepare("
            UPDATE assessment_attempts
            SET attempts_used = 0,
                extra_attempts = 0,
                start_time = NULL,
                deadline = NULL
            WHERE student_id = ?
              AND assessment_id = ?
        ");
        $stmt->execute([$studentId, $assessmentId]);

        // Optional: clear answers to allow full retake
        $pdo->prepare("
            DELETE FROM student_answers
            WHERE student_id = ?
              AND question_id IN (
                  SELECT id FROM questions
                  WHERE assessment_id = ?
              )
        ")->execute([$studentId, $assessmentId]);

        $success = "Attempts reset successfully.";
    }
}

/* ==============================
   OVERVIEW STATS
================================ */

$totalStudents = $pdo->query("
    SELECT COUNT(*) FROM users WHERE role='student'
")->fetchColumn();

$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

$totalAssessments = $pdo->query("SELECT COUNT(*) FROM assessments")->fetchColumn();

/* Total attempts taken */
$totalAttempts = $pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();

/* Pass rate */
$passRate = $pdo->query("
    SELECT ROUND(
        (SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100
    ,1)
    FROM results
")->fetchColumn();

/* ==============================
   TOP STUDENTS
================================ */

$topStudents = $pdo->query("
    SELECT 
        u.first_name,
        u.last_name,
        u.student_id,
        AVG(r.score) AS avg_score,
        COUNT(r.id) AS attempts
    FROM results r
    JOIN users u ON r.student_id = u.id
    GROUP BY r.student_id
    ORDER BY avg_score DESC
    LIMIT 5
")->fetchAll();

/* ==============================
   LOWEST STUDENTS
================================ */

$lowStudents = $pdo->query("
    SELECT 
        u.first_name,
        u.last_name,
        u.student_id,
        AVG(r.score) AS avg_score,
        COUNT(r.id) AS attempts
    FROM results r
    JOIN users u ON r.student_id = u.id
    GROUP BY r.student_id
    ORDER BY avg_score ASC
    LIMIT 5
")->fetchAll();

/* ==============================
   RECENT ACTIVITY (NOW WITH ATTEMPTS)
================================ */

$recent = $pdo->query("
    SELECT 
        u.first_name,
        u.last_name,
        a.title,
        r.score,
        r.passed,
        r.attempt_no,
        r.completed_at
    FROM results r
    JOIN users u ON r.student_id = u.id
    JOIN assessments a ON r.assessment_id = a.id
    ORDER BY r.completed_at DESC
    LIMIT 10
")->fetchAll();

/* ==============================
   FULL RESULTS + ATTEMPT DATA
================================ */

$results = $pdo->query("
    SELECT 
        u.first_name,
        u.last_name,
        u.student_id,
        a.title AS assessment,
        r.score,
        r.passed,
        r.attempt_no,
        a.max_attempts,
        COALESCE(at.extra_attempts,0) AS extra_attempts,
        COALESCE(at.attempts_used,0) AS attempts_used,
        r.completed_at
    FROM results r
    JOIN users u ON r.student_id = u.id
    JOIN assessments a ON r.assessment_id = a.id
    LEFT JOIN assessment_attempts at 
        ON at.student_id = r.student_id 
        AND at.assessment_id = r.assessment_id
    ORDER BY r.completed_at DESC
")->fetchAll();

$students = $pdo->query("
    SELECT id, first_name, last_name, student_id
    FROM users
    WHERE role='student'
    ORDER BY first_name
")->fetchAll();

$assessmentsList = $pdo->query("
    SELECT id, title
    FROM assessments
    ORDER BY title
")->fetchAll();


/* ==============================
   Account Security / Login Attempts
================================ */
$loginSecurity = $pdo->query("
    SELECT 
        id,
        first_name,
        last_name,
        student_id,
        email,
        failed_attempts,
        last_failed_login,
        account_locked_until,
        last_login,
        is_locked,
        DATEDIFF(NOW(), last_login) AS days_inactive,
        CASE
            WHEN account_locked_until IS NOT NULL 
                 AND account_locked_until > NOW() THEN 'LOCKED'
            WHEN failed_attempts > 0 THEN 'WARNING'
            ELSE 'OK'
        END AS status
    FROM users
    WHERE role = 'student'
    ORDER BY failed_attempts DESC, account_locked_until DESC
")->fetchAll();


?>



<!DOCTYPE html>
<html>
<head>
<title>Facilitator Analytics</title>



<style>
*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',Arial,sans-serif;
}

body{
min-height:100vh;
padding:30px;
color:white;
overflow-x:hidden;

background:
radial-gradient(circle at top right,#06b6d4 0%,transparent 25%),
radial-gradient(circle at bottom left,#8b5cf6 0%,transparent 30%),
linear-gradient(135deg,#0f172a,#111827,#1e293b);

position:relative;
}

/* BACKGROUND GLOW */

body::before{
content:"";
position:fixed;
top:-200px;
right:-120px;

width:500px;
height:500px;

border-radius:50%;

background:rgba(6,182,212,.12);

filter:blur(120px);

z-index:0;
}

body::after{
content:"";
position:fixed;
bottom:-200px;
left:-120px;

width:500px;
height:500px;

border-radius:50%;

background:rgba(139,92,246,.12);

filter:blur(120px);

z-index:0;
}

/* MAIN WRAPPER */

.section{
position:relative;
z-index:2;

background:rgba(255,255,255,.06);

border:1px solid rgba(255,255,255,.08);

backdrop-filter:blur(18px);
-webkit-backdrop-filter:blur(18px);

border-radius:24px;

padding:25px;

margin-bottom:30px;

box-shadow:
0 20px 45px rgba(0,0,0,.3);
}

/* PAGE TITLE */

h1{
font-size:40px;
font-weight:800;

margin-bottom:25px;

background:linear-gradient(90deg,#fff,#67e8f9);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

h2{
margin-bottom:20px;
font-size:24px;
font-weight:700;
color:#fff;
}

/* DASHBOARD BUTTON */

.btn.dashboard{
display:inline-flex;
align-items:center;
justify-content:center;

padding:10px 18px;

margin-bottom:20px;

border-radius:14px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.08);

color:white;

text-decoration:none;
font-weight:700;

transition:
transform .4s ease,
background .4s ease,
box-shadow .4s ease;
}

.btn.dashboard:hover{
transform:translateY(-3px);

background:rgba(6,182,212,.18);

box-shadow:
0 12px 24px rgba(6,182,212,.22);
}

/* OVERVIEW CARDS */

.cards{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
gap:20px;
margin-bottom:30px;
}

.card{
position:relative;

padding:28px;

border-radius:22px;

background:rgba(255,255,255,.05);

border:1px solid rgba(255,255,255,.08);

overflow:hidden;

box-shadow:
0 15px 35px rgba(0,0,0,.25);

transition:
transform .4s ease,
box-shadow .4s ease;
}

.card::before{
content:"";
position:absolute;
top:-30px;
right:-30px;

width:100px;
height:100px;

border-radius:50%;

background:rgba(255,255,255,.08);

filter:blur(20px);
}

.card:hover{
transform:translateY(-6px);

box-shadow:
0 25px 45px rgba(0,0,0,.35),
0 0 25px rgba(6,182,212,.12);
}

.card h2{
font-size:38px;

margin-bottom:10px;

background:linear-gradient(145deg,#67e8f9,#06b6d4);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

.card{
text-align:center;
font-weight:600;
color:#d1d5db;
}

/* TABLE */

table{
width:100%;
border-collapse:collapse;
margin-top:15px;

overflow:hidden;
border-radius:16px;
}

th{
background:rgba(6,182,212,.22);

padding:14px;

text-align:left;

font-size:14px;
font-weight:700;

color:white;
}

td{
padding:14px;

border-bottom:1px solid rgba(255,255,255,.08);

font-size:14px;

color:#e5e7eb;
}

tr:hover{
background:rgba(255,255,255,.04);
}

/* STATUS */

.pass{
color:#22c55e;
font-weight:700;
}

.fail{
color:#ef4444;
font-weight:700;
}

/* FORMS */

form{
margin-top:10px;
}

label{
display:block;
margin-bottom:8px;
font-weight:600;
color:#f3f4f6;
}

select,
input{
width:100%;

padding:12px 14px;

border:none;
outline:none;

border-radius:14px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.08);

color:white;

margin-bottom:18px;

font-size:14px;
}

select option{
background:#111827;
color:white;
}

input::placeholder{
color:#cbd5e1;
}

/* BUTTONS */

button{
padding:12px 20px;

border:none;
border-radius:14px;

cursor:pointer;

font-size:14px;
font-weight:700;

color:white;

background:
linear-gradient(145deg,#06b6d4,#0891b2);

transition:
transform .4s ease,
box-shadow .4s ease,
background .4s ease;

margin-right:10px;

box-shadow:
0 12px 24px rgba(0,0,0,.22);
}

button:hover{
transform:translateY(-4px);

box-shadow:
0 18px 35px rgba(6,182,212,.22);
}

/* RESET BUTTON */

button[name="reset_attempts"]{
background:
linear-gradient(145deg,#ef4444,#dc2626);
}

button[name="reset_attempts"]:hover{
box-shadow:
0 18px 35px rgba(239,68,68,.22);
}

/* SUCCESS MESSAGE */

.success{
padding:14px 18px;

margin-bottom:20px;

border-radius:14px;

background:rgba(34,197,94,.12);

border:1px solid rgba(34,197,94,.2);

color:#bbf7d0;

font-weight:600;
}

/* MOBILE */

@media(max-width:768px){

body{
padding:18px;
}

h1{
font-size:30px;
}

.section{
padding:18px;
overflow-x:auto;
}

.cards{
grid-template-columns:1fr;
}

table{
min-width:700px;
}

button{
width:100%;
margin-bottom:10px;
}

}
</style>

</head>
<body>

<a href="Facilitator-dashboard.php" class="btn dashboard">
Back to Dashboard
</a>
<h1>Facilitator Analytics Dashboard</h1>

<!-- ======================
     OVERVIEW CARDS
====================== -->

<div class="cards">

<div class="card">
    <h2><?= $totalStudents ?></h2>
    Students
</div>

<div class="card">
    <h2><?= $totalCourses ?></h2>
    Courses
</div>

<div class="card">
    <h2><?= $totalAssessments ?></h2>
    Assessments
</div>

<div class="card">
    <h2><?= $totalAttempts ?></h2>
    Attempts
</div>

<div class="card">
    <h2><?= $passRate ?>%</h2>
    Pass Rate
</div>

</div>

<!-- ======================
     TOP STUDENTS
====================== -->

<div class="section">
<h2>Top Performing Students</h2>

<?php foreach ($recent as $r): ?>
<p>
<?= htmlspecialchars($r['first_name']) ?> <?= htmlspecialchars($r['last_name']) ?>
completed <strong><?= htmlspecialchars($r['title']) ?></strong>
— Attempt <?= (int)$r['attempt_no'] ?>
— <?= round($r['score']) ?>%
(<?= $r['passed'] ? 'Passed' : 'Failed' ?>)
</p>
<?php endforeach; ?>

</div>

<!-- ======================
     LOWEST STUDENTS
====================== -->

<div class="section">
<h2>Students Needing Attention</h2>

<?php foreach ($lowStudents as $s): ?>
<p>
<?= $s['first_name'] ?> <?= $s['last_name'] ?>
(<?= $s['student_id'] ?>)
— <?= round($s['avg_score']) ?>%
</p>
<?php endforeach; ?>

</div>

<!-- ======================
     RECENT ACTIVITY
====================== -->

<div class="section">
<h2>Recent Activity</h2>

<?php foreach ($recent as $r): ?>
<p>
<?= $r['first_name'] ?> <?= $r['last_name'] ?>
completed <strong><?= $r['title'] ?></strong>
— <?= round($r['score']) ?>%
(<?= $r['passed'] ? 'Passed' : 'Failed' ?>)
</p>
<?php endforeach; ?>

</div> <!-- ✅ CLOSE -->

<div class="section">
<h2>Manual Attempt Management</h2>

<form method="POST">

<label>Student:</label><br>
<select name="student_id" required>
<option value="">Select Student</option>
<?php foreach ($students as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['first_name'] ?> <?= $s['last_name'] ?>
(<?= $s['student_id'] ?>)
</option>
<?php endforeach; ?>
</select>

<br><br>

<label>Assessment:</label><br>
<select name="assessment_id" required>
<option value="">Select Assessment</option>
<?php foreach ($assessmentsList as $a): ?>
<option value="<?= $a['id'] ?>">
<?= htmlspecialchars($a['title']) ?>
</option>
<?php endforeach; ?>
</select>

<br><br>

<label>Extra Attempts to Grant:</label><br>
<input type="number" name="extra_attempts" min="1" value="1" required>

<br><br>

<button name="grant_attempts">Grant Attempts</button>
<button name="reset_attempts" style="background:#e74c3c;color:white;">
Reset Attempts
</button>

</form>

</div>

</div>

<!-- ======================
     FULL RESULTS TABLE
====================== -->

<div class="section">
<h2>All Assessment Results</h2>

<table>
<thead>
<tr>
<th>Student</th>
<th>Student ID</th>
<th>Assessment</th>
<th>Attempt</th>
<th>Score</th>
<th>Status</th>
<th>Used</th>
<th>Allowed</th>
<th>Date</th>
</tr>
</thead>

<tbody>

<?php foreach ($results as $r): 
$totalAllowed = $r['max_attempts'] + $r['extra_attempts'];
?>

<tr>
<td><?= $r['first_name'].' '.$r['last_name'] ?></td>
<td><?= $r['student_id'] ?></td>
<td><?= $r['assessment'] ?></td>
<td>#<?= $r['attempt_no'] ?></td>
<td><?= round($r['score']) ?>%</td>

<td class="<?= $r['passed'] ? 'pass' : 'fail' ?>">
<?= $r['passed'] ? 'PASSED' : 'FAILED' ?>
</td>

<td><?= $r['attempts_used'] ?></td>
<td><?= $totalAllowed ?></td>
<td><?= date('M d, Y H:i', strtotime($r['completed_at'])) ?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

</div> <!-- ✅ CLOSE RESULTS SECTION -->


<!-- ======================
     LOGIN SECURITY
====================== -->

<div class="section">
<h2>Login Security / Failed Attempts</h2>

<table>

<thead>
<tr>
    <th>Name</th>
    <th>Student ID</th>
    <th>Email</th>
    <th>Failed Attempts</th>
    <th>Last Failed Login</th>
    <th>Locked Until</th>
    <th>Status</th>
    <th>Last Login</th>
    <th>Inactive</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach ($loginSecurity as $u): ?>
<tr>

    <td><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
    <td><?= htmlspecialchars($u['student_id']) ?></td>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td><?= $u['failed_attempts'] ?></td>
    <td><?= $u['last_failed_login'] ?? '—' ?></td>
    <td><?= $u['account_locked_until'] ?? '—' ?></td>

    <!-- STATUS -->
    <td>
        <?php
            if ($u['status'] === 'LOCKED') {
                echo "🔒 LOCKED";
            } elseif ($u['status'] === 'WARNING') {
                echo "⚠️ Attempts Failed";
            } else {
                echo "✅ OK";
            }
        ?>
    </td>

    <!-- LAST LOGIN -->
    <td>
        <?php
        if (!$u['last_login']) {
            echo "Never";
        } else {
            echo date('M d, Y H:i', strtotime($u['last_login']));
        }
        ?>
    </td>

    <!-- INACTIVITY -->
    <td>
        <?php
        if (!$u['last_login']) {
            echo "—";
        } elseif ($u['days_inactive'] == 0) {
            echo "Today";
        } elseif ($u['days_inactive'] == 1) {
            echo "1 day";
        } else {
            echo $u['days_inactive'] . " days";
        }

        if ($u['days_inactive'] >= 30) {
            echo " <span style='color:red;'>⚠ Inactive 30+ days</span>";
        }
        ?>
    </td>

    <!-- UNLOCK BUTTON -->
    <td>
        <form method="post">
            <input type="hidden" name="unlock_user_id" value="<?= $u['id'] ?>">
            <button type="submit">Unlock</button>
        </form>
    </td>

</tr>
<?php endforeach; ?>

</tbody>
</table>

</div> <!-- ✅ CLOSE LOGIN SECURITY SECTION -->



</body>
</html>