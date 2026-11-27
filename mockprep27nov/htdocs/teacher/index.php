<?php
require __DIR__.'/../lib.php';
require __DIR__.'/../lib_class.php';
$T=BASE.'/teacher/';
$GLOBALS['seo']=['noindex'=>1,'t'=>'Teacher panel | '.SITE];
$t=$_GET['t']??'home';
migrate();
migrate_class();
$u=me();
$ok=$u&&$u['role']==='teacher';

/* sample CSV download for the question bank upload */
if($t==='sample' && $ok){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="teacher-question-format.csv"');
    $o=fopen('php://output','w');
    fwrite($o,"\xEF\xBB\xBF");   /* so Excel shows Unicode correctly */
    fputcsv($o,['question','a','b','c','d','ans','exam','subject','topic','explanation']);
    fputcsv($o,['Which planet is known as the Red Planet?','Venus','Mars','Jupiter','Saturn','B','','Science','Solar System','Mars looks red because of iron oxide on its surface.']);
    fputcsv($o,['What is 12 x 8?','86','96','108','88','2','','Maths','Multiplication','12 x 8 = 96.']);
    fclose($o); exit;
}

if($t==='logout'){session_destroy();go($T);}
 
/* which sidebar item is highlighted */
$a0=$_GET['a']??'';
$curMap=['class'=>'classes','qbank'=>'qbank','test'=>'tests','results'=>'tests','doubts'=>'doubts','class_doubts'=>'doubts'];
$GLOBALS['cur']=$a0 ? ($curMap[$a0]??'home') : $t;
 
ob_start();
 
if(!$ok){
 /* ───────────── LOGIN / REGISTER ───────────── */
 check();
 if(isset($_POST['reg'])){
  $n=trim($_POST['name']);$e=strtolower(trim($_POST['email']));
  if(!$n||!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen($_POST['password'])<6)flash('Enter name, valid email and 6+ character password');
  elseif(val('SELECT id FROM users WHERE email=?',[$e]))flash('Email already registered');
  else{
   q('INSERT INTO users(name,email,pass,role) VALUES(?,?,?,?)',[$n,$e,password_hash($_POST['password'],PASSWORD_DEFAULT),'teacher']);
   $id=db()->lastInsertId();
   do{ $code=strtoupper(substr(md5(uniqid('',true)),0,6)); }while(val('SELECT id FROM classes WHERE code=?',[$code]));
   q('INSERT INTO classes(teacher_id,school,name,code) VALUES(?,?,?,?)',[$id,trim($_POST['school']??''),trim($_POST['cname']??'')?:'My Class',$code]);
   if(REQUIRE_VERIFY){sendVerify($id);flash('Account created! Check your email for the verification link, then login.');}
   else{q('UPDATE users SET verified=1 WHERE id=?',[$id]);session_regenerate_id(true);$_SESSION['uid']=$id;$_SESSION['csrf']=bin2hex(random_bytes(16));}
   go($T);
  }
 }
 if(isset($_POST['log'])&&doLogin('teacher','t'))go($T);
 echo vlinkCard().'<section class="thero"><div><span class="pill">🏫 For teachers &amp; schools</span><h1>Run your own tests. <em>Track every student.</em></h1><p>Create your school classes, build your own questions, set a time window for each test and watch the rank list update as students attempt it.</p><div class="tf"><div><b>🏫 School &amp; classes</b><br><span>Separate students and questions per class</span></div><div><b>📝 Own questions</b><br><span>Type, upload CSV or pick from our bank</span></div><div><b>⏱ Time window</b><br><span>Open for a period, or always open</span></div><div><b>🏆 Top 3 &amp; results</b><br><span>Who scored how much</span></div></div></div>
<div class="card tbox"><div class="tabs"><button type="button" class="on" id="b1" onclick="tab(1)">Login</button><button type="button" id="b2" onclick="tab(2)">Register</button></div>
<form id="f1" method="post">'.field().'<h2 style="margin-top:0">Welcome back</h2><label>Email</label><input name="email" type="email" required><label>Password</label><input name="password" type="password" required><button class="p" name="log" value="1" style="width:100%">Login to teacher panel</button></form>
<form id="f2" method="post" style="display:none">'.field().'<h2 style="margin-top:0">Create teacher account</h2><label>Your name</label><input name="name" required><label>School name</label><input name="school" placeholder="e.g. ABC High School"><label>First class name</label><input name="cname" placeholder="e.g. Class 8-A"><label>Email</label><input name="email" type="email" required><label>Password (6+ characters)</label><input name="password" type="password" required><button class="p" name="reg" value="1" style="width:100%">Create account</button></form>
<p class="mu" style="margin-bottom:0"><a href="'.BASE.'/?p=forgot">Forgot password?</a> · <a href="'.BASE.'/?p=resend">Resend verification email</a> · <a href="'.BASE.'/">Student site</a></p></div></section>'.(isset($_POST['reg'])?'<script>window.addEventListener("load",function(){tab(2)})</script>':'').'
<h2>How it works</h2><div class="grid"><div class="card"><div class="big">1</div><b>Create your school classes</b><div class="mu">Each class gets its own code and student join link.</div></div><div class="card"><div class="big">2</div><b>Add questions &amp; make a test</b><div class="mu">Pick the class, add questions, set the time window. A random test code is created.</div></div><div class="card"><div class="big">3</div><b>See the results</b><div class="mu">Top 3, every score, and student doubts &mdash; all in one place.</div></div></div>';
 $links=[];
}else{
 /* ───────────── LOGGED-IN TEACHER ───────────── */
 $pend=(int)val('SELECT COUNT(*) FROM class_doubts d JOIN classes c ON c.id=d.class_id WHERE c.teacher_id=? AND d.answer IS NULL',[$u['id']]);
 $links=[
  [$T.'?t=home',   '🏠 Dashboard'],
  [$T.'?t=classes','🏫 My Classes'],
  [$T.'?t=qbank',  '📚 Question Bank'],
  [$T.'?t=tests',  '📝 Tests & Results'],
  [$T.'?t=doubts', '💬 Doubts'.($pend?' ('.$pend.')':'')],
  [$T.'?t=perf',   '📊 Class Performance'],
  [$T.'?t=refer',  '🔗 Referral Link'],
  [$T.'?t=logout', '🚪 Logout'],
 ];
 teacher_panel($u);
}
$body=ob_get_clean();
head('Teacher panel',$links,$T,$ok);
echo $body;
foot();
 
/* ═══════════════════════════════════════════════════════════════
   DOUBTS LIST (all classes, or one class)
   ═══════════════════════════════════════════════════════════════ */
function t_doubts($TID,$classId,$back,$pendOnly=false){
    $w=($classId?' AND d.class_id='.(int)$classId:'').($pendOnly?' AND d.answer IS NULL':'');
    $ds=rows('SELECT d.*,u.name,t.title test_title,c.name cname,c.school
        FROM class_doubts d
        JOIN classes c ON c.id=d.class_id AND c.teacher_id=?
        JOIN users u ON u.id=d.user_id
        LEFT JOIN tests t ON t.id=d.test_id
        WHERE 1=1'.$w.'
        ORDER BY (d.answer IS NULL) DESC, d.id DESC',[$TID]);
    if(!$ds){ echo '<div class="card mu">No doubts here.</div>'; return; }
    foreach($ds as $d){
        $answered=!empty($d['answer']);
        echo '<div class="card" style="'.($answered?'opacity:.85':'').'">'
            .'<span class="pill">'.($answered?'✅ Answered':'⏳ Pending').'</span>'
            .'<span class="pill">🏫 '.h(classLabel($d)).'</span>'
            .($d['test_title']?'<span class="pill">'.h($d['test_title']).'</span>':'')
            .'<h3>'.h($d['question']).'</h3>'
            .'<div class="mu">Asked by '.h($d['name']).' · '.h(substr($d['created'],0,16)).'</div>';
        if($answered){
            echo '<div style="margin-top:10px;padding:10px 14px;background:#dcfce7;color:#14532d;border-radius:8px;border-left:4px solid #16a34a">'
                .'<b>Your answer:</b> '.nl2br(h($d['answer'])).'</div>';
        }else{
            echo '<form method="post" style="margin-top:10px">'.field()
                .'<input type="hidden" name="answer_doubt" value="1">'
                .'<input type="hidden" name="doubt_id" value="'.(int)$d['id'].'">'
                .'<input type="hidden" name="class_id" value="'.(int)$d['class_id'].'">'
                .'<input type="hidden" name="back" value="'.h($back).'">'
                .'<textarea name="answer" rows="2" placeholder="Type your answer…" required></textarea>'
                .'<button class="p" style="margin-top:6px">Send answer →</button></form>';
        }
        echo '</div>';
    }
}
 
/* ═══════════════════════════════════════════════════════════════
   TEACHER PANEL
   ═══════════════════════════════════════════════════════════════ */
function teacher_panel($u){
    $TID = (int)$u['id'];
    $t   = $_GET['t'] ?? 'home';
    $a   = $_GET['a'] ?? '';
    $B   = BASE.'/teacher/';
 
    /* full-width + responsive layout for every teacher page */
    echo '<style>
    .content main,main{max-width:none!important;width:100%}
    .tp{width:100%}
    .tp h1{margin:.1em 0 .4em}
    .tp .tiles{grid-template-columns:repeat(auto-fit,minmax(190px,1fr))!important}
    .tp .chart-wrap{position:relative;height:250px;width:100%}
    .tp .sc{overflow-x:auto;-webkit-overflow-scrolling:touch}
    .tp table{min-width:480px}
    .tp input,.tp select,.tp textarea{max-width:100%}
    .t-hero{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;padding:22px 26px;border-radius:18px;background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#db2777);color:#fff;margin-bottom:18px}
    .t-hero h2{margin:0;color:#fff}.t-hero p{margin:6px 0 0;color:#e0e7ff}
    .t-hero .acts{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .t-hero .btn{background:#fff;color:#4f46e5;border:0;font-weight:700}
    .t-cols{display:grid;gap:16px;grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start;margin-top:6px}
    .t-2{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));margin-bottom:16px}
    .t-cards{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(290px,1fr))}
    .t-stack{display:grid;gap:16px}
    .t-list .it{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(128,128,128,.2)}
    .t-list .it:last-child{border-bottom:0}
    .t-list .it small{display:block;opacity:.7}
    .t-sec{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:18px 0 8px}
    .t-sec h2{margin:0}
    @media(max-width:1000px){.t-cols{grid-template-columns:1fr}}
    @media(max-width:600px){.t-hero{padding:16px}.t-hero .acts .btn{flex:1;text-align:center}}
    </style><div class="tp">';
 
    /* ── helpers ── */
    $genCode = function($len=6){
        $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';$o='';
        for($i=0;$i<$len;$i++)$o.=$chars[random_int(0,strlen($chars)-1)];
        return $o;
    };
    $uniqueCode = function($table) use ($genCode){
        do{ $c=$genCode(); } while(val("SELECT id FROM $table WHERE code=?",[$c]));
        return $c;
    };
    $del = function($name,$val,$label,$extra=[]){
        $o='<form method="post" style="display:inline" onsubmit="return confirm(\'Are you sure?\')">'.field()
          .'<input type="hidden" name="'.$name.'" value="'.(int)$val.'">';
        foreach($extra as $k=>$v) $o.='<input type="hidden" name="'.$k.'" value="'.(int)$v.'">';
        return $o.'<button>'.$label.'</button></form>';
    };
    $statusOf = function($tt){
        return $tt['always_open'] ? '🌐 Always open'
            : (($tt['opens_at'] && strtotime($tt['opens_at']) > time()) ? '⏳ Not yet open'
            : (($tt['closes_at'] && strtotime($tt['closes_at']) < time()) ? '🔒 Closed' : '✅ Open'));
    };
    $toSql = function($v){ $s=strtotime((string)$v); return $s ? date('Y-m-d H:i:s',$s) : null; };

    /* shared filter for the class question bank (questions NOT yet in the test) */
    $qFilter = function($tt,$src) use ($TID){
        $w   = ' WHERE tq.class_id=? AND tq.teacher_id=? AND tq.id NOT IN (SELECT tq_id FROM test_q WHERE test_id=?)';
        $par = [(int)$tt['class_id'],$TID,(int)$tt['id']];
        $g = function($k) use ($src){ return trim((string)($src[$k] ?? '')); };
        if($g('fs')!==''){ $w.=' AND tq.subject=?';           $par[]=$g('fs'); }
        if($g('ft')!==''){ $w.=' AND tq.topic=?';             $par[]=$g('ft'); }
        if($g('fx')!==''){ $w.=' AND tq.source=?';            $par[]=$g('fx'); }
        if($g('d1')!==''){ $w.=' AND DATE(tq.created)>=?';    $par[]=$g('d1'); }
        if($g('d2')!==''){ $w.=' AND DATE(tq.created)<=?';    $par[]=$g('d2'); }
        if($g('fq')!==''){ $w.=' AND tq.q LIKE ?';            $par[]='%'.$g('fq').'%'; }
        return [$w,$par];
    };
    $attach = function($tt,$ids){
        $tid=(int)$tt['id'];
        $ord=(int)val('SELECT COALESCE(MAX(ord),0) FROM test_q WHERE test_id=?',[$tid]);
        $have=array_map('intval',array_column(rows('SELECT tq_id FROM test_q WHERE test_id=?',[$tid]),'tq_id'));
        $n=0;
        foreach(array_map('intval',$ids) as $qid){
            if(!$qid || in_array($qid,$have,true)) continue;
            if(!row('SELECT id FROM tq WHERE id=? AND class_id=?',[$qid,$tt['class_id']])) continue;
            $ord++; q('INSERT INTO test_q(test_id,tq_id,ord) VALUES(?,?,?)',[$tid,$qid,$ord]); $have[]=$qid; $n++;
        }
        return $n;
    };
    /* remove one class completely */
    $wipeClass = function($cid) use ($TID){
        $in='(SELECT id FROM tests WHERE class_id=? AND teacher_id=?)';
        q("DELETE FROM test_attempts WHERE test_id IN $in",[$cid,$TID]);
        q("DELETE FROM test_q WHERE test_id IN $in",[$cid,$TID]);
        q('DELETE FROM tests WHERE class_id=? AND teacher_id=?',[$cid,$TID]);
        q('DELETE FROM tq WHERE class_id=? AND teacher_id=?',[$cid,$TID]);
        q('DELETE FROM class_doubts WHERE class_id=?',[$cid]);
        q("UPDATE users SET class_id=NULL WHERE class_id=? AND role='student'",[$cid]);
        q('DELETE FROM classes WHERE id=? AND teacher_id=?',[$cid,$TID]);
    };
 
    /* ═══════════ POST handlers ═══════════ */
 
    if(isset($_POST['newclass'])){
        check();
        $name   = trim($_POST['cname'] ?? '');
        $school = trim($_POST['school'] ?? '');
        if(strlen($name) < 2) flash('Enter the class name (e.g. Class 8-A).');
        else {
            $code = $uniqueCode('classes');
            q('INSERT INTO classes(teacher_id,school,name,code) VALUES(?,?,?,?)',[$TID,$school,$name,$code]);
            flash('Class created. Code: '.$code);
            go($B.'?a=class&id='.db()->lastInsertId());
        }
        go($B.'?t=classes');
    }
 
    if(isset($_POST['addq'])){
        check();
        $class_id = (int)($_POST['q_class'] ?? 0);
        $cl = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[$class_id,$TID]);
        if(!$cl) flash('Pick a valid class.');
        elseif(!trim($_POST['qq'] ?? '')) flash('Question text is required.');
        elseif(!in_array((int)$_POST['ans'], [0,1,2,3], true)) flash('Pick a correct answer.');
        else {
            q('INSERT INTO tq(teacher_id,class_id,exam,subject,topic,q,a,b,c,d,ans,exp,source)
               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$TID,$cl['id'],trim($_POST['exam'] ?? ''),
               trim($_POST['subject'] ?? ''),trim($_POST['topic'] ?? ''),
               trim($_POST['qq']),trim($_POST['a']),trim($_POST['b']),
               trim($_POST['c']),trim($_POST['d']),(int)$_POST['ans'],
               trim($_POST['exp'] ?? ''),'manual']);
            flash('Question added for “'.classLabel($cl).'”.');
        }
        go($B.'?a=qbank&class='.$class_id);
    }
 
    if(isset($_POST['import_bank'])){
        check();
        $class_id = (int)($_POST['q_class'] ?? 0);
        $cl = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[$class_id,$TID]);
        $ids = $_POST['qids'] ?? [];
        if(!$cl) flash('Invalid class.');
        elseif(!$ids || !is_array($ids)) flash('Tick at least one question.');
        else {
            $ids = array_map('intval', array_filter($ids));
            $added = 0;
            foreach(rows('SELECT * FROM questions WHERE id IN('.implode(',',$ids).')') as $r){
                q('INSERT INTO tq(teacher_id,class_id,exam,subject,topic,q,a,b,c,d,ans,exp,source)
                   VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
                  [$TID,$cl['id'],$r['exam'],$r['subject'],$r['topic']??'',
                   $r['q'],$r['a'],$r['b'],$r['c'],$r['d'],(int)$r['ans'],
                   $r['exp']??'','bank']);
                $added++;
            }
            flash($added.' question(s) imported for “'.classLabel($cl).'”.');
        }
        go($B.'?a=qbank&class='.$class_id);
    }
 
    if(isset($_POST['csv_upload']) && !empty($_FILES['csv']['tmp_name'])){
        check();
        $class_id = (int)($_POST['q_class'] ?? 0);
        $cl = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[$class_id,$TID]);
        if(!$cl){ flash('Invalid class.'); go($B.'?t=qbank'); }
        $fh = fopen($_FILES['csv']['tmp_name'],'r');
        $header = fgetcsv($fh);
        if($header) $header[0] = preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
        $map = [];
        if($header){ foreach($header as $i=>$hd) $map[strtolower(trim($hd))] = $i; }
        $miss = [];
        foreach(['question','a','b','c','d','ans'] as $n) if(!isset($map[$n])) $miss[] = $n;
        if($miss){ fclose($fh); flash('CSV missing columns: '.implode(', ',$miss)); go($B.'?a=qbank&class='.$class_id); }
        $added = 0;
        while(($rw = fgetcsv($fh)) !== false){
            $get = function($k) use ($map,$rw){ return isset($map[$k]) ? trim((string)($rw[$map[$k]] ?? '')) : ''; };
            if(!$get('question')) continue;
            $ans = ans_index($get('ans'));
            if($ans === false) continue;
            q('INSERT INTO tq(teacher_id,class_id,exam,subject,topic,q,a,b,c,d,ans,exp,source)
               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$TID,$cl['id'],$get('exam'),$get('subject'),$get('topic'),
               $get('question'),$get('a'),$get('b'),$get('c'),$get('d'),
               $ans,$get('explanation'),'csv']);
            $added++;
        }
        fclose($fh);
        flash($added.' question(s) imported from CSV.');
        go($B.'?a=qbank&class='.$class_id);
    }
 
    if(isset($_POST['delq'])){
        check();
        $qid = (int)$_POST['delq'];
        if(row('SELECT id FROM tq WHERE id=? AND teacher_id=?',[$qid,$TID])){
            q('DELETE FROM tq WHERE id=?',[$qid]);
            q('DELETE FROM test_q WHERE tq_id=?',[$qid]);
            flash('Question removed.');
        }
        go($B.'?a=qbank&class='.(int)($_POST['class'] ?? 0));
    }
 
    if(isset($_POST['newtest'])){
        check();
        $class_id = (int)($_POST['t_class'] ?? 0);
        $cl = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[$class_id,$TID]);
        if(!$cl) flash('Invalid class.');
        elseif(!trim($_POST['ttitle'] ?? '')) flash('Test title required.');
        else {
            $always = !empty($_POST['always_open']) ? 1 : 0;
            $open  = $always ? null : $toSql($_POST['opens_at'] ?? '');
            $close = $always ? null : $toSql($_POST['closes_at'] ?? '');
            if(!$always && (!$open || !$close)) flash('Set open and close times, or tick “Always open”.');
            elseif(!$always && strtotime($close) <= strtotime($open)) flash('Closing time must be after opening time.');
            else {
                $code = $uniqueCode('tests');
                q('INSERT INTO tests(teacher_id,class_id,title,code,exam,minutes,opens_at,closes_at,always_open,share)
                   VALUES(?,?,?,?,?,?,?,?,?,?)',
                  [$TID,$cl['id'],trim($_POST['ttitle']),$code,
                   trim($_POST['exam'] ?? ''),max(5,min(180,(int)($_POST['minutes'] ?? 30))),
                   $open,$close,$always,'class']);
                flash('Test created. Test code: '.$code);
                go($B.'?a=test&id='.db()->lastInsertId());
            }
        }
        go($B.'?t=tests');
    }
 
    if(isset($_POST['attach_q'])){
        check();
        $tid=(int)$_POST['test_id'];
        $tt=row('SELECT * FROM tests WHERE id=? AND teacher_id=?',[$tid,$TID]);
        $ids=$_POST['tq_ids'] ?? [];
        if(!$tt) flash('Invalid test.');
        elseif(!$ids || !is_array($ids)) flash('Tick at least one question.');
        else flash($attach($tt,$ids).' question(s) added to the test.');
        go($B.'?a=test&id='.$tid);
    }

    /* add N random questions that match the current filters */
    if(isset($_POST['attach_random'])){
        check();
        $tid=(int)$_POST['test_id'];
        $tt=row('SELECT * FROM tests WHERE id=? AND teacher_id=?',[$tid,$TID]);
        if(!$tt) flash('Invalid test.');
        else{
            list($w,$par)=$qFilter($tt,$_POST);
            $n=max(1,min(200,(int)($_POST['n'] ?? 10)));
            $ids=array_column(rows('SELECT tq.id FROM tq'.$w.' ORDER BY RAND() LIMIT '.$n,$par),'id');
            flash($ids ? $attach($tt,$ids).' random question(s) added.' : 'No questions match these filters.');
        }
        go($B.'?a=test&id='.$tid);
    }
 
    if(isset($_POST['detach'])){
        check();
        $tid = (int)($_POST['test'] ?? 0);
        if(row('SELECT id FROM tests WHERE id=? AND teacher_id=?',[$tid,$TID])){
            q('DELETE FROM test_q WHERE test_id=? AND tq_id=?',[$tid,(int)$_POST['detach']]);
            flash('Question removed from test.');
        }
        go($B.'?a=test&id='.$tid);
    }
 
    if(isset($_POST['deltest'])){
        check();
        $tid = (int)$_POST['deltest'];
        if(row('SELECT id FROM tests WHERE id=? AND teacher_id=?',[$tid,$TID])){
            q('DELETE FROM test_q WHERE test_id=?',[$tid]);
            q('DELETE FROM test_attempts WHERE test_id=?',[$tid]);
            q('DELETE FROM tests WHERE id=?',[$tid]);
            flash('Test deleted.');
        }
        go($B.'?t=tests');
    }

    /* delete one class (tests, attempts, question bank, doubts; students are un-linked) */
    if(isset($_POST['delclass'])){
        check();
        $cid=(int)$_POST['delclass'];
        if(row('SELECT id FROM classes WHERE id=? AND teacher_id=?',[$cid,$TID])){
            $wipeClass($cid);
            flash('Class deleted with its tests, questions and doubts. Students were removed from it.');
        }
        go($B.'?t=classes');
    }
    /* delete a whole school = every class of this teacher with that school name */
    if(isset($_POST['delschool'])){
        check();
        $sch=trim((string)$_POST['delschool']);
        foreach(rows('SELECT id FROM classes WHERE teacher_id=? AND school=?',[$TID,$sch]) as $c) $wipeClass((int)$c['id']);
        flash('School “'.($sch?:'(no school)').'” and all its classes were deleted.');
        go($B.'?t=classes');
    }
 
    /* teacher lets ONE student write the test again (deletes that single attempt) */
    if(isset($_POST['reset_attempt'])){
        check();
        $aid = (int)$_POST['reset_attempt'];
        $at  = row('SELECT a.id,a.test_id FROM test_attempts a JOIN tests t ON t.id=a.test_id WHERE a.id=? AND t.teacher_id=?',[$aid,$TID]);
        if($at){ q('DELETE FROM test_attempts WHERE id=?',[$aid]); flash('Attempt cleared. The student can write this test again.'); }
        go($B.'?a=results&id='.(int)($at['test_id'] ?? 0));
    }
 
    if(isset($_POST['answer_doubt'])){
        check();
        $did = (int)$_POST['doubt_id'];
        $d = row('SELECT * FROM class_doubts WHERE id=?',[$did]);
        if($d){
            $cl = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[(int)$d['class_id'],$TID]);
            if($cl && trim($_POST['answer'] ?? '')){
                q('UPDATE class_doubts SET answer=?,answered_by=?,answered_at=NOW() WHERE id=?',
                  [trim($_POST['answer']),$TID,$did]);
                flash('Answer sent.');
            }
        }
        go(($_POST['back'] ?? '')==='class'
            ? $B.'?a=class_doubts&class='.(int)($_POST['class_id'] ?? 0)
            : $B.'?t=doubts');
    }
 
    /* ═══════════ DETAIL VIEWS (?a=...) ═══════════ */
 
    /* ---- one class ---- */
    if($a==='class'){
        $class = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[(int)($_GET['id'] ?? 0),$TID]);
        if(!$class){ echo '<div class="card mu">Class not found. <a href="'.$B.'?t=classes">Go to classes →</a></div></div>'; return; }
        $students = rows('SELECT id,name,email,points FROM users WHERE class_id=? AND role="student" ORDER BY points DESC',[$class['id']]);
        $tests    = rows('SELECT t.*,
            (SELECT COUNT(*) FROM test_q WHERE test_id=t.id) n_q,
            (SELECT COUNT(*) FROM test_attempts WHERE test_id=t.id) n_at
            FROM tests t WHERE teacher_id=? AND class_id=? ORDER BY t.id DESC',[$TID,$class['id']]);
        $n_questions = (int)val('SELECT COUNT(*) FROM tq WHERE class_id=? AND teacher_id=?',[$class['id'],$TID]);
        $n_doubts    = (int)val('SELECT COUNT(*) FROM class_doubts WHERE class_id=? AND answer IS NULL',[$class['id']]);
        $joinLink    = baseUrl().'/?p=register&ref='.urlencode($class['code']);
 
        echo '<a href="'.$B.'?t=classes" class="mu">← All classes</a>'
            .'<div class="t-hero"><div><h2>🏫 '.h($class['name']).'</h2><p>'.h($class['school'] ?: 'School not set').' · '.count($students).' students</p></div>'
            .'<div class="acts"><span class="pill" style="font-size:16px;letter-spacing:2px">Code: '.h($class['code']).'</span> '
            .$del('delclass',$class['id'],'🗑 Delete class').'</div></div>'
            .'<div class="card" style="margin-bottom:16px"><b>Student join link:</b> <a href="'.h($joinLink).'" style="word-break:break-all">'.h($joinLink).'</a></div>';
 
        echo '<div class="t-2">'
            .'<a class="card" href="'.$B.'?a=qbank&class='.(int)$class['id'].'" style="text-decoration:none"><b>📚 Question bank</b><div class="mu">'.$n_questions.' questions</div></a>'
            .'<a class="card" href="#ntf" style="text-decoration:none"><b>📝 New test</b><div class="mu">Create a timed paper</div></a>'
            .'<a class="card" href="'.$B.'?a=class_doubts&class='.(int)$class['id'].'" style="text-decoration:none"><b>💬 Doubts</b><div class="mu">'.$n_doubts.' unanswered</div></a></div>';
 
        echo '<div class="t-sec"><h2>👥 Students ('.count($students).')</h2></div>';
        if(!$students) echo '<div class="card mu">No students yet. Share the class code or link.</div>';
        else table(['#','Name','Email','Points'],
            array_map(function($s,$i){ return [$i+1,h($s['name']),h($s['email']),(int)$s['points']]; },$students,array_keys($students)));
 
        echo '<div class="t-sec" id="ntf"><h2>📝 Tests in this class</h2></div>'
            .'<form class="card" method="post" style="margin-bottom:16px">'.field()
            .'<input type="hidden" name="newtest" value="1"><input type="hidden" name="t_class" value="'.(int)$class['id'].'">'
            .'<label>Test title</label><input name="ttitle" placeholder="e.g. Reasoning Weekly Test" required>'
            .'<div class="t-2" style="margin:8px 0 0"><div><label>Exam (optional)</label><input name="exam" placeholder="oavs / ssc / otet"></div>'
            .'<div><label>Minutes</label><input name="minutes" type="number" value="30" min="5" max="180"></div></div>'
            .'<label style="margin-top:8px"><input type="checkbox" name="always_open" value="1"> Always open</label>'
            .'<div class="t-2" style="margin:0"><div><label>Opens at</label><input name="opens_at" type="datetime-local"></div>'
            .'<div><label>Closes at</label><input name="closes_at" type="datetime-local"></div></div>'
            .'<button class="p" style="margin-top:10px">Create test →</button></form>';
 
        if(!$tests) echo '<div class="card mu">No tests yet.</div>';
        else {
            echo '<div class="t-cards">';
            foreach($tests as $tt){
                echo '<div class="card"><span class="pill">'.h($statusOf($tt)).'</span><span class="pill">'.(int)$tt['n_q'].' Q</span>'
                    .'<h3>'.h($tt['title']).'</h3>'
                    .'<div class="mu">Test code <code>'.h($tt['code']).'</code> · '.(int)$tt['minutes'].' min · '.(int)$tt['n_at'].' attempt(s)</div>'
                    .($tt['always_open'] ? '' : '<div class="mu" style="margin-top:4px">'.($tt['opens_at']?'From '.h($tt['opens_at']):'').' '.($tt['closes_at']?'to '.h($tt['closes_at']):'').'</div>')
                    .'<p style="margin-top:10px"><a class="btn p" href="'.$B.'?a=test&id='.(int)$tt['id'].'">Manage →</a> '
                    .'<a class="btn" href="'.$B.'?a=results&id='.(int)$tt['id'].'">📊 Results</a></p></div>';
            }
            echo '</div>';
        }
        echo '</div>'; return;
    }
 
    /* ---- one class question bank ---- */
    if($a==='qbank'){
        $qbank = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[(int)($_GET['class'] ?? 0),$TID]);
        if(!$qbank){ echo '<div class="card mu">Class not found. <a href="'.$B.'?t=qbank">Back →</a></div></div>'; return; }
        $questions = rows('SELECT * FROM tq WHERE class_id=? AND teacher_id=? ORDER BY id DESC',[$qbank['id'],$TID]);
 
        echo '<a href="'.$B.'?t=qbank" class="mu">← All question banks</a>'
            .'<h1>📚 Question bank — '.h(classLabel($qbank)).'</h1>'
            .'<p class="mu">Questions saved here belong to this class only. Add them to any test of this class.</p>'
            .'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">'
            .'<a class="btn p" href="#t1">✏️ Type manually</a><a class="btn" href="#t2">📥 Upload CSV</a><a class="btn" href="#t3">🏦 From site bank</a></div>';
 
        echo '<h2 id="t1">✏️ Type a question</h2><form class="card" method="post">'.field()
            .'<input type="hidden" name="addq" value="1"><input type="hidden" name="q_class" value="'.(int)$qbank['id'].'">'
            .'<label>Question</label><textarea name="qq" rows="2" required></textarea>'
            .'<div class="t-2" style="margin:0"><div><label>A</label><input name="a" required></div><div><label>B</label><input name="b" required></div>'
            .'<div><label>C</label><input name="c" required></div><div><label>D</label><input name="d" required></div></div>'
            .'<div class="t-2" style="margin:0"><div><label>Correct answer</label><select name="ans"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></div>'
            .'<div><label>Exam (optional)</label><input name="exam" placeholder="oavs"></div><div><label>Subject</label><input name="subject"></div></div>'
            .'<label>Topic (optional)</label><input name="topic">'
            .'<label>Explanation (optional)</label><textarea name="exp" rows="2"></textarea>'
            .'<button class="p" style="margin-top:10px">Add question →</button></form>';
 
        echo '<h2 id="t2">📥 Upload CSV</h2><form class="card" method="post" enctype="multipart/form-data">'.field()
            .'<input type="hidden" name="csv_upload" value="1"><input type="hidden" name="q_class" value="'.(int)$qbank['id'].'">'
            .'<p class="mu">CSV columns: <code>question,a,b,c,d,ans</code> (ans = A/B/C/D or 1–4). Optional: <code>exam,subject,topic,explanation</code>.</p>'
            .'<p><a class="btn" href="'.$B.'?t=sample">⬇ Download CSV format</a></p>'
            .'<input type="file" name="csv" accept=".csv" required><button class="p" style="margin-top:10px">Upload →</button></form>';
 
        echo '<h2 id="t3">🏦 Import from site question bank</h2><form class="card" method="post">'.field()
            .'<input type="hidden" name="import_bank" value="1"><input type="hidden" name="q_class" value="'.(int)$qbank['id'].'">'
            .'<div style="max-height:340px;overflow:auto;border:1px solid rgba(128,128,128,.3);border-radius:8px;padding:8px">';
        foreach(rows('SELECT id,exam,subject,q FROM questions ORDER BY id DESC LIMIT 50') as $bq){
            echo '<label style="display:block;padding:4px 0"><input type="checkbox" name="qids[]" value="'.(int)$bq['id'].'"> '
                .'<span class="pill" style="font-size:11px">'.h($bq['exam'].' · '.$bq['subject']).'</span> '.h(substr($bq['q'],0,110)).'</label>';
        }
        echo '</div><button class="p" style="margin-top:10px">Import selected →</button></form>';
 
        echo '<h2 style="margin-top:32px">Questions in this bank ('.count($questions).')</h2>';
        if(!$questions) echo '<div class="card mu">No questions yet.</div>';
        else foreach($questions as $i=>$qq){
            echo '<div class="card"><b>'.($i+1).'. '.h($qq['q']).'</b> <span class="pill">'.h($qq['source']).'</span>';
            if($qq['subject']) echo ' <span class="pill">'.h($qq['subject']).'</span>';
            if($qq['topic'])   echo ' <span class="pill">'.h($qq['topic']).'</span>';
            echo '<div class="mu" style="margin-top:6px">';
            foreach(['a','b','c','d'] as $j=>$L)
                echo '<div style="'.((int)$qq['ans']===$j?'color:#16a34a;font-weight:700':'').'"><b>'.strtoupper($L).'.</b> '.h($qq[$L]).'</div>';
            echo '</div>';
            if($qq['exp']) echo '<div class="mu" style="margin-top:6px"><b>Explanation:</b> '.h($qq['exp']).'</div>';
            echo '<div style="margin-top:8px">'.$del('delq',$qq['id'],'Remove',['class'=>$qbank['id']]).'</div></div>';
        }
        echo '</div>'; return;
    }
 
    /* ---- test editor ---- */
    if($a==='test'){
        $test = row('SELECT * FROM tests WHERE id=? AND teacher_id=?',[(int)($_GET['id'] ?? 0),$TID]);
        if(!$test){ echo '<div class="card mu">Test not found. <a href="'.$B.'?t=tests">Back →</a></div></div>'; return; }
        $in_rows = rows('SELECT tq.* FROM test_q q JOIN tq ON tq.id=q.tq_id
            WHERE q.test_id=? ORDER BY q.ord',[$test['id']]);
 
        echo '<a href="'.$B.'?a=class&id='.(int)$test['class_id'].'" class="mu">← Back to class</a>'
            .'<h1>📝 '.h($test['title']).'</h1><div class="card" style="margin-bottom:16px">'
            .'<div class="mu"><b>Test code:</b> <code style="font-size:16px;letter-spacing:2px">'.h($test['code']).'</code> · '
            .'<b>Time:</b> '.(int)$test['minutes'].' min · <b>Status:</b> '.h($statusOf($test)).'</div>'
            .($test['opens_at']?'<div class="mu">Opens: '.h($test['opens_at']).'</div>':'')
            .($test['closes_at']?'<div class="mu">Closes: '.h($test['closes_at']).'</div>':'')
            .'<p style="margin-top:10px"><a class="btn" href="'.$B.'?a=results&id='.(int)$test['id'].'">📊 Results</a> '
            .$del('deltest',$test['id'],'Delete test').'</p></div>';
 
        echo '<h2>Questions in this test ('.count($in_rows).')</h2>';
        if(!$in_rows) echo '<div class="card mu">No questions attached yet.</div>';
        else { $i=0; foreach($in_rows as $qq){ $i++;
            echo '<div class="card"><b>'.$i.'. '.h($qq['q']).'</b>'
                .($qq['subject']?' <span class="pill">'.h($qq['subject']).'</span>':'')
                .($qq['topic']?' <span class="pill">'.h($qq['topic']).'</span>':'')
                .'<div class="mu" style="margin-top:6px">';
            foreach(['a','b','c','d'] as $j=>$L)
                echo '<div style="'.((int)$qq['ans']===$j?'color:#16a34a;font-weight:700':'').'">'.strtoupper($L).'. '.h($qq[$L]).'</div>';
            echo '</div>'.$del('detach',$qq['id'],'Detach',['test'=>$test['id']]).'</div>';
        }}
 
        /* ---------- filters ---------- */
        $F=[]; foreach(['fs','ft','fx','d1','d2','fq'] as $k) $F[$k]=trim((string)($_GET[$k] ?? ''));
        list($w,$par)=$qFilter($test,$_GET);
        $avail=rows('SELECT tq.* FROM tq'.$w.' ORDER BY tq.subject,tq.topic,tq.id DESC',$par);
        $sb=rows('SELECT subject,topic,COUNT(*) n FROM tq WHERE class_id=? AND teacher_id=?
                  AND id NOT IN (SELECT tq_id FROM test_q WHERE test_id=?) GROUP BY subject,topic ORDER BY subject,topic',
                 [$test['class_id'],$TID,$test['id']]);
        $subs=[]; foreach($sb as $r) $subs[$r['subject']]=($subs[$r['subject']]??0)+(int)$r['n'];
        $hid=''; foreach($F as $k=>$v) $hid.='<input type="hidden" name="'.$k.'" value="'.h($v).'">';

        echo '<style>
        .pk-bar{position:sticky;bottom:0;z-index:5;display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;margin-top:12px;border-radius:14px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
        .pk-bar .grow{flex:1}.pk-bar button{border:0}
        .pk-g{border:1px solid rgba(128,128,128,.3);border-radius:12px;margin:10px 0;overflow:hidden}
        .pk-g>summary{cursor:pointer;padding:10px 14px;font-weight:700;display:flex;gap:10px;align-items:center;background:rgba(79,70,229,.08)}
        .pk-q{display:flex;gap:10px;align-items:flex-start;padding:10px 14px;border-top:1px solid rgba(128,128,128,.15);cursor:pointer}
        .pk-q:hover{background:rgba(79,70,229,.06)}
        .pk-q input{width:auto;margin-top:4px}
        .pk-q .o{font-size:12px;opacity:.7;margin-top:4px}
        </style>';

        echo '<h2>➕ Add questions from the class bank <span class="mu">('.count($avail).' available)</span></h2>';

        /* filter form (GET) */
        echo '<form method="get" class="card" data-nolock="1" style="margin-bottom:12px">'
            .'<input type="hidden" name="a" value="test"><input type="hidden" name="id" value="'.(int)$test['id'].'">'
            .'<div class="t-2" style="margin:0">'
            .'<div><label>Subject</label><select name="fs" id="fsS"><option value="">All subjects</option>';
        foreach($subs as $s=>$n) echo '<option value="'.h($s).'" '.($F['fs']===$s?'selected':'').'>'.h($s?:'General').' ('.$n.')</option>';
        echo '</select></div><div><label>Topic</label><select name="ft" id="fsT"><option value="">All topics</option>';
        foreach($sb as $r) if($r['topic']!=='') echo '<option data-s="'.h($r['subject']).'" value="'.h($r['topic']).'" '.($F['ft']===$r['topic']?'selected':'').'>'.h($r['topic']).' ('.(int)$r['n'].')</option>';
        echo '</select></div><div><label>Source</label><select name="fx"><option value="">Any</option>';
        foreach(['manual'=>'Typed','csv'=>'CSV upload','bank'=>'Site bank'] as $k=>$v) echo '<option value="'.$k.'" '.($F['fx']===$k?'selected':'').'>'.$v.'</option>';
        echo '</select></div><div><label>Added from</label><input type="date" name="d1" value="'.h($F['d1']).'"></div>'
            .'<div><label>Added to</label><input type="date" name="d2" value="'.h($F['d2']).'"></div>'
            .'<div><label>Search text</label><input name="fq" value="'.h($F['fq']).'" placeholder="word in question"></div></div>'
            .'<p style="margin:10px 0 0"><button class="p">🔍 Apply filters</button> <a class="btn" href="'.$B.'?a=test&id='.(int)$test['id'].'">Reset</a></p></form>';

        /* random set (POST, uses the same filters) */
        echo '<form method="post" class="card" style="margin-bottom:12px">'.field()
            .'<input type="hidden" name="attach_random" value="1"><input type="hidden" name="test_id" value="'.(int)$test['id'].'">'.$hid
            .'<b>🎲 Random set</b><p class="mu" style="margin:4px 0 8px">Randomly pick questions from the filtered list above ('.count($avail).' match).</p>'
            .'<div class="inl"><input type="number" name="n" value="10" min="1" max="200" style="width:110px"><span>random questions</span>'
            .'<button class="p"'.($avail?'':' disabled').'>Add random →</button></div></form>';

        /* manual pick, grouped Subject ▸ Topic */
        if(!$avail){
            echo '<div class="card mu">No questions match. Change the filters or <a href="'.$B.'?a=qbank&class='.(int)$test['class_id'].'">add questions to the bank</a>.</div>';
        }else{
            $grp=[]; foreach($avail as $r) $grp[$r['subject']?:'General'][$r['topic']?:'No topic'][]=$r;
            echo '<form method="post" id="pickf" data-nolock="1">'.field().'<input type="hidden" name="attach_q" value="1"><input type="hidden" name="test_id" value="'.(int)$test['id'].'">'
                .'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px">'
                .'<button type="button" class="btn" id="selall">✔ Select all shown</button>'
                .'<button type="button" class="btn" id="selnone">✖ Clear</button></div>';
            foreach($grp as $sn=>$tps){
                $tot=0; foreach($tps as $l) $tot+=count($l);
                echo '<details class="pk-g" open><summary><input type="checkbox" class="gall" style="width:auto" onclick="event.stopPropagation()"> 📘 '.h($sn).' <span class="pill">'.$tot.'</span></summary>';
                foreach($tps as $tn=>$list){
                    echo '<div style="padding:6px 14px;font-weight:600;opacity:.8">▸ '.h($tn).' <span class="pill">'.count($list).'</span></div>';
                    foreach($list as $r){
                        echo '<label class="pk-q"><input type="checkbox" name="tq_ids[]" value="'.(int)$r['id'].'"><div><div>'.h($r['q']).'</div>'
                            .'<div class="o">A) '.h($r['a']).' · B) '.h($r['b']).' · C) '.h($r['c']).' · D) '.h($r['d'])
                            .' &nbsp;|&nbsp; ✔ '.strtoupper('abcd'[(int)$r['ans']]).' &nbsp;|&nbsp; '.h(substr($r['created'],0,10)).'</div></div></label>';
                    }
                }
                echo '</details>';
            }
            echo '<div class="pk-bar"><b><span id="pk">0</span> selected</b><span class="grow"></span>'
                .'<a class="btn" href="'.$B.'?a=qbank&class='.(int)$test['class_id'].'">+ Add more to bank</a>'
                .'<button class="p" style="background:#fff;color:#4f46e5">Attach selected →</button></div></form>';
        }
        echo '<script>(function(){
          var f=document.getElementById("pickf");
          var sS=document.getElementById("fsS"),sT=document.getElementById("fsT");
          function ft(){if(!sT)return;Array.prototype.forEach.call(sT.options,function(o){if(!o.dataset.s)return;o.hidden=sS.value&&o.dataset.s!==sS.value;if(o.hidden&&o.selected)sT.value=""})}
          if(sS){sS.onchange=ft;ft()}
          if(!f)return;
          function boxes(){return f.querySelectorAll("input[name=\'tq_ids[]\']")}
          function cnt(){document.getElementById("pk").textContent=f.querySelectorAll("input[name=\'tq_ids[]\']:checked").length}
          f.addEventListener("change",function(e){
            if(e.target.classList.contains("gall")){e.target.closest("details").querySelectorAll("input[name=\'tq_ids[]\']").forEach(function(c){c.checked=e.target.checked})}
            cnt()});
          document.getElementById("selall").onclick=function(){boxes().forEach(function(c){c.checked=true});cnt()};
          document.getElementById("selnone").onclick=function(){boxes().forEach(function(c){c.checked=false});f.querySelectorAll(".gall").forEach(function(c){c.checked=false});cnt()};
        })();</script>';
        echo '</div>'; return;
    }
 
    /* ---- test results ---- */
    if($a==='results'){
        $rt = row('SELECT * FROM tests WHERE id=? AND teacher_id=?',[(int)($_GET['id'] ?? 0),$TID]);
        if(!$rt){ echo '<div class="card mu">Test not found. <a href="'.$B.'?t=tests">Back →</a></div></div>'; return; }
        $attempts = rows('SELECT a.*, u.name, u.email FROM test_attempts a JOIN users u ON u.id=a.user_id
            WHERE a.test_id=? ORDER BY a.score DESC, a.secs ASC',[$rt['id']]);
        echo '<a href="'.$B.'?a=test&id='.(int)$rt['id'].'" class="mu">← Back to test</a>'
            .'<h1>📊 Results — '.h($rt['title']).'</h1>'
            .'<div class="mu">Test code <code>'.h($rt['code']).'</code> · '.count($attempts).' attempt(s)</div>';
        if(!$attempts){ echo '<div class="card mu" style="margin-top:16px">No attempts yet.</div></div>'; return; }
        echo '<h2>🏆 Top 3</h2><div class="t-2">';
        $podium = [['🥇 1st','#f59e0b'],['🥈 2nd','#94a3b8'],['🥉 3rd','#b45309']];
        for($i=0;$i<3;$i++){
            $x = $attempts[$i] ?? null; if(!$x) continue;
            echo '<div class="card" style="border-left:4px solid '.$podium[$i][1].'"><b>'.$podium[$i][0].'</b><h3>'.h($x['name']).'</h3>'
                .'<div style="font-size:28px;font-weight:800;color:'.$podium[$i][1].'">'.$x['score'].'%</div>'
                .'<div class="mu">'.round($x['marks'],2).' / '.round($x['maxm'],2).' · '.floor($x['secs']/60).'m '.($x['secs']%60).'s</div></div>';
        }
        echo '</div><h2>All attempts</h2>';
        table(['#','Name','Email','Score','Marks','Time',''],
            array_map(function($x,$i) use ($del){ return [$i+1,h($x['name']),h($x['email']),$x['score'].'%',
                round($x['marks'],2).' / '.round($x['maxm'],2),floor($x['secs']/60).'m '.($x['secs']%60).'s',
                $del('reset_attempt',$x['id'],'Allow retake')]; },$attempts,array_keys($attempts)));
        echo '</div>'; return;
    }
 
    /* ---- doubts of one class ---- */
    if($a==='class_doubts' || $a==='doubts'){
        $dc = row('SELECT * FROM classes WHERE id=? AND teacher_id=?',[(int)($_GET['class'] ?? 0),$TID]);
        if(!$dc){ echo '<div class="card mu">Class not found. <a href="'.$B.'?t=doubts">Back →</a></div></div>'; return; }
        echo '<a href="'.$B.'?a=class&id='.(int)$dc['id'].'" class="mu">← Back to class</a><h1>💬 Doubts — '.h(classLabel($dc)).'</h1>';
        t_doubts($TID,(int)$dc['id'],'class');
        echo '</div>'; return;
    }
 
    /* ═══════════ SIDEBAR PAGES (?t=...) ═══════════ */
    $classes = rows('SELECT c.*,
        (SELECT COUNT(*) FROM users u WHERE u.class_id=c.id AND u.role="student") n_students,
        (SELECT COUNT(*) FROM tq WHERE class_id=c.id) n_questions,
        (SELECT COUNT(*) FROM tests WHERE class_id=c.id) n_tests
        FROM classes c WHERE teacher_id=? ORDER BY id DESC',[$TID]);
 
    /* ---- MY CLASSES ---- */
    if($t==='classes'){
        $lastSchool = $classes ? $classes[0]['school'] : '';
        echo '<h1>🏫 My classes</h1><p class="mu">Add every class you teach. Each class has its own code, students, question bank and tests.</p>'
            .'<form class="card" method="post" style="max-width:720px">'.field().'<input type="hidden" name="newclass" value="1">'
            .'<div class="t-2" style="margin:0"><div><label>School name</label><input name="school" value="'.h($lastSchool).'" placeholder="e.g. ABC High School"></div>'
            .'<div><label>Class name</label><input name="cname" placeholder="e.g. Class 8-A" required></div></div>'
            .'<button class="p" style="margin-top:10px">Create class →</button></form>';
        echo '<div class="t-sec"><h2>Your classes ('.count($classes).')</h2></div>';
        if(!$classes) echo '<div class="card mu">No classes yet. Create one above.</div>';
        else {
            /* schools (delete a whole school with all its classes) */
            $schools=array_values(array_unique(array_map(function($c){return $c['school'];},$classes)));
            echo '<div class="card" style="margin-bottom:16px"><b>🏫 Schools</b><div class="t-list">';
            foreach($schools as $s){
                $cnt=count(array_filter($classes,function($c) use ($s){return $c['school']===$s;}));
                echo '<div class="it"><div><b>'.h($s?:'School not set').'</b><small>'.$cnt.' class(es)</small></div>'
                    .'<form method="post" style="display:inline" onsubmit="return confirm(\'Delete this school and ALL its classes, tests, questions and doubts?\')">'.field()
                    .'<input type="hidden" name="delschool" value="'.h($s).'"><button>🗑 Delete school</button></form></div>';
            }
            echo '</div></div>';

            echo '<div class="t-cards">';
            foreach($classes as $c){
                echo '<div class="card"><span class="pill">Code '.h($c['code']).'</span><h3>'.h($c['name']).'</h3>'
                    .'<div class="mu">🏫 '.h($c['school'] ?: 'School not set').'</div>'
                    .'<div class="mu">'.(int)$c['n_students'].' students · '.(int)$c['n_questions'].' questions · '.(int)$c['n_tests'].' tests</div>'
                    .'<p style="margin-top:10px"><a class="btn p" href="'.$B.'?a=class&id='.(int)$c['id'].'">Open →</a> '
                    .'<a class="btn" href="'.$B.'?a=qbank&class='.(int)$c['id'].'">📚 Questions</a> '
                    .$del('delclass',$c['id'],'🗑 Delete').'</p></div>';
            }
            echo '</div>';
        }
        echo '</div>'; return;
    }
 
    /* ---- QUESTION BANK (choose class) ---- */
    if($t==='qbank'){
        echo '<h1>📚 Question bank</h1><p class="mu">Every class has its own bank. Pick a class to type, upload (CSV) or import questions.</p>';
        if(!$classes){ echo '<div class="card mu">Create a class first. <a href="'.$B.'?t=classes">My classes →</a></div></div>'; return; }
        table(['School','Class','Code','Questions','Tests',''],array_map(function($c) use ($B){
            return [h($c['school']),h($c['name']),'<code>'.h($c['code']).'</code>',(int)$c['n_questions'],(int)$c['n_tests'],
                '<a class="btn p" href="'.$B.'?a=qbank&class='.(int)$c['id'].'">Open bank →</a>'];
        },$classes));
        echo '</div>'; return;
    }
 
    /* ---- TESTS & RESULTS ---- */
    if($t==='tests'){
        echo '<h1>📝 Tests &amp; results</h1><p class="mu">Create a timed test for any class, then open its results to see who scored how much.</p>';
        if(!$classes){ echo '<div class="card mu">Create a class first. <a href="'.$B.'?t=classes">My classes →</a></div></div>'; return; }
        echo '<form class="card" method="post" style="margin-bottom:16px">'.field().'<input type="hidden" name="newtest" value="1">'
            .'<h3 style="margin-top:0">New test</h3><div class="t-2" style="margin:0"><div><label>For class</label><select name="t_class">';
        foreach($classes as $c) echo '<option value="'.(int)$c['id'].'">'.h(classLabel($c)).'</option>';
        echo '</select></div><div><label>Test title</label><input name="ttitle" required></div></div>'
            .'<div class="t-2" style="margin:8px 0 0"><div><label>Exam (optional)</label><input name="exam" placeholder="oavs / ssc / otet"></div>'
            .'<div><label>Minutes</label><input name="minutes" type="number" value="30" min="5" max="180"></div></div>'
            .'<label style="margin-top:8px"><input type="checkbox" name="always_open" value="1"> Always open</label>'
            .'<div class="t-2" style="margin:0"><div><label>Opens at</label><input name="opens_at" type="datetime-local"></div>'
            .'<div><label>Closes at</label><input name="closes_at" type="datetime-local"></div></div>'
            .'<button class="p" style="margin-top:10px">Create test →</button></form>';
 
        $all = rows('SELECT t.*,c.name cname,c.school,
            (SELECT COUNT(*) FROM test_q WHERE test_id=t.id) n_q,
            (SELECT COUNT(*) FROM test_attempts WHERE test_id=t.id) n_at,
            (SELECT ROUND(AVG(score)) FROM test_attempts WHERE test_id=t.id) av
            FROM tests t JOIN classes c ON c.id=t.class_id WHERE t.teacher_id=? ORDER BY t.id DESC',[$TID]);
        echo '<div class="t-sec"><h2>All tests ('.count($all).')</h2></div>';
        if(!$all){ echo '<div class="card mu">No tests yet.</div></div>'; return; }
        table(['Test','Class','Test code','Status','Q','Attempts','Avg',''],array_map(function($x) use ($B,$statusOf){
            return [h($x['title']),h(classLabel($x)),'<code>'.h($x['code']).'</code>',h($statusOf($x)),(int)$x['n_q'],(int)$x['n_at'],
                $x['av']!==null?$x['av'].'%':'—',
                '<a class="btn p" href="'.$B.'?a=test&id='.(int)$x['id'].'">Manage</a> <a class="btn" href="'.$B.'?a=results&id='.(int)$x['id'].'">📊 Results</a>'];
        },$all));
        echo '</div>'; return;
    }
 
    /* ---- DOUBTS (all classes) ---- */
    if($t==='doubts'){
        $pendOnly = !empty($_GET['f']);
        echo '<h1>💬 Student doubts</h1>'
            .'<p><a class="btn '.(!$pendOnly?'p':'').'" href="'.$B.'?t=doubts">All</a> '
            .'<a class="btn '.($pendOnly?'p':'').'" href="'.$B.'?t=doubts&f=pending">⏳ Pending only</a></p>';
        t_doubts($TID,0,'all',$pendOnly);
        echo '</div>'; return;
    }
 
    /* ---- CLASS PERFORMANCE ---- */
    if($t==='perf'){
        $GLOBALS['chart']=true;
        echo '<h1>📊 Class performance</h1>';
        if(!$classes){ echo '<div class="card mu">Create a class first. <a href="'.$B.'?t=classes">My classes →</a></div></div>'; return; }
        $cid = (int)($_GET['class'] ?? $classes[0]['id']);
        $cur = null; foreach($classes as $c) if((int)$c['id']===$cid) $cur=$c;
        if(!$cur){ $cur=$classes[0]; $cid=(int)$cur['id']; }
 
        echo '<form method="get" class="card" data-nolock="1" style="margin-bottom:16px"><input type="hidden" name="t" value="perf">'
            .'<label>Class</label><select name="class" onchange="this.form.submit()">';
        foreach($classes as $c) echo '<option value="'.(int)$c['id'].'" '.((int)$c['id']===$cid?'selected':'').'>'.h(classLabel($c)).'</option>';
        echo '</select></form>';
 
        $r = rows('SELECT s.name,s.email,COUNT(a.id) n,COALESCE(ROUND(AVG(a.score)),0) av,COALESCE(MAX(a.score),0) b
            FROM users s
            LEFT JOIN test_attempts a ON a.user_id=s.id AND a.test_id IN (SELECT id FROM tests WHERE teacher_id=? AND class_id=?)
            WHERE s.class_id=? AND s.role="student" GROUP BY s.id ORDER BY av DESC, n DESC',[$TID,$cid,$cid]);
 
        if(!$r){ echo '<div class="card mu">No students in this class yet.</div>'; }
        else {
            echo '<div class="card"><b>Average score per student</b>';
            chart('k1','bar',array_column($r,'name'),array_map('intval',array_column($r,'av')),'Avg %');
            echo '</div><div class="t-sec"><h2>🥇 Rank list</h2></div>';
            table(['Rank','Student','Email','Tests taken','Average','Best'],array_map(function($x,$i){
                return [$i+1,h($x['name']),h($x['email']),$x['n'],$x['av'].'%',$x['b'].'%']; },$r,array_keys($r)));
        }
 
        $pt = rows('SELECT t.id,t.title,COUNT(a.id) n,ROUND(AVG(a.score)) av,MAX(a.score) mx
            FROM tests t LEFT JOIN test_attempts a ON a.test_id=t.id
            WHERE t.teacher_id=? AND t.class_id=? GROUP BY t.id ORDER BY t.id DESC',[$TID,$cid]);
        echo '<div class="t-sec"><h2>Test-wise performance</h2></div>';
        if(!$pt) echo '<div class="card mu">No tests for this class yet.</div>';
        else table(['Test','Attempts','Average','Highest',''],array_map(function($x) use ($B){
            return [h($x['title']),(int)$x['n'],$x['av']!==null?$x['av'].'%':'—',$x['mx']!==null?$x['mx'].'%':'—',
                '<a class="btn" href="'.$B.'?a=results&id='.(int)$x['id'].'">Top 3 &amp; results</a>']; },$pt));
        echo '</div>'; return;
    }
 
    /* ---- REFERRAL LINK ---- */
    if($t==='refer'){
        echo '<h1>🔗 Student referral links</h1><p class="mu">Students who register with a link join that class automatically. Or they can type the class code in <b>My Class</b>.</p>';
        if(!$classes){ echo '<div class="card mu">Create a class first. <a href="'.$B.'?t=classes">My classes →</a></div></div>'; return; }
        echo '<div class="t-cards">';
        foreach($classes as $c){
            $l = baseUrl().'/?p=register&ref='.urlencode($c['code']);
            echo '<div class="card"><h3>'.h($c['name']).'</h3><div class="mu">🏫 '.h($c['school'] ?: 'School not set').'</div>'
                .'<p>Class code: <span class="pill">'.h($c['code']).'</span></p>'
                .'<input readonly value="'.h($l).'" onclick="this.select()" style="width:100%">'
                .'<a class="btn p" style="margin-top:8px" target="_blank" rel="noopener" href="https://wa.me/?text='.urlencode('Join my class on '.SITE.' ('.classLabel($c).'): '.$l).'">Share on WhatsApp</a></div>';
        }
        echo '</div></div>'; return;
    }
 
    /* ═══════════ DASHBOARD (default) ═══════════ */
    $GLOBALS['chart']=true;
    $nc = count($classes);
    $ns = (int)val('SELECT COUNT(*) FROM users WHERE role="student" AND class_id IN (SELECT id FROM classes WHERE teacher_id=?)',[$TID]);
    $nt = (int)val('SELECT COUNT(*) FROM tests WHERE teacher_id=?',[$TID]);
    $nq = (int)val('SELECT COUNT(*) FROM tq WHERE teacher_id=?',[$TID]);
    $nd = (int)val('SELECT COUNT(*) FROM class_doubts d JOIN classes c ON c.id=d.class_id WHERE c.teacher_id=? AND d.answer IS NULL',[$TID]);
    $na = (int)val('SELECT COUNT(*) FROM test_attempts a JOIN tests t ON t.id=a.test_id WHERE t.teacher_id=?',[$TID]);
    $av = round((float)val('SELECT AVG(a.score) FROM test_attempts a JOIN tests t ON t.id=a.test_id WHERE t.teacher_id=?',[$TID]));
 
    echo '<div class="t-hero"><div><h2>Welcome, '.h($u['name']).' 👋</h2>'
        .'<p>'.$nc.' class(es) · '.$ns.' students · '.$na.' test attempts so far</p></div>'
        .'<div class="acts"><a class="btn" href="'.$B.'?t=classes">＋ New class</a>'
        .'<a class="btn" href="'.$B.'?t=tests">＋ New test</a>'
        .'<a class="btn" href="'.$B.'?t=qbank">＋ Add questions</a></div></div>';
 
    echo tiles([['🏫',$nc,'Classes','#4f46e5'],['🎓',$ns,'Students','#0891b2'],['📝',$nt,'Tests','#16a34a'],
                ['📚',$nq,'My questions','#7c3aed'],['✅',$na,'Attempts','#0ea5e9'],
                ['💬',$nd,'Pending doubts','#ef4444'],['📈',$av.'%','Average score','#f59e0b']]);
 
    /* chart data */
    $b=[0,0,0,0];
    foreach(rows('SELECT a.score FROM test_attempts a JOIN tests t ON t.id=a.test_id WHERE t.teacher_id=?',[$TID]) as $r){
        $sc=(int)$r['score']; $b[$sc>=90?0:($sc>=75?1:($sc>=60?2:3))]++;
    }
    $wk=array_reverse(rows('SELECT YEARWEEK(a.created,1) w,ROUND(AVG(a.score)) av FROM test_attempts a JOIN tests t ON t.id=a.test_id
        WHERE t.teacher_id=? GROUP BY w ORDER BY w DESC LIMIT 8',[$TID]));
    $recent = rows('SELECT t.*,c.name cname,c.school,
        (SELECT COUNT(*) FROM test_attempts WHERE test_id=t.id) n_at,
        (SELECT ROUND(AVG(score)) FROM test_attempts WHERE test_id=t.id) av
        FROM tests t JOIN classes c ON c.id=t.class_id WHERE t.teacher_id=? ORDER BY t.id DESC LIMIT 6',[$TID]);
    $pd = rows('SELECT d.question,d.created,u.name,c.name cname FROM class_doubts d
        JOIN classes c ON c.id=d.class_id AND c.teacher_id=? JOIN users u ON u.id=d.user_id
        WHERE d.answer IS NULL ORDER BY d.id DESC LIMIT 5',[$TID]);
    $top = rows('SELECT u.name,ROUND(AVG(a.score)) av,COUNT(*) n FROM test_attempts a
        JOIN tests t ON t.id=a.test_id AND t.teacher_id=? JOIN users u ON u.id=a.user_id
        GROUP BY u.id ORDER BY av DESC,n DESC LIMIT 5',[$TID]);
 
    echo '<div class="t-cols"><div class="t-stack">';
 
    /* LEFT column */
    echo '<div class="t-2" style="margin:0"><div class="card"><b>Score distribution</b>';
    if(array_sum($b)) chart('t1','doughnut',['90-100%','75-89%','60-74%','Below 60%'],$b);
    else echo '<p class="mu">No attempts yet — charts appear when students submit tests.</p>';
    echo '</div><div class="card"><b>Weekly average score (%)</b>';
    if($wk) chart('t2','line',array_map(function($r){return 'W'.substr($r['w'],4);},$wk),array_map('intval',array_column($wk,'av')),'Avg %');
    else echo '<p class="mu">No attempts yet.</p>';
    echo '</div></div>';
 
    echo '<div class="card"><div class="t-sec" style="margin-top:0"><b>📝 Recent tests</b><a class="btn" href="'.$B.'?t=tests">All tests →</a></div>';
    if(!$recent) echo '<p class="mu">No tests yet. <a href="'.$B.'?t=tests">Create your first test →</a></p>';
    else {
        echo '<div class="sc"><table><tr><th>Test</th><th>Class</th><th>Status</th><th>Attempts</th><th>Avg</th><th></th></tr>';
        foreach($recent as $x)
            echo '<tr><td>'.h($x['title']).'</td><td>'.h(classLabel($x)).'</td><td>'.h($statusOf($x)).'</td><td>'.(int)$x['n_at'].'</td><td>'
                .($x['av']!==null?$x['av'].'%':'—').'</td><td><a class="btn" href="'.$B.'?a=results&id='.(int)$x['id'].'">📊 Results</a></td></tr>';
        echo '</table></div>';
    }
    echo '</div></div>';   /* /left */
 
    /* RIGHT column */
    echo '<div class="t-stack">';
 
    echo '<div class="card"><div class="t-sec" style="margin-top:0"><b>💬 Pending doubts ('.$nd.')</b></div><div class="t-list">';
    if(!$pd) echo '<p class="mu">No pending doubts 🎉</p>';
    foreach($pd as $d) echo '<div class="it"><div><b>'.h(mb_substr($d['question'],0,80)).'</b><small>'.h($d['name']).' · '.h($d['cname']).'</small></div></div>';
    echo '</div>'.($nd?'<a class="btn p" style="margin-top:10px" href="'.$B.'?t=doubts&f=pending">Answer doubts →</a>':'').'</div>';
 
    echo '<div class="card"><b>🏆 Top students</b><div class="t-list">';
    if(!$top) echo '<p class="mu">No results yet.</p>';
    foreach($top as $i=>$s) echo '<div class="it"><div>'.($i+1).'. <b>'.h($s['name']).'</b><small>'.(int)$s['n'].' test(s)</small></div><span class="pill">'.$s['av'].'%</span></div>';
    echo '</div></div>';
 
    echo '<div class="card"><div class="t-sec" style="margin-top:0"><b>🏫 My classes</b><a class="btn" href="'.$B.'?t=classes">Manage →</a></div><div class="t-list">';
    if(!$classes) echo '<p class="mu">No classes yet.</p>';
    foreach(array_slice($classes,0,6) as $c)
        echo '<div class="it"><div><a href="'.$B.'?a=class&id='.(int)$c['id'].'"><b>'.h($c['name']).'</b></a><small>'.h($c['school'] ?: 'School not set').' · '.(int)$c['n_students'].' students</small></div><span class="pill">'.h($c['code']).'</span></div>';
    echo '</div></div>';
 
    echo '</div></div></div>';   /* /right, /t-cols, /tp */
}