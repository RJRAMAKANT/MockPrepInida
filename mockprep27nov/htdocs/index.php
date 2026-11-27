<?php
require __DIR__.'/lib.php';
require __DIR__.'/lib_class.php';
ob_start('prettyUrls');migrate();migrate_class();
$p=$_GET['p']??'home';
$pages=['home','register','login','logout','dashboard','tests','test','submit','daily',
        'leaderboard','notes','community','exams','verify','resend','practice','review',
        'about','privacy','exam','forgot','reset','profile','certificate','certificates','requests',
        'askdoubt','submit_ttest','ttresult','myclass','askai','aichat','feedback'];
if(!in_array($p,$pages,true))$p='home';
if($p==='logout'){session_destroy();go(BASE.'/');}
if($p==='submit'){submit();exit;}
if($p==='submit_ttest'){submit_ttest();exit;}
if($p==='aichat'){aichat();exit;}
$SEO=['home'=>['MockPrep India | Free OAVS, SSC & OTET Mock Tests, Topic-wise Practice','Practise OAVS, SSC, OTET and government exam mock tests free. Topic-wise practice, real CBT exam interface, answers with explanations, daily question and leaderboard.','/'],
'register'=>['Create a free account | MockPrep India','Register free to take timed mock tests, practise topic by topic and track your progress for OAVS, SSC, OTET and more.','/register'],
'login'=>['Student login | MockPrep India','Log in to your MockPrep India account.','/login','x'],
'notes'=>['Notes & Tricks for Govt Exams | MockPrep India','Shortcuts, tricks and quick notes for reasoning, maths, English and general awareness for OAVS, SSC and OTET.','/notes'],
'exams'=>['Upcoming Exams & Previous Year Papers | MockPrep India','Upcoming government exam dates and downloadable previous year question papers.','/exams'],
'leaderboard'=>['Leaderboard - Top Students | MockPrep India','See the top scorers and fastest solvers on MockPrep India.','/leaderboard'],
'about'=>['About MockPrep India','MockPrep India offers free exam-pattern mock tests and topic-wise practice for government job aspirants and school entrance candidates.','/about'],
'privacy'=>['Privacy Policy | MockPrep India','How MockPrep India handles your data.','/privacy'],
'feedback'=>['Feedback | MockPrep India','Tell us what you think about MockPrep India and how we can improve.','/feedback'],
'exam'=>['Exam | MockPrep India','','/exam'],
'teacher'=>['Teacher panel | MockPrep India','Create classes, build papers, share with your class.','/teacher','x']];
$sx=$SEO[$p]??[ucfirst($p).' | '.SITE,'','/'.$p,'x'];
$GLOBALS['seo']=['t'=>$sx[0],'d'=>$sx[1]?:null,'path'=>$sx[2],'noindex'=>!empty($sx[3])||!isset($SEO[$p])];
if($GLOBALS['seo']['d']===null)unset($GLOBALS['seo']['d']);
if($p==='home')$GLOBALS['seo']['ld']=['@context'=>'https://schema.org','@graph'=>[['@type'=>'EducationalOrganization','name'=>SITE,'url'=>baseUrl().'/','description'=>'Free mock tests and topic-wise practice for government exams'],['@type'=>'WebSite','name'=>SITE,'url'=>baseUrl().'/'],['@type'=>'FAQPage','mainEntity'=>[['@type'=>'Question','name'=>'Is MockPrep India free?','acceptedAnswer'=>['@type'=>'Answer','text'=>'Yes, practising mock tests is free.']],['@type'=>'Question','name'=>'How is the score calculated?','acceptedAnswer'=>['@type'=>'Answer','text'=>'Each correct answer earns marks and each wrong answer deducts the negative marking set for that test.']]]]]];
ob_start();call_user_func('pg_'.$p);$body=ob_get_clean();
$u=me();$S=$u&&$u['role']==='student';
$GLOBALS['cur']=$p;

if($u && $u['role']==='teacher'){
    $nav=[
        [BASE.'/teacher','🏫 My classes'],
        [BASE.'/?p=logout','🚪 Logout'],
    ];
} elseif($S){
    $nav=[['','🏠 Home'],['?p=tests','📝 Mock Tests'],['?p=myclass','🏫 My Class'],['?p=practice','🎯 Topic Practice'],['?p=askai','🤖 AI Tutor'],['?p=daily','⚡ Daily Question'],['?p=notes','📚 Notes & Tricks'],['?p=community','💬 Community'],['?p=leaderboard','🏆 Leaderboard'],['?p=exams','📅 Exams & PYQ'],['?p=dashboard','📊 My Dashboard'],['?p=certificates','🏆 Certificates'],['?p=requests','💡 Any Request'],['?p=feedback','⭐ Feedback'],['?p=profile','👤 My Profile'],['?p=logout','🚪 Logout']];
} else {
    $nav=[['?p=notes','Notes & Tricks'],['?p=exams','Exams & PYQ'],['?p=leaderboard','Leaderboard'],[BASE.'/teacher/','For Teachers'],['?p=feedback','Feedback'],['?p=login','Login'],['?p=register','Register']];
}

head($p==='dashboard'?'My dashboard':($p==='askai'?'AI Tutor':ucfirst($p)),array_map(function($l){return [$l[0]===''?BASE.'/':($l[0][0]==='?'?BASE.'/'.$l[0]:$l[0]),$l[1]];},$nav),null,$S);
echo $body;foot();   /* foot() also adds the floating 🤖 AI Tutor + WhatsApp buttons */

function stu(){return need('student');}

/* ═══════════════════════════════════════════════════════════════
   HOME
   Hero carousel: 3 slides. Each slide can show an image that the
   admin uploads in Admin → Hero image (hero_img(1..3) in lib_class.php).
   A slide with no image shows its default illustrated card.
   ═══════════════════════════════════════════════════════════════ */
function pg_home(){
 $st=sval("SELECT COUNT(*) FROM users WHERE role='student'");$tc=sval("SELECT COUNT(*) FROM users WHERE role='teacher'");$qs=sval('SELECT COUNT(*) FROM questions');$at=sval('SELECT COUNT(*) FROM attempts');$vs=sval("SELECT v FROM stats WHERE k='visits'");
 echo noticeBar();

 /* default (illustrated) visuals, used when no image is uploaded for that slide */
 $d1='<div class="hv"><div class="gl hm"><div class="inl"><b>OAVS · Full Mock</b><span class="pill tp">⏱ 01:42:10</span></div><div class="htabs"><span class="on">Reasoning</span><span>English</span><span>Computer Science</span></div><p class="hq">Q12. Ravi walks 5 km north, then turns right and walks 3 km. He is now in which direction from the start?</p><div class="ho">A. North-West</div><div class="ho hy">B. North-East</div><div class="ho">C. South-East</div><div class="hpal"><i class="g"></i><i class="g"></i><i class="g"></i><i class="r"></i><i class="g"></i><i class="v"></i><i class="g"></i><i class="n"></i><i class="n"></i><i class="n"></i></div></div><span class="fi f1">🏆</span><span class="fi f2">🎯</span><span class="fi f3">✓</span></div>';

 $d2='<div class="hv"><div class="gl hm"><b>Topic practice (example)</b><div class="tb"><span>Direction Sense</span><div class="bar2"><i style="--w:82%"></i></div></div><div class="tb"><span>Blood Relations</span><div class="bar2"><i style="--w:64%"></i></div></div><div class="tb"><span>Number Series</span><div class="bar2"><i style="--w:91%"></i></div></div><div class="tb"><span>Coding-Decoding</span><div class="bar2"><i style="--w:47%"></i></div></div><div class="tb"><span>Tenses</span><div class="bar2"><i style="--w:73%"></i></div></div></div><span class="fi f1">🎯</span><span class="fi f2">📊</span></div>';

 $d3='<div class="hv"><div class="gl hm"><b>Class analytics (illustration)</b><div class="inl" style="margin-top:12px"><div class="ringc"><b>78%</b></div><div style="flex:1"><div class="mb"><i style="height:50%"></i><i style="height:68%"></i><i style="height:60%"></i><i style="height:82%"></i><i style="height:75%"></i><i style="height:90%"></i></div></div></div><div class="rk"><span>🥇 Rank 1</span><b>92%</b></div><div class="rk"><span>🥈 Rank 2</span><b>88%</b></div><div class="rk"><span>🥉 Rank 3</span><b>85%</b></div></div><span class="fi f1">📊</span><span class="fi f2">🥇</span></div>';

 /* right-hand visual of slide $n: the uploaded image if there is one, otherwise the default card */
 $heroVis=function($n,$default){
  $f=hero_img($n);
  if($f==='') return $default;
  return '<div class="hv"><img class="hero-img" src="'.BASE.'/uploads/hero/'.rawurlencode($f).'" alt="'.h(hero_alt($n)).'" '
        .($n===1?'fetchpriority="high"':'loading="lazy"').' decoding="async"></div>';
 };

 echo '<style>.hv .hero-img{width:100%;max-height:430px;aspect-ratio:4/3;object-fit:cover;border-radius:22px;box-shadow:0 18px 50px rgba(0,0,0,.28);display:block}</style>';

 echo '<section class="hs rv"><div class="hs-bg"></div><i class="blob b1"></i><i class="blob b2"></i><i class="blob b3"></i><i class="spark s1"></i><i class="spark s2"></i><i class="spark s3"></i><div class="hslides">'

 /* slide 1 */
 .'<div class="hslide on"><div><span class="pill">🎯 Free mock tests for govt exams</span><h1>Practise like the real exam. <em>Score higher.</em></h1><p>Real CBT-style mock tests with timer, question palette and mark-for-review, built from each exam&rsquo;s own pattern and syllabus.</p><div class="hcta"><a class="btn p" href="?p=register">Start free →</a><a class="btn ghost" href="?p=login">I have an account</a></div><div class="htr"><span>✓ No payment</span><span>✓ Answers with explanations</span><span>✓ Topic-wise practice</span></div></div>'
 .$heroVis(1,$d1).'</div>'

 /* slide 2 */
 .'<div class="hslide"><div><span class="pill">🎯 Topic-wise practice</span><h1>Practise only what you <em>need</em>.</h1><p>Pick Direction Sense and you get only Direction Sense questions. Every subject is broken into the exact topics in the syllabus.</p><div class="hcta"><a class="btn p" href="?p=register">Try topic practice →</a></div><div class="htr"><span>✓ Syllabus-wise topics</span><span>✓ Weak-topic charts</span></div></div>'
 .$heroVis(2,$d2).'</div>'

 /* slide 3 */
 .'<div class="hslide"><div><span class="pill">🏫 For teachers &amp; schools</span><h1>Your class. Your tests. <em>Your rank list.</em></h1><p>Create papers, share them with your class, a code or everyone, invite students with one link and see analytics instantly.</p><div class="hcta"><a class="btn p" href="'.BASE.'/teacher/">Open teacher panel →</a></div><div class="htr"><span>✓ Referral link</span><span>✓ Rank list</span><span>✓ Class analytics</span></div></div>'
 .$heroVis(3,$d3).'</div>'

 .'</div><button class="hnav hprev" aria-label="Previous">‹</button><button class="hnav hnext" aria-label="Next">›</button><div class="hdots"><i class="hdot on"></i><i class="hdot"></i><i class="hdot"></i></div></section>';

 echo '<div class="stats rv"><div><b data-count="'.(int)$st.'">0</b>Registered students</div><div><b data-count="'.(int)$vs.'">0</b>Total visitors</div><div><b data-count="'.(int)$tc.'">0</b>Teachers</div><div><b data-count="'.(int)$qs.'">0</b>Questions</div><div><b data-count="'.(int)$at.'">0</b>Tests taken</div></div>';

 /* inspiration card (image left, quote right) — managed from Admin → Inspiration */
 ensure_inspire();
 $ins=srows("SELECT * FROM inspire WHERE active=1 ORDER BY RAND() LIMIT 1");
 if($ins){
  $x=$ins[0];
  echo '<style>
  .insp{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:0;margin:28px 0;border-radius:20px;overflow:hidden;border:1px solid rgba(128,128,128,.25);background:linear-gradient(135deg,rgba(79,70,229,.08),rgba(219,39,119,.08))}
  .insp .im{min-height:260px;background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#db2777);display:flex;align-items:center;justify-content:center;font-size:64px}
  .insp .im img{width:100%;height:100%;object-fit:cover;display:block}
  .insp .qt{padding:32px 36px;display:flex;flex-direction:column;justify-content:center}
  .insp .qm{font-size:54px;line-height:.6;color:#7c3aed;font-family:Georgia,serif}
  .insp blockquote{margin:8px 0 14px;font-size:clamp(18px,2.2vw,26px);font-weight:700;line-height:1.4;letter-spacing:-.2px}
  .insp cite{font-style:normal;opacity:.7;font-weight:600}
  @media(max-width:760px){.insp{grid-template-columns:1fr}.insp .im{min-height:200px}.insp .qt{padding:22px}}
  </style><section class="insp rv"><div class="im">'
   .($x['image']?'<img src="'.BASE.'/uploads/inspire/'.rawurlencode($x['image']).'" alt="Inspiration" loading="lazy">':'🌟')
   .'</div><div class="qt"><span class="qm">&ldquo;</span><blockquote>'.nl2br(h($x['quote'])).'</blockquote>'
   .($x['author']?'<cite>— '.h($x['author']).'</cite>':'').'</div></section>';
 }

 echo '<h2 class="rv">Choose your exam</h2><div class="grid">';foreach(exams() as $k=>$e)echo '<a class="card exc rv" href="'.BASE.pux($k).'"><span class="pill">'.h($k).'</span><h3>'.h($e['t']).'</h3><p class="mu">'.array_sum($e['sec']).' questions · '.$e['min'].' min · '.count($e['sec']).' subjects</p><b class="lnk">View pattern &amp; syllabus →</b></a>';echo '</div>';
 echo '<h2 class="rv">Everything you need to crack it</h2><div class="bento">';foreach([['🎯','Real CBT interface','Section tabs, question palette, mark for review, running timer and auto-submit.','w2'],['🎯','Topic-wise practice','Only the topic you pick.',''],['✓','Answers & explanations','Review every question after the test.',''],['📊','Smart dashboard','Accuracy, speed, weak topics and progress charts.','w2'],['⚡','Daily question','Fastest solver wins the board.',''],['🤖','AI Tutor','Ask any doubt, any time.','']] as $f)echo '<div class="card bn rv '.$f[3].'"><div class="bi">'.$f[0].'</div><h3>'.$f[1].'</h3><p class="mu">'.$f[2].'</p></div>';echo '</div>';
 echo '<h2 class="rv">How it works</h2><div class="steps rv"><div class="card"><i>1</i><h3>Create a free account</h3><p class="mu">Verify your email and pick your exam.</p></div><div class="card"><i>2</i><h3>Practise &amp; take mocks</h3><p class="mu">Topic tests or full CBT mocks with the real pattern.</p></div><div class="card"><i>3</i><h3>Review &amp; improve</h3><p class="mu">See answers, explanations and your weak topics.</p></div></div>';
 echo '<h2 class="rv">🏆 Top 10 students</h2>';table(['#','Student','Points'],array_map(function($r,$i){return [$i+1,h($r['name']),$r['points']];},$t=srows("SELECT name,points FROM users WHERE role='student' ORDER BY points DESC LIMIT 10"),array_keys($t)));
 echo '<section class="teach rv"><i class="blob b1"></i><i class="blob b2"></i><h2>🏫 For Teachers &amp; Schools</h2><div class="grid">';foreach([['🔗','Student referral link','One link brings your whole class.'],['🎯','Easy onboarding','Students join with a class code.'],['📝','Create your own tests','Build papers from your questions.'],['📊','Class-wise tracking','See who needs help.'],['🏆','Rank list & analytics','Instant ranks and charts.'],['🔒','Private or public','Share with class or everyone.']] as $f)echo '<div class="gl2"><div class="big">'.$f[0].'</div><b>'.$f[1].'</b><div>'.$f[2].'</div></div>';
 echo '</div><a class="btn p" href="'.BASE.'/teacher/">Open teacher panel →</a></section>';
 echo '<h2 class="rv">🌍 Our vision</h2><div class="grid2 rv"><div class="card"><h3>Vision</h3><p class="mu">Every aspirant in India, in a city or a village, gets free exam-quality practice and a fair shot at their dream school or job.</p></div><div class="card"><h3>Mission</h3><p class="mu">Make preparation simple: real exam patterns, instant feedback and a community that learns together.</p></div></div>';
 echo '<h2 class="rv">FAQ</h2><details class="rv"><summary>Is it free?</summary><p class="mu">Yes, practising mock tests is free.</p></details><details class="rv"><summary>How is the score calculated?</summary><p class="mu">Each correct answer earns marks and each wrong answer deducts the negative marking set for that test.</p></details><details class="rv"><summary>Can my school use it?</summary><p class="mu">Yes. Teachers create papers and share them with their class or publicly.</p></details>';
 echo '<div class="cta rv"><i class="blob b1"></i><i class="blob b2"></i><h2>Your exam is closer than you think</h2><p>Join free and take your first mock test in minutes.</p><a class="btn" href="?p=register">Create free account</a></div>';
}

/* ═══════════════════════════════════════════════════════════════
   REGISTER
   ═══════════════════════════════════════════════════════════════ */
function pg_register(){
 $ref=strtoupper(trim($_GET['ref']??$_POST['ref']??''));
 if($_SERVER['REQUEST_METHOD']==='POST'){ check();
  $n=trim($_POST['name']);$e=strtolower(trim($_POST['email']));$pw=$_POST['password'];
  if(!$n||!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen($pw)<6)flash('Enter your name, a valid email and a password of 6+ characters.');
  elseif(val('SELECT id FROM users WHERE email=?',[$e]))flash('This email is already registered.');
  else{
   $cid=$ref?(val('SELECT id FROM classes WHERE code=?',[$ref])?:null):null;
   q('INSERT INTO users(name,email,pass,role,exam,class_id,verified) VALUES(?,?,?,?,?,?,?)',[$n,$e,password_hash($pw,PASSWORD_DEFAULT),'student',isset(exams()[$_POST['exam']])?$_POST['exam']:(string)key(exams()),$cid,REQUIRE_VERIFY?0:1]);
   $id=db()->lastInsertId();
   if(REQUIRE_VERIFY){
    sendVerify($id);
    flash(!empty($GLOBALS['mailok'])?'Account created! We sent a verification link to '.$e.'. Verify your email, then login.':'Account created! The verification email could not be sent, use the button below.');
    go(BASE.'/?p=login');
   }
   session_regenerate_id(true);
   $_SESSION['uid']=$id;
   $_SESSION['csrf']=bin2hex(random_bytes(16));
   flash('Welcome to '.SITE.'!');
   go(BASE.'/?p=dashboard');
  }
 }
 $f='<h1>Create your free account</h1><p class="mu">Join thousands of aspirants practising smarter.</p><form method="post">'.field().'<input type="hidden" name="ref" value="'.h($ref).'"><label>Full name</label><input name="name" required autocomplete="name" placeholder="Your name"><label>Email</label><input name="email" type="email" required autocomplete="email" placeholder="you@example.com"><label>Password</label><input name="password" type="password" required autocomplete="new-password" data-meter="1" placeholder="At least 6 characters"><label>Target exam</label><select name="exam">';
 foreach(exams() as $k=>$v)$f.='<option value="'.h($k).'">'.h($k.' - '.$v['t']).'</option>';
 $refRow = $ref ? row('SELECT name,school FROM classes WHERE code=?',[$ref]) : null;
 $refNote = '';
 if($ref){
     $refNote = $refRow
        ? '<p class="pill" style="background:#dcfce7;color:#166534">🏫 Joining <b>'.h(classLabel($refRow)).'</b> (code '.h($ref).')</p>'
        : '<p class="pill" style="background:#fef3c7;color:#b45309">⚠ Class code '.h($ref).' not recognised — you can join later from your profile.</p>';
 }
 $f.='</select>'.$refNote
   .'<button class="p" style="width:100%">Create account →</button></form>'
   .'<p class="mu" style="text-align:center">Already registered? <a href="?p=login"><b>Login</b></a></p>';
 echo authPage('g2','✓ Free forever','Start your preparation journey','Real exam-pattern mocks, topic-wise practice and instant explanations.',['🎯 Real CBT-style mock tests','🎯 Topic-wise practice','✓ Answers with explanations','📊 Charts, rank and streaks'],$f);
}

/* ═══════════════════════════════════════════════════════════════
   LOGIN / FORGOT / RESET
   ═══════════════════════════════════════════════════════════════ */
function pg_login(){
 if(isset($_POST['email'])&&doLogin('student','login'))go(BASE.'/?p=dashboard');
 $f='<h1>Welcome back 👋</h1><p class="mu">Log in to continue practising.</p><form method="post">'.field().'<label>Email</label><input name="email" type="email" required autocomplete="email" placeholder="you@example.com"><label>Password</label><input name="password" type="password" required autocomplete="current-password" placeholder="Your password"><button class="p" style="width:100%">Login →</button></form><p class="mu" style="text-align:center"><a href="?p=forgot">Forgot password?</a> · <a href="?p=resend">Resend verification</a><br>New here? <a href="?p=register"><b>Create an account</b></a> · Teacher? <a href="'.BASE.'/teacher/"><b>Teacher login</b></a></p>';
 echo vlinkCard().authPage('g1','🔐 Student login','Pick up where you left off','Your streak, rank and progress are waiting.',['📈 Track your progress','🔥 Keep your streak going','🏆 Climb the leaderboard'],$f);
}
function pg_forgot(){
 check();$card='';
 if(isset($_POST['email'])){
  $u=row('SELECT * FROM users WHERE email=?',[strtolower(trim($_POST['email']))]);
  if($u){$l=sendReset($u);
   if(empty($GLOBALS['mailok'])&&MAIL_FALLBACK_LINK)$card='<div class="card" style="border-color:var(--pr)"><b>📧 We could not send the email</b><p class="mu">'.h($GLOBALS['mailerr']??'').' Use this link to choose a new password:</p><a class="btn p" href="'.h($l).'">Reset my password</a></div>';}
  if(!$card)flash('If that email is registered, a password reset link has been sent. It works for one hour.');
 }
 $f='<h1>Forgot password?</h1><p class="mu">Enter your email and we will send you a reset link.</p><form method="post">'.field().'<label>Email</label><input name="email" type="email" required placeholder="you@example.com"><button class="p" style="width:100%">Send reset link</button></form><p class="mu" style="text-align:center"><a href="?p=login"><b>Back to login</b></a></p>';
 echo $card.authPage('g1','🔑 Account recovery','Reset your password','We will email you a secure link that works for one hour.',['🔒 Secure one-time link','⏱ Valid for 60 minutes'],$f);
}
function pg_reset(){
 check();$t=preg_replace('/[^a-f0-9]/','',$_GET['t']??'');
 $u=$t?row('SELECT * FROM users WHERE rtoken=? AND rexp>?',[$t,time()]):null;
 if(!$u){echo '<div class="auth card"><h2>Link expired</h2><p class="mu">This reset link is invalid or has expired.</p><a class="btn p" href="?p=forgot">Request a new link</a></div>';return;}
 if(isset($_POST['password'])){
  if(strlen($_POST['password'])<6)flash('Use at least 6 characters.');
  else{
   q('UPDATE users SET pass=?,rtoken=NULL,rexp=0,verified=1 WHERE id=?',[password_hash($_POST['password'],PASSWORD_DEFAULT),$u['id']]);
   flash('Password changed. Please login.');
   go($u['role']==='admin'?BASE.'/'.ADMIN_DIR.'/':($u['role']==='teacher'?BASE.'/teacher/':BASE.'/?p=login'));
  }
 }
 $f='<h1>Choose a new password</h1><form method="post">'.field().'<label>New password</label><input name="password" type="password" required data-meter="1" placeholder="At least 6 characters"><button class="p" style="width:100%">Change password</button></form>';
 echo authPage('g1','🔑 Account recovery','Almost there','Pick a strong new password.',['🔒 Use 10+ characters','✓ Mix letters and numbers'],$f);
}

/* ═══════════════════════════════════════════════════════════════
   PROFILE
   ═══════════════════════════════════════════════════════════════ */
function pg_profile(){
    $u=stu();check();
    if(isset($_POST['name'])){
        $n=trim($_POST['name']);
        if($n) q('UPDATE users SET name=?,exam=? WHERE id=?',
                 [$n, isset(exams()[$_POST['exam']])?$_POST['exam']:$u['exam'], $u['id']]);
        if(($_POST['newpass']??'')!==''){
            if(!password_verify($_POST['oldpass']??'',$u['pass'])) flash('Current password is wrong.');
            elseif(strlen($_POST['newpass'])<6)                    flash('New password must be 6+ characters.');
            else{
                q('UPDATE users SET pass=? WHERE id=?',[password_hash($_POST['newpass'],PASSWORD_DEFAULT),$u['id']]);
                flash('Profile and password updated.');
                go(BASE.'/?p=profile');
            }
        }else{
            flash('Profile updated.');
            go(BASE.'/?p=profile');
        }
    }
    $my = myClass($u);
    echo '<div class="welcome"><h2>My profile</h2></div>';
    echo '<div class="card" style="max-width:640px;margin-bottom:16px"><b>🏫 My class</b><p style="margin:8px 0">'
        .($my ? h(classLabel($my)) : '<span class="mu">Not in a class yet.</span>')
        .'</p><a class="btn p" href="'.BASE.'/?p=myclass">'.($my?'Open My Class':'Join a class').' →</a></div>';
    echo '<form class="card" method="post" style="max-width:640px">'.field()
        .'<label>Name</label><input name="name" value="'.h($u['name']).'" required>'
        .'<label>Email</label><input value="'.h($u['email']).'" disabled>'
        .'<label>Target exam</label><select name="exam">';
    foreach(exams() as $k=>$v)
        echo '<option value="'.h($k).'" '.($k===$u['exam']?'selected':'').'>'.h($k.' - '.$v['t']).'</option>';
    echo '</select><h3>Change password (optional)</h3>'
        .'<label>Current password</label><input name="oldpass" type="password">'
        .'<label>New password</label><input name="newpass" type="password" data-meter="1">'
        .'<button class="p">Save changes</button></form>';
}

/* ═══════════════════════════════════════════════════════════════
   HELPERS
   ═══════════════════════════════════════════════════════════════ */
function canSee($pp,$u){
    if(!$pp) return false;
    if(($pp['share'] ?? '')==='public') return true;
    if(($pp['share'] ?? '')==='class'
       && !empty($u['class_id'])
       && (int)$pp['class_id']===(int)$u['class_id']) return true;
    if(($pp['share'] ?? '')==='code'
       && in_array((int)$pp['id'], $_SESSION['codes'] ?? [], true)) return true;
    return false;
}
function myClass($u){
    if(empty($u['class_id'])) return null;
    return row('SELECT * FROM classes WHERE id=?',[(int)$u['class_id']]);
}

/* ═══════════════════════════════════════════════════════════════
   TESTS (student view)
   ═══════════════════════════════════════════════════════════════ */
function pg_tests(){
    $u=stu();
    echo '<div class="card" style="margin-bottom:16px"><b>🏫 Tests from your teacher</b>'
        .'<p class="mu" style="margin:6px 0 10px">Join your school class and open the tests your teacher prepared in <b>My Class</b>.</p>'
        .'<a class="btn p" href="'.BASE.'/?p=myclass">Open My Class →</a></div>';

    echo '<h2>📝 Full mock tests</h2><div class="grid">';
    foreach(exams() as $k=>$e){
        $qcount = (int) val('SELECT COUNT(*) FROM questions WHERE exam=?',[$k]);
        echo '<div class="card"><h3>'.h($k).' Full Mock</h3>'
            .'<div class="mu">'.$e['min'].' min · '.$qcount.' questions in bank</div><br>'
            .($qcount ? '<a class="btn p" href="'.BASE.'/?p=test&m=full&e='.h($k).'">Start</a>'
                      : '<span class="mu">No questions yet.</span>')
            .'</div>';
    }
    echo '</div>';

    echo '<h2>🎯 Sectional (partial) mocks</h2>';
    foreach(exams() as $k=>$e){
        echo '<div class="card"><b>'.h($k).'</b><br>';
        foreach($e['sec'] as $s=>$n){
            $have = (int) val('SELECT COUNT(*) FROM questions WHERE exam=? AND subject=?',[$k,$s]);
            echo $have
                ? '<a class="btn" style="margin:4px" href="'.BASE.'/?p=test&m=sec&e='.h($k).'&s='.urlencode($s).'&n=10">'.h($s).' ('.$have.')</a>'
                : '<span class="btn" style="margin:4px;opacity:.45">'.h($s).' — coming soon</span>';
        }
        echo '</div>';
    }

    echo '<h2>🗂 Practice sets</h2><div class="grid">';
    foreach(rows('SELECT s.*,(SELECT COUNT(*) FROM qset_q WHERE set_id=s.id) n FROM qsets s ORDER BY id DESC') as $x){
        echo '<div class="card"><span class="pill">'.h($x['exam']).'</span><h3>'.h($x['title']).'</h3>'
            .'<div class="mu">'.$x['n'].' Q · '.$x['minutes'].' min</div><br>'
            .'<a class="btn p" href="'.BASE.'/?p=test&m=set&id='.(int)$x['id'].'">Start</a></div>';
    }
    echo '</div>';
}

/* ═══════════════════════════════════════════════════════════════
   MY CLASS — school + class panel (join, teacher tests, doubts)
   ═══════════════════════════════════════════════════════════════ */
function pg_myclass(){
    $u=stu();check();

    /* join a class with the code from the teacher */
    if(isset($_POST['ccode'])){
        $code=strtoupper(trim($_POST['ccode']));
        $c=$code?row('SELECT * FROM classes WHERE code=?',[$code]):null;
        if($c){
            q('UPDATE users SET class_id=? WHERE id=?',[(int)$c['id'],$u['id']]);
            flash('You joined '.classLabel($c).'.');
        }else flash('Invalid class code — please check with your teacher.');
        go(BASE.'/?p=myclass');
    }
    if(isset($_POST['leaveclass'])){
        q('UPDATE users SET class_id=NULL WHERE id=?',[$u['id']]);
        flash('You have left the class.');
        go(BASE.'/?p=myclass');
    }
    /* unlock one teacher test with its test code */
    if(isset($_POST['tcode'])){
        $tt=row('SELECT * FROM tests WHERE code=?',[strtoupper(trim($_POST['tcode']))]);
        if($tt){ $_SESSION['tcodes'][]=(int)$tt['id']; flash('Test unlocked: '.$tt['title']); }
        else flash('Invalid test code.');
        go(BASE.'/?p=myclass');
    }
    /* unlock an older teacher paper with its code */
    if(isset($_POST['pcode'])){
        $pp=row('SELECT * FROM papers WHERE code=?',[strtoupper(trim($_POST['pcode']))]);
        if($pp){ $_SESSION['codes'][]=(int)$pp['id']; flash('Paper unlocked: '.$pp['title']); }
        else flash('Invalid paper code.');
        go(BASE.'/?p=myclass');
    }

    $my=myClass($u);
    $teacher=$my?val('SELECT name FROM users WHERE id=?',[(int)$my['teacher_id']]):'';
    $mates=$my?(int)val('SELECT COUNT(*) FROM users WHERE class_id=? AND role="student"',[(int)$my['id']]):0;

    echo '<style>
    .mc-hero{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;padding:22px 26px;border-radius:18px;background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#db2777);color:#fff;margin-bottom:18px}
    .mc-hero h2{margin:0;color:#fff}.mc-hero p{margin:6px 0 0;color:#e0e7ff}
    .mc-hero .pill{background:#fff;color:#4f46e5}
    .mc-cols{display:grid;gap:16px;grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start}
    .mc-cards{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(270px,1fr))}
    .mc-stack{display:grid;gap:16px}
    .mc-list .it{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(128,128,128,.2)}
    .mc-list .it:last-child{border-bottom:0}
    .mc-list .me{font-weight:700;color:#4f46e5}
    @media(max-width:1000px){.mc-cols{grid-template-columns:1fr}}
    </style>';

    /* ── header ── */
    if($my){
        echo '<div class="mc-hero"><div><h2>🏫 '.h($my['school'] ?: 'My school').'</h2>'
            .'<p>'.h($my['name']).' · Teacher: '.h($teacher ?: '—').' · '.$mates.' students</p></div>'
            .'<div><span class="pill">Class code '.h($my['code']).'</span> '
            .'<form method="post" style="display:inline" onsubmit="return confirm(\'Leave this class?\')">'.field()
            .'<button name="leaveclass" value="1">Leave class</button></form></div></div>';
    }else{
        echo '<div class="mc-hero"><div><h2>🏫 My Class</h2><p>Join your school class to see the tests your teacher has prepared for you.</p></div></div>'
            .'<form class="card" method="post" style="max-width:560px;margin-bottom:18px">'.field()
            .'<b>Join your class</b><p class="mu" style="margin:6px 0 10px">Ask your teacher for the 6-character class code (it is also in the link they share).</p>'
            .'<div class="inl"><input name="ccode" placeholder="Class code e.g. K7P2XM" style="flex:1" required maxlength="12">'
            .'<button class="p">Join class</button></div></form>';
    }

    /* ── tests of my class + tests unlocked by code ── */
    $ids=array_map('intval',$_SESSION['tcodes'] ?? []);
    $in =$ids?implode(',',$ids):'0';
    $myTests=rows("SELECT t.*,u.name tn FROM tests t JOIN users u ON u.id=t.teacher_id
        WHERE t.class_id=? OR t.id IN($in) ORDER BY t.id DESC",[$my?(int)$my['id']:0]);

    $sub=0;$todo=0;$sum=0;$cards=[];
    foreach($myTests as $t){
        $nq =(int)val('SELECT COUNT(*) FROM test_q WHERE test_id=?',[$t['id']]);
        $att=myTAttempt($t['id'],$u['id']);
        list($open,$why)=testWindow($t);
        if($att){ $sub++; $sum+=(int)$att['score']; }
        elseif($open && $nq>0) $todo++;
        $cards[]=[$t,$nq,$att,$open,$why];
    }
    $avg=$sub?round($sum/$sub):0;
    echo tiles([['📝',count($myTests),'Tests for me','#4f46e5'],['✅',$sub,'Submitted','#16a34a'],
                ['⏳',$todo,'Waiting to attempt','#f59e0b'],['📈',$avg.'%','My average','#0891b2']]);

    echo '<div class="mc-cols"><div class="mc-stack">';

    echo '<div><h2 style="margin-top:0">📘 Tests from my teacher</h2>';
    if(!$cards){
        echo '<div class="card mu">'.($my?'No tests yet. Check back later.':'Join your class to see tests.').'</div>';
    }else{
        echo '<div class="mc-cards">';
        foreach($cards as $c){
            list($t,$nq,$att,$open,$why)=$c;
            $label = $t['always_open'] ? '🌐 Always open'
                   : (!$open ? ($why==='Test closed.' ? '🔒 Closed' : '⏳ Opens '.h($t['opens_at']))
                   : ($t['closes_at'] ? '✅ Open until '.h($t['closes_at']) : '✅ Open now'));
            echo '<div class="card"><span class="pill">'.$label.'</span><span class="pill">'.$nq.' Q</span>'
                .($att ? '<span class="pill" style="background:#dcfce7;color:#166534">✅ Submitted · '.(int)$att['score'].'%</span>' : '')
                .'<h3>'.h($t['title']).'</h3><div class="mu">By '.h($t['tn']).' · '.(int)$t['minutes'].' min</div><p style="margin-top:10px">';
            if($att){
                echo '<button class="btn" disabled style="opacity:.5;cursor:not-allowed">Submitted ✓</button> '
                    .'<a class="btn p" href="'.BASE.'/?p=ttresult&id='.(int)$t['id'].'">View result</a>';
            }elseif(!$open){
                echo '<span class="mu">'.$label.'</span>';
            }elseif($nq===0){
                echo '<span class="mu">No questions yet.</span>';
            }else{
                echo '<a class="btn p" href="'.BASE.'/?p=test&m=ttest&id='.(int)$t['id'].'">Start test →</a>'
                    .'<div class="mu" style="margin-top:6px">You can submit this test only once.</div>';
            }
            echo '</p></div>';
        }
        echo '</div>';
    }
    echo '</div>';

    /* older papers shared by the teacher (one attempt only) */
    $pp=rows('SELECT p.*,t.name tn FROM papers p JOIN users t ON t.id=p.teacher_id ORDER BY p.id DESC');
    $vis=array_values(array_filter($pp,function($x) use ($u){ return canSee($x,$u); }));
    if($vis){
        echo '<div><h2>🗂 Other papers from my teacher</h2><div class="mc-cards">';
        foreach($vis as $x){
            $done=row('SELECT id,score FROM attempts WHERE user_id=? AND paper_id=? ORDER BY id LIMIT 1',[$u['id'],$x['id']]);
            echo '<div class="card"><span class="pill">'.($x['share']==='public'?'🌐 Public':($x['share']==='class'?'🔒 My class':'🎟 Code')).'</span>'
                .($done?'<span class="pill" style="background:#dcfce7;color:#166534">✅ Submitted · '.(int)$done['score'].'%</span>':'')
                .'<h3>'.h($x['title']).'</h3><div class="mu">By '.h($x['tn']).' · '.(int)$x['minutes'].' min</div><p style="margin-top:10px">'
                .($done
                    ? '<button class="btn" disabled style="opacity:.5;cursor:not-allowed">Submitted ✓</button> <a class="btn p" href="'.BASE.'/?p=review&id='.(int)$done['id'].'">View answers</a>'
                    : '<a class="btn p" href="'.BASE.'/?p=test&m=paper&id='.(int)$x['id'].'">Start →</a>')
                .'</p></div>';
        }
        echo '</div></div>';
    }
    echo '</div>';   /* /left */

    /* ── right column ── */
    echo '<div class="mc-stack">';

    echo '<form class="card" method="post">'.field().'<b>🔑 Have a test code?</b>'
        .'<p class="mu" style="margin:4px 0 8px">Unlock a test shared by code.</p>'
        .'<div class="inl"><input name="tcode" placeholder="Test code" style="flex:1" required><button class="p">Unlock</button></div></form>';

    if($my){
        echo '<div class="card"><b>💬 Ask my teacher a doubt</b>'
            .'<form method="post" action="'.BASE.'/?p=askdoubt" style="margin-top:8px">'.field()
            .'<textarea name="question" rows="3" placeholder="Type your doubt…" required></textarea>'
            .'<button class="p" style="margin-top:6px">Send doubt →</button></form></div>';

        $dd=rows('SELECT d.*,t.title ttitle FROM class_doubts d LEFT JOIN tests t ON t.id=d.test_id
                  WHERE d.user_id=? ORDER BY d.id DESC LIMIT 8',[$u['id']]);
        echo '<div class="card"><b>📨 My doubts &amp; answers</b>';
        if(!$dd) echo '<p class="mu">No doubts asked yet.</p>';
        foreach($dd as $d){
            echo '<div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(128,128,128,.2)">'
                .'<div><b>'.h($d['question']).'</b></div>'
                .($d['answer']
                    ? '<div style="margin-top:6px;padding:8px 10px;background:#dcfce7;color:#14532d;border-radius:8px"><b>Teacher:</b> '.nl2br(h($d['answer'])).'</div>'
                    : '<div class="mu" style="margin-top:4px">⏳ Waiting for your teacher</div>')
                .'</div>';
        }
        echo '</div>';

        $mates=rows('SELECT id,name,points FROM users WHERE class_id=? AND role="student" ORDER BY points DESC LIMIT 10',[(int)$my['id']]);
        echo '<div class="card"><b>🏆 My class leaderboard</b><div class="mc-list">';
        foreach($mates as $i=>$m)
            echo '<div class="it"><span class="'.((int)$m['id']===(int)$u['id']?'me':'').'">'.($i+1).'. '.h($m['name']).((int)$m['id']===(int)$u['id']?' (you)':'').'</span><span class="pill">'.(int)$m['points'].' pts</span></div>';
        echo '</div></div>';
    }

    echo '<form class="card" method="post">'.field().'<b>📄 Have a paper code?</b>'
        .'<div class="inl" style="margin-top:8px"><input name="pcode" placeholder="Paper code" style="flex:1" required><button class="p">Unlock</button></div></form>';

    echo '</div></div>';   /* /right, /cols */
}

/* ═══════════════════════════════════════════════════════════════
   TEACHER TEST — saved result page (after submit)
   ═══════════════════════════════════════════════════════════════ */
function pg_ttresult(){
    $u=stu();
    $t=row('SELECT * FROM tests WHERE id=?',[(int)($_GET['id']??0)]);
    $a=$t?myTAttempt($t['id'],$u['id']):null;
    if(!$t||!$a){ flash('Result not found.'); go(BASE.'/?p=myclass'); }
    echo '<div class="card"><span class="pill">✅ Submitted</span><h2>'.h($t['title']).'</h2>'
        .'<div class="big">'.(int)$a['correct'].'/'.(int)$a['total'].' · '.(int)$a['score'].'%</div>'
        .'<div class="mu">Time: '.floor($a['secs']/60).'m '.($a['secs']%60).'s</div>'
        .'<a class="btn" href="'.BASE.'/?p=myclass">← Back to My Class</a></div>';
    foreach(ttReview($t['id'],json_decode($a['answers'],true)?:[]) as $n=>$r){
        echo '<div class="card"><b>'.($n+1).'. '.h($r['q']).'</b>';
        foreach($r['o'] as $j=>$x){
            $cls=$j===$r['ans']?'right':(($r['pick']!==null&&$j===$r['pick'])?'wrong':'');
            echo '<div class="opt '.$cls.'" style="padding:6px 10px;border-radius:8px">'.'ABCD'[$j].'. '.h($x).'</div>';
        }
        echo '<div class="mu">'.($r['pick']===null?'<b>Skipped</b> · ':'').'Explanation: '.h($r['exp']?:'—').'</div></div>';
    }
}

/* ═══════════════════════════════════════════════════════════════
   ASK DOUBT
   ═══════════════════════════════════════════════════════════════ */
function pg_askdoubt(){
    $u = stu();
    check();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $question = trim($_POST['question'] ?? '');
        if(empty($u['class_id'])){
            flash('Join a class first.');
        } elseif(strlen($question) < 5){
            flash('Question is too short.');
        } else {
            q('INSERT INTO class_doubts(class_id,user_id,question) VALUES(?,?,?)',
              [(int)$u['class_id'],$u['id'],$question]);
            flash('Your doubt has been sent to the teacher.');
        }
    }
    go(BASE.'/?p=myclass');
}

/* ═══════════════════════════════════════════════════════════════
   AI TUTOR (student chatbot) — used by the full page AND the
   floating button. Tries every provider in config.php in order.
   ═══════════════════════════════════════════════════════════════ */
function aichat(){
    header('Content-Type: application/json');
    try{
        $u=me();
        if(!$u||$u['role']!=='student'){http_response_code(401);echo json_encode(['error'=>'not_logged_in','message'=>'Please log in again.']);return;}
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if(!$csrf||!hash_equals($_SESSION['csrf']??'',$csrf)){http_response_code(401);echo json_encode(['error'=>'csrf','message'=>'Session expired. Reload the page.']);return;}
        $in=json_decode(file_get_contents('php://input'),true)?:[];

        ensure_ai();
        $used=(int)val('SELECT COUNT(*) FROM ai_log WHERE user_id=? AND created>=DATE_SUB(NOW(),INTERVAL 1 HOUR)',[$u['id']]);
        if($used>=30){echo json_encode(['error'=>'limit','message'=>'You have reached 30 questions this hour. Please try again a little later.']);return;}

        $msgs=[];
        foreach(array_slice((array)($in['messages']??[]),-8) as $m){
            $role=(($m['role']??'')==='assistant')?'assistant':'user';
            $txt=trim(mb_substr((string)($m['content']??''),0,2000));
            if($txt==='') continue;
            if(!$msgs && $role!=='user') continue;               // must start with a user turn
            if($msgs && end($msgs)['role']===$role){ $msgs[count($msgs)-1]['content'].="\n".$txt; continue; }  // never two same roles in a row
            $msgs[]=['role'=>$role,'content'=>$txt];
        }
        if(!$msgs||end($msgs)['role']!=='user'){echo json_encode(['error'=>'empty','message'=>'Type a question first.']);return;}

        /* the student's name is NOT sent to the AI provider */
        $sys="You are the friendly AI tutor of ".SITE.", a free mock-test site for Indian government and school-entrance exams (OAVS, SSC, OTET and similar). "
            ."The student is preparing for ".($u['exam']?:'a competitive exam').". "
            ."Answer any question the student asks: studies, exam preparation, concepts, doubts, study plans, career guidance, general knowledge. "
            ."Explain simply and step by step, with a short example when it helps. For maths and reasoning, show the working. "
            ."Reply in the language the student writes in (English, Hindi, Odia, etc.). Keep answers focused, usually under 250 words. "
            ."If you are not sure of a fact, say so instead of guessing. Do not help with cheating on live exams, and refuse anything harmful or unsafe in a kind way.";

        $reply=ai_call($sys,$msgs,false,0.6);
        if($reply===''){
            echo json_encode(['error'=>'ai','message'=>DEBUG?($GLOBALS['aierr']?:'AI failed'):'The AI tutor is busy right now. Please try again in a minute.']);
            return;
        }
        q('INSERT INTO ai_log(user_id) VALUES(?)',[$u['id']]);
        echo json_encode(['reply'=>$reply]);
    }catch(Throwable $e){
        http_response_code(500);
        echo json_encode(['error'=>'server','message'=>DEBUG?$e->getMessage():'Server error']);
    }
}

function pg_askai(){
    $u=stu();
    echo '<style>
    .ai-wrap{max-width:860px;margin:0 auto}
    .ai-box{height:min(60vh,540px);overflow-y:auto;padding:16px;border:1px solid rgba(128,128,128,.25);border-radius:16px;background:rgba(128,128,128,.05);display:flex;flex-direction:column;gap:10px}
    .ai-m{max-width:88%;padding:10px 14px;border-radius:14px;line-height:1.55;word-wrap:break-word}
    .ai-m.user{align-self:flex-end;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-bottom-right-radius:4px}
    .ai-m.assistant{align-self:flex-start;background:rgba(79,70,229,.10);border-bottom-left-radius:4px}
    .ai-m.err{align-self:flex-start;background:#fee2e2;color:#991b1b}
    .ai-m pre{white-space:pre-wrap;background:rgba(0,0,0,.08);padding:8px;border-radius:8px;margin:6px 0}
    .ai-m code{background:rgba(0,0,0,.08);padding:1px 5px;border-radius:5px}
    .ai-in{display:flex;gap:8px;margin-top:10px}
    .ai-in textarea{flex:1;margin:0;resize:none}
    .ai-chips{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
    .ai-chips button{font-size:13px}
    </style>';
    echo '<div class="ai-wrap"><div class="welcome"><h2>🤖 AI Tutor</h2>'
        .'<p class="mu">Hi '.h($u['name']).'! Ask any doubt: concepts, tricks, study plans, anything. The AI can make mistakes, so double-check important facts.</p></div>'
        .'<div class="ai-chips"><button type="button" data-q="Explain Direction Sense with an easy example">Direction Sense</button>'
        .'<button type="button" data-q="Give me a 30-day study plan for '.h($u['exam']?:'my exam').'">30-day plan</button>'
        .'<button type="button" data-q="Tricks to solve percentage questions fast">Percentage tricks</button>'
        .'<button type="button" data-q="Explain tenses in English grammar with examples">Tenses</button></div>'
        .'<div class="ai-box" id="aiBox"><div class="ai-m assistant">Hello! What would you like to learn today?</div></div>'
        .'<div class="ai-in"><textarea id="aiIn" rows="2" maxlength="2000" placeholder="Type your question… (Enter to send, Shift+Enter for new line)"></textarea>'
        .'<button class="p" type="button" id="aiSend">Send ➤</button></div></div>';
    $js=<<<'JS'
(function(){
var U="__URL__",C="__CSRF__",box=document.getElementById("aiBox"),inp=document.getElementById("aiIn"),btn=document.getElementById("aiSend"),hist=[],busy=false;
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function fmt(s){return esc(s).replace(/```([\s\S]*?)```/g,'<pre>$1</pre>').replace(/`([^`]+)`/g,'<code>$1</code>').replace(/\*\*([^*]+)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>')}
function add(cls,html){var d=document.createElement("div");d.className="ai-m "+cls;d.innerHTML=html;box.appendChild(d);box.scrollTop=box.scrollHeight;return d}
function send(t){
  t=(t||"").trim();if(!t||busy)return;
  busy=true;btn.disabled=true;inp.value="";
  add("user",esc(t).replace(/\n/g,"<br>"));hist.push({role:"user",content:t});
  var w=add("assistant","Thinking…");
  fetch(U,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-CSRF-Token":C},body:JSON.stringify({messages:hist})})
  .then(function(r){return r.json()})
  .then(function(r){
    w.remove();
    if(r&&r.reply){add("assistant",fmt(r.reply));hist.push({role:"assistant",content:r.reply})}
    else{hist.pop();add("err",esc((r&&r.message)||"Something went wrong. Try again."))}
  })
  .catch(function(){w.remove();hist.pop();add("err","Network problem. Please try again.")})
  .then(function(){busy=false;btn.disabled=false;inp.focus()});
}
btn.onclick=function(){send(inp.value)};
inp.addEventListener("keydown",function(e){if(e.key==="Enter"&&!e.shiftKey){e.preventDefault();send(inp.value)}});
document.querySelectorAll(".ai-chips button").forEach(function(b){b.onclick=function(){send(b.dataset.q)}});
})();
JS;
    echo '<script>'.str_replace(['__URL__','__CSRF__'],[BASE.'/?p=aichat',csrf()],$js).'</script>';
}

/* ═══════════════════════════════════════════════════════════════
   REQUESTS
   ═══════════════════════════════════════════════════════════════ */
function pg_requests(){
    $u = stu();
    check();

    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['body'])){
        $exam    = $_POST['exam']    ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $topic   = trim($_POST['topic']   ?? '');
        $body    = trim($_POST['body']    ?? '');
        $want    = max(0, min(100, (int)($_POST['want_count'] ?? 0)));

        if(!isset(exams()[$exam]))         flash('Choose a valid exam.');
        elseif(strlen($body) < 15)         flash('Please describe your request in at least 15 characters.');
        else{
            $today = val("SELECT COUNT(*) FROM requests WHERE user_id=? AND DATE(created)=CURDATE()",[$u['id']]);
            if($today >= 3){
                flash('You can send up to 3 requests per day. Try again tomorrow.');
            }else{
                q('INSERT INTO requests(user_id,exam,subject,topic,body,want_count)
                   VALUES(?,?,?,?,?,?)',
                   [$u['id'],$exam,$subject,$topic,$body,$want]);
                flash('Request sent! Our team will review it shortly.');
                go(BASE.'/?p=requests');
            }
        }
    }

    $rows = rows('SELECT r.*,s.title set_title
                  FROM requests r
                  LEFT JOIN qsets s ON s.id=r.set_id
                  WHERE r.user_id=?
                  ORDER BY r.id DESC',
                 [$u['id']]);

    $E = exams();
    $uExam = $E[$u['exam']] ?? reset($E);

    echo '<div class="welcome"><h2>💡 Request a set</h2>'
        .'<p class="mu">Want more questions on a topic, a harder mock, or a specific pattern? '
        .'Ask here and our team will build it. You can send up to 3 requests per day.</p></div>';

    echo '<form class="card" method="post">'.field();

    echo '<div class="grid3">';
    echo '<div><label class="mu">Exam</label><select name="exam" id="rqExam">';
    foreach($E as $k=>$v)
        echo '<option value="'.h($k).'" '.($k===$u['exam']?'selected':'').'>'.h($k.' - '.$v['t']).'</option>';
    echo '</select></div>';

    echo '<div><label class="mu">Subject (optional)</label><select name="subject" id="rqSub"><option value="">Any</option>';
    foreach(($uExam['topics']??[]) as $s=>$ts)
        echo '<option value="'.h($s).'">'.h($s).'</option>';
    echo '</select></div>';

    echo '<div><label class="mu">Topic (optional)</label><select name="topic" id="rqTop"><option value="">Any</option></select></div>';
    echo '</div>';

    echo '<label class="mu">What do you want? (be specific — helps us build the right set)</label>'
        .'<textarea name="body" rows="3" required minlength="15" placeholder="Example: 15 hard-level Blood Relations questions with detailed explanations, like SSC CGL."></textarea>';

    echo '<label class="mu">How many questions would you like? (optional)</label>'
        .'<input type="number" name="want_count" value="15" min="0" max="100" style="width:140px">';

    echo '<button class="p" style="margin-top:10px">Send request →</button>';
    echo '</form>';

    echo '<script>var RQ='.json_encode(array_map(function($e){
        return ['subjects'=>$e['topics']];
    },$E),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS).';</script>';

    echo '<h2>Your requests</h2>';
    if(!$rows){
        echo '<div class="card mu">No requests yet. Use the form above to send your first one.</div>';
        return;
    }

    $badge = ['pending'=>'⏳ Pending','fulfilled'=>'✅ Fulfilled','declined'=>'❌ Declined'];
    $color = ['pending'=>'#f59e0b','fulfilled'=>'#16a34a','declined'=>'#ef4444'];

    echo '<div class="grid">';
    foreach($rows as $r){
        echo '<div class="card">'
            .'<span class="pill" style="background:'.$color[$r['status']].'22;color:'.$color[$r['status']].'">'
            .$badge[$r['status']].'</span> '
            .'<span class="pill">'.h($r['exam']).'</span>'
            .($r['subject']?' <span class="pill">'.h($r['subject']).'</span>':'')
            .($r['topic']?' <span class="pill">'.h($r['topic']).'</span>':'');

        echo '<p style="margin:10px 0;white-space:pre-wrap">'.h($r['body']).'</p>';

        if($r['want_count']>0)
            echo '<div class="mu">Requested '.$r['want_count'].' questions</div>';
        echo '<div class="mu">Sent '.h(substr($r['created'],0,10)).'</div>';

        if($r['status']==='fulfilled' && $r['set_id']){
            echo '<p style="margin-top:10px">'
                .'<a class="btn p" href="'.BASE.'/?p=test&m=set&id='.$r['set_id'].'">▶ Start the set now</a>'
                .'</p>';
        }
        if($r['admin_note']){
            echo '<div class="mu" style="margin-top:8px;padding:8px 10px;background:#eef2ff;border-radius:8px">'
                .'<b>Admin note:</b> '.h($r['admin_note']).'</div>';
        }
        echo '</div>';
    }
    echo '</div>';

    echo '<script>
    (function(){
      var data=RQ, ex=document.getElementById("rqExam"), sub=document.getElementById("rqSub"), top=document.getElementById("rqTop");
      function fill(el,arr,any){el.innerHTML="";if(any){var o=document.createElement("option");o.value="";o.textContent="Any";el.appendChild(o)}arr.forEach(function(k){var o=document.createElement("option");o.value=k;o.textContent=k;el.appendChild(o)})}
      function subjects(){return Object.keys((data[ex.value]||{}).subjects||{})}
      function topics(){return ((data[ex.value]||{}).subjects||{})[sub.value]||[]}
      function refreshSub(){fill(sub,subjects(),true);refreshTop()}
      function refreshTop(){fill(top,topics(),true)}
      ex.onchange=refreshSub; sub.onchange=refreshTop;
      refreshSub();
    })();
    </script>';
}

/* ═══════════════════════════════════════════════════════════════
   FEEDBACK (open to everyone: guests, students, teachers)
   ═══════════════════════════════════════════════════════════════ */
function pg_feedback(){
 ensure_feedback();
 $u=me();
 $cats=['General','Mock tests','Practice','AI Tutor','Design / ease of use','Bug / problem','Suggestion'];
 $v=function($k,$d=''){ return h($_POST[$k] ?? $d); };

 if($_SERVER['REQUEST_METHOD']==='POST'){
  check();
  if(trim($_POST['website']??'')!==''){                      // hidden field: only bots fill it
   flash('Thank you for your feedback!'); go(BASE.'/?p=feedback');
  }
  $msg   =trim($_POST['message']??'');
  $rating=(int)($_POST['rating']??0);
  $cat   =in_array($_POST['category']??'',$cats,true)?$_POST['category']:'General';
  $name  =mb_substr(trim($_POST['name']??''),0,80);
  $email =trim($_POST['email']??'');
  if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) flash('Please enter a valid email, or leave it empty.');
  elseif($rating<1||$rating>5)                                  flash('Please choose a star rating.');
  elseif(mb_strlen($msg)<10)                                    flash('Please write at least 10 characters.');
  else{
   $ip=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.DB_NAME);
   if((int)val('SELECT COUNT(*) FROM feedback WHERE ip_hash=? AND created>=DATE_SUB(NOW(),INTERVAL 1 HOUR)',[$ip])>=3)
    flash('You have sent a few messages already. Please try again later.');
   else{
    q('INSERT INTO feedback(user_id,role,name,email,rating,category,message,ip_hash) VALUES(?,?,?,?,?,?,?,?)',
      [$u?(int)$u['id']:null,$u?$u['role']:'guest',$name,$email,$rating,$cat,mb_substr($msg,0,2000),$ip]);
    flash('Thank you! Your feedback has been sent to our team.');
    go(BASE.'/?p=feedback');
   }
  }
 }

 echo '<style>
 .fb-wrap{max-width:680px;margin:0 auto}
 .stars{display:inline-flex;flex-direction:row-reverse;gap:4px}
 .stars input{display:none}
 .stars label{font-size:36px;line-height:1;cursor:pointer;color:#cbd5e1;transition:.15s}
 .stars input:checked ~ label,.stars label:hover,.stars label:hover ~ label{color:#f59e0b}
 .fb-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
 </style><div class="fb-wrap"><div class="welcome"><h2>⭐ Feedback</h2>'
 .'<p class="mu">Tell us what you like and what we should improve. Everyone can send feedback.</p></div>'
 .'<form class="card" method="post">'.field()
 .'<label>How would you rate '.h(SITE).'?</label><div class="stars">';
 $sel=(int)($_POST['rating']??0);
 for($i=5;$i>=1;$i--)
  echo '<input type="radio" name="rating" id="st'.$i.'" value="'.$i.'"'.($sel===$i?' checked':'').'><label for="st'.$i.'" title="'.$i.' star'.($i>1?'s':'').'">★</label>';
 echo '</div><label>What is it about?</label><select name="category">';
 foreach($cats as $c) echo '<option'.((($_POST['category']??'')===$c)?' selected':'').'>'.h($c).'</option>';
 echo '</select><label>Your feedback</label>'
 .'<textarea name="message" rows="5" maxlength="2000" required placeholder="What worked well? What was confusing or broken? What would you like to see?">'.$v('message').'</textarea>'
 .'<div class="grid2"><div><label>Name (optional)</label><input name="name" maxlength="80" value="'.$v('name',$u['name']??'').'"></div>'
 .'<div><label>Email (optional, only if you want a reply)</label><input name="email" type="email" maxlength="150" value="'.$v('email',$u['email']??'').'"></div></div>'
 .'<div class="fb-hp" aria-hidden="true"><label>Leave this empty</label><input name="website" tabindex="-1" autocomplete="off"></div>'
 .'<button class="p">Send feedback →</button></form></div>';
}

/* ═══════════════════════════════════════════════════════════════
   TEST (CBT interface — supports m=ttest)
   Test pages are "bare" → no floating buttons during an exam.
   ═══════════════════════════════════════════════════════════════ */
function pg_test(){
 $GLOBALS['bare']=true;
 $u=stu();
 $m=$_GET['m']??'full';

 /* ── teacher test mode ── */
 if($m==='ttest'){
     $t = row('SELECT * FROM tests WHERE id=?',[(int)($_GET['id']??0)]);
     if(!$t || !canTakeTest($t,$u)){ flash('Test not available.'); go(BASE.'/?p=myclass'); }

     /* one attempt only: already submitted -> show the saved result */
     if(myTAttempt($t['id'],$u['id'])){
         flash('You have already submitted this test.');
         go(BASE.'/?p=ttresult&id='.(int)$t['id']);
     }
     list($ok,$why) = testWindow($t);
     if(!$ok){ flash($why); go(BASE.'/?p=myclass'); }

     $qs = rows('SELECT q.* FROM test_q tq JOIN tq q ON q.id=tq.tq_id
         WHERE tq.test_id=? ORDER BY tq.ord',[$t['id']]);
     if(!$qs){ flash('No questions in this test.'); go(BASE.'/?p=myclass'); }

     $testJson = [
         'title'=>$t['title'],
         'name'=>$u['name'],
         'min'=>(int)$t['minutes'],
         'marks'=>1,'neg'=>0,
         'qs'=>array_map(function($r){
             return ['id'=>(int)$r['id'],'s'=>$r['subject']?:'General','q'=>$r['q'],
                     'o'=>[$r['a'],$r['b'],$r['c'],$r['d']]];
         },$qs),
         'csrf'=>csrf(),
         'url'=>BASE.'/?p=submit_ttest',
         'dash'=>BASE.pu('dashboard'),
         'tests'=>BASE.'/?p=myclass',
         'test_id'=>(int)$t['id'],
     ];
     echo '<div id="t"></div><script>var TEST='.json_encode($testJson,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS).';</script>';
     return;
 }

 $e=$_GET['e']??(string)key(exams());
 $src='bank';$ids=[];$min=30;$title='';$paper=null;$mk=1.0;$ng=0.25;
 if($m!=='paper'&&$m!=='set'&&!isset(exams()[$e]))go(BASE.'/?p=tests');

 if($m==='paper'){
  $pp=row('SELECT * FROM papers WHERE id=?',[(int)($_GET['id']??0)]);
  if(!$pp||!canSee($pp,$u)){flash('Paper not available');go(BASE.'/?p=myclass');}
  if(val('SELECT id FROM attempts WHERE user_id=? AND paper_id=? LIMIT 1',[$u['id'],$pp['id']])){flash('You have already submitted this paper.');go(BASE.'/?p=myclass');}
  $src='paper';
  $ids=array_column(rows('SELECT id FROM paper_q WHERE paper_id=?',[$pp['id']]),'id');
  shuffle($ids);
  $min=(int)$pp['minutes'];$title=$pp['title'];$e=$pp['exam'];$paper=$pp['id'];
 }
 elseif($m==='set'){
  $st=row('SELECT * FROM qsets WHERE id=?',[(int)($_GET['id']??0)]);
  if(!$st){flash('Set not found');go(BASE.'/?p=tests');}
  $ids=array_column(rows('SELECT qid FROM qset_q WHERE set_id=? ORDER BY ord',[$st['id']]),'qid');
  /* older sets may already hold the same question twice — show each question only once */
  $seenT=[];$uniq=[];
  foreach($ids as $qid){
      $k=qnorm(val('SELECT q FROM questions WHERE id=?',[$qid]));
      if($k!=='' && isset($seenT[$k])) continue;
      $seenT[$k]=1; $uniq[]=$qid;
  }
  $ids=$uniq;
  $title=$st['title'];$e=$st['exam'];$min=(int)$st['minutes'];$mk=(float)$st['marks'];$ng=(float)$st['neg'];
 }
 else{
  $tot=array_sum(exams()[$e]['sec']);
  $sn=[];$stx=[];                                   // shared "already chosen" lists → no repeats in one test
  if($m==='sec'){
   $s=$_GET['s']??'';$tp=$_GET['t']??'';
   $n=min(50,max(5,(int)($_GET['n']??10)));
   pickQ('exam=? AND subject=? AND (?=\'\' OR topic=?)',[$e,$s,$tp,$tp],$n,$ids,$sn,$stx);
   $title="$e · $s".($tp?" · $tp":'');
  }else{
   foreach(exams()[$e]['sec'] as $s=>$n)
    pickQ('exam=? AND subject=?',[$e,$s],(int)$n,$ids,$sn,$stx);
   $title=exams()[$e]['t'].' Full Mock';
  }
  $min=max(5,(int)round(exams()[$e]['min']*count($ids)/max(1,$tot)));
 }
 if(!$ids){flash('No questions available yet. Ask your admin to add some.');go(BASE.'/?p=tests');}

 $in=implode(',',array_map('intval',$ids));
 $rs=$src==='paper'
    ?rows("SELECT id,'Paper' AS subject,q,a,b,c,d FROM paper_q WHERE id IN($in)")
    :rows("SELECT id,subject,q,a,b,c,d FROM questions WHERE id IN($in)");
 $pos=array_flip($ids);usort($rs,function($a,$b)use($pos){return $pos[$a['id']]<=>$pos[$b['id']];});
 $qs=array_map(function($r){return ['id'=>(int)$r['id'],'s'=>$r['subject'],'q'=>$r['q'],'o'=>[$r['a'],$r['b'],$r['c'],$r['d']]];},$rs);

 $def=['src'=>$src,'ids'=>array_column($qs,'id'),'start'=>time(),'min'=>$min,
       'exam'=>$e,'kind'=>$m,'paper'=>$paper,'marks'=>$mk,'neg'=>$ng];

 /* The test definition stays on the SERVER (in the session).
    The browser only gets a random token, so nothing can be tampered with
    and there is no signature that can fail to match. */
 $tok=bin2hex(random_bytes(12));
 $def['tok']=$tok;
 if(empty($_SESSION['tests'])||!is_array($_SESSION['tests'])) $_SESSION['tests']=[];
 $_SESSION['tests'][$tok]=$def;
 while(count($_SESSION['tests'])>10) array_shift($_SESSION['tests']);
 $_SESSION['t']=$def;

 echo '<div id="t"></div><script>var TEST='.json_encode([
   'title'=>$title,'name'=>$u['name'],'min'=>$min,'marks'=>$mk,'neg'=>$ng,
   'qs'=>$qs,'csrf'=>csrf(),'url'=>BASE.'/?p=submit',
   'dash'=>BASE.pu('dashboard'),'tests'=>BASE.pu('tests'),'def'=>['tok'=>$tok]
 ],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS).';</script>';
}

/* ═══════════════════════════════════════════════════════════════
   SUBMIT (regular tests)
   ═══════════════════════════════════════════════════════════════ */
function submit(){
    header('Content-Type: application/json');
    try{
        $u=me();
        $in=json_decode(file_get_contents('php://input'),true) ?: [];
        if(!$u){http_response_code(401);echo json_encode(['error'=>'not_logged_in']);return;}

        $csrfIn=$_SERVER['HTTP_X_CSRF_TOKEN']??($in['csrf']??'');
        if(!$csrfIn||!hash_equals($_SESSION['csrf']??'',$csrfIn)){
            http_response_code(401);echo json_encode(['error'=>'csrf_mismatch']);return;
        }

        /* test definition is read from the server-side session, found by token */
        $tok=(string)($in['def']['tok']??'');
        $t=($tok!=='' && isset($_SESSION['tests'][$tok])) ? $_SESSION['tests'][$tok] : ($_SESSION['t']??null);
        if(!$t||empty($t['ids'])){http_response_code(401);echo json_encode(['error'=>'no_test']);return;}

        if(!empty($t['paper']) && val('SELECT id FROM attempts WHERE user_id=? AND paper_id=? LIMIT 1',[$u['id'],$t['paper']])){
            http_response_code(400);echo json_encode(['error'=>'already_submitted']);return;
        }
        unset($_SESSION['t']);
        if($tok!=='') unset($_SESSION['tests'][$tok]);
        $tbl=$t['src']==='paper'?'paper_q':'questions';
        $c=$w=0;$rev=[];$items=[];$sec=[];

        foreach($t['ids'] as $id){
            $r=row("SELECT * FROM $tbl WHERE id=?",[$id]);if(!$r)continue;
            $pick=isset($in['ans'][$id])?(int)$in['ans'][$id]:null;
            $items[]=[$id,$pick];
            $sn=$tbl==='questions'?$r['subject']:'Paper';
            if(!isset($sec[$sn]))$sec[$sn]=['n'=>$sn,'t'=>0,'c'=>0,'w'=>0];
            $sec[$sn]['t']++;
            if($pick!==null){
                if($pick===(int)$r['ans']){$c++;$sec[$sn]['c']++;}
                else{$w++;$sec[$sn]['w']++;}
            }
            $rev[]=['q'=>$r['q'],'o'=>[$r['a'],$r['b'],$r['c'],$r['d']],'ans'=>(int)$r['ans'],'pick'=>$pick,'exp'=>$r['exp']];
        }

        $n=count($rev);
        $mk=(float)$t['marks'];$ng=(float)$t['neg'];
        $m=$c*$mk-$w*$ng;$max=$n*$mk;
        $sc=$max>0?(int)round(max(0,$m)*100/$max):0;
        $secs=max(1,min((int)($in['secs']??(time()-$t['start'])),$t['min']*60+120));
        $pts=$c*10+($sc>=80?50:0);

        q('INSERT INTO attempts(user_id,paper_id,exam,kind,total,correct,wrong,score,secs,marks,maxm) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
          [$u['id'],$t['paper'],$t['exam'],$t['kind'],$n,$c,$w,$sc,$secs,$m,$max]);
        addPoints($u['id'],$pts);
        $aid=db()->lastInsertId();

        foreach($items as $i)
            q('INSERT INTO attempt_items(attempt_id,qid,src,pick) VALUES(?,?,?,?)',
              [$aid,$i[0],$t['src'],$i[1]]);

        $GLOBALS['mailok']=false;
        if(MAIL_RESULTS && $u['verified']){
            $cert=certificateHtml($u['name'],$t['exam'],$sc,$c,$n,$m,$max,$secs,$aid,false);
            $intro='<p>Hi '.h($u['name']).', here is your result for <b>'.h($t['exam']).'</b>:</p>'
                 .'<p>Marks: <b>'.round($m,2).' / '.$max.'</b> ('.$sc.'%)<br>'
                 .$c.' correct, '.$w.' wrong, '.($n-$c-$w).' skipped<br>'
                 .'Time: '.floor($secs/60).' min<br>Points earned: '.$pts.'</p>'
                 .'<p><a href="'.baseUrl().pu('certificate','id='.$aid).'">View certificate</a> · '
                 .'<a href="'.baseUrl().pu('certificate','id='.$aid.'&print=1').'">Download / print PDF</a> · '
                 .'<a href="'.baseUrl().pu('review','id='.$aid).'">See all answers</a></p><hr>';
            mailTo($u['email'],'Your result & certificate: '.$t['exam'].' - '.$sc.'%',$intro.$cert);
        }

        $sections=array_values(array_map(function($x)use($mk,$ng){
            $x['k']=$x['t']-$x['c']-$x['w'];
            $x['m']=round($x['c']*$mk-$x['w']*$ng,2);
            return $x;
        },$sec));

        echo json_encode([
            'rid'=>$aid,'correct'=>$c,'wrong'=>$w,'total'=>$n,'score'=>$sc,
            'marks'=>round($m,2),'max'=>$max,'secs'=>$secs,'points'=>$pts,
            'sections'=>$sections,'review'=>$rev,
            'certificate'=>BASE.pu('certificate','id='.$aid),
            'certificates'=>BASE.pu('certificates'),
            'mailok'=>!empty($GLOBALS['mailok'])
        ]);
    }catch(Throwable $e){
        http_response_code(500);
        echo json_encode([
            'error'=>'server_error',
            'message'=>DEBUG ? $e->getMessage() : 'Server error during submit'
        ]);
    }
}

/* ═══════════════════════════════════════════════════════════════
   SUBMIT (teacher test) — one attempt only, window enforced
   ═══════════════════════════════════════════════════════════════ */
function submit_ttest(){
    header('Content-Type: application/json');
    try{
        $u = me();
        $in = json_decode(file_get_contents('php://input'),true) ?: [];
        if(!$u){ http_response_code(401); echo json_encode(['error'=>'not_logged_in']); return; }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf'] ?? '');
        if(!$csrf || !hash_equals($_SESSION['csrf'] ?? '', $csrf)){
            http_response_code(401); echo json_encode(['error'=>'csrf']); return;
        }
        $tid = (int)($in['test_id'] ?? 0);
        $t = row('SELECT * FROM tests WHERE id=?',[$tid]);
        if(!$t || !canTakeTest($t,$u)){
            http_response_code(400); echo json_encode(['error'=>'bad_test']); return;
        }

        /* already submitted (double click / second tab / back button)
           -> return the SAVED result, never save twice */
        $old = myTAttempt($tid,$u['id']);
        if($old){
            echo json_encode([
                'ok'=>true,'already'=>true,'correct'=>(int)$old['correct'],'wrong'=>(int)$old['wrong'],
                'total'=>(int)$old['total'],'score'=>(int)$old['score'],'secs'=>(int)$old['secs'],
                'review'=>ttReview($tid,json_decode($old['answers'],true) ?: []),
                'tests'=>BASE.'/?p=myclass',
            ]); return;
        }

        list($ok,$why) = testWindow($t);
        if(!$ok){ http_response_code(400); echo json_encode(['error'=>'closed','message'=>$why]); return; }

        $qs = rows('SELECT q.* FROM test_q tq JOIN tq q ON q.id=tq.tq_id WHERE tq.test_id=? ORDER BY tq.ord',[$tid]);
        $c=$w=0; $total=count($qs); $answers=[];
        foreach($qs as $r){
            $pick = isset($in['ans'][$r['id']]) ? (int)$in['ans'][$r['id']] : null;
            $answers[$r['id']] = $pick;
            if($pick !== null){
                if($pick === (int)$r['ans']) $c++;
                else $w++;
            }
        }
        $secs  = max(1, min((int)($in['secs'] ?? 0), (int)$t['minutes']*60+120));
        $score = $total ? (int)round($c*100/$total) : 0;

        try{
            q('INSERT INTO test_attempts(test_id,user_id,total,correct,wrong,score,marks,maxm,secs,answers)
               VALUES(?,?,?,?,?,?,?,?,?,?)',
              [$tid,$u['id'],$total,$c,$w,$score,$c,$total,$secs,json_encode($answers)]);
            addPoints($u['id'], $c*10);
        }catch(PDOException $e){
            /* duplicate (unique key test_id+user_id) -> a parallel request already saved it */
            if($e->getCode()!=='23000') throw $e;
        }
        echo json_encode([
            'ok'=>true,'correct'=>$c,'wrong'=>$w,'total'=>$total,
            'score'=>$score,'secs'=>$secs,
            'review'=>ttReview($tid,$answers),
            'tests'=>BASE.'/?p=myclass',
        ]);
    } catch(Throwable $e){
        http_response_code(500);
        echo json_encode(['error'=>'server','message'=>DEBUG?$e->getMessage():'Server error']);
    }
}

/* ═══════════════════════════════════════════════════════════════
   CERTIFICATES
   ═══════════════════════════════════════════════════════════════ */
function pg_certificates(){
    $u = stu();
    $rows = rows('SELECT * FROM attempts WHERE user_id=? ORDER BY id DESC',[$u['id']]);

    echo '<div class="welcome"><h2>🏆 My certificates</h2>'
        .'<p class="mu">Every test you have taken. Open the certificate, or review answers with explanations.</p></div>';

    if(!$rows){
        echo '<div class="card"><b>No tests yet.</b>'
            .'<p class="mu">Take a mock test and your certificate will appear here.</p>'
            .'<a class="btn p" href="'.BASE.'/?p=tests">Browse tests</a></div>';
        return;
    }

    $kindMap = ['full'=>'Full mock','sec'=>'Topic / subject','set'=>'Practice set','paper'=>'Teacher paper'];

    echo '<div class="grid">';
    foreach($rows as $a){
        $date = substr($a['created'],0,10);
        $kind = $kindMap[$a['kind']] ?? $a['kind'];
        $hasItems = (int) val('SELECT COUNT(*) FROM attempt_items WHERE attempt_id=?',[$a['id']]);
        $reviewBtn = $hasItems
            ? '<a class="btn" href="'.BASE.pu('review','id='.$a['id']).'">📝 Answers &amp; explanations</a>'
            : '<a class="btn" style="opacity:.55;pointer-events:none" title="No per-question data saved for this attempt">📝 Answers (not saved)</a>';

        echo '<div class="card">'
            .'<span class="pill">'.h($a['exam']).'</span> <span class="pill">'.h($kind).'</span>'
            .'<h3>'.$a['score'].'% · '.round($a['marks'],2).' / '.round($a['maxm'],2).' marks</h3>'
            .'<div class="mu">'.h($date).' · '.$a['correct'].'/'.$a['total'].' correct · '
            .floor($a['secs']/60).' min'
            .($hasItems ? '' : ' · answers not stored')
            .'</div>'
            .'<p style="margin-top:10px">'
            .'<a class="btn p" href="'.BASE.pu('certificate','id='.$a['id']).'">🏆 Certificate</a> '
            .$reviewBtn
            .'</p>'
            .'</div>';
    }
    echo '</div>';
}

function pg_certificate(){
 $u=stu();
 $id=(int)($_GET['id']??0);
 $a=row('SELECT * FROM attempts WHERE id=? AND user_id=?',[$id,$u['id']]);
 if(!$a){flash('Certificate not found.');go(BASE.'/?p=certificates');}
 $GLOBALS['seo']=['noindex'=>1,'t'=>'Certificate · '.$a['exam'].' · '.SITE];
 $GLOBALS['bare']=true;

 echo certificateHtml($u['name'],$a['exam'],$a['score'],$a['correct'],$a['total'],
                     $a['marks'],$a['maxm'],$a['secs'],$a['id'],true);

 echo '<div class="no-print" style="text-align:center;margin:18px 0">'
     .'<button class="btn p" onclick="window.print()" style="font-size:16px;padding:10px 22px">'
     .'⬇ Download / Print as PDF</button> '
     .'<a class="btn" href="'.BASE.pu('certificates').'">← My certificates</a> '
     .'<a class="btn" href="'.BASE.pu('review','id='.$a['id']).'">📝 Answers &amp; explanations</a>'
     .'</div>'
     .'<style>@media print{.no-print{display:none!important}}</style>';

 if(!empty($_GET['print'])){
     echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},500)})</script>';
 }
}

/* ═══════════════════════════════════════════════════════════════
   DAILY QUESTION
   ═══════════════════════════════════════════════════════════════ */
function pg_daily(){
 $u=stu();$q=qotdToday();
 echo '<h2>⚡ Question of the Day</h2>';
 if(!$q){echo '<div class="card mu">No questions yet. Check back soon.</div>';return;}
 $a=row('SELECT * FROM qotd_ans WHERE qid=? AND user_id=?',[$q['id'],$u['id']]);
 if(($_SESSION['dq0'][0]??0)!=$q['id'])$_SESSION['dq0']=[$q['id'],time()];
 if($_SERVER['REQUEST_METHOD']==='POST'&&!$a){
  check();
  $pick=(int)$_POST['pick'];
  $ok=$pick===(int)$q['ans']?1:0;
  q('INSERT INTO qotd_ans(qid,user_id,pick,ok,secs) VALUES(?,?,?,?,?)',[$q['id'],$u['id'],$pick,$ok,max(1,min(3600,time()-$_SESSION['dq0'][1]))]);
  if($ok)addPoints($u['id'],20);
  go(BASE.'/?p=daily');
 }
 $o=[$q['a'],$q['b'],$q['c'],$q['d']];
 echo '<div class="card"><span class="pill">'.h($q['exam']).' · '.h($q['subject']).'</span><h3>'.h($q['q']).'</h3>';
 if($a){
  foreach($o as $j=>$x)echo '<div class="opt '.($j==$q['ans']?'right':($j==$a['pick']?'wrong':'')).'" style="padding:8px 12px;border-radius:10px">'.'ABCD'[$j].'. '.h($x).'</div>';
  echo '<p>'.($a['ok']?'✓ Correct in '.$a['secs'].'s! +20 points':'✗ Not this time. Come back tomorrow!').'</p><div class="mu">'.h($q['exp']).'</div>';
 }else{
  echo '<form method="post">'.field();
  foreach($o as $j=>$x)echo '<button class="opt" name="pick" value="'.$j.'">'.'ABCD'[$j].'. '.h($x).'</button>';
  echo '</form><p class="mu">One try a day. The fastest correct solvers top the board.</p>';
 }
 echo '</div><h2>🏅 Fastest solvers today</h2>';
 table(['#','Student','Time'],array_map(function($r,$i){return [$i+1,h($r['name']),$r['secs'].'s'];},$f=rows('SELECT u.name,a.secs FROM qotd_ans a JOIN users u ON u.id=a.user_id WHERE a.qid=? AND a.ok=1 ORDER BY a.secs LIMIT 10',[$q['id']]),array_keys($f)));
}

/* ═══════════════════════════════════════════════════════════════
   LEADERBOARD
   ═══════════════════════════════════════════════════════════════ */
function pg_leaderboard(){
 echo '<h2>🏆 Top scorers</h2>';
 table(['#','Student','Points'],array_map(function($r,$i){return [$i+1,h($r['name']),$r['points']];},$t=rows("SELECT name,points FROM users WHERE role='student' ORDER BY points DESC LIMIT 20"),array_keys($t)));
 echo '<h2>⚡ Fastest solvers</h2><p class="mu">Best seconds per question in tests (score 50%+).</p>';
 table(['#','Student','Sec / question'],array_map(function($r,$i){return [$i+1,h($r['name']),round($r['f'],1).'s'];},$f=rows("SELECT u.name,MIN(a.secs/a.total) f FROM attempts a JOIN users u ON u.id=a.user_id WHERE a.score>=50 AND a.total>0 AND u.role='student' GROUP BY u.id ORDER BY f LIMIT 10"),array_keys($f)));
}

/* ═══════════════════════════════════════════════════════════════
   NOTES
   ═══════════════════════════════════════════════════════════════ */
function pg_notes(){
 $e=$_GET['e']??'';
 echo '<h2>📚 Notes &amp; Tricks</h2><form method="get"><input type="hidden" name="p" value="notes"><select name="e" onchange="this.form.submit()"><option value="">All exams</option>';
 foreach(exams() as $k=>$v)echo '<option '.($e===$k?'selected':'').'>'.$k.'</option>';
 echo '</select></form><div class="grid">';
 foreach(rows('SELECT * FROM notes '.($e?'WHERE exam=?':'').' ORDER BY id DESC',$e?[$e]:[]) as $n)
  echo '<div class="card"><span class="pill">'.($n['kind']==='trick'?'💡 Trick':'📒 Note').' · '.h($n['exam']).' · '.h($n['subject']).'</span><h3>'.h($n['title']).'</h3><div style="white-space:pre-wrap">'.h($n['body']).'</div></div>';
 echo '</div>';
}

/* ═══════════════════════════════════════════════════════════════
   COMMUNITY
   ═══════════════════════════════════════════════════════════════ */
function pg_community(){
 $u=stu();check();
 if(isset($_POST['title'])&&trim($_POST['title'])){q('INSERT INTO posts(user_id,title,body) VALUES(?,?,?)',[$u['id'],trim($_POST['title']),trim($_POST['body'])]);addPoints($u['id'],5);go(BASE.'/?p=community');}
 if(isset($_POST['ans'])&&trim($_POST['ans'])){q('INSERT INTO answers(post_id,user_id,body) VALUES(?,?,?)',[(int)$_POST['pid'],$u['id'],trim($_POST['ans'])]);addPoints($u['id'],10);go(BASE.'/?p=community');}
 echo '<h2>💬 Community Q&amp;A</h2><form class="card" method="post">'.field().'<input name="title" placeholder="Ask your doubt…" required><textarea name="body" rows="3" placeholder="Details"></textarea><button class="p">Post question</button></form>';
 foreach(rows('SELECT p.*,u.name FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 40') as $po){
  echo '<div class="card"><span class="mu">'.h($po['name']).' · '.h($po['created']).'</span><h3>'.h($po['title']).'</h3><div style="white-space:pre-wrap">'.h($po['body']).'</div>';
  foreach(rows('SELECT a.*,u.name FROM answers a JOIN users u ON u.id=a.user_id WHERE post_id=? ORDER BY a.id',[$po['id']]) as $a)
   echo '<div class="card" style="box-shadow:none;margin:8px 0"><b>'.h($a['name']).'</b>: '.h($a['body']).'</div>';
  echo '<form method="post">'.field().'<input type="hidden" name="pid" value="'.$po['id'].'"><input name="ans" placeholder="Write an answer…"><button>Answer</button></form></div>';
 }
}

/* ═══════════════════════════════════════════════════════════════
   EXAMS
   ═══════════════════════════════════════════════════════════════ */
function pg_exams(){
 echo '<h2>Upcoming exams</h2><div class="grid">';
 foreach(rows('SELECT * FROM exams ORDER BY id DESC') as $x){
  $d=days($x['exam_date']);
  echo '<div class="card"><h3>'.h($x['name']).'</h3><span class="pill">'.h($x['exam_date']).'</span>'.($d!==null?'<span class="pill">⏳ '.$d.' days left</span>':'').'<p class="mu">'.h($x['info']).'</p></div>';
 }
 echo '</div><h2>Previous year papers</h2><div class="grid">';
 foreach(rows('SELECT * FROM pyq ORDER BY id DESC') as $x)
  echo '<div class="card"><h3>'.h($x['title']).'</h3><div class="mu">'.h($x['exam']).' · '.h($x['year']).'</div><br><a class="btn p" href="'.BASE.'/uploads/pyq/'.rawurlencode($x['file']).'" target="_blank" rel="noopener">Download PDF</a></div>';
 echo '</div>';
}

/* ═══════════════════════════════════════════════════════════════
   DASHBOARD
   ═══════════════════════════════════════════════════════════════ */
function pg_dashboard(){
 $GLOBALS['chart']=true;
 $u=stu();$id=$u['id'];
 $a=rows('SELECT * FROM attempts WHERE user_id=? ORDER BY id DESC',[$id]);
 $n=count($a);$avg=$n?round(array_sum(array_column($a,'score'))/$n):0;$best=$n?max(array_column($a,'score')):0;
 $C=(int)array_sum(array_column($a,'correct'));$W=(int)array_sum(array_column($a,'wrong'));$T=(int)array_sum(array_column($a,'total'));$S=(int)array_sum(array_column($a,'secs'));
 $acc=($C+$W)?round(100*$C/($C+$W)):0;$spq=$T?round($S/$T):0;
 $ds=array_unique(array_map(function($r){return substr($r['created'],0,10);},$a));
 $st=0;$d=date('Y-m-d');if(!in_array($d,$ds))$d=date('Y-m-d',strtotime('-1 day'));
 while(in_array($d,$ds)){$st++;$d=date('Y-m-d',strtotime($d.' -1 day'));}
 $rank=1+val("SELECT COUNT(*) FROM users WHERE role='student' AND points>?",[$u['points']]);
 echo '<div class="welcome"><h2>Hi '.h($u['name']).' 👋</h2><p class="mu">Practise one topic today and keep your streak alive.</p></div>'.
  tiles([
   ['📝',$n,'Tests taken','#4f46e5'],
   ['📈',$avg.'%','Average score','#0891b2'],
   ['🏅',$best.'%','Best score','#16a34a'],
   ['🎯',$acc.'%','Accuracy','#7c3aed'],
   ['⏱️',$spq.'s','Avg time / question','#f59e0b'],
   ['🏆',$u['points'],'Points · Rank #'.$rank,'#db2777'],
   ['🔥',$st,'Day streak','#ef4444'],
   ['✏️',$T,'Questions attempted','#64748b']
  ]);
 echo '<div class="grid3"><a class="act" href="?p=practice"><i>🎯</i><b>Topic practice</b><span>Only the topic you choose</span></a><a class="act" href="?p=tests"><i>📝</i><b>Full mock test</b><span>Real CBT exam interface</span></a><a class="act" href="?p=askai"><i>🤖</i><b>AI Tutor</b><span>Ask any doubt, any time</span></a><a class="act" href="?p=daily"><i>⚡</i><b>Question of the day</b><span>Be the fastest solver</span></a></div>'.dashNotices().'<h2>Performance analytics</h2>';
 $tr=array_reverse(array_slice($a,0,12));
 echo '<div class="grid2"><div class="card"><b>Score trend (%)</b>';
 chart('c1','line',array_map(function($r){return substr($r['created'],5,5);},$tr),array_column($tr,'score'),'Score %');
 echo '</div><div class="card"><b>Speed: seconds per question</b>';
 chart('c2','line',array_map(function($r){return substr($r['created'],5,5);},$tr),array_map(function($r){return $r['total']?round($r['secs']/$r['total']):0;},$tr),'Sec / question');
 $sub=rows("SELECT q.subject s,ROUND(100*SUM(i.pick=q.ans)/COUNT(*)) pc FROM attempt_items i JOIN attempts a ON a.id=i.attempt_id JOIN questions q ON q.id=i.qid WHERE a.user_id=? AND i.src='bank' AND i.pick IS NOT NULL GROUP BY q.subject ORDER BY pc DESC LIMIT 8",[$id]);
 echo '</div><div class="card"><b>Subject-wise accuracy (%)</b>';
 if($sub)chart('c3','bar',array_column($sub,'s'),array_map('intval',array_column($sub,'pc')),'% correct');
 else echo '<p class="mu">Take a few tests to see this chart.</p>';
 echo '</div><div class="card"><b>Answer breakdown</b>';
 chart('c4','doughnut',['Correct','Wrong','Skipped'],[$C,$W,max(0,$T-$C-$W)]);
 $wt=rows("SELECT q.topic tp,ROUND(100*SUM(i.pick=q.ans)/COUNT(*)) pc FROM attempt_items i JOIN attempts a ON a.id=i.attempt_id JOIN questions q ON q.id=i.qid WHERE a.user_id=? AND i.src='bank' AND i.pick IS NOT NULL AND q.topic<>'' GROUP BY q.topic HAVING COUNT(*)>=3 ORDER BY pc LIMIT 8",[$id]);
 echo '</div><div class="card"><b>🎯 Weakest topics (% correct)</b>';
 if($wt){chart('c5','bar',array_column($wt,'tp'),array_map('intval',array_column($wt,'pc')),'% correct');echo '<a href="?p=practice"><b>Practise these topics →</b></a>';}
 else echo '<p class="mu">Answer 3+ questions in a topic to see it here.</p>';
 $ty=rows('SELECT kind k,COUNT(*) n FROM attempts WHERE user_id=? GROUP BY kind',[$id]);
 echo '</div><div class="card"><b>Test types</b>';
 if($ty)chart('c6','doughnut',array_map(function($r){return ['full'=>'Full mock','sec'=>'Topic / subject','set'=>'Practice set','paper'=>'Teacher paper'][$r['k']]??$r['k'];},$ty),array_map('intval',array_column($ty,'n')));
 else echo '<p class="mu">No tests yet.</p>';
 $wk=rows('SELECT DATE(created) d,COUNT(*) n FROM attempts WHERE user_id=? AND created>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d',[$id]);
 $mm=array_column($wk,'n','d');$L=[];$D=[];
 for($i=6;$i>=0;$i--){$k=date('Y-m-d',strtotime("-$i day"));$L[]=date('D',strtotime($k));$D[]=(int)($mm[$k]??0);}
 echo '</div><div class="card"><b>Tests this week</b>';
 chart('c7','bar',$L,$D,'Tests');
 echo '</div></div><h2>History</h2>';
 table(['Date','Test','Marks','Score','Time',''],array_map(function($r){
  return [substr($r['created'],0,10),h($r['exam'].' · '.$r['kind']),
          round($r['marks'],2).' / '.round($r['maxm'],2),$r['score'].'%',
          floor($r['secs']/60).' min',
          '<a class="btn" href="?p=review&id='.$r['id'].'">Answers</a> <a class="btn" href="?p=certificate&id='.$r['id'].'">🏆</a>'];
 },array_slice($a,0,20)));
}

/* ═══════════════════════════════════════════════════════════════
   VERIFY / RESEND
   ═══════════════════════════════════════════════════════════════ */
function pg_verify(){
 $t=preg_replace('/[^a-f0-9]/','',$_GET['t']??'');
 $u=$t?row('SELECT * FROM users WHERE vtoken=?',[$t]):null;
 if($u){
  q('UPDATE users SET verified=1,vtoken=NULL WHERE id=?',[$u['id']]);
  flash('Email verified! You can login now.');
  go($u['role']==='admin'?BASE.'/'.ADMIN_DIR.'/':($u['role']==='teacher'?BASE.'/teacher/':BASE.'/?p=login'));
 }
 echo '<div class="auth card"><h2>Invalid or expired link</h2><p class="mu">Request a new verification email.</p><a class="btn p" href="?p=resend">Resend email</a></div>';
}

function pg_resend(){
 check();
 if(isset($_POST['email'])){
  $u=row('SELECT * FROM users WHERE email=? AND verified=0',[strtolower(trim($_POST['email']))]);
  if($u)sendVerify($u['id']);
  flash('If that email is registered and not yet verified, a new link has been sent.');
  go(BASE.'/?p=login');
 }
 echo '<div class="auth card"><h2>Resend verification</h2><form method="post">'.field().'Your email<input name="email" type="email" required><button class="p">Send link</button></form></div>';
}

/* ═══════════════════════════════════════════════════════════════
   PRACTICE — subjects & topics come from the QUESTIONS table
   ═══════════════════════════════════════════════════════════════ */
function pg_practice(){
 $u=stu();$E=exams();$e=$_GET['e']??$u['exam'];
 if(!isset($E[$e]))$e=(string)key($E);

 echo '<h2>🎯 Topic practice</h2><p class="mu">Pick a topic and you get only that topic\'s questions, then see answers and explanations.</p>'
     .'<form method="get"><input type="hidden" name="p" value="practice"><select name="e" onchange="this.form.submit()">';
 foreach($E as $k=>$v)echo '<option '.($k===$e?'selected':'').'>'.h($k).'</option>';
 echo '</select></form>';

 /* 1) what really exists in the question bank: subject → topic → count */
 $c=[]; $subjTotal=[];
 foreach(rows('SELECT subject,topic,COUNT(*) n FROM questions WHERE exam=? GROUP BY subject,topic ORDER BY subject,topic',[$e]) as $r){
  $s=trim((string)$r['subject']); $t=trim((string)$r['topic']);
  if($s==='') $s='General';
  $subjTotal[$s]=($subjTotal[$s]??0)+(int)$r['n'];
  if($t!=='') $c[$s][$t]=($c[$s][$t]??0)+(int)$r['n'];
 }

 /* 2) syllabus topics that have no questions yet (shown greyed out) */
 $soon=[];
 foreach(($E[$e]['topics']??[]) as $s=>$ts){
  if(!isset($subjTotal[$s])) $subjTotal[$s]=0;
  foreach($ts as $t){
   $has=false;
   foreach(($c[$s]??[]) as $tn=>$n) if(strcasecmp($tn,$t)===0){ $has=true; break; }
   if(!$has) $soon[$s][]=$t;
  }
 }

 if(!$subjTotal){ echo '<div class="card mu">No subjects yet for this exam.</div>'; return; }

 /* 3) show every subject: questions first, then syllabus-only (0 questions) */
 uksort($subjTotal,function($a,$b)use($subjTotal){
  return ($subjTotal[$b]>0)<=>($subjTotal[$a]>0) ?: strcasecmp($a,$b);
 });

 foreach($subjTotal as $s=>$tot){
  echo '<div class="card"><h3>'.h($s).' <span class="mu">('.$tot.' questions)</span></h3>'
      .($tot?'<a class="btn p" href="?p=test&m=sec&e='.urlencode($e).'&s='.urlencode($s).'&n=20">Whole subject (mixed)</a><br><br>':'');
  foreach(($c[$s]??[]) as $t=>$n)
   echo '<a class="pill" href="?p=test&m=sec&e='.urlencode($e).'&s='.urlencode($s).'&t='.urlencode($t).'&n=15">'.h($t).' · '.$n.'</a>';
  foreach(($soon[$s]??[]) as $t)
   echo '<span class="pill" style="opacity:.45">'.h($t).' · coming soon</span>';
  if(!$tot && empty($soon[$s])) echo '<span class="mu">No topics yet.</span>';
  echo '</div>';
 }
}

/* ═══════════════════════════════════════════════════════════════
   REVIEW
   ═══════════════════════════════════════════════════════════════ */
function pg_review(){
 $u=stu();
 $a=row('SELECT * FROM attempts WHERE id=? AND user_id=?',[(int)($_GET['id']??0),$u['id']]);
 if(!$a){flash('Attempt not found');go(BASE.'/?p=certificates');}

 echo '<h2>Answers &amp; explanations</h2>';
 echo '<div class="card"><b>'.h($a['exam'].' · '.$a['kind']).'</b>'
     .'<div class="big">'.$a['correct'].'/'.$a['total'].' · '.$a['score'].'%</div>'
     .'<p><a class="btn p" href="'.BASE.pu('certificate','id='.$a['id']).'">🏆 View / print certificate</a> '
     .'<a class="btn" href="'.BASE.pu('certificates').'">← My certificates</a> '
     .'<a class="btn" href="'.BASE.'/?p=dashboard">Dashboard</a></p></div>';

 $items = rows('SELECT * FROM attempt_items WHERE attempt_id=? ORDER BY id',[$a['id']]);
 if(!$items){
     echo '<div class="card"><b>No per-question details saved for this attempt.</b>'
         .'<p class="mu">This attempt was recorded before question-level storage was enabled. '
         .'The overall score is saved and the certificate is still valid — take a new test to see '
         .'answers &amp; explanations here.</p></div>';
     return;
 }

 $missing = 0; $n = 0;
 foreach($items as $i){
     $tbl = $i['src'] === 'paper' ? 'paper_q' : 'questions';
     $r = row("SELECT * FROM $tbl WHERE id=?",[$i['qid']]);
     if(!$r){$missing++;continue;}
     $n++;
     echo '<div class="card"><b>'.$n.'. '.h($r['q']).'</b>';
     if(!empty($r['topic'])) echo ' <span class="pill">'.h($r['topic']).'</span>';
     foreach([$r['a'],$r['b'],$r['c'],$r['d']] as $j=>$x){
         $cls = ($j==(int)$r['ans']) ? 'right' : (($i['pick']!==null && $j==(int)$i['pick']) ? 'wrong' : '');
         echo '<div class="opt '.$cls.'" style="padding:6px 10px;border-radius:8px">'
             .'ABCD'[$j].'. '.h($x).'</div>';
     }
     echo '<div class="mu">'
         .($i['pick']===null?'<b>Skipped</b> · ':'')
         .'Explanation: '.h($r['exp'] ?: '—')
         .'</div></div>';
 }
 if($missing){
     echo '<div class="card mu">'.$missing.' question(s) in this attempt have been removed from the bank and cannot be shown.</div>';
 }
}

/* ═══════════════════════════════════════════════════════════════
   EXAM (public SEO page)
   ═══════════════════════════════════════════════════════════════ */
function pg_exam(){
 $c=strtoupper($_GET['c']??'');$E=exams();
 if(!isset($E[$c])){http_response_code(404);$GLOBALS['seo']['noindex']=true;echo '<div class="card"><h2>Exam not found</h2><a class="btn p" href="'.BASE.'/">Go home</a></div>';return;}
 $e=$E[$c];$tot=array_sum($e['sec']);
 $GLOBALS['seo']=['t'=>$e['t'].' Mock Test, Syllabus & Exam Pattern | '.SITE,
  'd'=>'Free '.$e['t'].' mock tests and topic-wise practice. Exam pattern: '.$tot.' questions in '.$e['min'].' minutes across '.count($e['sec']).' subjects, with full syllabus.',
  'path'=>'/exam/'.$c,
  'ld'=>['@context'=>'https://schema.org','@type'=>'Course','name'=>$e['t'].' mock test preparation',
         'description'=>'Mock tests and topic-wise practice for '.$e['t'],
         'provider'=>['@type'=>'Organization','name'=>SITE]]];
 echo '<section class="hero"><span class="pill">'.h($c).'</span><h1>'.h($e['t']).' <em>mock tests &amp; syllabus</em></h1><p>'.$tot.' questions · '.$e['min'].' minutes · '.count($e['sec']).' subjects. Practise the whole paper or one topic at a time.</p><a class="btn p" href="?p=register">Start free practice →</a></section>';
 echo '<h2>Exam pattern</h2>';
 table(['Subject','Questions','Topics'],array_map(function($s,$n)use($e){return [h($s),$n,count($e['topics'][$s]??[])];},array_keys($e['sec']),$e['sec']));
 echo '<h2>Syllabus (topic-wise)</h2><div class="grid">';
 foreach($e['topics'] as $s=>$ts){echo '<div class="card"><h3>'.h($s).'</h3>';foreach($ts as $t)echo '<span class="pill">'.h($t).'</span>';echo '</div>';}
 echo '</div><div class="cta"><h2>Practise '.h($c).' now</h2><a class="btn" href="?p=register">Create free account</a></div>';
}

/* ═══════════════════════════════════════════════════════════════
   ABOUT / PRIVACY
   ═══════════════════════════════════════════════════════════════ */
function pg_about(){
 echo '<div class="narrow"><h2>About '.SITE.'</h2><div class="card"><p>'.SITE.' gives government job and school entrance aspirants free, exam-pattern mock tests with a real CBT interface, topic-wise practice, answers with explanations and progress analytics.</p><p>Teachers and schools can create their own papers, invite students with a referral link and track class performance.</p><p class="mu">Question sets are prepared to follow each exam\'s published pattern and syllabus. Always cross-check with the official notification.</p><a class="btn p" href="?p=register">Get started</a></div></div>';
}

function pg_privacy(){
 echo '<div class="narrow"><h2>Privacy policy</h2><div class="card"><p>We store your name, email address, test results and community posts to run your account and show your progress. We email you only for account verification and your test results.</p><p>We do not sell your data. Passwords are stored hashed. You can ask the site administrator to delete your account at any time.</p><p>The <b>AI Tutor</b> sends the questions you type to third-party AI providers so they can answer. Your name and email are not sent. Please do not type personal or sensitive information into it.</p><p>If you send <b>feedback</b>, we store your rating and message, plus the name and email only if you choose to enter them, so the site administrator can read and answer it.</p></div></div>';
}

/* ═══════════════════════════════════════════════════════════════
   NOTICES
   ═══════════════════════════════════════════════════════════════ */
function dashNotices(){
 $n=srows('SELECT * FROM notices WHERE active=1 ORDER BY id DESC LIMIT 5');
 if(!$n)return '';
 $h='<div class="card"><b>📢 What\'s new</b>';
 foreach($n as $x){$t=h($x['icon'].' '.$x['title']);$h.='<div class="nl">'.($x['link']?'<a href="'.h($x['link']).'" rel="noopener">'.$t.'</a>':$t).'<span class="mu"> · '.h(substr($x['created'],0,10)).'</span></div>';}
 return $h.'</div>';
}