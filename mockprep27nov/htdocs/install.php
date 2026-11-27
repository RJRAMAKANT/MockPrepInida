<?php
require __DIR__.'/lib.php';
if(file_exists(__DIR__.'/install.lock'))die('Already installed. Delete install.php from the server.');
$msg='';$link='';
$seed=[
'OAVS-CS'=>['OAVS - Computer Science post',180,['English'=>[20,'Synonyms & Antonyms|Tenses|Articles & Prepositions|Error Spotting|Fill in the Blanks|Reading Comprehension|Sentence Rearrangement|Idioms & Phrases'],'Odia'=>[20,'Grammar|Vocabulary|Comprehension|Translation'],'Reasoning'=>[30,'Number Series|Coding-Decoding|Direction Sense|Blood Relations|Analogy|Odd One Out|Ranking & Order|Syllogism|Venn Diagrams|Mirror & Water Images|Figure Series|Paper Folding & Cutting'],'Current Affairs'=>[30,'National|International|Odisha|Sports|Awards & Honours|Science & Technology|Economy & Budget|Important Days'],'Computer Science'=>[100,'Computer Fundamentals|Operating System|Programming Basics|Data Structures|DBMS & SQL|Computer Networks|Web Technologies|Software Engineering|Number Systems & Boolean Logic|Computer Organisation']]],
'SSC'=>['SSC CGL / CHSL',60,['Reasoning'=>[25,'Analogy|Coding-Decoding|Direction Sense|Blood Relations|Syllogism|Number Series|Ranking & Order'],'Quantitative Aptitude'=>[25,'Percentage|Profit & Loss|Time & Work|Time Speed Distance|Algebra|Geometry|Trigonometry|Data Interpretation'],'English'=>[25,'Error Spotting|Fill in the Blanks|Synonyms & Antonyms|Idioms & Phrases|Reading Comprehension|Cloze Test'],'General Awareness'=>[25,'History|Geography|Polity|Economy|General Science|Current Affairs']]],
'OTET'=>['OTET (Odisha TET)',150,['Child Development & Pedagogy'=>[30,'Growth & Development|Learning Theories|Inclusive Education|Teaching Methods|Assessment & Evaluation'],'Language I (Odia)'=>[30,'Grammar|Comprehension|Pedagogy of Language'],'Language II (English)'=>[30,'Grammar|Comprehension|Pedagogy of Language'],'Maths / EVS'=>[30,'Number System|Geometry|Mensuration|Pedagogy of Maths|Environmental Studies'],'Science / Social Science'=>[30,'Physical Science|Life Science|History|Geography|Civics']]]];
if($_SERVER['REQUEST_METHOD']==='POST'){
 $sql=[
 "CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100),email VARCHAR(150) UNIQUE,pass VARCHAR(255),role ENUM('student','teacher','admin') DEFAULT 'student',exam VARCHAR(20) DEFAULT 'OAVS',class_id INT NULL,points INT DEFAULT 0,verified TINYINT DEFAULT 0,vtoken VARCHAR(40) NULL,rtoken VARCHAR(40) NULL,rexp INT DEFAULT 0,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS classes(id INT AUTO_INCREMENT PRIMARY KEY,teacher_id INT,name VARCHAR(100),code VARCHAR(12) UNIQUE) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS exam_defs(code VARCHAR(20) PRIMARY KEY,title VARCHAR(120),minutes INT) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS subjects(id INT AUTO_INCREMENT PRIMARY KEY,exam VARCHAR(20),name VARCHAR(80),qcount INT DEFAULT 10,KEY(exam)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS topics(id INT AUTO_INCREMENT PRIMARY KEY,exam VARCHAR(20),subject VARCHAR(80),name VARCHAR(100),KEY(exam,subject)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS questions(id INT AUTO_INCREMENT PRIMARY KEY,exam VARCHAR(20),subject VARCHAR(80),topic VARCHAR(100) DEFAULT '',q TEXT,a VARCHAR(255),b VARCHAR(255),c VARCHAR(255),d VARCHAR(255),ans TINYINT,exp TEXT,src VARCHAR(10) DEFAULT 'manual',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY(exam,subject,topic)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS qsets(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(150),exam VARCHAR(20),minutes INT,marks DECIMAL(5,2) DEFAULT 1,neg DECIMAL(5,2) DEFAULT 0.25,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS qset_q(set_id INT,qid INT,ord INT DEFAULT 0,PRIMARY KEY(set_id,qid)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS papers(id INT AUTO_INCREMENT PRIMARY KEY,teacher_id INT,class_id INT NULL,title VARCHAR(150),exam VARCHAR(20),minutes INT,share ENUM('class','code','public') DEFAULT 'class',code VARCHAR(10),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS paper_q(id INT AUTO_INCREMENT PRIMARY KEY,paper_id INT,q TEXT,a VARCHAR(255),b VARCHAR(255),c VARCHAR(255),d VARCHAR(255),ans TINYINT,exp TEXT,KEY(paper_id)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS attempts(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,paper_id INT NULL,exam VARCHAR(20),kind VARCHAR(10),total INT,correct INT,wrong INT,score INT,secs INT,marks DECIMAL(8,2) DEFAULT 0,maxm DECIMAL(8,2) DEFAULT 0,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY(user_id)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS attempt_items(id INT AUTO_INCREMENT PRIMARY KEY,attempt_id INT,qid INT,src VARCHAR(6),pick TINYINT NULL,KEY(attempt_id)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS notes(id INT AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(10),exam VARCHAR(20),subject VARCHAR(80),title VARCHAR(150),body TEXT,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS posts(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(200),body TEXT,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS answers(id INT AUTO_INCREMENT PRIMARY KEY,post_id INT,user_id INT,body TEXT,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY(post_id)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS exams(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150),exam_date VARCHAR(40),info VARCHAR(255)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS pyq(id INT AUTO_INCREMENT PRIMARY KEY,exam VARCHAR(20),year VARCHAR(10),title VARCHAR(150),file VARCHAR(200)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS qotd(id INT AUTO_INCREMENT PRIMARY KEY,qdate DATE UNIQUE,exam VARCHAR(20),subject VARCHAR(80),q TEXT,a VARCHAR(255),b VARCHAR(255),c VARCHAR(255),d VARCHAR(255),ans TINYINT,exp TEXT) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS qotd_ans(id INT AUTO_INCREMENT PRIMARY KEY,qid INT,user_id INT,pick TINYINT,ok TINYINT,secs INT,UNIQUE KEY(qid,user_id)) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS notices(id INT AUTO_INCREMENT PRIMARY KEY,icon VARCHAR(16) DEFAULT '',title VARCHAR(200),link VARCHAR(300),active TINYINT DEFAULT 1,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS stats(k VARCHAR(20) PRIMARY KEY,v BIGINT DEFAULT 0) DEFAULT CHARSET=utf8mb4"];
 try{foreach($sql as $s)db()->exec($s);
  foreach(["ALTER TABLE users ADD verified TINYINT DEFAULT 0","ALTER TABLE users ADD vtoken VARCHAR(40) NULL","ALTER TABLE questions ADD topic VARCHAR(100) DEFAULT ''","ALTER TABLE qset_q ADD ord INT DEFAULT 0","ALTER TABLE qsets ADD marks DECIMAL(5,2) DEFAULT 1","ALTER TABLE qsets ADD neg DECIMAL(5,2) DEFAULT 0.25","ALTER TABLE attempts ADD marks DECIMAL(8,2) DEFAULT 0","ALTER TABLE attempts ADD maxm DECIMAL(8,2) DEFAULT 0"] as $s){try{db()->exec($s);}catch(Exception $x){}}
  q("INSERT IGNORE INTO stats(k,v) VALUES('visits',0)");q("REPLACE INTO stats(k,v) VALUES('schema',3)");
  if(!val('SELECT COUNT(*) FROM exam_defs'))foreach($seed as $code=>$d){q('INSERT INTO exam_defs(code,title,minutes) VALUES(?,?,?)',[$code,$d[0],$d[1]]);
   foreach($d[2] as $sub=>$x){q('INSERT INTO subjects(exam,name,qcount) VALUES(?,?,?)',[$code,$sub,$x[0]]);foreach(explode('|',$x[1]) as $tp)q('INSERT INTO topics(exam,subject,name) VALUES(?,?,?)',[$code,$sub,$tp]);}}
  $e=strtolower(trim($_POST['email']));if(!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen($_POST['password'])<8)throw new Exception('Valid email and 8+ character password required');
  q("INSERT INTO users(name,email,pass,role,verified) VALUES('Admin',?,?,'admin',0) ON DUPLICATE KEY UPDATE pass=VALUES(pass),role='admin'",[$e,password_hash($_POST['password'],PASSWORD_DEFAULT)]);
  $link=sendVerify(val('SELECT id FROM users WHERE email=?',[$e]));
  file_put_contents(__DIR__.'/install.lock','done');$msg='Installed! Verify your admin email using the link below, then DELETE install.php. Admin login: /'.ADMIN_DIR.'/';
 }catch(Exception $ex){$msg='Error: '.$ex->getMessage();}
}
?><!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css"><body><main class="auth"><div class="card"><h2>Install <?=SITE?></h2>
<?php if($msg)echo '<div class="toast">'.h($msg).'</div>';if($link)echo '<p>Admin email verification link (also sent by email):<br><a href="'.h($link).'"><b>'.h($link).'</b></a></p>';?>
<p class="mu">Creates the tables, a starter syllabus (edit it later in Admin &gt; Syllabus) and your admin account.</p>
<form method="post">Admin email<input name="email" type="email" required>Admin password (8+ chars)<input name="password" type="password" required><button class="p">Install</button></form></div></main>
