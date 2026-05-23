<?php

require_once 'Auth.php';
require_once 'DB.php';

auth('facilitator');

$error = "";
$success = "";

// =========================
// GET IDS
// =========================
$courseId = $_GET['course_id'] ?? null;
$assessmentId = $_GET['assessment_id'] ?? null;

// =========================
// LOAD COURSE
// =========================
$course = null;
if ($courseId) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
}

// =========================
// UPDATE COURSE
// =========================
if (isset($_POST['update_course'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $content = $_POST['content'] ?? null;
    $videoPath = $course['video_url'];

    // Handle video upload
    if (!empty($_FILES['video_file']['name'])) {
        $uploadDir = "uploads/videos/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["video_file"]["name"]);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES["video_file"]["tmp_name"], $targetFile)) {
            $videoPath = $targetFile;
        }
    }

    // Handle video URL
    if (!empty($_POST['video_url'])) {
        $videoPath = $_POST['video_url'];
    }

    $stmt = $pdo->prepare("UPDATE courses SET title=?, description=?, content=?, video_url=? WHERE id=?");
    $stmt->execute([$title, $description, $content, $videoPath, $courseId]);

    $success = "Course updated successfully";
}

// =========================
// LOAD QUESTIONS
// =========================
$questions = [];

if ($assessmentId) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE assessment_id = ?");
    $stmt->execute([$assessmentId]);
    $questions = $stmt->fetchAll();
}

// =========================
// UPDATE QUESTION
// =========================
if (isset($_POST['update_question'])) {

    $qid = $_POST['question_id'];
    $text = trim($_POST['question']);
    $answers = $_POST['answers'] ?? [];
    $correct = $_POST['correct_answer_' . $qid] ?? null;

    // Update question
    $stmt = $pdo->prepare("UPDATE questions SET question=? WHERE id=?");
    $stmt->execute([$text, $qid]);

    // Update answers
    foreach ($answers as $optionId => $answerText) {

        $isCorrect = ($correct == $optionId) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE options 
            SET option_text = ?, is_correct = ?
            WHERE id = ?
        ");
        $stmt->execute([$answerText, $isCorrect, $optionId]);
    }

    $success = "Question and answers updated";
    header("Location: " . $_SERVER['REQUEST_URI']);
exit;
}

// =========================
// DELETE COURSE
// =========================
if (isset($_POST['delete_course'])) {

    $id = $_POST['delete_course'];

    $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);

    header("Location: Facilitator-dashboard.php");
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Course & Questions</title>
<style>
*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',Arial,sans-serif;
}

body{
min-height:100vh;
padding:35px;
overflow-x:hidden;
color:white;

background:
radial-gradient(circle at top right,#06b6d4 0%,transparent 24%),
radial-gradient(circle at bottom left,#8b5cf6 0%,transparent 28%),
linear-gradient(135deg,#0f172a,#111827,#1e293b);

position:relative;
}

/* BACKGROUND GLOW */

body::before{
content:"";
position:fixed;
top:-180px;
right:-120px;

width:500px;
height:500px;

background:rgba(6,182,212,.12);

border-radius:50%;

filter:blur(120px);

z-index:0;
}

body::after{
content:"";
position:fixed;
bottom:-180px;
left:-120px;

width:500px;
height:500px;

background:rgba(139,92,246,.12);

border-radius:50%;

filter:blur(120px);

z-index:0;
}

/* CONTAINER */

.container{
position:relative;
z-index:2;

max-width:1100px;
margin:auto;
}

/* PAGE TITLE */

h1{
font-size:40px;
font-weight:800;

margin-bottom:28px;

background:linear-gradient(90deg,#fff,#67e8f9);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

h2{
margin-bottom:20px;
font-size:24px;
font-weight:700;
color:white;
}

/* CARD */

.card{
background:rgba(255,255,255,.06);

border:1px solid rgba(255,255,255,.08);

backdrop-filter:blur(18px);
-webkit-backdrop-filter:blur(18px);

padding:28px;

border-radius:24px;

margin-bottom:28px;

box-shadow:
0 20px 45px rgba(0,0,0,.3);

position:relative;

overflow:hidden;
}

.card::before{
content:"";
position:absolute;
top:-40px;
right:-40px;

width:120px;
height:120px;

border-radius:50%;

background:rgba(255,255,255,.06);

filter:blur(20px);
}

/* INPUTS */

input,
textarea{
width:100%;

padding:14px 16px;

margin-top:8px;
margin-bottom:18px;

border:none;
outline:none;

border-radius:16px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.08);

color:white;

font-size:14px;

transition:
border .35s ease,
background .35s ease,
box-shadow .35s ease;
}

textarea{
min-height:120px;
resize:vertical;
}

input:focus,
textarea:focus{
border:1px solid rgba(6,182,212,.45);

background:rgba(255,255,255,.12);

box-shadow:
0 0 0 4px rgba(6,182,212,.12);
}

input::placeholder,
textarea::placeholder{
color:#cbd5e1;
}

/* LABELS */

label{
display:block;

margin-bottom:8px;

font-weight:600;

color:#f3f4f6;
}

/* BUTTONS */

button{
padding:12px 22px;

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

margin-top:8px;

box-shadow:
0 12px 24px rgba(0,0,0,.22);
}

button:hover{
transform:translateY(-4px);

box-shadow:
0 18px 35px rgba(6,182,212,.22);
}

/* DELETE BUTTON */

form button:last-child{
background:
linear-gradient(145deg,#ef4444,#dc2626);
}

form button:last-child:hover{
box-shadow:
0 18px 35px rgba(239,68,68,.25);
}

/* SUCCESS + ERROR */

.success,
.error{
padding:14px 18px;

border-radius:14px;

margin-bottom:20px;

font-weight:600;
}

.success{
background:rgba(34,197,94,.12);

border:1px solid rgba(34,197,94,.25);

color:#bbf7d0;
}

.error{
background:rgba(239,68,68,.12);

border:1px solid rgba(239,68,68,.25);

color:#fecaca;
}

/* QUESTION FORMS */

.question-box{
padding-bottom:20px;
margin-bottom:25px;

border-bottom:1px solid rgba(255,255,255,.08);
}

/* ANSWERS */

.answer-row{
display:flex;
align-items:center;
gap:12px;

margin-bottom:12px;
}

.answer-row input[type="text"]{
margin:0;
}

.answer-row label{
display:flex;
align-items:center;
gap:6px;

margin:0;

white-space:nowrap;
}

/* RADIO */

input[type="radio"]{
width:auto;
accent-color:#06b6d4;
}

/* VIDEO PREVIEW */

video{
width:100%;
max-width:500px;

border-radius:18px;

margin-top:12px;

box-shadow:
0 15px 35px rgba(0,0,0,.35);
}

/* MOBILE */

@media(max-width:768px){

body{
padding:18px;
}

h1{
font-size:30px;
}

.card{
padding:20px;
}

.answer-row{
flex-direction:column;
align-items:flex-start;
}

button{
width:100%;
}

}
</style>
</head>
<body>

<div class="container">

<h1>Edit Course & Questions</h1>

<?php if ($error): ?>
<p style="color:red;"><?= $error ?></p>
<?php endif; ?>

<?php if ($success): ?>
<p style="color:green;"><?= $success ?></p>
<?php endif; ?>

<!-- COURSE EDIT -->
<?php if ($course): ?>
<div class="card">
<h2>Edit Course</h2>

<form method="POST" enctype="multipart/form-data">

<input type="text" name="title" value="<?= htmlspecialchars($course['title']) ?>">

<textarea name="description"><?= htmlspecialchars($course['description']) ?></textarea>

<textarea name="content"><?= htmlspecialchars($course['content']) ?></textarea>

<input type="text" name="video_url" value="<?= htmlspecialchars($course['video_url']) ?>" placeholder="Video URL">

<input type="file" name="video_file">

<button name="update_course">Update Course</button>

</form>

<form method="POST" onsubmit="return confirm('Delete course?')">
<input type="hidden" name="delete_course" value="<?= $courseId ?>">
<button>Delete Course</button>
</form>

</div>
<?php endif; ?>

<!-- QUESTIONS -->
<?php if (!empty($questions)): ?>

<div class="card">
<h2>Edit Questions</h2>

<?php foreach ($questions as $q): ?>

<form method="POST" style="margin-bottom:20px; border-bottom:1px solid #ccc; padding-bottom:10px;">

<input type="hidden" name="question_id" value="<?= $q['id'] ?>">

<!-- QUESTION -->
<label>Question:</label>
<textarea name="question"><?= htmlspecialchars($q['question']) ?></textarea>

<?php
// ✅ LOAD ANSWERS
$stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ?");
$stmt->execute([$q['id']]);
$options = $stmt->fetchAll();
?>

<p>Answers:</p>

<?php foreach ($options as $opt): ?>

  <div style="margin-bottom:5px;">

    <input type="text"
           name="answers[<?= $opt['id'] ?>]"
           value="<?= htmlspecialchars($opt['option_text']) ?>">

    <label>
      <input type="radio"
             name="correct_answer_<?= $q['id'] ?>"
             value="<?= $opt['id'] ?>"
             <?= $opt['is_correct'] ? 'checked' : '' ?>>
      Correct
    </label>

  </div>

<?php endforeach; ?>

<br>

<button name="update_question">Update Question</button>

</form>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</body>
</html>
