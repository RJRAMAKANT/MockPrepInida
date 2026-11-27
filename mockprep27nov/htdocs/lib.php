<?php
require_once __DIR__.'/config.php';
foreach([
  'DEBUG'=>true,'PRETTY_URLS'=>'auto','MAIL_FROM'=>'no-reply@localhost',
  'REQUIRE_VERIFY'=>true,'MAIL_FALLBACK_LINK'=>true,'MAIL_RESULTS'=>true,
  'SMTP_HOST'=>'','SMTP_PORT'=>587,'SMTP_SECURE'=>'tls','SMTP_USER'=>'','SMTP_PASS'=>'',
  'MAIL_API_KEY'=>'','MAIL_WEBHOOK_URL'=>'','MAIL_WEBHOOK_SECRET'=>'',
  'AI_PROVIDER'=>'gemini','AI_KEY'=>'','AI_MODEL'=>'gemini-2.5-flash',
  'AI_URL'=>'https://api.groq.com/openai/v1/chat/completions'
] as $k=>$v) if(!defined($k)) define($k,$v);

if(DEBUG){ ini_set('display_errors','1'); error_reporting(E_ERROR|E_PARSE); }

set_exception_handler(function($e){
  http_response_code(500);
  echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:24px;max-width:720px;margin:auto"><h2>Setup needed</h2><p>The site could not use its database. Open <a href="'.BASE.'/check.php"><b>check.php</b></a> to see exactly what is wrong (database details, tables, install).</p>'.(DEBUG?'<p style="color:#b91c1c">'.htmlspecialchars($e->getMessage()).'</p>':'').'</body>';
});

/* ---------- SESSION (hardened) ---------- */
if(session_status()===PHP_SESSION_NONE){
    ini_set('session.gc_maxlifetime', 7200);
    ini_set('session.cookie_lifetime', 7200);
    ini_set('session.use_strict_mode', 1);
    session_set_cookie_params([
        'lifetime'=>7200,'path'=>'/','httponly'=>true,'samesite'=>'Lax'
    ]);
    session_start();
}

/* ---------- DATABASE ---------- */
function db($reset=false){
    static $p;
    if($reset) $p=null;
    if(!$p) $p=new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    return $p;
}
function q($s,$a=[]){
    for($try=0;;$try++){
        try{ $t=db()->prepare($s); $t->execute($a); return $t; }
        catch(PDOException $e){
            $m=$e->getMessage();
            /* connection dropped while waiting (e.g. long AI call) → reconnect once and retry */
            if($try===0 && (stripos($m,'gone away')!==false || stripos($m,'Lost connection')!==false
                            || strpos($m,'2006')!==false || strpos($m,'2013')!==false)){
                db(true); continue;
            }
            if(strpos($m,'Incorrect string value')!==false){
                $a=array_map(function($v){return is_string($v)?preg_replace('/[\x{10000}-\x{10FFFF}]/u','',$v):$v;},$a);
                $t=db()->prepare($s); $t->execute($a); return $t;
            }
            throw $e;
        }
    }
}
function rows($s,$a=[]){ return q($s,$a)->fetchAll(); }
function row($s,$a=[]){ return q($s,$a)->fetch(); }
function val($s,$a=[]){ return q($s,$a)->fetchColumn(); }
function sval($s,$a=[],$d=0){ try{return val($s,$a);}catch(Exception $e){return $d;} }
function srows($s,$a=[]){ try{return rows($s,$a);}catch(Exception $e){return [];} }

/* ---------- HELPERS ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function baseUrl(){ return (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').BASE; }
function days($d){ $t=strtotime($d); return $t?max(0,(int)ceil(($t-time())/86400)):null; }
function addPoints($uid,$n){ if($n) q('UPDATE users SET points=points+? WHERE id=?',[$n,$uid]); }

/* ---------- AUTH ---------- */
function me(){
    static $u=false;
    if($u===false){
        try{ $u=isset($_SESSION['uid'])?row('SELECT * FROM users WHERE id=?',[$_SESSION['uid']]):null; }
        catch(Exception $e){ $u=null; }
    }
    return $u;
}
function need($role){
    $u=me();
    if(!$u||$u['role']!==$role) go($role==='admin'?BASE.'/'.ADMIN_DIR.'/':($role==='teacher'?BASE.'/teacher/':BASE.'/?p=login'));
    return $u;
}
function doLogin($role,$back){
    check();
    $u=row('SELECT * FROM users WHERE email=? AND role=?',[strtolower(trim($_POST['email']??'')),$role]);
    if($u && password_verify($_POST['password']??'',$u['pass'])){
        if(REQUIRE_VERIFY && !$u['verified']){
            flash('Please verify your email first. Check your inbox and spam folder, or use "Resend verification email" on the student login page.');
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(16));   // fresh CSRF after rotate
        return true;
    }
    flash('Wrong email or password');
    return false;
}
function check(){
    if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'',$_POST['csrf']??''))
        die('Invalid request. Go back and retry.');
}
function csrf(){
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function field(){ return '<input type="hidden" name="csrf" value="'.csrf().'">'; }

/* ---------- FLASH + REDIRECT ---------- */
function flash($m=null){
    if($m!==null){ $_SESSION['f']=$m; return; }
    $f=$_SESSION['f']??''; unset($_SESSION['f']); return $f;
}
function go($u){ header('Location: '.prettyLoc($u)); exit; }

/* ---------- URL PRETTIFY ---------- */
function prettyOn(){ return PRETTY_URLS===true || (PRETTY_URLS==='auto'&&file_exists(__DIR__.'/.htaccess')); }
function pu($p,$qs=''){ if($p==='home') return '/'; return prettyOn()?'/'.$p.($qs?'?'.$qs:''):'/?p='.$p.($qs?'&'.$qs:''); }
function pux($c){ return prettyOn()?'/exam/'.urlencode($c):'/?p=exam&c='.urlencode($c); }
function prettyLoc($u){
    if(!prettyOn()) return $u;
    return preg_replace_callback('~^(?:'.preg_quote(BASE,'~').')?/?\?p=([a-z-]+)(&|$)~',function($m){
        return BASE.'/'.($m[1]==='home'?'':$m[1]).($m[2]==='&'?'?':'');
    },$u);
}
function prettyUrls($h){
    if(!prettyOn()) return $h;
    $b=preg_quote(BASE,'~');
    return preg_replace_callback('~(href|action)="(?:'.$b.')?/?\?p=([a-z-]+)(&amp;|&|")~',function($m){
        $n=$m[2]==='home'?'':$m[2];
        return $m[1].'="'.BASE.'/'.$n.($m[3]==='"'?'"':'?');
    },$h);
}

/* ---------- EXAM / SYLLABUS ---------- */
function exams(){
    static $E=null;
    if($E!==null) return $E;
    $E=[];
    try{
        foreach(rows('SELECT * FROM exam_defs ORDER BY code') as $x)
            $E[$x['code']]=['t'=>$x['title'],'min'=>(int)$x['minutes'],'sec'=>[],'topics'=>[]];
        foreach(rows('SELECT * FROM subjects ORDER BY id') as $x)
            if(isset($E[$x['exam']])){
                $E[$x['exam']]['sec'][$x['name']]=(int)$x['qcount'];
                $E[$x['exam']]['topics'][$x['name']]=[];
            }
        foreach(rows('SELECT * FROM topics ORDER BY id') as $x)
            if(isset($E[$x['exam']]['topics'][$x['subject']]))
                $E[$x['exam']]['topics'][$x['subject']][]=$x['name'];
    }catch(Exception $ex){}
    return $E;
}
function ensureSyl($e,$s,$t){
    if(!val('SELECT code FROM exam_defs WHERE code=?',[$e])) return false;
    if(!val('SELECT id FROM subjects WHERE exam=? AND name=?',[$e,$s]))
        q('INSERT INTO subjects(exam,name,qcount) VALUES(?,?,10)',[$e,$s]);
    if($t&&!val('SELECT id FROM topics WHERE exam=? AND subject=? AND name=?',[$e,$s,$t]))
        q('INSERT INTO topics(exam,subject,name) VALUES(?,?,?)',[$e,$s,$t]);
    return true;
}
function sylData(){ $o=[]; foreach(exams() as $k=>$e) $o[$k]=['subjects'=>$e['topics']]; return $o; }
function sylPick($topic=true,$opt=false){
    $GLOBALS['syl']=1;
    return '<select name="exam" class="syE"'.($opt?' data-opt="1"':'').'></select><select name="subject" class="syS"></select>'.($topic?'<select name="topic" class="syT"></select>':'');
}

/* ---------- QUESTION IMPORT ---------- */
function ansIdx($v){
    $v = strtoupper(trim((string)$v));
    $v = preg_replace('/^(OPTION|ANS|ANSWER)\s*/', '', $v);
    $v = trim($v, " .)(");
    if($v==='') return false;
    if(in_array($v,['A','1','0'])) return 0;
    if(in_array($v,['B','2','1'])) return 1;
    if(in_array($v,['C','3','2'])) return 2;
    if(in_array($v,['D','4','3'])) return 3;
    return false;
}

/* normalised question text: lower-case, letters and digits only → used to spot repeats */
function qnorm($s){ return preg_replace('/[^\p{L}\p{N}]+/u','',mb_strtolower((string)$s)); }

/* returns 1 if saved, 0 if invalid OR already in the bank (same exam + subject + question text) */
function insertQ($e,$s,$q,$a,$b,$c,$d,$ans,$x,$src='manual',$tp=''){
    if(!$q || !in_array((int)$ans,[0,1,2,3],true) || !ensureSyl($e,$s,$tp)) return 0;
    static $cache=[];
    $ck=$e.'|'.$s;
    if(!isset($cache[$ck])){
        $cache[$ck]=[];
        foreach(rows('SELECT q FROM questions WHERE exam=? AND subject=?',[$e,$s]) as $r) $cache[$ck][qnorm($r['q'])]=1;
    }
    $k=qnorm($q);
    if($k!=='' && isset($cache[$ck][$k])) return 0;
    q('INSERT INTO questions(exam,subject,topic,q,a,b,c,d,ans,exp,src) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
      [$e,$s,$tp,$q,$a,$b,$c,$d,(int)$ans,$x,$src]);
    $cache[$ck][$k]=1;
    $GLOBALS['newq'][]=db()->lastInsertId();
    return 1;
}

/* add questions to a set: skips a question already in the set (same id OR same text) */
function addToSet($sid,$ids){
    if(!$ids) return;
    $sid  = (int)$sid;
    $o    = (int) val('SELECT COALESCE(MAX(ord),0) FROM qset_q WHERE set_id=?',[$sid]);
    $have = []; $text = [];
    foreach(rows('SELECT s.qid,q.q FROM qset_q s LEFT JOIN questions q ON q.id=s.qid WHERE s.set_id=?',[$sid]) as $r){
        $have[(int)$r['qid']] = 1;
        $text[qnorm($r['q'])] = 1;
    }
    $st = db()->prepare('INSERT INTO qset_q(set_id,qid,ord) VALUES(?,?,?)');
    foreach($ids as $i){
        $i = (int)$i;
        if(!$i || isset($have[$i])) continue;
        $k = qnorm(val('SELECT q FROM questions WHERE id=?',[$i]));
        if($k!=='' && isset($text[$k])) continue;
        $o++;
        try{ $st->execute([$sid,$i,$o]); }catch(Throwable $e){}
        $have[$i] = 1; $text[$k] = 1;
    }
}

/* pick up to $n random questions where ($where) matches, never choosing one already in $ids
   (by id or by identical text). $ids / $seen / $seenText are shared across calls for one set. */
function pickQ($where,$params,$n,&$ids,&$seen,&$seenText){
    $n  = (int)$n;
    $ex = $ids ? ' AND id NOT IN ('.implode(',',array_map('intval',$ids)).')' : '';
    $rs = rows('SELECT id,q FROM questions WHERE ('.$where.')'.$ex.' ORDER BY RAND() LIMIT '.($n*4+10),$params);
    $add = 0;
    foreach($rs as $r){
        if($add>=$n) break;
        $id=(int)$r['id']; $k=qnorm($r['q']);
        if(isset($seen[$id]) || isset($seenText[$k])) continue;
        $seen[$id]=1; $seenText[$k]=1; $ids[]=$id; $add++;
    }
    return $add;
}

/* ---------- QOTD ---------- */
function qotdToday(){
    $d=date('Y-m-d');
    $r=row('SELECT * FROM qotd WHERE qdate=?',[$d]);
    if($r) return $r;
    $tot=(int)val('SELECT COUNT(*) FROM questions');
    if(!$tot) return null;
    $off=floor(time()/86400)%$tot;
    $b=row('SELECT * FROM questions ORDER BY id LIMIT 1 OFFSET '.$off);
    if(!$b) return null;
    q('INSERT INTO qotd(qdate,exam,subject,q,a,b,c,d,ans,exp) VALUES(?,?,?,?,?,?,?,?,?,?)',
      [$d,$b['exam'],$b['subject'],$b['q'],$b['a'],$b['b'],$b['c'],$b['d'],$b['ans'],$b['exp']]);
    return row('SELECT * FROM qotd WHERE qdate=?',[$d]);
}
function qotdForm(){
    check();
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['qq'])){
        $d=$_POST['qdate']?:date('Y-m-d');
        q('DELETE FROM qotd WHERE qdate=?',[$d]);
        q('INSERT INTO qotd(qdate,exam,subject,q,a,b,c,d,ans,exp) VALUES(?,?,?,?,?,?,?,?,?,?)',
          [$d,$_POST['exam'],$_POST['subject'],$_POST['qq'],$_POST['a'],$_POST['b'],$_POST['c'],$_POST['d'],(int)$_POST['ans'],$_POST['exp']]);
        flash('Question of the Day saved for '.$d);
        go($_SERVER['REQUEST_URI']);
    }
    echo '<h2>⚡ Set Question of the Day</h2><form class="card" method="post">'.field().'<select name="exam">';
    foreach(exams() as $k=>$v) echo "<option>$k</option>";
    echo '</select><input name="subject" placeholder="Subject" required><textarea name="qq" rows="3" placeholder="Question" required></textarea><input name="a" placeholder="Option A" required><input name="b" placeholder="Option B" required><input name="c" placeholder="Option C" required><input name="d" placeholder="Option D" required><select name="ans"><option value="0">Answer A</option><option value="1">Answer B</option><option value="2">Answer C</option><option value="3">Answer D</option></select><input name="exp" placeholder="Explanation"><label class="mu">Show on date</label><input type="date" name="qdate" value="'.date('Y-m-d').'"><button class="p">Publish</button></form>';
}

/* ---------- MIGRATE (schema v4 adds nothing new but re-runs safely) ---------- */
function migrate(){
    static $done=false;
    if($done) return;
    $done=true;
    $GLOBALS['migerr']=[];

    $safe = function($sql) use (&$err){
        try{ db()->exec($sql); }
        catch(Exception $e){
            $m=$e->getMessage();
            if(stripos($m,'Duplicate column')===false
               &&stripos($m,'already exists')===false)
                $GLOBALS['migerr'][]=substr($m,0,170);
        }
    };

    /* --- ensure required columns exist on every table used by app code --- */
    $safe("ALTER TABLE users      ADD COLUMN rtoken   VARCHAR(40) NULL");
    $safe("ALTER TABLE users      ADD COLUMN rexp     INT DEFAULT 0");
    $safe("ALTER TABLE users      ADD COLUMN verified TINYINT DEFAULT 0");
    $safe("ALTER TABLE users      ADD COLUMN vtoken   VARCHAR(40) NULL");
    $safe("ALTER TABLE questions  ADD COLUMN topic    VARCHAR(100) DEFAULT ''");
    $safe("ALTER TABLE qsets      ADD COLUMN marks    DECIMAL(5,2) NOT NULL DEFAULT 1");
    $safe("ALTER TABLE qsets      ADD COLUMN neg      DECIMAL(5,2) NOT NULL DEFAULT 0.25");
    $safe("ALTER TABLE qset_q     ADD COLUMN ord      INT NOT NULL DEFAULT 0");
    $safe("ALTER TABLE attempts   ADD COLUMN marks    DECIMAL(8,2) NOT NULL DEFAULT 0");
    $safe("ALTER TABLE attempts   ADD COLUMN maxm     DECIMAL(8,2) NOT NULL DEFAULT 0");
    
    
$safe("CREATE TABLE IF NOT EXISTS requests(
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  exam VARCHAR(20) NOT NULL,
  subject VARCHAR(80) DEFAULT '',
  topic VARCHAR(100) DEFAULT '',
  body TEXT NOT NULL,
  want_count INT DEFAULT 0,
  status ENUM('pending','fulfilled','declined') DEFAULT 'pending',
  admin_note TEXT NULL,
  set_id INT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated TIMESTAMP NULL,
  KEY(user_id), KEY(status)
) DEFAULT CHARSET=utf8mb4");

    /* --- optionally seed stats / schema marker (never blocks the ALTERs above) --- */
    try{
        db()->exec("INSERT IGNORE INTO stats(k,v) VALUES('visits',0)");
        db()->exec("INSERT IGNORE INTO stats(k,v) VALUES('schema',3)");
    }catch(Exception $e){}
}

/* ---------- EMAIL ---------- */
/**
 * Send HTML email. Tries, in order:
 *   1. Google Apps Script / Brevo webhook (port 443, works on InfinityFree)
 *   2. SMTP (smtpSend — real STARTTLS/AUTH)
 *   3. PHP mail() as last resort
 * Sets $GLOBALS['mailok'] and $GLOBALS['mailerr'].
 *
 * NOTE: This version keeps the ORIGINAL signature (3 args) so any caller
 * — sendReset(), teacher panel, admin panel, etc. — keeps working.
 * For a plain-text fallback we auto-derive it from $html inside.
 */
function mailTo($to, $subject, $html){
    $GLOBALS['mailok'] = false;
    $GLOBALS['mailerr'] = '';

    // 1) webhook
    if(defined('MAIL_WEBHOOK_URL') && MAIL_WEBHOOK_URL !== ''){
        $payload = json_encode([
            'secret'  => MAIL_WEBHOOK_SECRET,
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
            'from'    => MAIL_FROM,
            'site'    => SITE
        ]);
        $ch = curl_init(MAIL_WEBHOOK_URL);
        curl_setopt_array($ch,[
            CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER=>1, CURLOPT_TIMEOUT=>20,
            CURLOPT_FOLLOWLOCATION=>1
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if($code===200 && stripos((string)$resp,'ok')!==false){
            $GLOBALS['mailok'] = true;
            return true;
        }
        $GLOBALS['mailerr'] = 'webhook HTTP '.$code.($cerr?' '.$cerr:'');
    }

    // 2) SMTP
    if(SMTP_HOST !== ''){
        $err = null;
        if(smtpSend($to, $subject, $html, $err)){
            $GLOBALS['mailok'] = true;
            return true;
        }
        $GLOBALS['mailerr'] = trim($GLOBALS['mailerr'].' | SMTP: '.$err, ' |');
    }

    // 3) PHP mail()
    $headers = "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "From: ".SITE." <".MAIL_FROM.">\r\n";
    if(@mail($to, $subject, $html, $headers)){
        $GLOBALS['mailok'] = true;
        return true;
    }
    if(!$GLOBALS['mailerr']) $GLOBALS['mailerr'] = 'webhook, SMTP and mail() all failed';
    return false;
}

/**
 * Pure-PHP SMTP client — STARTTLS + AUTH LOGIN.
 * Returns true on success; sets $err on failure.
 * (Original signature — unchanged.)
 */
function smtpSend($to, $subject, $html, &$err = null){
    $host   = SMTP_HOST;
    $port   = (int)SMTP_PORT;
    $secure = SMTP_SECURE;        // 'tls' | 'ssl' | ''
    $user   = SMTP_USER;
    $pass   = SMTP_PASS;
    $from   = MAIL_FROM;

    $remote = ($secure==='ssl'?'ssl://':'').$host.':'.$port;
    $fp = @stream_socket_client($remote, $eNo, $eStr, 20);
    if(!$fp){ $err = "connect $host:$port — $eStr ($eNo)"; return false; }
    stream_set_timeout($fp, 20);

    $read = function() use ($fp){
        $out = '';
        while(($line = fgets($fp, 515)) !== false){
            $out .= $line;
            if(strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    };
    $expect = function($code, $what) use ($read, &$err){
        $r = $read();
        if(substr($r, 0, 3) !== (string)$code){ $err = "$what got: ".trim($r); return false; }
        return true;
    };
    $cmd = function($line, $code, $what) use ($fp, $expect, &$err){
        fwrite($fp, $line."\r\n");
        return $expect($code, $what);
    };

    if(!$expect(220, 'greeting')) return false;
    if(!$cmd('EHLO '.gethostname(), 250, 'EHLO')) return false;

    if($secure === 'tls'){
        if(!$cmd('STARTTLS', 220, 'STARTTLS')) return false;
        if(!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)){
            $err = 'TLS negotiation failed'; return false;
        }
        if(!$cmd('EHLO '.gethostname(), 250, 'EHLO2')) return false;
    }

    if($user !== ''){
        if(!$cmd('AUTH LOGIN', 334, 'AUTH')) return false;
        if(!$cmd(base64_encode($user), 334, 'AUTH user')) return false;
        if(!$cmd(base64_encode($pass), 235, 'AUTH pass')) return false;
    }

    if(!$cmd("MAIL FROM:<$from>", 250, 'MAIL FROM')) return false;
    if(!$cmd("RCPT TO:<$to>", 250, 'RCPT TO')) return false;
    if(!$cmd('DATA', 354, 'DATA')) return false;

    $headers = "Date: ".date('r')."\r\n"
             . "From: ".SITE." <$from>\r\n"
             . "To: <$to>\r\n"
             . "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: base64\r\n";
    $body = $headers."\r\n".chunk_split(base64_encode($html));
    // dot-stuff lines that begin with a period
    $body = preg_replace('/^\./m', '..', $body);

    fwrite($fp, $body."\r\n.\r\n");
    if(!$expect(250, 'DATA end')) return false;
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return true;
}

/* ---------- VERIFY / RESET ---------- */

/**
 * Beautiful, table-based HTML verification email.
 * All CSS inline so it renders in Gmail, Outlook, Apple Mail.
 */
function verificationEmailHtml($name, $verifyUrl, $siteName, $baseUrl) {
    $nameSafe = h($name);
    $safeUrl  = h($verifyUrl);
    $siteSafe = h($siteName);
    $baseSafe = h($baseUrl);
    $year     = date('Y');

    $html  = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    $html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
    $html .= '<title>Verify your email - '.$siteSafe.'</title></head>';
    $html .= '<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">';

    /* hidden preheader */
    $html .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">';
    $html .= 'One tap and you are in. Confirm your email to unlock free mock tests.';
    $html .= '</div>';

    /* outer shell */
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:32px 12px;">';
    $html .= '<tr><td align="center">';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(79,70,229,.10);">';

    /* hero */
    $html .= '<tr><td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#db2777 100%);padding:40px 32px 34px;text-align:center;">';
    $html .= '<div style="font-size:44px;line-height:1;margin-bottom:14px;">🎯</div>';
    $html .= '<h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:800;letter-spacing:-.3px;">You\'re almost in, '.$nameSafe.'</h1>';
    $html .= '<p style="margin:10px 0 0;color:#e0e7ff;font-size:14px;line-height:1.5;">Confirm your email to activate your free account</p>';
    $html .= '</td></tr>';

    /* body */
    $html .= '<tr><td style="padding:36px 36px 12px;">';
    $html .= '<p style="margin:0 0 16px;color:#111827;font-size:16px;line-height:1.6;">Hi <b>'.$nameSafe.'</b> 👋</p>';
    $html .= '<p style="margin:0 0 22px;color:#4b5563;font-size:15px;line-height:1.65;">Welcome to <b>'.$siteSafe.'</b>! One tap below unlocks:</p>';

    /* benefits */
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px;">';
    $html .= '<tr><td style="padding:0 0 10px;color:#374151;font-size:14px;line-height:1.5;"><span style="color:#16a34a;font-weight:700;">&#10003;</span>&nbsp; Real CBT-style mock tests</td></tr>';
    $html .= '<tr><td style="padding:0 0 10px;color:#374151;font-size:14px;line-height:1.5;"><span style="color:#16a34a;font-weight:700;">&#10003;</span>&nbsp; Topic-wise practice with explanations</td></tr>';
    $html .= '<tr><td style="padding:0 0 10px;color:#374151;font-size:14px;line-height:1.5;"><span style="color:#16a34a;font-weight:700;">&#10003;</span>&nbsp; Progress charts &amp; leaderboard</td></tr>';
    $html .= '</table>';

    /* CTA */
    $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:8px auto 26px;"><tr>';
    $html .= '<td align="center" bgcolor="#4f46e5" style="border-radius:12px;">';
    $html .= '<a href="'.$safeUrl.'" style="display:inline-block;padding:15px 40px;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);">Verify my email &rarr;</a>';
    $html .= '</td></tr></table>';

    /* fallback url */
    $html .= '<p style="margin:0 0 6px;text-align:center;color:#9ca3af;font-size:12px;">Button not working? Copy this link into your browser:</p>';
    $html .= '<p style="margin:0 0 28px;text-align:center;word-break:break-all;"><a href="'.$safeUrl.'" style="color:#4f46e5;font-size:12px;text-decoration:underline;">'.$safeUrl.'</a></p>';

    $html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 22px;">';
    $html .= '<p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">This link stays valid until it is used once. If you didn\'t create an account, just ignore this email.</p>';
    $html .= '</td></tr>';

    /* footer */
    $html .= '<tr><td style="padding:22px 36px 30px;background:#fafafa;border-top:1px solid #f1f5f9;text-align:center;">';
    $html .= '<p style="margin:0 0 8px;color:#6b7280;font-size:12px;">Made with &#10084; for Indian aspirants</p>';
    $html .= '<p style="margin:0;color:#9ca3af;font-size:11px;line-height:1.6;">&copy; '.$year.' '.$siteSafe.' &middot; <a href="'.$baseSafe.'" style="color:#9ca3af;text-decoration:underline;">Visit site</a></p>';
    $html .= '</td></tr>';

    $html .= '</table>'; /* card */
    $html .= '<p style="margin:18px 0 0;color:#9ca3af;font-size:11px;">You are receiving this because someone used this address to sign up.</p>';
    $html .= '</td></tr></table>';
    $html .= '</body></html>';

    return $html;
}

function sendVerify($id){
    $u = row('SELECT * FROM users WHERE id=?',[$id]);
    if(!$u) return '';

    $t = bin2hex(random_bytes(16));
    q('UPDATE users SET vtoken=? WHERE id=?',[$t,$id]);

    $l = baseUrl().pu('verify','t='.$t);

    $subject = 'Confirm your email to unlock '.SITE;
    $html    = verificationEmailHtml($u['name'], $l, SITE, baseUrl());

    $GLOBALS['mailok'] = mailTo($u['email'], $subject, $html);
    if(!$GLOBALS['mailok'] && MAIL_FALLBACK_LINK) $_SESSION['vlink'] = $l;
    $_SESSION['verr'] = $GLOBALS['mailerr'] ?? '';
    return $l;
}

function sendReset($u){
    $t = bin2hex(random_bytes(16));
    q('UPDATE users SET rtoken=?,rexp=? WHERE id=?',[$t,time()+3600,$u['id']]);
    $l = baseUrl().pu('reset','t='.$t);

    $subject = 'Reset your '.SITE.' password';
    $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:32px 12px;">'
          . '<tr><td align="center">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(79,70,229,.10);">'
          . '<tr><td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#db2777 100%);padding:36px 32px 30px;text-align:center;">'
          . '<div style="font-size:40px;line-height:1;margin-bottom:12px;">🔒</div>'
          . '<h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;">Reset your password</h1>'
          . '</td></tr>'
          . '<tr><td style="padding:32px 36px;">'
          . '<p style="margin:0 0 14px;color:#111827;font-size:15px;">Hi <b>'.h($u['name']).'</b> 👋</p>'
          . '<p style="margin:0 0 22px;color:#4b5563;font-size:14px;line-height:1.6;">Click the button below to choose a new password. This link works for <b>one hour</b>.</p>'
          . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 22px;"><tr><td align="center" bgcolor="#4f46e5" style="border-radius:12px;">'
          . '<a href="'.h($l).'" style="display:inline-block;padding:14px 36px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);">Choose new password &rarr;</a>'
          . '</td></tr></table>'
          . '<p style="margin:0 0 6px;text-align:center;color:#9ca3af;font-size:12px;">Or copy this link into your browser:</p>'
          . '<p style="margin:0 0 22px;text-align:center;word-break:break-all;"><a href="'.h($l).'" style="color:#4f46e5;font-size:12px;">'.h($l).'</a></p>'
          . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 18px;">'
          . '<p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">If you did not request this, you can safely ignore this email - your password will not change.</p>'
          . '</td></tr>'
          . '<tr><td style="padding:18px 36px 24px;background:#fafafa;border-top:1px solid #f1f5f9;text-align:center;color:#9ca3af;font-size:11px;">'
          . '&copy; '.date('Y').' '.h(SITE).' &middot; <a href="'.h(baseUrl()).'" style="color:#9ca3af;text-decoration:underline;">Visit site</a>'
          . '</td></tr>'
          . '</table></td></tr></table></body></html>';

    $GLOBALS['mailok'] = mailTo($u['email'], $subject, $html);
    return $l;
}

/* ---------- CERTIFICATE ---------- */
/**
 * Renders the certificate. Used by both the /certificate page and the result email.
 * $isPage=true wraps in a centred container (for the browser view).
 */
function certificateHtml($name,$exam,$score,$correct,$total,$marks,$max,$secs,$aid,$isPage=false){
    $date  = date('d M Y');
    $idNo  = 'MPI-'.str_pad((int)$aid,6,'0',STR_PAD_LEFT);
    $marks = round((float)$marks,2);
    $max   = round((float)$max,2);
    $min   = floor($secs/60).'m '.($secs%60).'s';
    $score = (int)$score;
    $grade = $score>=90?'A+':($score>=80?'A':($score>=70?'B':($score>=60?'C':($score>=50?'D':'Participation'))));

    $body =
    '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
      .'style="max-width:780px;margin:0 auto;background:#fffdf7;border:2px solid #4f46e5;'
      .'border-radius:6px;padding:0;font-family:Georgia,\'Times New Roman\',serif;color:#1f2937">'
      .'<tr><td style="border:6px double #4f46e5;padding:0">'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#4f46e5">'
        .'<tr><td style="padding:14px 24px;color:#fff;text-align:center;letter-spacing:6px;'
        .'font-family:Helvetica,Arial,sans-serif;font-size:11px;font-weight:bold">'
        .'C E R T I F I C A T E &nbsp;&nbsp; O F &nbsp;&nbsp; A C H I E V E M E N T</td></tr></table>'
        .'<div style="padding:36px 40px 28px 40px;text-align:center">'
          .'<table role="presentation" width="100%"><tr>'
            .'<td style="text-align:left;font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#6b7280;letter-spacing:2px">'.strtoupper(h(SITE)).'</td>'
            .'<td style="text-align:right;font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#6b7280">'
              .'<span style="display:inline-block;border:2px solid #f59e0b;color:#b45309;border-radius:50%;'
              .'width:44px;height:44px;line-height:40px;font-weight:bold">'.h($grade).'</span></td>'
          .'</tr></table>'
          .'<p style="margin:22px 0 6px;font-size:15px;letter-spacing:1px;color:#6b7280">This certificate is proudly presented to</p>'
          .'<h1 style="margin:0;font-size:38px;font-weight:normal;color:#111827;'
                .'font-family:\'Playfair Display\',Georgia,serif;line-height:1.2">'.h($name).'</h1>'
          .'<div style="width:120px;height:2px;background:#f59e0b;margin:14px auto"></div>'
          .'<p style="margin:0 0 6px;font-size:15px;color:#4b5563">for successfully completing the</p>'
          .'<p style="margin:0 0 18px;font-size:20px;color:#111827"><b>'.h($exam).'</b> mock test</p>'
          .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" '
                .'style="margin:20px auto;border-collapse:collapse;font-family:Helvetica,Arial,sans-serif;font-size:14px">'
            .'<tr>'
              .'<td style="padding:8px 20px;border-right:1px solid #e5e7eb;text-align:center">'
                .'<div style="font-size:11px;letter-spacing:1px;color:#6b7280">SCORE</div>'
                .'<div style="font-size:26px;color:#16a34a;font-weight:bold;line-height:1">'.$score.'%</div></td>'
              .'<td style="padding:8px 20px;border-right:1px solid #e5e7eb;text-align:center">'
                .'<div style="font-size:11px;letter-spacing:1px;color:#6b7280">MARKS</div>'
                .'<div style="font-size:20px;color:#111827;font-weight:bold;line-height:1">'.$marks.' / '.$max.'</div></td>'
              .'<td style="padding:8px 20px;text-align:center">'
                .'<div style="font-size:11px;letter-spacing:1px;color:#6b7280">CORRECT</div>'
                .'<div style="font-size:20px;color:#111827;font-weight:bold;line-height:1">'.(int)$correct.' / '.(int)$total.'</div></td>'
            .'</tr>'
          .'</table>'
          .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
                .'style="margin-top:32px;font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#6b7280">'
            .'<tr>'
              .'<td style="text-align:left;vertical-align:bottom">'
                .'<div>Certificate ID</div>'
                .'<div style="font-size:14px;color:#111827;font-weight:bold">'.h($idNo).'</div></td>'
              .'<td style="text-align:center;vertical-align:bottom">'
                .'<div>Time taken</div>'
                .'<div style="font-size:14px;color:#111827;font-weight:bold">'.h($min).'</div></td>'
              .'<td style="text-align:right;vertical-align:bottom">'
                .'<div style="font-family:\'Playfair Display\',Georgia,serif;font-size:18px;color:#111827;'
                .'border-bottom:1px solid #9ca3af;padding-bottom:2px">MockPrep India</div>'
                .'<div style="margin-top:4px">Authorised Signatory &middot; '.h($date).'</div></td>'
            .'</tr>'
          .'</table>'
          .'<p style="margin-top:26px;font-size:10px;color:#9ca3af;letter-spacing:.5px">'
            .'Verify online: <a href="'.baseUrl().'/?p=certificate&id='.(int)$aid.'" '
            .'style="color:#4f46e5;text-decoration:none">'.baseUrl().'/?p=certificate&id='.(int)$aid.'</a></p>'
        .'</div>'
      .'</td></tr></table>';

    if(!$isPage) return $body;

    return '<!doctype html><html><head><meta charset="utf-8">'
        .'<title>Certificate &middot; '.h($name).' &middot; '.h($exam).'</title>'
        .'<meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<style>'
          .'@page{size:A4;margin:12mm}'
          .'html,body{margin:0;padding:0;background:#eef2ff;'
             .'font-family:Georgia,"Times New Roman",serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
          .'body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px 0}'
          .'@media print{body{background:#fff;padding:0;min-height:auto}'
             .'body>*{page-break-inside:avoid}'
             .'a{color:#4f46e5;text-decoration:none}}'
        .'</style></head><body>'.$body.'</body></html>';
}

/* ---------- UI HELPERS ---------- */
function authSide($h,$pts){
    return '<div class="authside"><div><div class="big">🎯</div><h2>'.$h.'</h2><ul>'.implode('',array_map(function($x){return '<li>'.$x.'</li>';},$pts)).'</ul></div></div>';
}
function vlinkCard(){
    if(empty($_SESSION['vlink'])) return '';
    $l=$_SESSION['vlink']; unset($_SESSION['vlink']);
    return '<div class="card" style="border-color:var(--pr)"><b>📧 We could not send the email</b><p class="mu">'.h($_SESSION['verr']??'').' You can verify right now with this link:</p><a class="btn p" href="'.h($l).'">Verify my email now</a></div>';
}
function authPage($g,$pill,$title,$sub,$pts,$form){
    return '<div class="auth2 '.$g.'"><div class="a-vis"><i class="blob b1"></i><i class="blob b2"></i><i class="blob b3"></i><div class="a-in"><span class="pill">'.$pill.'</span><h2>'.$title.'</h2><p>'.$sub.'</p><ul class="a-ul">'.implode('',array_map(function($x){return '<li>'.$x.'</li>';},$pts)).'</ul><div class="a-fl"><div class="gl f1">🏆 Top scorers</div><div class="gl f2">⚡ Daily question</div><div class="gl f3">📊 Progress charts</div></div></div></div><div class="a-form"><div class="gcard rv">'.$form.'</div></div></div>';
}
function tiles($a){
    $o='<div class="tiles">';
    foreach($a as $t) $o.='<div class="tile"><i style="--c:'.$t[3].'">'.$t[0].'</i><div><b>'.h($t[1]).'</b><span>'.h($t[2]).'</span></div></div>';
    return $o.'</div>';
}
/**
 * Renders a Chart.js canvas with site-wide styling defaults.
 * - No gridlines (clean look)
 * - Fixed height so cards line up
 * - Doughnut/pie has a proper cutout so it isn't tiny
 * - Line charts have smooth tension, no fill noise
 * - Bar charts have rounded corners
 */
function chart($id,$type,$labels,$data,$label=''){
    $GLOBALS['chart']=1;

    $isRound  = in_array($type,['doughnut','pie'],true);
    $isLine   = $type==='line';
    $isBar    = $type==='bar';

    // palette shared across all charts
    $palette = ['#4f46e5','#db2777','#16a34a','#f59e0b','#0891b2','#7c3aed','#ef4444','#0ea5e9'];

    // per-type dataset extras
    $ds = [
        'label'           => $label,
        'data'            => $data,
        'backgroundColor' => $isLine ? 'rgba(79,70,229,.12)' : $palette,
        'borderColor'     => $isLine ? '#4f46e5' : ($isBar ? '#4f46e5' : $palette),
        'borderWidth'     => $isLine ? 2.5 : 0,
        'tension'         => $isLine ? 0.35 : 0,
        'fill'            => $isLine ? true : false,
        'pointRadius'     => $isLine ? 3 : 0,
        'pointHoverRadius'=> $isLine ? 5 : 0,
        'pointBackgroundColor' => '#4f46e5',
        'pointBorderColor'     => '#fff',
        'pointBorderWidth'     => 2,
    ];
    if($isBar){
        $ds['borderRadius'] = 6;
        $ds['maxBarThickness'] = 42;
    }
    if($isRound){
        $ds['borderWidth'] = 2;
        $ds['borderColor'] = '#ffffff';
        $ds['hoverOffset'] = 6;
    }

    // scale config — no gridlines anywhere
    $scaleXY = [
        'x' => [
            'grid'  => ['display'=>false,'drawBorder'=>false],
            'ticks' => ['color'=>'#9ca3af','font'=>['size'=>11]],
        ],
        'y' => [
            'grid'  => ['display'=>false,'drawBorder'=>false],
            'ticks' => ['color'=>'#9ca3af','font'=>['size'=>11],'precision'=>0],
            'beginAtZero' => true,
        ],
    ];
    if($isBar){
        // bars look better with a very faint horizontal grid removed too
        $scaleXY['x']['grid']['display'] = false;
    }

    $options = [
        'responsive'          => true,
        'maintainAspectRatio' => false,   // fills the fixed-height wrapper
        'plugins'             => [
            'legend' => [
                'display'  => $isRound,
                'position' => 'bottom',
                'labels'   => [
                    'boxWidth'  => 10,
                    'boxHeight' => 10,
                    'padding'   => 12,
                    'color'     => '#6b7280',
                    'font'      => ['size'=>12],
                ],
            ],
            'tooltip' => [
                'backgroundColor' => '#111827',
                'padding'         => 10,
                'cornerRadius'    => 8,
                'titleFont'       => ['size'=>12,'weight'=>'600'],
                'bodyFont'        => ['size'=>12],
            ],
        ],
        'elements' => [
            'arc' => ['borderWidth'=>$isRound?2:0],
        ],
    ];
    if($isRound){
        $options['cutout'] = '62%';      // bigger ring, not a tiny hole
    } else {
        $options['scales'] = $scaleXY;
    }

    $json = json_encode($ds, JSON_UNESCAPED_SLASHES);
    $opts = json_encode($options, JSON_UNESCAPED_SLASHES);

    echo '<div class="chart-wrap"><canvas id="'.$id.'"></canvas></div>'
        .'<script>window.addEventListener("load",function(){'
        .'new Chart(document.getElementById("'.$id.'"),{'
        .'type:"'.$type.'",'
        .'data:{labels:'.json_encode($labels).',datasets:['.$json.']},'
        .'options:'.$opts
        .'});})</script>';
}
function table($h,$r){
    echo '<div class="card sc"><table><tr>';
    foreach($h as $x) echo '<th>'.$x.'</th>';
    echo '</tr>';
    foreach($r as $x){
        echo '<tr>';
        foreach($x as $c) echo '<td>'.$c.'</td>';
        echo '</tr>';
    }
    if(!$r) echo '<tr><td class="mu">Nothing here yet.</td></tr>';
    echo '</table></div>';
}
function noticeBar(){
    $n=srows('SELECT * FROM notices WHERE active=1 ORDER BY id DESC LIMIT 12');
    if(!$n) $n=[['icon'=>'🎉','title'=>'Welcome to '.SITE.' - take a free mock test today','link'=>'']];
    $h='';
    foreach($n as $x){
        $t=h($x['icon'].' '.$x['title']);
        $h.=$x['link']?'<a href="'.h($x['link']).'" rel="noopener">'.$t.'</a>':'<span>'.$t.'</span>';
    }
    return '<div class="tk rv"><div class="tk-l">📢 What\'s new</div><div class="tk-m"><div class="tk-t">'.$h.$h.'</div></div></div>';
}

/* ---------- LAYOUT ---------- */
function head($title,$links=[],$home=null,$shell=false){
    if(empty($_SESSION['v'])){
        $_SESSION['v']=1;
        try{ q("UPDATE stats SET v=v+1 WHERE k='visits'"); }catch(Exception $e){}
    }
    $S=$GLOBALS['seo']??[];
    $bare=!empty($GLOBALS['bare']);
    $GLOBALS['shell']=$shell&&!$bare;
    $home=$home??BASE.'/';
    $u=me();
    $full=$S['t']??($title.' | '.SITE);
    $d=$S['d']??'Free timed mock tests, topic-wise practice, daily question and leaderboard for OAVS, SSC, OTET and other government exams.';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($full).'</title><meta name="description" content="'.h($d).'">';
    if(!empty($S['noindex'])) echo '<meta name="robots" content="noindex,nofollow">';
    else echo '<link rel="canonical" href="'.h(baseUrl().($S['path']??'/')).'">';
    echo '<meta property="og:title" content="'.h($full).'"><meta property="og:description" content="'.h($d).'"><meta property="og:type" content="website"><meta property="og:site_name" content="'.SITE.'"><meta name="twitter:card" content="summary"><meta name="theme-color" content="#4f46e5">';
    if(!empty($S['ld'])) echo '<script type="application/ld+json">'.json_encode($S['ld'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG).'</script>';
    echo '<link rel="stylesheet" href="'.BASE.'/assets/app.css"><script>document.documentElement.className+=" js";var t=localStorage.getItem("th");if(t)document.documentElement.dataset.theme=t;</script></head><body>';

    if($bare) echo '<main class="bare">';
    elseif($shell){
        $cur=$GLOBALS['cur']??'home';
        echo '<div class="ov" onclick="sd()"></div><div class="shell"><aside class="side"><a class="brand" href="'.$home.'">🎯 '.SITE.'</a>';
        foreach($links as $l){
            parse_str((string)parse_url($l[0],PHP_URL_QUERY),$q2);
            $k=$q2['p']??$q2['t']??'home';
            echo '<a href="'.$l[0].'" class="'.($k===$cur?'on':'').'">'.$l[1].'</a>';
        }
        echo '<div class="user"><b>'.h($u['name']??'').'</b><span class="mu">'.h(ucfirst($u['role']??'')).'</span></div></aside><div class="content"><div class="top"><button class="ic burger" onclick="sd()" aria-label="Menu">☰</button><h1>'.h($title).'</h1><span class="grow"></span><button class="ic" onclick="tg()" title="Light / dark">🌓</button></div><main>';
    } else {
        echo '<nav><a class="brand" href="'.$home.'">🎯 '.SITE.'</a><button class="ic burger" onclick="mn()" aria-label="Menu">☰</button><div class="links" id="mn">';
        foreach($links as $l) echo '<a href="'.$l[0].'">'.$l[1].'</a>';
        echo '</div><button class="ic" onclick="tg()" title="Light / dark">🌓</button></nav><main>';
    }
    if($f=flash()) echo '<div class="toast">'.h($f).'</div>';
}
function foot(){
    $bare  = !empty($GLOBALS['bare']);
    $shell = !empty($GLOBALS['shell']);
    echo '</main>';

    if(!$bare){
        $year  = date('Y');
        $site  = defined('SITE') ? h(SITE) : 'MockPrep India';
        $base  = defined('BASE') ? BASE : '';

        echo <<<HTML
<style>
.ft{background:#0f172a;color:#cbd5e1;padding:48px 16px 20px;margin-top:48px;font-family:system-ui,sans-serif}
.ft .wrap{max-width:1080px;margin:auto;display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:32px}
@media(max-width:800px){.ft .wrap{grid-template-columns:1fr 1fr}}
@media(max-width:500px){.ft .wrap{grid-template-columns:1fr}}
.ft h4{color:#fff;margin:0 0 14px;font-size:13px;text-transform:uppercase;letter-spacing:.5px}
.ft a{color:#cbd5e1;text-decoration:none;display:block;padding:4px 0;font-size:14px}
.ft a:hover{color:#fff;text-decoration:underline}
.ft .brand b{color:#fff;font-size:20px;display:block;margin-bottom:10px}
.ft .brand p{color:#94a3b8;font-size:13px;line-height:1.6;margin:0}
.ft .social{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.ft .social a{display:inline-flex;width:36px;height:36px;border-radius:50%;background:#1e293b;align-items:center;justify-content:center;transition:.2s;padding:0}
.ft .social a:hover{background:#4f46e5;transform:translateY(-2px)}
.ft .social svg{width:18px;height:18px;fill:#fff}
.ft .bar{border-top:1px solid #1e293b;margin-top:36px;padding-top:18px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:13px;color:#94a3b8;max-width:1080px;margin-left:auto;margin-right:auto}
.ft .bar b{color:#e2e8f0}
</style>
<footer class="ft">
  <div class="wrap">
    <div class="brand">
      <b>🎯 $site</b>
      <p>Free, exam-pattern mock tests and topic-wise practice for OAVS, SSC, OTET and more. Built by students, for students.</p>
      <div class="social">
        <a href="https://www.youtube.com/@MockPrepIndia" target="_blank" rel="noopener" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.2 3.6-6.2 3.6z"/></svg></a>
        <a href="https://t.me/MockPrepIndia" target="_blank" rel="noopener" aria-label="Telegram"><svg viewBox="0 0 24 24"><path d="M9.8 16.1l-.4 5.5c.6 0 .9-.2 1.2-.5l2.8-2.7 5.8 4.3c1.1.6 1.8.3 2.1-1l3.8-17.8c.4-1.6-.6-2.2-1.7-1.8L1.3 10.3c-1.6.6-1.6 1.5-.3 1.9l5.1 1.6L18.1 6.5c.6-.4 1.1-.2.7.2L9.8 16.1z"/></svg></a>
        <a href="https://instagram.com/mockprepindia" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.3 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .3-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.3-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.3 2.2-.4C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.7.1-1.1.1-1.7.2-2.1.4-.5.2-.9.4-1.2.7-.3.3-.5.7-.7 1.2-.2.4-.3 1-.4 2.1-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1 1.1.2 1.7.4 2.1.2.5.4.9.7 1.2.3.3.7.5 1.2.7.4.2 1 .3 2.1.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.2 2.1-.4.5-.2.9-.4 1.2-.7.3-.3.5-.7.7-1.2.2-.4.3-1 .4-2.1.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-1.1-.2-1.7-.4-2.1-.2-.5-.4-.9-.7-1.2-.3-.3-.7-.5-1.2-.7-.4-.2-1-.3-2.1-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 8.1a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4zm6.3-8.3a1.1 1.1 0 1 1-2.3 0 1.1 1.1 0 0 1 2.3 0z"/></svg></a>
        <a href="https://x.com/mockprepindia" target="_blank" rel="noopener" aria-label="X (Twitter)"><svg viewBox="0 0 24 24"><path d="M18.9 2H22l-7 8 8.2 12H16l-5-7.3L5.3 22H2l7.5-8.6L1.5 2H8l4.5 6.6L18.9 2zm-1.1 18h1.7L7.3 3.9H5.5L17.8 20z"/></svg></a>
        <a href="https://wa.me/7751975616" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M17.5 14.4c-.3-.1-1.7-.8-1.9-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-1.5-.7-2.4-1.3-3.4-2.9-.3-.4.3-.4.8-1.3.1-.2 0-.4-.1-.6-.1-.1-.7-1.6-.9-2.2-.2-.5-.4-.5-.6-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.1 1-1.1 2.4s1.1 2.8 1.3 3c.1.2 2 3.1 4.9 4.3 1.8.8 2.5.9 3.4.7.5-.1 1.7-.7 1.9-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.2-.6-.4zM12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2z"/></svg></a>
      </div>
    </div>
    <div>
      <h4>Students</h4>
      <a href="$base/?p=tests">Mock tests</a>
      <a href="$base/?p=practice">Topic practice</a>
      <a href="$base/?p=daily">Daily question</a>
      <a href="$base/?p=leaderboard">Leaderboard</a>
      <a href="$base/?p=community">Community</a>
    </div>
    <div>
      <h4>Exams</h4>
      <a href="$base/exam/oavs">OAVS</a>
      <a href="$base/exam/ssc">SSC</a>
      <a href="$base/exam/otet">OTET</a>
      <a href="$base/?p=exams">Upcoming exams</a>
      <a href="$base/?p=notes">Notes &amp; tricks</a>
    </div>
    <div>
      <h4>Company</h4>
      <a href="$base/?p=about">About us</a>
      <a href="$base/?p=privacy">Privacy policy</a>
      <a href="$base/teacher/">For teachers</a>
      <a href="$base/?p=requests">Any request</a>
       <a href="$base/?p=feedback">Feedback</a>
      <a href="mailto:hello@mockprep.in">Contact</a>
    </div>
  </div>
  <div class="bar">
    <span>© $year <b>$site</b>. All rights reserved.</span>
    <span>Made with ❤ for Indian aspirants</span>
    <span>Developed by <b>@ Ramakanta Gahan</b></span>
  </div>
</footer>
HTML;

        /* close shell wrappers opened by head() */
        if($shell) echo '</div></div>';
    }

    /* ── scripts (preserved from your original) ── */
    echo (!empty($GLOBALS['chart']) ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>' : '')
        .(!empty($GLOBALS['syl']) ? '<script>var SYL='.json_encode(sylData(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS).';</script>' : '')
        .'<script src="'.BASE.'/assets/app.js"></script>';

    /* ── floating action buttons (AI Tutor + WhatsApp) ──
       Skip on bare/exam pages so the CBT screen stays clean. */
    if(!$bare) echo aiFloat();

    echo '</body></html>';
}

/* NOTE: aiGenerate() now lives in lib_class.php (multi-provider with automatic switching). */

function aiFloat(){
    /* ── WhatsApp group invite link — change this to YOUR group link ── */
    $WA = 'https://chat.whatsapp.com/ECooGIiutVRJYAP5S6mSiW';

    $css='<style>
/* ────────────────────────────────────────────────
   FLOATING BUTTON STACK  (bottom-right)
   Order (top → bottom):  🤖 AI Tutor  ·  🟢 WhatsApp
   ──────────────────────────────────────────────── */
#fabstack{position:fixed;right:18px;bottom:18px;z-index:9998;
  display:flex;flex-direction:column;align-items:center;gap:12px}

/* shared look for both round buttons */
#aifab,#wafab{
  position:relative;width:58px;height:58px;border-radius:50%;border:0;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 10px 28px rgba(0,0,0,.28);
  transition:transform .2s ease, box-shadow .2s ease;
}
#aifab:hover,#wafab:hover{transform:translateY(-2px) scale(1.08);box-shadow:0 14px 34px rgba(0,0,0,.32)}
#aifab:active,#wafab:active{transform:scale(.96)}

/* AI Tutor — gradient, with a soft pulse */
#aifab{font-size:26px;color:#fff;
  background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#db2777)}
#aifab::after{content:"";position:absolute;inset:0;border-radius:50%;
  box-shadow:0 0 0 0 rgba(124,58,237,.55);animation:aifpulse 2.4s infinite;pointer-events:none}
@keyframes aifpulse{
  0%{box-shadow:0 0 0 0 rgba(124,58,237,.55)}
  70%{box-shadow:0 0 0 16px rgba(124,58,237,0)}
  100%{box-shadow:0 0 0 0 rgba(124,58,237,0)}
}

/* WhatsApp — brand green */
#wafab{background:#25D366;color:#fff;text-decoration:none}
#wafab svg{width:30px;height:30px;fill:#fff;display:block}

/* hover tooltip on both */
#aifab .tip,#wafab .tip{
  position:absolute;right:70px;top:50%;transform:translateY(-50%);
  background:#111827;color:#fff;font-size:12px;padding:5px 10px;border-radius:8px;
  white-space:nowrap;opacity:0;pointer-events:none;transition:.2s;
}
#aifab:hover .tip,#wafab:hover .tip{opacity:1}

/* ── AI chat panel (opens above the stack) ── */
#aifp{position:fixed;right:18px;bottom:150px;z-index:9999;
  width:370px;max-width:calc(100vw - 24px);height:520px;max-height:calc(100vh - 180px);
  display:none;flex-direction:column;background:#fff;color:#111827;
  border:1px solid rgba(128,128,128,.3);border-radius:18px;
  box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden}
#aifp.open{display:flex}
html[data-theme="dark"] #aifp{background:#1e293b;color:#e5e7eb}
.aif-h{display:flex;align-items:center;gap:8px;padding:12px 14px;color:#fff;
  background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#db2777)}
.aif-h b{flex:1}
.aif-h button{background:rgba(255,255,255,.2);color:#fff;border:0;border-radius:8px;
  padding:4px 9px;cursor:pointer;margin:0;width:auto}
#aifb{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px}
.aif-m{max-width:88%;padding:8px 12px;border-radius:14px;line-height:1.5;font-size:14px;word-wrap:break-word}
.aif-m.user{align-self:flex-end;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-bottom-right-radius:4px}
.aif-m.assistant{align-self:flex-start;background:rgba(79,70,229,.12);border-bottom-left-radius:4px}
.aif-m.err{align-self:flex-start;background:#fee2e2;color:#991b1b}
.aif-m pre{white-space:pre-wrap;background:rgba(0,0,0,.1);padding:6px;border-radius:8px}
.aif-in{display:flex;gap:6px;padding:10px;border-top:1px solid rgba(128,128,128,.25)}
.aif-in textarea{flex:1;margin:0;resize:none;font-size:14px}
.aif-in button{margin:0;width:auto}

/* small screens */
@media(max-width:520px){
  #fabstack{right:12px;bottom:12px;gap:10px}
  #aifab,#wafab{width:52px;height:52px}
  #aifab{font-size:23px}
  #wafab svg{width:26px;height:26px}
  #aifp{right:8px;left:8px;width:auto;bottom:132px}
}

/* never print the floating stack */
@media print{#fabstack,#aifp{display:none!important}}
</style>';

    /* ── markup: one wrapper holds BOTH round buttons + the chat panel ── */
    $html =
      /* AI Tutor chat panel (hidden until #aifab is clicked) */
      '<div id="aifp" role="dialog" aria-label="AI Tutor">'
        .'<div class="aif-h"><span>🤖</span><b>AI Tutor</b>'
          .'<button type="button" id="aifc" title="New chat">🗑</button>'
          .'<button type="button" id="aifx" title="Close">✕</button>'
        .'</div>'
        .'<div id="aifb"></div>'
        .'<div class="aif-in">'
          .'<textarea id="aifi" rows="2" maxlength="2000" placeholder="Ask anything… (Enter to send)"></textarea>'
          .'<button class="p" type="button" id="aifs">➤</button>'
        .'</div>'
      .'</div>'

      /* stacked round buttons */
      .'<div id="fabstack">'
        /* 1) AI Tutor (top) */
        .'<button id="aifab" type="button" aria-label="Open AI Tutor">🤖'
          .'<span class="tip">Ask AI Tutor</span>'
        .'</button>'
        /* 2) WhatsApp group (bottom) — opens in a new tab */
        .'<a id="wafab" href="'.$WA.'" target="_blank" rel="noopener" aria-label="Join our WhatsApp group">'
          .'<svg viewBox="0 0 24 24" aria-hidden="true">'
            .'<path d="M17.5 14.4c-.3-.1-1.7-.8-1.9-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-1.5-.7-2.4-1.3-3.4-2.9-.3-.4.3-.4.8-1.3.1-.2 0-.4-.1-.6-.1-.1-.7-1.6-.9-2.2-.2-.5-.4-.5-.6-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.1 1-1.1 2.4s1.1 2.8 1.3 3c.1.2 2 3.1 4.9 4.3 1.8.8 2.5.9 3.4.7.5-.1 1.7-.7 1.9-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.2-.6-.4z"/>'
            .'<path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm0 18.2c-1.6 0-3.2-.4-4.6-1.3l-.3-.2-3.1.8.8-3-.2-.3A8.2 8.2 0 1 1 12 20.2z"/>'
          .'</svg>'
          .'<span class="tip">Join WhatsApp group</span>'
        .'</a>'
      .'</div>';

    /* ── chat behaviour (unchanged logic, same aichat endpoint) ── */
    $js=<<<'JS'
(function(){
var U="__URL__",C="__CSRF__",
    fab=document.getElementById("aifab"),
    pn =document.getElementById("aifp"),
    bx =document.getElementById("aifb"),
    inp=document.getElementById("aifi"),
    btn=document.getElementById("aifs"),
    busy=false,hist=[];
try{hist=JSON.parse(sessionStorage.getItem("aif")||"[]")}catch(e){}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function fmt(s){return esc(s).replace(/```([\s\S]*?)```/g,'<pre>$1</pre>').replace(/`([^`]+)`/g,'<code>$1</code>').replace(/\*\*([^*]+)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>')}
function add(cls,html){var d=document.createElement("div");d.className="aif-m "+cls;d.innerHTML=html;bx.appendChild(d);bx.scrollTop=bx.scrollHeight;return d}
function save(){try{sessionStorage.setItem("aif",JSON.stringify(hist.slice(-16)))}catch(e){}}
function draw(){bx.innerHTML="";add("assistant","Hi! Ask me any doubt, study plan or concept 😊");hist.forEach(function(m){add(m.role,m.role==="user"?esc(m.content).replace(/\n/g,"<br>"):fmt(m.content))})}
function send(){
  var t=inp.value.trim();if(!t||busy)return;
  busy=true;btn.disabled=true;inp.value="";
  add("user",esc(t).replace(/\n/g,"<br>"));hist.push({role:"user",content:t});
  var w=add("assistant","Thinking…");
  fetch(U,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-CSRF-Token":C},body:JSON.stringify({messages:hist})})
  .then(function(r){return r.json()})
  .then(function(r){w.remove();
    if(r&&r.reply){add("assistant",fmt(r.reply));hist.push({role:"assistant",content:r.reply});save()}
    else{hist.pop();add("err",esc((r&&r.message)||"Something went wrong."))}})
  .catch(function(){w.remove();hist.pop();add("err","Network problem. Try again.")})
  .then(function(){busy=false;btn.disabled=false;inp.focus()});
}
fab.onclick=function(){pn.classList.toggle("open");if(pn.classList.contains("open")){draw();inp.focus()}};
document.getElementById("aifx").onclick=function(){pn.classList.remove("open")};
document.getElementById("aifc").onclick=function(){hist=[];save();draw()};
btn.onclick=send;
inp.addEventListener("keydown",function(e){if(e.key==="Enter"&&!e.shiftKey){e.preventDefault();send()}});
})();
JS;
    return $css.$html.'<script>'.str_replace(['__URL__','__CSRF__'],[BASE.'/?p=aichat',csrf()],$js).'</script>';
}