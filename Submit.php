<?php

session_start();
require_once 'Auth.php';
require_once 'DB.php';

auth('student');

/* ===============================
   GET REQUIRED DATA FIRST
================================ */

$assessmentId = $_POST['assessment_id'] ?? null;
$studentId = $_SESSION['user_id'] ?? null;

if (!$assessmentId || !$studentId) {
    die("Invalid submission");
}

/* ===============================
   TIME LIMIT CHECK
================================ */

$stmt = $pdo->prepare("
    SELECT deadline
    FROM assessment_attempts
    WHERE student_id = ? AND assessment_id = ?
");

$stmt->execute([$studentId, $assessmentId]);
$deadline = $stmt->fetchColumn();

if ($deadline && strtotime($deadline) < time()) {
    die("Time expired. Assessment auto-closed.");
}

/* ===============================
   CALCULATE SCORE
================================ */

$total = 0;
$correct = 0;

/* Get all questions */
$qStmt = $pdo->prepare("
    SELECT id FROM questions WHERE assessment_id = ?
");
$qStmt->execute([$assessmentId]);

while ($q = $qStmt->fetch(PDO::FETCH_ASSOC)) {

    $total++;

    $selectedAnswer = $_POST['q' . $q['id']] ?? null;

    if (!$selectedAnswer) continue;

    /* Check if selected option is correct */
    $aStmt = $pdo->prepare("
        SELECT is_correct
        FROM options
        WHERE id = ?
    ");
    $aStmt->execute([$selectedAnswer]);

    if ($aStmt->fetchColumn()) {
        $correct++;
    }
}

/* Prevent division by zero */
if ($total == 0) {
    die("No questions found for this assessment.");
}

/* Score percentage */
$score = ($correct / $total) * 100;
$passed = $score >= 85 ? 1 : 0;

/* ===============================
   SAVE RESULT
================================ */

$stmt = $pdo->prepare("
    INSERT INTO results
    (student_id, assessment_id, score, passed, completed_at)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([$studentId, $assessmentId, $score, $passed]);

/* ===============================
   UPDATE ATTEMPT COUNT
================================ */

$pdo->prepare("
    UPDATE assessment_attempts
    SET attempts_used = attempts_used + 1,
        start_time = NULL,
        deadline = NULL
    WHERE student_id = ?
    AND assessment_id = ?
")->execute([
    $_SESSION['user_id'],
    $_POST['assessment_id']
]);
/* ===============================
   UPDATE ATTEMPT USAGE
================================ */

$attemptUpdate = $pdo->prepare("
    UPDATE assessment_attempts
    SET attempts_used = attempts_used + 1,
        start_time = NULL,
        deadline = NULL
    WHERE assessment_id = ?
      AND student_id = ?
      AND start_time IS NOT NULL
");
$attemptUpdate->execute([$assessmentId, $studentId]);
?>

<!DOCTYPE html>
<html>
<head>
<title>Result</title>
</head>
<body>

<h2>Your Score: <?= round($score) ?>%</h2>

<?php if ($passed): ?>
<h3 style="color:green;">PASSED 🎉</h3>
<?php else: ?>
<h3 style="color:red;">FAILED</h3>
<?php endif; ?>

<a href="Student-dashboard.php">Back to Dashboard</a>

</body>
</html>