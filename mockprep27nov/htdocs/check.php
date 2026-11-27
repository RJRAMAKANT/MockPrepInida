<?php
// Setup checker. DELETE this file after everything works.
error_reporting(E_ERROR|E_PARSE);ini_set('display_errors','1');
require __DIR__.'/config.php';
function row_($ok,$label,$detail=''){echo '<tr><td>'.($ok?'✅':'❌').'</td><td><b>'.$label.'</b></td><td>'.$detail.'</td></tr>';}
echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:20px;max-width:860px;margin:auto"><h2>MockPrep setup check</h2><table cellpadding="8" style="border-collapse:collapse;width:100%">';
row_(version_compare(PHP_VERSION,'7.2','>='),'PHP version',PHP_VERSION);
foreach(['pdo_mysql','curl','mbstring','json'] as $x)row_(extension_loaded($x),'PHP extension: '.$x);
row_(strpos(DB_HOST,'XXX')===false&&strpos(DB_NAME,'XXXX')===false&&strpos(DB_USER,'XXXX')===false,'config.php filled in','Host: '.htmlspecialchars(DB_HOST).' &middot; DB: '.htmlspecialchars(DB_NAME).' &middot; User: '.htmlspecialchars(DB_USER));
$pdo=null;try{$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);row_(true,'Database connection','Connected');}catch(Exception $e){row_(false,'Database connection',htmlspecialchars($e->getMessage()).'<br><i>Check the host (it is NOT localhost on InfinityFree), database name, username and password in config.php.</i>');}
if($pdo){$need=['users','classes','exam_defs','subjects','topics','questions','qsets','qset_q','papers','paper_q','attempts','attempt_items','notes','posts','answers','exams','pyq','qotd','qotd_ans','stats'];$have=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $r)$have[]=$r[0];$miss=array_diff($need,$have);
 row_(!$miss,'Database tables',$miss?'Missing: '.implode(', ',$miss).'<br><i>Open <a href="install.php">install.php</a> and run it (delete install.lock first if it exists).</i>':'All tables exist');
 if(!$miss){$u=$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();row_($u>0,'Admin account',$u>0?'Exists':'None yet - run install.php');row_($pdo->query('SELECT COUNT(*) FROM exam_defs')->fetchColumn()>0,'Syllabus / exams loaded');}}
if($pdo&&!$miss){$hasN=in_array('notices',$have);row_($hasN,'Notice board table','<a href="?repair=1">Click to repair / create it</a>');
 $cols=[];foreach($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c)$cols[]=$c['Field'];row_(in_array('rtoken',$cols),'Password-reset columns','<a href="?repair=1">Click to repair</a>');
 if(isset($_GET['repair'])){$out=[];$run=function($sql)use($pdo,&$out){try{$pdo->exec($sql);$out[]='OK: '.substr($sql,0,70);}catch(Exception $e){$out[]='skipped: '.substr($sql,0,50).' -> '.htmlspecialchars(substr($e->getMessage(),0,140));}};
  $run("ALTER TABLE users ADD rtoken VARCHAR(40) NULL");$run("ALTER TABLE users ADD rexp INT DEFAULT 0");
  $run("CREATE TABLE IF NOT EXISTS notices(id INT AUTO_INCREMENT PRIMARY KEY,icon VARCHAR(16) DEFAULT '',title VARCHAR(200),link VARCHAR(300),active TINYINT DEFAULT 1,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4");
  foreach(['users','posts','answers','notices','notes','papers','paper_q','questions','qsets','classes','exams','pyq','qotd'] as $t)$run("ALTER TABLE $t CONVERT TO CHARACTER SET utf8mb4");
  $run("REPLACE INTO stats(k,v) VALUES('schema',3)");echo '<tr><td></td><td><b>Repair log</b></td><td>'.implode('<br>',$out).'<br><b>Reload this page to see the new status.</b></td></tr>';}}
row_(file_exists(__DIR__.'/.htaccess'),'.htaccess uploaded','Needed for clean URLs like /login. It is a hidden file, make sure your uploader shows hidden files.');
row_(file_exists(__DIR__.'/install.lock'),'Installed (install.lock)',file_exists(__DIR__.'/install.php')?'Remember to delete install.php':'');
row_(is_dir(__DIR__.'/uploads/pyq')&&is_writable(__DIR__.'/uploads/pyq'),'uploads/pyq writable (for PDFs)');
row_(is_dir(__DIR__.'/'.(defined('ADMIN_DIR')?ADMIN_DIR:'')),'Admin folder matches ADMIN_DIR',defined('ADMIN_DIR')?htmlspecialchars(ADMIN_DIR):'');
echo '</table><p><a href="./">Open the site</a></p><p style="color:#b91c1c"><b>Delete check.php and install.php when finished.</b></p>';
