<?php

session_start();

require_once 'Auth.php';   // for auth()
require_once 'DB.php';     // 🔥 THIS CREATES $pdo

auth('facilitator');       // ensure only facilitators access
?>
<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'facilitator') {
    header("Location: auth.php");
    exit;
}

$stats = [
    'student' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'courses' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'assessments' => $pdo->query("SELECT COUNT(*) FROM assessments")->fetchColumn()
];
?>
<!DOCTYPE html>
<html>
<head>
<title>Facilitator Dashboard | Quiz-me</title>
<style>
*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',Arial,sans-serif;
}

body{
min-height:100vh;
overflow-x:hidden;
color:white;

background:
radial-gradient(circle at top right,#f59e0b 0%,transparent 22%),
radial-gradient(circle at bottom left,#7c3aed 0%,transparent 28%),
linear-gradient(135deg,#0f172a,#111827,#1e293b);

padding:40px 20px;
position:relative;
}

/* ambient glow */

body::before{
content:"";
position:fixed;
top:-180px;
right:-120px;

width:500px;
height:500px;

background:rgba(245,158,11,.12);

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

background:rgba(124,58,237,.12);

border-radius:50%;

filter:blur(120px);

z-index:0;
}

/* MAIN CONTAINER */

.container{
position:relative;
z-index:2;

max-width:1150px;
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

/* TOP BAR */

.topbar{
display:flex;
justify-content:space-between;
align-items:center;
gap:20px;
margin-bottom:35px;
flex-wrap:wrap;
}

h2{
font-size:38px;
font-weight:800;

background:linear-gradient(90deg,#fff,#facc15);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

/* LOGOUT */

.logout{
display:inline-flex;
align-items:center;
justify-content:center;

padding:10px 20px;

border-radius:14px;

text-decoration:none;
font-weight:700;
font-size:14px;

background:rgba(255,255,255,.06);

border:1px solid rgba(255,255,255,.08);

color:white;

transition:.3s;

box-shadow:
0 8px 18px rgba(0,0,0,.2);
}

.logout:hover{
transform:translateY(-3px);

background:rgba(239,68,68,.18);

box-shadow:
0 12px 24px rgba(239,68,68,.2);
}

/* STAT CARDS */

.cards{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:24px;
margin-top:25px;
}

/* CARD */

.card{
position:relative;

padding:28px;

border-radius:24px;

background:rgba(255,255,255,.05);

border:1px solid rgba(255,255,255,.08);

backdrop-filter:blur(16px);

overflow:hidden;

transition:.45s ease;

box-shadow:
0 15px 35px rgba(0,0,0,.25);
}

/* SMALLER GLASS GLOW */

.card::before{
content:"";
position:absolute;
top:-20px;
right:-20px;

width:70px;
height:70px;

background:rgba(255,255,255,.06);

border-radius:50%;

filter:blur(14px);
}

.card:hover{
transform:translateY(-6px);

box-shadow:
0 22px 45px rgba(0,0,0,.35),
0 0 20px rgba(245,158,11,.10);
}

/* numbers */

.card h3{
font-size:42px;
margin-bottom:10px;

background:linear-gradient(145deg,#facc15,#f59e0b);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

.card p{
font-size:15px;
color:#d1d5db;
letter-spacing:.5px;
}

/* ACTION BUTTON AREA */

.actions{
display:flex;
gap:24px;
flex-wrap:wrap;
margin-top:45px;
}

/* BUTTONS */

button{
padding:14px 26px;

border:none;
border-radius:16px;

cursor:pointer;

font-size:15px;
font-weight:700;

color:white;

transition:
transform .45s ease,
box-shadow .45s ease,
background .45s ease;

position:relative;
overflow:hidden;

box-shadow:
0 12px 25px rgba(0,0,0,.25);
}

/* CREATE COURSE */

.create-btn{
background:
linear-gradient(
145deg,
#f59e0b,
#d97706
);
}

/* ANALYTICS */

.analytics-btn{
background:
linear-gradient(
145deg,
#8b5cf6,
#6d28d9
);
}

/* shine */

button::before{
content:"";
position:absolute;
top:0;
left:-60%;

width:45%;
height:100%;

background:linear-gradient(
90deg,
transparent,
rgba(255,255,255,.22),
transparent
);

transform:skewX(-25deg);

transition:1s ease;
}

button:hover::before{
left:130%;
}

button:hover{
transform:translateY(-4px);

box-shadow:
0 18px 38px rgba(0,0,0,.35);
}

/* MOBILE */

@media(max-width:768px){

.container{
padding:24px;
}

.topbar{
flex-direction:column;
align-items:flex-start;
}

h2{
font-size:30px;
}

.actions{
flex-direction:column;
gap:18px;
margin-top:35px;
}

button{
width:100%;
}

}
</style>
</head>
<body>

<div class="container">
  <a href="logout.php" class="logout">Logout</a>

  <h2>Facilitator Dashboard</h2>

  <div class="cards">
    <div class="card">
      <h3><?= $stats['student'] ?></h3>
      <p>Students</p>
    </div>
    <div class="card">
      <h3><?= $stats['courses'] ?></h3>
      <p>Courses</p>
    </div>
    <div class="card">
      <h3><?= $stats['assessments'] ?></h3>
      <p>Assessments</p>
    </div>
  </div>

  <button onclick="location.href='Facilitator-quiz.php'">Create Course</button>
  <button onclick="location.href='Facilitator-analytics.php'">Students Overview</button>
</div>

</body>
</html>
