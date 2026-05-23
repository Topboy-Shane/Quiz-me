<?php

require_once 'Auth.php';
require_once 'DB.php';

auth('facilitator');

$error = "";
$success = "";


if (isset($_POST['delete_question'])) {

    $id = $_POST['delete_question'];

    $pdo->beginTransaction();

    try {
        $pdo->prepare("DELETE FROM options WHERE question_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$id]);

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Delete failed: " . $e->getMessage());
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ==============================
   CREATE COURSE + VIDEO UPLOAD
================================ */
if (isset($_POST['create_course'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $contentType = $_POST['content_type'];
    $content = $_POST['content'] ?? null;
    $video = $_POST['video_url'] ?? null;

    if (!$title) {
        $error = "Course title required.";
    } else {


$videoPath = null;
$error = null;
$success = null;

// Check if user tried to upload a file
if (!empty($_FILES['video_file']['name'])) {

    if ($_FILES['video_file']['error'] === 0) {

        $uploadDir = "uploads/videos/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["video_file"]["name"]);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES["video_file"]["tmp_name"], $targetFile)) {
            $videoPath = $targetFile;
            $success = "Video uploaded successfully!";
        } else {
            $error = "Video upload failed (move failed).";
        }

    } else {
        $error = "Upload error code: " . $_FILES['video_file']['error'];
    }

} 
// If no file uploaded, check for URL
elseif (!empty($_POST['video_url'])) {
    $videoPath = $_POST['video_url'];
    $success = "Video URL added successfully!";
}

        $stmt = $pdo->prepare("
            INSERT INTO courses 
            (title, description, content_type, content, video_url, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $title,
            $description,
            $contentType,
            $content,
            $videoPath,
            $_SESSION['user_id']
        ]);

        $success = "Course created successfully.";
    }
}

/* ==============================
   CREATE QUIZ (ASSESSMENT)
================================ */

if (isset($_POST['create_quiz'])) {

    $courseId = $_POST['course_id'];
    $quizTitle = trim($_POST['quiz_title']);
    $time = $_POST['time_limit'] ?: null;

    if (!$courseId || !$quizTitle) {
        $error = "Select course and enter quiz title.";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO assessments (course_id, title, time_limit)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$courseId, $quizTitle, $time]);

        $success = "Quiz added to course.";
    }
}

/* ==============================
   ASSIGN COURSE TO STUDENT
================================ */
if (isset($_POST['assign_course'])) {

    $courseId = $_POST['course_id'] ?? null;
    $userId   = $_POST['user_id'] ?? null;

    if (!$courseId || !$userId) {
        $error = "Select course and student.";
    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO course_assignments 
                (course_id, user_id, assigned_at, status)
                VALUES (?, ?, NOW(), 'assigned')
            ");

            $stmt->execute([$courseId, $userId]);

            $success = "Course assigned successfully.";

        } catch (PDOException $e) {

            // 🔥 Handle duplicate assignment nicely
            if ($e->getCode() == 23000) {
                $error = "This course is already assigned to this user.";
            } else {
                error_log($e->getMessage()); // log real issue
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
/* ==============================
   ADD QUESTION + ANSWERS
================================ */
if (isset($_POST['add_question'])) {

    $assessmentId = $_POST['assessment_id'];
    $questionText = trim($_POST['question_text']);

    $answers = [
        $_POST['answer1'],
        $_POST['answer2'],
        $_POST['answer3'],
        $_POST['answer4']
    ];

    $correct = $_POST['correct_answer'];

    if (!$assessmentId || !$questionText) {
        $error = "Select assessment and enter question.";
    } else {

        // ✅ Insert question (DO NOT insert ID)
        $stmt = $pdo->prepare("
    INSERT INTO questions (assessment_id, question)
    VALUES (?, ?)
");

        $stmt->execute([$assessmentId, $questionText]);

        $questionId = $pdo->lastInsertId();

        // ✅ Insert answers linked to question ID
        foreach ($answers as $index => $answer) {

            if (!$answer) continue;

            $isCorrect = ($correct == $index + 1) ? 1 : 0;

            
                $stmt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
            

            $stmt->execute([$questionId, $answer, $isCorrect]);
        }

        $_SESSION['success'] = "Question added successfully.";

header("Location: " . $_SERVER['REQUEST_URI']);
exit;
    }
}
/* LOAD DATA */
$stmt = $pdo->query("
    SELECT *
    FROM courses
    ORDER BY id DESC
");

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agents = $pdo->query("
    SELECT id, first_name, last_name
    FROM users
    WHERE role='student'
")->fetchAll();

/* =========================
   FULL ASSESSMENTS LIST
========================= */
$allAssessments = $pdo->query("
    SELECT assessments.id, assessments.title, courses.title AS course
    FROM assessments
    JOIN courses ON courses.id = assessments.course_id
")->fetchAll();

/* =========================
   FILTERED ASSESSMENTS
========================= */
$courseId = $_GET['course_id'] ?? null;
$assessmentId = $_GET['assessment_id'] ?? null;

$assessments = [];

// ✅ If course selected → show its quizzes
if (!empty($courseId)) {
    $stmt = $pdo->prepare("
        SELECT id, title 
        FROM assessments
        WHERE course_id = ?
    ");
    $stmt->execute([$courseId]);
    $assessments = $stmt->fetchAll();

} else {
    // ✅ No course selected → show ALL quizzes
    $assessments = $pdo->query("
        SELECT id, title FROM assessments
    ")->fetchAll();
}



/* =========================
   QUESTIONS
========================= */
$assessmentId = $_GET['assessment_id'] ?? null;

$questions = [];

if (!empty($assessmentId)) {
    $stmt = $pdo->prepare("
        SELECT 
            q.id,
            q.question,
            a.id AS assessment_id,
            a.title AS quiz_title,
            c.id AS course_id,
            c.title AS course_title,
            c.video_url,
            c.content
        FROM questions q
        JOIN assessments a ON q.assessment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ?
        ORDER BY q.id DESC
    ");

    $stmt->execute([$assessmentId]);
    $questions = $stmt->fetchAll();
}
?>
<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',Arial,sans-serif;
}

body{
background:
radial-gradient(circle at top right,#f59e0b 0%,transparent 20%),
radial-gradient(circle at bottom left,#7c3aed 0%,transparent 26%),
linear-gradient(135deg,#0f172a,#111827,#1e293b);

color:white;
padding:40px 20px;
min-height:100vh;
overflow-x:hidden;
}

/* PAGE TITLE */

h1{
font-size:42px;
margin-bottom:25px;
font-weight:800;

background:linear-gradient(90deg,#fff,#facc15);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

/* HEADINGS */

h2{
margin:35px 0 18px;
font-size:28px;
font-weight:700;
color:#f8fafc;
}

h3{
margin:20px 0 10px;
color:#cbd5e1;
}

/* GLASS PANEL */

form,
.question-box,
.info-box{
background:rgba(255,255,255,.05);

border:1px solid rgba(255,255,255,.08);

backdrop-filter:blur(18px);
-webkit-backdrop-filter:blur(18px);

border-radius:24px;

padding:28px;
margin-bottom:30px;

box-shadow:
0 20px 40px rgba(0,0,0,.35),
inset 0 1px 1px rgba(255,255,255,.06);
}

/* INPUTS */

input,
textarea,
select{
width:100%;

padding:14px 16px;
margin-top:12px;
margin-bottom:16px;

border:none;
outline:none;

border-radius:16px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.08);

color:white;
font-size:15px;

transition:.35s;
}

input::placeholder,
textarea::placeholder{
color:#cbd5e1;
}

textarea{
min-height:130px;
resize:vertical;
}

input:focus,
textarea:focus,
select:focus{
border:1px solid rgba(245,158,11,.5);

background:rgba(255,255,255,.12);

box-shadow:
0 0 0 4px rgba(245,158,11,.12);
}

/* SELECT */

select option{
background:#111827;
color:white;
}

/* BUTTONS */

button,
.btn{
display:inline-flex;
align-items:center;
justify-content:center;

padding:14px 26px;

border:none;
border-radius:16px;

cursor:pointer;

font-size:15px;
font-weight:700;

color:white;
text-decoration:none;

transition:
transform .45s ease,
box-shadow .45s ease,
background .45s ease;

position:relative;
overflow:hidden;

margin-top:12px;
margin-right:12px;

box-shadow:
0 12px 25px rgba(0,0,0,.25);
}

/* SHINE EFFECT */

button::before,
.btn::before{
content:"";
position:absolute;
top:0;
left:-50%;

width:50%;
height:100%;

background:linear-gradient(
90deg,
transparent,
rgba(255,255,255,.25),
transparent
);

transform:skewX(-25deg);

transition:.8s;
}

button:hover::before,
.btn:hover::before{
left:130%;
}

/* BUTTON HOVER */

button:hover,
.btn:hover{
transform:translateY(-4px);

box-shadow:
0 18px 35px rgba(0,0,0,.35);
}

/* PRIMARY BUTTON */

button,
.btn{
background:
linear-gradient(
145deg,
#f59e0b,
#d97706
);
}

/* DASHBOARD BUTTON */

.dashboard{
background:
linear-gradient(
145deg,
#8b5cf6,
#6d28d9
);
}

/* SUCCESS + ERROR */

.success{
padding:14px 18px;
border-radius:14px;

background:rgba(34,197,94,.12);
border:1px solid rgba(34,197,94,.25);

color:#bbf7d0;
font-weight:600;

margin-bottom:20px;
}

.error{
padding:14px 18px;
border-radius:14px;

background:rgba(239,68,68,.12);
border:1px solid rgba(239,68,68,.25);

color:#fecaca;
font-weight:600;

margin-bottom:20px;
}

/* QUESTION DISPLAY */

.question-card{
background:rgba(255,255,255,.04);

border:1px solid rgba(255,255,255,.08);

border-radius:20px;

padding:22px;
margin-bottom:20px;

box-shadow:
0 10px 25px rgba(0,0,0,.25);
}

.question-card strong{
display:block;
font-size:18px;
margin-bottom:15px;
color:#fff;
}

.option{
padding:10px 14px;
margin-bottom:10px;

border-radius:12px;

background:rgba(255,255,255,.05);

color:#e2e8f0;
}

.option.correct{
border:1px solid rgba(34,197,94,.4);

background:rgba(34,197,94,.12);

color:#bbf7d0;
}

/* VIDEO */

video{
margin-top:20px;
border-radius:18px;
width:100%;
max-width:500px;

box-shadow:
0 15px 35px rgba(0,0,0,.35);
}

/* HR */

hr{
border:none;
height:1px;

background:rgba(255,255,255,.08);

margin:40px 0;
}

/* LABELS */

label{
display:inline-flex;
align-items:center;
gap:8px;

margin-right:18px;
margin-top:8px;

color:#e2e8f0;
}

/* FILE INPUT */

input[type="file"]{
padding:12px;
background:rgba(255,255,255,.04);
}

/* MOBILE */

@media(max-width:768px){

body{
padding:25px 15px;
}

h1{
font-size:34px;
}

h2{
font-size:24px;
}

form,
.question-box,
.info-box{
padding:22px;
}

button,
.btn{
width:100%;
margin-right:0;
}

label{
display:flex;
margin-bottom:10px;
}

}

</style>
<h1>Facilitator Control Panel</h1>

<a href="Facilitator-dashboard.php" class="btn dashboard">
  Back to Dashboard
</a>

<?php if (!empty($error)): ?>
    <div style="color:red; font-weight:bold;">
        <?= $error ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div style="color:green; font-weight:bold;">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>
  <p style="color:green"><?= $_SESSION['success'] ?></p>
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<hr>

<!-- =====================
     CREATE COURSE
===================== -->

<h2>Create New Course</h2>

<form method="POST" enctype="multipart/form-data">
  <input type="text" name="title" placeholder="Course Title" required>

  <textarea name="description" placeholder="Short Description"></textarea>
  

  <select name="content_type" required>
    <option value="text">Text Only</option>
    <option value="video">Video Only</option>
    <option value="mixed">Text + Video</option>
  </select>

  <textarea name="content" placeholder="Course Content (for reading)"></textarea>

<input type="text" name="video_url" placeholder="Video URL (optional)">

<p>OR Upload Video:</p>
<input type="file" name="video_file" accept="video/*">

<button name="create_course">Add Course</button>


</form>

<hr>

<!-- =====================
     CREATE QUIZ
===================== -->
<h2>Add Quiz to Course</h2>

<form method="POST">

  <select name="course_id" required>
    <option value="">Select Course</option>

    <?php foreach ($courses as $course): ?>
      <option value="<?= $course['id'] ?>">
        <?= htmlspecialchars($course['title'] ?: $course['course_name'] ?: 'Untitled Course') ?>
      </option>
    <?php endforeach; ?>

  </select>

  <input type="text" name="quiz_title" placeholder="Quiz Title" required>
  <input type="number" name="time_limit" placeholder="Time limit (minutes)">

  <button name="create_quiz">Add Quiz</button>
</form>

<hr>

<!-- =====================
     ASSIGN COURSE
===================== -->
<h2>Assign Course to Student</h2>

<form method="POST">

  <select name="course_id" required>
    <option value="">Select Course</option>

    <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>">
        <?= htmlspecialchars($c['title'] ?: $c['course_name'] ?: 'Untitled Course') ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="user_id" required>
    <option value="">Select Student</option>

    <?php foreach ($agents as $agent): ?>
      <option value="<?= $agent['id'] ?>">
        <?= $agent['first_name'] . " " . $agent['last_name'] ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button type="submit" name="assign_course">
    Assign Course
  </button>

</form>

<hr>



<!-- =====================
     QUESTION BUILDER
===================== -->

<h2>Add Question to Quiz</h2>

<form method="POST">

  <select name="assessment_id" required>
    <option value="">Select Quiz</option>

    <?php foreach ($allAssessments as $a): ?>
  <option value="<?= $a['id'] ?>">
    <?= htmlspecialchars($a['course'] . ' - ' . $a['title']) ?>
  </option>
<?php endforeach; ?>

  </select>

  <textarea name="question_text" placeholder="Enter question" required></textarea>

  <input type="text" name="answer1" placeholder="Answer 1" required>
  <input type="text" name="answer2" placeholder="Answer 2" required>
  <input type="text" name="answer3" placeholder="Answer 3">
  <input type="text" name="answer4" placeholder="Answer 4">

  <p>Select Correct Answer:</p>

  <label><input type="radio" name="correct_answer" value="1" required> Answer 1</label>
  <label><input type="radio" name="correct_answer" value="2"> Answer 2</label>
  <label><input type="radio" name="correct_answer" value="3"> Answer 3</label>
  <label><input type="radio" name="correct_answer" value="4"> Answer 4</label>

  <br><br>

  <button type="submit" name="add_question">
    Add Question
  </button>

</form>

<hr>


<!-- =====================
     FILTER (COURSE → QUIZ)
===================== -->

<h2>View Questions by Course</h2>

<form method="GET">

  <!-- COURSE -->
  <select name="course_id">
    <option value="">Select Course</option>

    <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>"
        <?= (($_GET['course_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['title'] ?: $c['course_name'] ?: 'Untitled Course') ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- QUIZ -->
  <select name="assessment_id">
    <option value="">Select Quiz</option>

    <?php foreach ($assessments as $a): ?>
      <option value="<?= $a['id'] ?>"
        <?= (($_GET['assessment_id'] ?? '') == $a['id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($a['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button type="submit">View Questions</button>

</form>

<hr>
<hr>

<!-- COURSE + QUIZ INFO -->
<?php if (!empty($questions)): ?>

  <h2>
    Course: <?= htmlspecialchars($questions[0]['course_title']) ?>
  </h2>

  <h3>
    Quiz: <?= htmlspecialchars($questions[0]['quiz_title']) ?>
  </h3>

  <!-- ✅ MOVED WORKING EDIT BUTTON TO TOP -->
    <button onclick="window.location.href='Edit course-question.php?course_id=<?= $questions[0]['course_id'] ?>&assessment_id=<?= $questions[0]['assessment_id'] ?>'">
      Edit Course
    </button>

  <form method="POST" style="display:inline;">
    <input type="hidden" name="delete_course" value="<?= $questions[0]['course_id'] ?>">
    <button onclick="return confirm('Delete this course?')">
      Delete Course
    </button>
  </form>

  <br><br>

  <!-- VIDEO DISPLAY -->
  <?php if (!empty($questions[0]['video_url'])): ?>
    <video width="400" controls>
      <source src="<?= $questions[0]['video_url'] ?>" type="video/mp4">
    </video>
  <?php endif; ?>

  <hr>

<?php endif; ?>
<!-- =====================
     DISPLAY QUESTIONS
===================== -->
<h2>Manage Questions</h2>

<?php if (empty($_GET['assessment_id'])): ?>

  <p>Select a course and quiz to view questions.</p>

<?php else: ?>

  <?php if (!empty($questions)): ?>

    

    <br><br>

    <?php foreach ($questions as $q): ?>

      <div style="border:1px solid #ccc; margin:10px; padding:10px;">

        <strong><?= htmlspecialchars($q['question']) ?></strong>

        <?php
        $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ?");
        $stmt->execute([$q['id']]);
        $opts = $stmt->fetchAll();
        ?>

        <?php foreach ($opts as $opt): ?>
          <div>
            <?= htmlspecialchars($opt['option_text']) ?>
            <?= $opt['is_correct'] ? '✅' : '' ?>
          </div>
        <?php endforeach; ?>

        <br>

        <!-- ❌ REMOVED EDIT BUTTON HERE -->

        <form method="POST" style="display:inline;">
          <input type="hidden" name="delete_question" value="<?= $q['id'] ?>">
          <button onclick="return confirm('Delete this question?')">
            Delete
          </button>
        </form>

      </div>

    <?php endforeach; ?>

  <?php else: ?>

    <p>No questions for this quiz yet.</p>

  <?php endif; ?>

<?php endif; ?>