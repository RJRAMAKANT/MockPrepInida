<?php
require __DIR__.'/../lib.php';
require __DIR__.'/../lib_class.php';
$A=BASE.'/'.ADMIN_DIR.'/';
$GLOBALS['seo']=['noindex'=>1,'t'=>'Admin | '.SITE];
$t=$_GET['t']??'dash';
migrate();
$GLOBALS['cur']=$t;
$u=me();
$ok=$u&&$u['role']==='admin';

/* ---------- clean logout ---------- */
if($t==='logout'){
    $_SESSION=[];
    if(ini_get('session.use_cookies')){
        $pp=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$pp['path'],$pp['domain'],$pp['secure'],$pp['httponly']);
    }
    session_destroy();
    go($A);
}

/* sample CSV download for the question bank upload */
if($t==='sample' && $ok){
    $codes=array_keys(exams());
    $ex=$codes[0] ?? 'OAVS';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin-question-format.csv"');
    $o=fopen('php://output','w');
    fwrite($o,"\xEF\xBB\xBF");
    fputcsv($o,['exam','subject','topic','question','A','B','C','D','answer','explanation']);
    fputcsv($o,[$ex,'Reasoning','Direction Sense','A man walks 5 km north, then turns right and walks 3 km. In which direction is he from the start?','North-West','North-East','South-East','South-West','B','He ends up north and east of the start.']);
    fputcsv($o,[$ex,'Maths','Percentage','What is 20% of 250?','25','40','50','60','C','20/100 x 250 = 50.']);
    fclose($o); exit;
}

ob_start();

if(!$ok){
    if(isset($_POST['email'])&&doLogin('admin','a')) go($A);
    echo vlinkCard().authPage('g3','🛠 Admin console','Run the whole platform',
        'Question bank, syllabus, mock sets, notices and users in one place.',
        ['❓ Manage the question bank','🧭 Syllabus and exam patterns','🗂 Build mock sets','📢 Publish notices'],
        '<h1>Admin login</h1><form method="post">'.field()
        .'<label>Email</label><input name="email" type="email" required>'
        .'<label>Password</label><input name="password" type="password" required>'
        .'<button class="p" style="width:100%">Login →</button></form>'
        .'<p class="mu" style="text-align:center"><a href="'.BASE.'/?p=forgot">Forgot password?</a> · '
        .'<a href="'.BASE.'/?p=resend">Resend verification</a></p>');
    $links=[];
}else{
    check();

    /* ---------- shared helpers ---------- */
    $back=function($m)use($A,$t){flash($m);go($A.'?t='.$t);};
    $del=function($id,$extra=''){
        return '<form method="post" style="display:inline">'.field().$extra
            .'<button name="del" value="'.$id.'" onclick="return confirm(\'Delete?\')">✕</button></form>';
    };
    $genTopic=function($e,$s,$tp,$n){
        $g=aiGenerate($e,$s,$n,$tp);$c=0;
        foreach($g as $x) if(isset($x['o'][3]))
            $c+=insertQ($e,$s,$x['q'],$x['o'][0],$x['o'][1],$x['o'][2],$x['o'][3],(int)$x['a'],$x['x']??'','ai',$tp);
        return $c;
    };

    /* ============ POST HANDLERS ============ */
    if($_SERVER['REQUEST_METHOD']==='POST'){

        /* ---- Test email ---- */
        if($t==='dash'&&isset($_POST['testmail'])){
            $okMail=mailTo($u['email'],'Test email from '.SITE,
                '<p>This is a test message from <b>'.h(SITE).'</b>.</p>'
                .'<p>If you can read this, email is working on the server.</p>'
                .'<p style="color:#555;font-size:12px">Sent at '.date('r').'</p>');
            if($okMail){
                $ch=defined('MAIL_WEBHOOK_URL')&&MAIL_WEBHOOK_URL!==''?'webhook'
                   :(SMTP_HOST!==''?'SMTP':'PHP mail()');
                $back('Test email sent via '.$ch.'. Check inbox and spam folder.');
            }else{
                $back('Mail failed: '.($GLOBALS['mailerr']?:'unknown error'));
            }
        }

        /* ---- AI status ---- */
        if($t==='ai'){
            ensure_ai();
            if(isset($_POST['refresh'])){
                q('DELETE FROM ai_models'); q('DELETE FROM ai_cool');
                $back('Model cache and provider pauses cleared. Click "Test AI now".');
            }
            if(isset($_POST['test'])){
                $r=ai_call('You are a connection test.',[['role'=>'user','content'=>'Reply with the single word OK.']],false,0);
                $back($r!==''
                    ? 'AI works via '.$GLOBALS['aiused'].': '.mb_substr($r,0,60)
                    : 'All providers failed: '.$GLOBALS['aierr']);
            }
        }

        /* ---- Questions ---- */
        if($t==='questions'){
            if(isset($_POST['q'])){
                $back(insertQ($_POST['exam'],$_POST['subject'],$_POST['q'],
                              $_POST['a'],$_POST['b'],$_POST['c'],$_POST['d'],
                              (int)$_POST['ans'],$_POST['exp'],'manual',$_POST['topic']??'')
                     ?'Question added':'Invalid question, or the same question already exists');
            }

            if(isset($_FILES['csv'])&&is_uploaded_file($_FILES['csv']['tmp_name'])){
                $GLOBALS['newq']=[];
                $f=fopen($_FILES['csv']['tmp_name'],'r');
                $n=0;$bad=0;$log=[];
                fgetcsv($f);
                $rowNo=1;
                while(($r=fgetcsv($f))!==false){
                    $rowNo++;
                    if(!$r||count($r)<9){$bad++;$log[]="row $rowNo: too few columns";continue;}
                    if($rowNo===2)$r[0]=preg_replace('/^\xEF\xBB\xBF/','',$r[0]);
                    $r=array_map(function($x){return trim((string)$x);},$r);
                    $examCode=strtoupper($r[0]);
                    $subject=$r[1];$topic=$r[2];$question=$r[3];
                    $a=$r[4];$b=$r[5];$c=$r[6];$d=$r[7];
                    $ansLetter=$r[8];$expl=$r[9]??'';
                    if($question===''||$a===''||$b===''||$c===''||$d===''){
                        $bad++;$log[]="row $rowNo: empty question or option";continue;
                    }
                    $examRow = row('SELECT code,title FROM exam_defs WHERE code=?',[$examCode]);
                    if(!$examRow){
                        $alt = val('SELECT code FROM exam_defs WHERE TRIM(code)=? OR UPPER(code)=?',[$examCode,$examCode]);
                        if($alt){ $examCode = $alt; }
                        else{
                            $known = implode(', ',array_column(rows('SELECT code FROM exam_defs LIMIT 20'),'code'));
                            $bad++;
                            $log[]="row $rowNo: unknown exam '$examCode'. Known codes: [".($known?:'<exam_defs is EMPTY>')."]";
                            continue;
                        }
                    }
                    $ansIdx=ansIdx($ansLetter);
                    if($ansIdx===false||$ansIdx===null||$ansIdx<0){
                        $bad++;$log[]="row $rowNo: bad answer letter '$ansLetter'";continue;
                    }
                    $k=insertQ($examCode,$subject,$question,$a,$b,$c,$d,(int)$ansIdx,$expl,'csv',$topic);
                    $n+=$k;
                    if(!$k){$bad++;$log[]="row $rowNo: skipped (duplicate question or invalid)";}
                }
                fclose($f);
                $sn=trim($_POST['setname']??'');
                if($n&&$sn){
                    $ex=val('SELECT exam FROM questions WHERE id=?',[$GLOBALS['newq'][0]]);
                    q('INSERT INTO qsets(title,exam,minutes) VALUES(?,?,?)',
                      [$sn,$ex,max(5,(int)ceil($n*0.9))]);
                    addToSet(db()->lastInsertId(),$GLOBALS['newq']);
                }
                $msg="$n questions imported";
                if($bad){
                    $msg.=", $bad rows skipped";
                    if($log) $msg.=' — '.implode('; ',array_slice($log,0,5))
                        .(count($log)>5?' …('.count($log).' total)':'');
                }
                $msg.=($n&&$sn?'. Practice set created.':'.');
                $back($msg);
            }

            if(isset($_POST['ai'])){
                $c=$genTopic($_POST['exam'],$_POST['subject'],$_POST['topic']??'',min(20,max(1,(int)$_POST['n'])));
                $back($c?"$c AI questions saved (duplicates skipped). Please review them."
                       :'AI returned no new questions. '.($GLOBALS['aierr']?:'Check Admin → AI status, or upload a CSV.'));
            }
            if(isset($_POST['del'])){q('DELETE FROM questions WHERE id=?',[(int)$_POST['del']]);$back('Deleted');}
        }

        /* ---- Syllabus ---- */
        if($t==='syllabus'){
            if(isset($_POST['setmin'])){q('UPDATE exam_defs SET minutes=? WHERE code=?',[max(5,(int)$_POST['minutes']),$_POST['exam']]);$back('Exam time updated');}
            if(isset($_POST['setcount'])){q('UPDATE subjects SET qcount=? WHERE exam=? AND name=?',[max(0,(int)$_POST['qcount']),$_POST['exam'],$_POST['subject']]);$back('Pattern updated');}
            if(isset($_POST['cloneexam'])){
                $n=strtoupper(preg_replace('/[^A-Za-z0-9_-]/','',$_POST['code']));
                $o=$_POST['exam'];
                if($n&&!val('SELECT code FROM exam_defs WHERE code=?',[$n])){
                    q('INSERT INTO exam_defs(code,title,minutes) SELECT ?,?,minutes FROM exam_defs WHERE code=?',[$n,$_POST['title']?:$n,$o]);
                    q('INSERT INTO subjects(exam,name,qcount) SELECT ?,name,qcount FROM subjects WHERE exam=?',[$n,$o]);
                    q('INSERT INTO topics(exam,subject,name) SELECT ?,subject,name FROM topics WHERE exam=?',[$n,$o]);
                    $back('Exam cloned. Now edit its subject-related section.');
                }
                $back('Choose a new, unused code');
            }
            if(isset($_POST['addexam'])){
                $c=strtoupper(preg_replace('/[^A-Za-z0-9_-]/','',$_POST['code']));
                if($c)q('INSERT IGNORE INTO exam_defs(code,title,minutes) VALUES(?,?,?)',[$c,$_POST['title'],max(5,(int)$_POST['minutes'])]);
                $back('Exam added');
            }
            if(isset($_POST['addsub'])){
                if(!val('SELECT id FROM subjects WHERE exam=? AND name=?',[$_POST['exam'],trim($_POST['name'])]))
                    q('INSERT INTO subjects(exam,name,qcount) VALUES(?,?,?)',[$_POST['exam'],trim($_POST['name']),max(1,(int)$_POST['qcount'])]);
                $back('Subject added');
            }
            if(isset($_POST['addtops'])){
                $c=0;
                foreach(preg_split('/\r?\n/',$_POST['names']) as $nm){
                    $nm=trim($nm);
                    if($nm&&!val('SELECT id FROM topics WHERE exam=? AND subject=? AND name=?',[$_POST['exam'],$_POST['subject'],$nm])){
                        q('INSERT INTO topics(exam,subject,name) VALUES(?,?,?)',[$_POST['exam'],$_POST['subject'],$nm]);
                        $c++;
                    }
                }
                $back("$c topics added");
            }
            if(isset($_POST['deltop'])){q('DELETE FROM topics WHERE exam=? AND subject=? AND name=?',[$_POST['exam'],$_POST['subject'],$_POST['topic']]);$back('Topic removed (its questions are kept)');}
            if(isset($_POST['delsub'])){q('DELETE FROM topics WHERE exam=? AND subject=?',[$_POST['exam'],$_POST['subject']]);q('DELETE FROM subjects WHERE exam=? AND name=?',[$_POST['exam'],$_POST['subject']]);$back('Subject removed');}
            if(isset($_POST['aitopic'])){
                $c=$genTopic($_POST['exam'],$_POST['subject'],$_POST['topic'],10);
                $back($c?"$c questions generated for ".$_POST['topic'].'. Please review them.'
                       :'AI returned no new questions. '.($GLOBALS['aierr']?:'Check Admin → AI status, or upload a CSV.'));
            }
        }

        /* ---- Sets ---- */
        if($t==='sets'){
            /* ---------- PATTERN BUILDER (supports __ANY__ and section order) ---------- */
            if(isset($_POST['mkpattern'])){
                $e       = (string) ($_POST['exam'] ?? '');
                $anyExam = ($e === '__ANY__');
                $E       = exams();

                if(!$anyExam && !isset($E[$e])){
                    $back('Unknown exam code.');
                }

                /* read pattern by NAME; accepts both keyed and legacy indexed formats */
                $wanted = [];          // subject => [ topic => N ]
                $subjectRandom = [];   // subject => N

                if(isset($_POST['tc']) && is_array($_POST['tc'])
                   && isset($_POST['tn']) && is_array($_POST['tn'])){
                    // Legacy indexed format
                    foreach(($_POST['sub'] ?? []) as $i => $sb){
                        $sb = trim((string)$sb);
                        if($sb==='') continue;
                        $wanted[$sb] = $wanted[$sb] ?? [];
                        foreach(($_POST['tn'][$i] ?? []) as $j => $tp){
                            $tp = trim((string)$tp);
                            $n  = (int)($_POST['tc'][$i][$j] ?? 0);
                            if($tp!=='' && $n>0){
                                $wanted[$sb][$tp] = ($wanted[$sb][$tp] ?? 0) + $n;
                            }
                        }
                        $rnd = (int)($_POST['rnd'][$i] ?? 0);
                        if($rnd>0) $subjectRandom[$sb] = ($subjectRandom[$sb] ?? 0) + $rnd;
                    }
                }else{
                    // New name-keyed format
                    foreach(($_POST['tc'] ?? []) as $sb => $tps){
                        if(!is_array($tps)) continue;
                        $sb = trim((string)$sb);
                        if($sb==='') continue;
                        foreach($tps as $tp => $n){
                            $tp = trim((string)$tp);
                            $n  = (int)$n;
                            if($tp!=='' && $n>0){
                                $wanted[$sb][$tp] = ($wanted[$sb][$tp] ?? 0) + $n;
                            }
                        }
                    }
                    foreach(($_POST['rnd'] ?? []) as $sb => $n){
                        $n = (int)$n;
                        if($n>0) $subjectRandom[(string)$sb] = $n;
                    }
                }

                /* ---- resolve section order ---- */
                $postedOrder = $_POST['sec_order'] ?? [];
                $postedOrder = is_array($postedOrder) ? $postedOrder : [$postedOrder];
                $postedOrder = array_values(array_filter(array_map(function($x){return trim((string)$x);}, $postedOrder), function($x){return $x!=='';}));

                $allSubjects = array_unique(array_merge(
                    array_keys($wanted),
                    array_keys($subjectRandom),
                    $postedOrder
                ));
                $orderedSubjects = [];
                foreach($postedOrder as $sb) if(in_array($sb, $allSubjects, true)) $orderedSubjects[] = $sb;
                foreach($allSubjects as $sb) if(!in_array($sb, $orderedSubjects, true)) $orderedSubjects[] = $sb;

                /* ---- pick questions IN ORDER, never repeating (same id OR same text) ---- */
                $ids = []; $seen = []; $seenT = []; $short = [];
                $requested = 0; $filled = 0;

                foreach($orderedSubjects as $sb){
                    $sbRandom = (int)($subjectRandom[$sb] ?? 0);

                    /* subject-level "random any topic" picks first */
                    if($sbRandom > 0){
                        $requested += $sbRandom;
                        $add = $anyExam
                            ? pickQ('subject=?',[$sb],$sbRandom,$ids,$seen,$seenT)
                            : pickQ('exam=? AND subject=?',[$e,$sb],$sbRandom,$ids,$seen,$seenT);
                        $filled += $add;
                        if($add < $sbRandom) $short[] = $sb.' (random) ('.$add.'/'.$sbRandom.')';
                    }

                    /* then the per-topic picks */
                    foreach(($wanted[$sb] ?? []) as $tp => $n){
                        $requested += $n;
                        $add = $anyExam
                            ? pickQ('subject=? AND topic=?',[$sb,$tp],$n,$ids,$seen,$seenT)
                            : pickQ('exam=? AND subject=? AND topic=?',[$e,$sb,$tp],$n,$ids,$seen,$seenT);
                        $filled += $add;
                        if($add < $n) $short[] = $sb.' · '.$tp.' ('.$add.'/'.$n.')';
                    }
                }

                if(!$ids){
                    $back('No questions in the bank match this selection. Add questions first, or loosen the filters. Requested: '.$requested.' — got 0.');
                }

                /* NOTE: no shuffle() — we keep the exact section order. */
                $title   = trim((string)($_POST['title'] ?? '')) ?: ('Set '.date('Y-m-d H:i'));
                $setExam = $anyExam ? 'MIXED' : $e;
                q('INSERT INTO qsets(title,exam,minutes,marks,neg) VALUES(?,?,?,?,?)',[
                    $title,
                    $setExam,
                    max(5,(int)($_POST['minutes'] ?? 30)),
                    max(0.25,(float)($_POST['marks'] ?? 1)),
                    max(0,(float)($_POST['neg'] ?? 0.25)),
                ]);
                $sid = (int)db()->lastInsertId();

                addToSet($sid,$ids);

                $msg = 'Set “'.$title.'” created: '.count($ids).' unique questions';
                if($orderedSubjects){
                    $msg .= ' — sections: '.implode(' → ', $orderedSubjects);
                }
                if($requested && count($ids) < $requested){
                    $msg .= '. Short on: '.implode(', ',$short).' (not enough different questions in the bank)';
                }elseif($short){
                    $msg .= '. Note: '.implode(', ',$short);
                }
                $back($msg);
            }

            /* ---------- QUICK SET (no repeats) ---------- */
            if(isset($_POST['mkset'])){
                $ids=[];$sn=[];$stx=[];
                pickQ('exam=? AND (?=\'\' OR subject=?) AND (?=\'\' OR topic=?)',
                    [$_POST['exam'],$_POST['subject'],$_POST['subject'],$_POST['topic']??'',$_POST['topic']??''],
                    max(5,min(100,(int)$_POST['n'])),$ids,$sn,$stx);
                if(!$ids)$back('No questions match that selection yet');
                q('INSERT INTO qsets(title,exam,minutes) VALUES(?,?,?)',[trim($_POST['title']),$_POST['exam'],max(5,(int)$_POST['minutes'])]);
                addToSet(db()->lastInsertId(),$ids);
                $back(count($ids).' unique questions added to the set');
            }

            if(isset($_POST['del'])){q('DELETE FROM qset_q WHERE set_id=?',[(int)$_POST['del']]);q('DELETE FROM qsets WHERE id=?',[(int)$_POST['del']]);$back('Set deleted');}
        }

        /* ---- Notes ---- */
        if($t==='notes'){
            if(isset($_POST['title'])){q('INSERT INTO notes(kind,exam,subject,title,body) VALUES(?,?,?,?,?)',[$_POST['kind'],$_POST['exam'],$_POST['subject'],$_POST['title'],$_POST['body']]);$back('Note published');}
            if(isset($_POST['del'])){q('DELETE FROM notes WHERE id=?',[(int)$_POST['del']]);$back('Deleted');}
        }

        /* ---- Exam dates ---- */
        if($t==='exams'){
            if(isset($_POST['name'])){q('INSERT INTO exams(name,exam_date,info) VALUES(?,?,?)',[$_POST['name'],$_POST['date'],$_POST['info']]);$back('Exam added');}
            if(isset($_POST['del'])){q('DELETE FROM exams WHERE id=?',[(int)$_POST['del']]);$back('Deleted');}
        }

        /* ---- PYQ PDFs ---- */
        if($t==='pyq'){
            if(isset($_FILES['pdf'])&&is_uploaded_file($_FILES['pdf']['tmp_name'])){
                $f=$_FILES['pdf'];
                $fh=fopen($f['tmp_name'],'rb');$sig=fread($fh,4);fclose($fh);
                if($sig!=='%PDF'||$f['size']>10*1024*1024)$back('Only PDF files up to 10 MB');
                $name=bin2hex(random_bytes(6)).'.pdf';
                $dir=__DIR__.'/../uploads/pyq/';
                if(!is_dir($dir))@mkdir($dir,0755,true);
                move_uploaded_file($f['tmp_name'],$dir.$name);
                q('INSERT INTO pyq(exam,year,title,file) VALUES(?,?,?,?)',[$_POST['exam'],$_POST['year'],$_POST['title'],$name]);
                $back('PDF uploaded');
            }
            if(isset($_POST['del'])){
                $r=row('SELECT file FROM pyq WHERE id=?',[(int)$_POST['del']]);
                if($r)@unlink(__DIR__.'/../uploads/pyq/'.basename($r['file']));
                q('DELETE FROM pyq WHERE id=?',[(int)$_POST['del']]);
                $back('Deleted');
            }
        }

        /* ---- Notices ---- */
        if($t==='notices'){
            if(isset($_POST['title'])&&trim($_POST['title'])){
                q('INSERT INTO notices(icon,title,link,active) VALUES(?,?,?,1)',[
                    trim($_POST['icon'])?:'📢',trim($_POST['title']),trim($_POST['link'])
                ]);
                $back('Notice published');
            }
            if(isset($_POST['toggle'])){q('UPDATE notices SET active=1-active WHERE id=?',[(int)$_POST['toggle']]);$back('Updated');}
            if(isset($_POST['del'])){q('DELETE FROM notices WHERE id=?',[(int)$_POST['del']]);$back('Deleted');}
        }

        /* ---- Inspiration card ---- */
        if($t==='inspire'){
            ensure_inspire();
            $saveImg=function() use ($back){
                if(!isset($_FILES['img'])||!is_uploaded_file($_FILES['img']['tmp_name'])) return '';
                $f=$_FILES['img'];
                $info=@getimagesize($f['tmp_name']);
                $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$info['mime']??'']??null;
                if(!$ext||$f['size']>3*1024*1024) $back('Image must be JPG, PNG, WEBP or GIF, up to 3 MB.');
                $dir=__DIR__.'/../uploads/inspire/';
                if(!is_dir($dir))@mkdir($dir,0755,true);
                $name=bin2hex(random_bytes(6)).'.'.$ext;
                move_uploaded_file($f['tmp_name'],$dir.$name);
                return $name;
            };
            if(isset($_POST['addins'])){
                $quote=trim($_POST['quote']??'');
                if($quote==='') $back('Write a quote first.');
                q('INSERT INTO inspire(image,quote,author,active) VALUES(?,?,?,1)',
                  [$saveImg(),$quote,trim($_POST['author']??'')]);
                $back('Inspiration card added');
            }
            if(isset($_POST['saveins'])){
                $id=(int)$_POST['saveins'];
                $old=row('SELECT * FROM inspire WHERE id=?',[$id]);
                if(!$old) $back('Not found');
                $quote=trim($_POST['quote']??'');
                if($quote==='') $back('Quote cannot be empty.');
                $img=$saveImg();
                if($img!==''){
                    if($old['image']) @unlink(__DIR__.'/../uploads/inspire/'.basename($old['image']));
                }else $img=$old['image'];
                q('UPDATE inspire SET image=?,quote=?,author=? WHERE id=?',[$img,$quote,trim($_POST['author']??''),$id]);
                $back('Saved');
            }
            if(isset($_POST['toggle'])){q('UPDATE inspire SET active=1-active WHERE id=?',[(int)$_POST['toggle']]);$back('Updated');}
            if(isset($_POST['del'])){
                $r=row('SELECT image FROM inspire WHERE id=?',[(int)$_POST['del']]);
                if($r&&$r['image'])@unlink(__DIR__.'/../uploads/inspire/'.basename($r['image']));
                q('DELETE FROM inspire WHERE id=?',[(int)$_POST['del']]);
                $back('Deleted');
            }
        }

        /* ---- Feedback ---- */
        if($t==='feedback'){
            if(isset($_POST['toggle'])){ q('UPDATE feedback SET is_read=1-is_read WHERE id=?',[(int)$_POST['toggle']]); $back('Updated'); }
            if(isset($_POST['readall'])){ q('UPDATE feedback SET is_read=1'); $back('All feedback marked as read'); }
            if(isset($_POST['del'])){ q('DELETE FROM feedback WHERE id=?',[(int)$_POST['del']]); $back('Feedback deleted'); }
        }

        /* ---- Hero carousel images (home page slides 1-3) ---- */
        if($t==='hero'){
            ensure_site();
            $slot=(int)($_POST['slot']??0);
            if($slot<1||$slot>3) $back('Unknown slide.');

            if(isset($_POST['savehero'])){
                $cur=hero_img($slot);
                $img=$cur;
                $new=hero_save_upload($_FILES['img']??null,$err);
                if($err!=='') $back($err);
                if($new!==''){ hero_delete_file($cur); $img=$new; }
                site_set('hero_image_'.$slot,$img);
                site_set('hero_alt_'.$slot,mb_substr(trim($_POST['alt']??''),0,150));
                if($slot===1) site_set('hero_image','');      // old single-image setting is now migrated
                $back('Slide '.$slot.' saved');
            }
            if(isset($_POST['rmhero'])){
                hero_delete_file(hero_img($slot));
                site_set('hero_image_'.$slot,'');
                if($slot===1) site_set('hero_image','');
                $back('Slide '.$slot.' image removed. The default card is shown again.');
            }
        }

        /* ---- Users ---- */
        if($t==='users'&&isset($_POST['verify'])){q('UPDATE users SET verified=1,vtoken=NULL WHERE id=?',[(int)$_POST['verify']]);$back('User verified');}
        if($t==='users'&&isset($_POST['del'])){q("DELETE FROM users WHERE id=? AND role<>'admin'",[(int)$_POST['del']]);$back('User removed');}

        /* ---- Community ---- */
        if($t==='community'&&isset($_POST['del'])){
            if($_POST['k']==='p'){q('DELETE FROM answers WHERE post_id=?',[(int)$_POST['del']]);q('DELETE FROM posts WHERE id=?',[(int)$_POST['del']]);}
            else q('DELETE FROM answers WHERE id=?',[(int)$_POST['del']]);
            $back('Removed');
        }

        /* ---- Requests ---- */
        if($t==='requests'){
            if(isset($_POST['fulfil'])){
                $rid = (int)$_POST['fulfil'];
                $rq  = row('SELECT * FROM requests WHERE id=?',[$rid]);
                if(!$rq) $back('Request not found');
                if($rq['status']!=='pending') $back('Already handled');

                $n       = max(1, min(100, (int)$rq['want_count'] ?: 15));
                $where   = 'exam=?';
                $params  = [$rq['exam']];
                if($rq['subject']!==''){ $where.=' AND subject=?'; $params[]=$rq['subject']; }
                if($rq['topic']  !==''){ $where.=' AND topic=?';   $params[]=$rq['topic'];   }

                $ids=[];$sn=[];$stx=[];
                pickQ($where,$params,$n,$ids,$sn,$stx);          // unique questions only
                if(!$ids){
                    q('UPDATE requests SET status=?,admin_note=?,updated=NOW() WHERE id=?',
                      ['fulfilled',trim($_POST['note']??'We have noted your request but the bank has no matching questions yet.'),$rid]);
                    $back('Marked fulfilled. No matching questions in the bank — build a set manually and link it later.');
                }

                $title = 'Requested · '.($rq['topic'] ?: $rq['subject'] ?: $rq['exam']).' ('.count($ids).' Q)';
                q('INSERT INTO qsets(title,exam,minutes,marks,neg) VALUES(?,?,?,?,?)',
                  [$title,$rq['exam'],max(5,(int)ceil(count($ids)*0.9)),1,0.25]);
                $sid = db()->lastInsertId();
                addToSet($sid, $ids);

                q('UPDATE requests SET status=?,set_id=?,admin_note=?,updated=NOW() WHERE id=?',
                  ['fulfilled',$sid,trim($_POST['note']??''),$rid]);

                $stu = row('SELECT * FROM users WHERE id=?',[$rq['user_id']]);
                if($stu && MAIL_RESULTS && $stu['verified']){
                    $link = baseUrl().'/?p=test&m=set&id='.$sid;
                    mailTo($stu['email'],
                        'Your requested set is ready: '.$rq['exam'],
                        '<p>Hi '.h($stu['name']).',</p>'
                        .'<p>Your requested practice set is ready:</p>'
                        .'<p><b>'.h($title).'</b></p>'
                        .'<p><a href="'.$link.'">Start the set now</a></p>'
                        .'<p style="color:#666;font-size:12px">Or open it from '.baseUrl().'/?p=requests</p>');
                }
                $back('Set #'.$sid.' created and request marked fulfilled');
            }

            if(isset($_POST['reply'])){
                $rid = (int)$_POST['reply'];
                q('UPDATE requests SET admin_note=?,updated=NOW() WHERE id=?',[trim($_POST['note']??''),$rid]);
                $back('Reply saved');
            }

            if(isset($_POST['decline'])){
                $rid = (int)$_POST['decline'];
                $rq  = row('SELECT * FROM requests WHERE id=?',[$rid]);
                if(!$rq){ $back('Request not found'); }
                q('UPDATE requests SET status=?,admin_note=?,updated=NOW() WHERE id=?',
                  ['declined',trim($_POST['note']??''),$rid]);

                $stu = row('SELECT * FROM users WHERE id=?',[$rq['user_id']]);
                if($stu && MAIL_RESULTS && $stu['verified']){
                    mailTo($stu['email'],
                        'Update on your set request · '.SITE,
                        '<p>Hi '.h($stu['name']).',</p>'
                        .'<p>We reviewed your request for <b>'.h($rq['exam']).'</b>'
                        .($rq['topic']?' · '.h($rq['topic']):'')
                        .'. Unfortunately we could not build it this time.</p>'
                        .($_POST['note'] ? '<p><b>Admin note:</b> '.h(trim($_POST['note'])).'</p>' : '')
                        .'<p>Feel free to send a different request anytime at '
                        .'<a href="'.baseUrl().'/?p=requests">'.baseUrl().'/?p=requests</a>.</p>');
                }
                $back('Request declined');
            }

            if(isset($_POST['del'])){
                q('DELETE FROM requests WHERE id=?',[(int)$_POST['del']]);
                $back('Request deleted');
            }
        }
    }

    /* ============ NAV + RENDER ============ */
    ensure_feedback();
    $unreadFb = (int) sval("SELECT COUNT(*) FROM feedback WHERE is_read=0");

    $pendingReq = (int) sval("SELECT COUNT(*) FROM requests WHERE status='pending'");
    $tabs=[
        'dash'=>'📊 Dashboard','schools'=>'🏫 Schools & Classes','syllabus'=>'🧭 Syllabus & Coverage','questions'=>'❓ Questions',
        'sets'=>'🗂 Mock / Practice sets','qotd'=>'⚡ Q of the Day','notes'=>'📚 Notes & Tricks',
        'exams'=>'🗓 Exam dates','pyq'=>'📄 PYQ PDFs','notices'=>'📢 Notice board','inspire'=>'🌟 Inspiration','hero'=>'🖼 Hero image',
        'users'=>'👥 Users','community'=>'💬 Community','ai'=>'🤖 AI status','feedback'=>'⭐ Feedback'.($unreadFb ? ' <span class="pill" style="background:#fef3c7;color:#b45309;padding:0 6px;font-size:10px">'.$unreadFb.'</span>' : ''),
        'requests'=>'💡 Requests'.($pendingReq ? ' <span class="pill" style="background:#fef3c7;color:#b45309;padding:0 6px;font-size:10px">'.$pendingReq.'</span>' : '')
    ];
    $links=[];
    foreach($tabs as $k=>$v)$links[]=[$A.'?t='.$k,$v];
    $links[]=[$A.'?t=logout','🚪 Logout'];
    $eopts=implode('',array_map(function($k){return '<option>'.h($k).'</option>';},array_keys(exams())));

    if($t==='dash'){
        $c=function($s){return number_format((int)val($s));};

        $students  = $c("SELECT COUNT(*) FROM users WHERE role='student'");
        $teachers  = $c("SELECT COUNT(*) FROM users WHERE role='teacher'");
        $questions = $c('SELECT COUNT(*) FROM questions');
        $attempts  = $c('SELECT COUNT(*) FROM attempts');
        $visitors  = $c("SELECT v FROM stats WHERE k='visits'");
        $unverif   = $c('SELECT COUNT(*) FROM users WHERE verified=0');
        $schoolsN  = $c("SELECT COUNT(DISTINCT NULLIF(TRIM(school),'')) FROM classes");
        $classesN  = $c('SELECT COUNT(*) FROM classes');

        $m=array_column(rows('SELECT DATE(created) d,COUNT(*) n FROM users WHERE created>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d'),'n','d');
        $L=[];$D=[];
        for($i=6;$i>=0;$i--){$k=date('Y-m-d',strtotime("-$i day"));$L[]=date('D',strtotime($k));$D[]=(int)($m[$k]??0);}

        $ex=rows("SELECT exam,COUNT(*) n FROM users WHERE role='student' AND exam<>'' GROUP BY exam");

        $td=array_column(rows('SELECT DATE(created) d,COUNT(*) n FROM attempts WHERE created>=DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY d'),'n','d');
        $L2=[];$D2=[];
        for($i=13;$i>=0;$i--){$k=date('Y-m-d',strtotime("-$i day"));$L2[]=date('d M',strtotime($k));$D2[]=(int)($td[$k]??0);}

        $qs=rows('SELECT subject s,COUNT(*) n FROM questions GROUP BY subject ORDER BY n DESC LIMIT 10');
        $ae=rows('SELECT exam,ROUND(AVG(score)) a FROM attempts GROUP BY exam');

        echo '<style>
            .adm-dash{display:flex;flex-direction:column;gap:22px}
            .adm-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
            .adm-kpi{position:relative;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px 18px 14px;overflow:hidden;transition:transform .18s ease,box-shadow .18s ease}
            .adm-kpi:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(15,23,42,.08)}
            .adm-kpi::before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:var(--accent,#4f46e5);opacity:.9}
            .adm-kpi .k-ico{font-size:22px;line-height:1;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:11px;background:rgba(79,70,229,.10);color:var(--accent,#4f46e5)}
            .adm-kpi .k-num{font-size:26px;font-weight:800;letter-spacing:-.5px;color:#111827;margin-top:10px;line-height:1.05}
            .adm-kpi .k-lbl{font-size:12.5px;font-weight:600;color:#6b7280;margin-top:4px;letter-spacing:.2px}
            .adm-h{display:flex;align-items:baseline;gap:12px;margin:6px 0 -6px;flex-wrap:wrap}
            .adm-h h2{margin:0;font-size:18px;font-weight:800;letter-spacing:-.3px}
            .adm-h .mu{font-size:13px}
            .adm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
            @media (max-width:900px){.adm-grid{grid-template-columns:1fr}}
            .adm-chart{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px 12px;display:flex;flex-direction:column;min-height:320px;transition:box-shadow .18s ease}
            .adm-chart:hover{box-shadow:0 12px 28px rgba(15,23,42,.06)}
            .adm-chart .ch-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
            .adm-chart .ch-head b{font-size:14.5px;font-weight:700;letter-spacing:-.2px;color:#111827}
            .adm-chart .ch-head .ch-tag{font-size:11px;font-weight:700;letter-spacing:.3px;padding:3px 9px;border-radius:999px;background:#eef2ff;color:#4f46e5}
            .adm-chart .ch-body{flex:1 1 auto;min-height:0;position:relative}
            .adm-chart .chart-wrap{position:relative;width:100%;height:230px}
            .adm-chart canvas{display:block;width:100%!important;height:100%!important}
            .adm-note{background:linear-gradient(135deg,#eef2ff 0%,#fdf2f8 100%);border:1px dashed #c7d2fe;border-radius:16px;padding:26px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-height:320px}
            .adm-note .adn-ico{font-size:44px;line-height:1;margin-bottom:12px}
            .adm-note b{font-size:16px;letter-spacing:-.2px;color:#1f2937}
            .adm-note p{margin:8px 0 0;font-size:13.5px;color:#6b7280;max-width:340px;line-height:1.55}
            .adm-mail{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px 22px;display:flex;align-items:center;gap:18px;flex-wrap:wrap}
            .adm-mail .mail-ico{width:44px;height:44px;border-radius:12px;flex:0 0 auto;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:22px}
            .adm-mail .mail-body{flex:1 1 260px;min-width:0}
            .adm-mail .mail-body b{font-size:15px;letter-spacing:-.2px}
            .adm-mail .mail-body p{margin:4px 0 0;font-size:13px;color:#6b7280;line-height:1.5;word-break:break-word}
            .adm-mail .mail-body code{background:#f3f4f6;padding:1px 6px;border-radius:6px;font-size:12px;color:#374151}
        </style>';

        $GLOBALS['chart']=1;
        $drawChart = function($id,$type,$labels,$data,$label=''){
            $isRound = in_array($type,['doughnut','pie'],true);
            $isLine  = $type==='line';
            $isBar   = $type==='bar';
            $palette = ['#4f46e5','#db2777','#16a34a','#f59e0b','#0891b2','#7c3aed','#ef4444','#0ea5e9'];

            $ds = [
                'label'           => $label,
                'data'            => $data,
                'backgroundColor' => $isLine ? 'rgba(79,70,229,.12)' : $palette,
                'borderColor'     => $isLine ? '#4f46e5' : ($isBar ? '#4f46e5' : $palette),
                'borderWidth'     => $isLine ? 2.5 : 0,
                'tension'         => $isLine ? 0.35 : 0,
                'fill'            => $isLine,
                'pointRadius'     => $isLine ? 3 : 0,
                'pointHoverRadius'=> $isLine ? 5 : 0,
                'pointBackgroundColor' => '#4f46e5',
                'pointBorderColor'     => '#ffffff',
                'pointBorderWidth'     => 2,
            ];
            if($isBar){ $ds['borderRadius']=6; $ds['maxBarThickness']=42; }
            if($isRound){ $ds['borderWidth']=2; $ds['borderColor']='#ffffff'; $ds['hoverOffset']=6; }

            $opts = [
                'responsive'=>true,
                'maintainAspectRatio'=>false,
                'plugins'=>[
                    'legend'=>[
                        'display'=>$isRound,
                        'position'=>'bottom',
                        'labels'=>['boxWidth'=>10,'boxHeight'=>10,'padding'=>12,'color'=>'#6b7280','font'=>['size'=>12]],
                    ],
                    'tooltip'=>['backgroundColor'=>'#111827','padding'=>10,'cornerRadius'=>8],
                ],
            ];
            if($isRound){ $opts['cutout']='62%'; }
            else{
                $opts['scales']=[
                    'x'=>['grid'=>['display'=>false,'drawBorder'=>false],'ticks'=>['color'=>'#9ca3af','font'=>['size'=>11]]],
                    'y'=>['grid'=>['display'=>false,'drawBorder'=>false],'ticks'=>['color'=>'#9ca3af','font'=>['size'=>11],'precision'=>0],'beginAtZero'=>true],
                ];
            }

            echo '<div class="chart-wrap"><canvas id="'.$id.'"></canvas></div>'
                .'<script>window.addEventListener("load",function(){'
                .'new Chart(document.getElementById("'.$id.'"),{'
                .'type:'.json_encode($type).','
                .'data:{labels:'.json_encode($labels).',datasets:['.json_encode($ds).']},'
                .'options:'.json_encode($opts)
                .'});});</script>';
        };

        echo '<div class="adm-dash">';

        $kpis = [
            ['🎓',$students,'Students','#4f46e5'],
            ['🧑‍🏫',$teachers,'Teachers','#0891b2'],
            ['🏫',$schoolsN,'Schools registered','#0ea5e9'],
            ['📚',$classesN,'Classes','#8b5cf6'],
            ['❓',$questions,'Questions in bank','#16a34a'],
            ['📝',$attempts,'Tests taken','#f59e0b'],
            ['👀',$visitors,'Visitors','#db2777'],
            ['✉️',$unverif,'Unverified emails','#64748b'],
            ['💡',(string)$pendingReq,'Pending requests','#b45309'],
        ];
        echo '<div class="adm-kpis">';
        foreach($kpis as $k){
            echo '<div class="adm-kpi" style="--accent:'.$k[3].'">'
                .'<span class="k-ico">'.$k[0].'</span>'
                .'<div class="k-num">'.h($k[1]).'</div>'
                .'<div class="k-lbl">'.h($k[2]).'</div>'
                .'</div>';
        }
        echo '</div>';

        echo '<div class="adm-h"><h2>Growth</h2>'
            .'<span class="mu">Signups over the last week, and which exams students are targeting</span></div>'
            .'<div class="adm-grid">'
              .'<div class="adm-chart">'
                .'<div class="ch-head"><b>New registrations</b><span class="ch-tag">7 days</span></div>'
                .'<div class="ch-body">';
        $drawChart('a1','line',$L,$D,'Signups');
        echo    '</div>'
              .'</div>'
              .'<div class="adm-chart">'
                .'<div class="ch-head"><b>Students by exam</b><span class="ch-tag">'.array_sum(array_map('intval',array_column($ex,'n'))).' total</span></div>'
                .'<div class="ch-body">';
        if(count($ex)) $drawChart('a2','doughnut',array_column($ex,'exam'),array_map('intval',array_column($ex,'n')));
        else echo '<div class="mu" style="text-align:center;padding-top:40px">No students yet.</div>';
        echo    '</div>'
              .'</div>'
            .'</div>';

        echo '<div class="adm-h"><h2>Activity &amp; content</h2>'
            .'<span class="mu">How often tests are taken, and how healthy the question bank is</span></div>'
            .'<div class="adm-grid">'
              .'<div class="adm-chart">'
                .'<div class="ch-head"><b>Tests taken</b><span class="ch-tag">14 days</span></div>'
                .'<div class="ch-body">';
        $drawChart('a3','bar',$L2,$D2,'Tests');
        echo    '</div>'
              .'</div>'
              .'<div class="adm-chart">'
                .'<div class="ch-head"><b>Questions per subject</b><span class="ch-tag">Top 10</span></div>'
                .'<div class="ch-body">';
        if(count($qs)) $drawChart('a4','bar',array_column($qs,'s'),array_map('intval',array_column($qs,'n')),'Questions');
        else echo '<div class="mu" style="text-align:center;padding-top:40px">No questions yet — add some from the Questions tab.</div>';
        echo    '</div>'
              .'</div>'
            .'</div>';

        echo '<div class="adm-h"><h2>Performance</h2>'
            .'<span class="mu">Average score per exam — a quick health signal for difficulty</span></div>'
            .'<div class="adm-grid">'
              .'<div class="adm-chart">'
                .'<div class="ch-head"><b>Average score by exam</b><span class="ch-tag">%</span></div>'
                .'<div class="ch-body">';
        if(count($ae)) $drawChart('a5','bar',array_column($ae,'exam'),array_map('intval',array_column($ae,'a')),'Avg %');
        else echo '<div class="mu" style="text-align:center;padding-top:40px">No test attempts yet.</div>';
        echo    '</div>'
              .'</div>'
              .'<div class="adm-note">'
                .'<div class="adn-ico">📊</div>'
                .'<b>All charts update live</b>'
                .'<p>Numbers refresh automatically as students register, take tests and as you add questions. No action needed.</p>'
              .'</div>'
            .'</div>';

        $primary = defined('MAIL_WEBHOOK_URL') && MAIL_WEBHOOK_URL !== ''
            ? 'webhook'
            : (SMTP_HOST !== '' ? 'SMTP (' . SMTP_HOST . ':' . SMTP_PORT . ')' : 'PHP mail()');

        echo '<div class="adm-h"><h2>Email delivery</h2>'
            .'<span class="mu">Verify that outgoing mail works on this server</span></div>'
            .'<form class="adm-mail" method="post">'
              .field()
              .'<div class="mail-ico">📧</div>'
              .'<div class="mail-body">'
                .'<b>Send a test email to '.h($u['email']).'</b>'
                .'<p>Primary channel: <code>'.h($primary).'</code>. The webhook, if set, takes precedence over SMTP.</p>'
              .'</div>'
              .'<button class="p" name="testmail" value="1">Send test email</button>'
            .'</form>';

        echo '</div>'; /* /.adm-dash */
    }

    /* ───────────── SCHOOLS & CLASSES ───────────── */
    elseif($t==='schools'){
        $sc =(int)val("SELECT COUNT(DISTINCT NULLIF(TRIM(school),'')) FROM classes");
        $cl =(int)val('SELECT COUNT(*) FROM classes');
        $tc =(int)val('SELECT COUNT(DISTINCT teacher_id) FROM classes');
        $in =(int)val("SELECT COUNT(*) FROM users WHERE role='student' AND class_id IS NOT NULL");
        $out=(int)val("SELECT COUNT(*) FROM users WHERE role='student' AND class_id IS NULL");

        echo '<h2>🏫 Schools &amp; classes</h2>'
            .'<p class="mu">Built from the school name and class that teachers create. Students join with the class code or referral link.</p>'
            .tiles([
                ['🏫',(string)$sc, 'Schools','#4f46e5'],
                ['📚',(string)$cl, 'Classes','#0891b2'],
                ['🧑‍🏫',(string)$tc,'Teachers','#16a34a'],
                ['🎓',(string)$in, 'Students in a class','#f59e0b'],
                ['👤',(string)$out,'Students without a class','#64748b'],
            ]);

        $bySchool=rows("SELECT COALESCE(NULLIF(TRIM(c.school),''),'(No school name)') sch,
                COUNT(DISTINCT c.teacher_id) teachers, COUNT(DISTINCT c.id) classes, COUNT(u.id) students
            FROM classes c LEFT JOIN users u ON u.class_id=c.id AND u.role='student'
            GROUP BY sch ORDER BY students DESC, sch");

        if(!$bySchool){
            echo '<div class="card mu">No classes yet. Schools appear here once a teacher creates a class.</div>';
        }else{
            echo '<div class="card"><b>Students per school</b>';
            chart('sch1','bar',array_column($bySchool,'sch'),array_map('intval',array_column($bySchool,'students')),'Students');
            echo '</div>';

            echo '<h2>Per school</h2>';
            table(['School','Teachers','Classes','Students'],array_map(function($r){
                return [h($r['sch']),$r['teachers'],$r['classes'],'<b>'.$r['students'].'</b>'];
            },$bySchool));

            $cls=rows("SELECT c.id,COALESCE(NULLIF(TRIM(c.school),''),'(No school name)') sch,c.name,c.code,t.name tname,
                (SELECT COUNT(*) FROM users u WHERE u.class_id=c.id AND u.role='student') n,
                (SELECT COUNT(*) FROM tests x WHERE x.class_id=c.id) tests
                FROM classes c LEFT JOIN users t ON t.id=c.teacher_id ORDER BY sch,c.name");
            $g=[]; foreach($cls as $r) $g[$r['sch']][]=$r;

            echo '<h2>Per class</h2>';
            foreach($g as $sch=>$list){
                echo '<div class="card"><b>'.h($sch).'</b> <span class="mu">· '.count($list).' classes · '
                    .array_sum(array_column($list,'n')).' students</span></div>';
                table(['Class','Code','Teacher','Students','Tests'],array_map(function($r){
                    return [h($r['name']),'<span class="pill">'.h($r['code']).'</span>',h($r['tname']?:'—'),'<b>'.$r['n'].'</b>',$r['tests']];
                },$list));
            }
        }
    }

    /* ───────────── AI STATUS ───────────── */
    elseif($t==='ai'){
        ensure_ai();
        $P=ai_providers();
        $cool=[]; foreach(srows('SELECT * FROM ai_cool') as $r) $cool[$r['name']]=$r;
        $mods=[]; foreach(srows('SELECT name,models FROM ai_mlist') as $r) $mods[$r['name']]=str_replace(',',' → ',$r['models']);

        echo '<h2>🤖 AI status</h2>'
            .'<p class="mu">Providers are tried top to bottom. A provider that runs out of quota is paused and the next one is used automatically. '
            .'Models are discovered from each provider, so retired model names no longer break things.</p>'
            .'<form class="card inl" method="post">'.field()
            .'<button class="p" name="test" value="1">Test AI now</button>'
            .'<button name="refresh" value="1">Refresh models &amp; clear pauses</button>'
            .'<span class="mu">Student chat questions in the last 24 h: <b>'.(int)sval("SELECT COUNT(*) FROM ai_log WHERE created>=DATE_SUB(NOW(),INTERVAL 1 DAY)").'</b></span></form>';

        if(!$P){
            echo '<div class="card"><b>No AI key found.</b>'
                .'<p class="mu">Add one or more of GEMINI_KEY, GROQ_KEY, OPENROUTER_KEY, CEREBRAS_KEY, HF_KEY, MISTRAL_KEY in config.php.</p></div>';
        }
        table(['Provider','Key','Model','Status','Last error'],array_map(function($p)use($cool,$mods){
            $c=$cool[$p[0]]??null; $paused=$c&&(int)$c['until_ts']>time();
            return [h($p[0]),'…'.h(substr($p[3],-4)),h($mods[$p[0]]??($p[4]?:'auto')),
                    $paused?'⏸ paused until '.date('H:i',(int)$c['until_ts']):'✅ ready',
                    h($c['last_err']??'')];
        },$P));

        echo '<div class="card"><b>Where to get free keys</b><p class="mu" style="margin-top:6px">'
            .'Gemini: aistudio.google.com/apikey · Groq: console.groq.com/keys · OpenRouter: openrouter.ai/keys · '
            .'Cerebras: cloud.cerebras.ai · Hugging Face: huggingface.co/settings/tokens · Mistral: console.mistral.ai</p></div>';
    }

    elseif($t==='syllabus'){
        $E=exams();$c=[];
        foreach(rows('SELECT exam,subject,topic,COUNT(*) n FROM questions GROUP BY exam,subject,topic') as $r)
            $c[$r['exam']][$r['subject']][$r['topic']]=$r['n'];
        echo '<h2>Syllabus &amp; question coverage</h2><p class="mu">Each exam has its own subjects and topics. Students practise topic by topic and only get that topic\'s questions. Match this to the official syllabus of each exam.</p>';
        echo '<div class="grid2">'
            .'<form class="card" method="post">'.field().'<h3>Add exam</h3>'
            .'<input name="code" placeholder="Code e.g. OSSC" required>'
            .'<input name="title" placeholder="Full name" required>'
            .'<label class="mu">Full mock minutes</label><input type="number" name="minutes" value="60">'
            .'<button class="p" name="addexam" value="1">Add exam</button></form>'
            .'<form class="card" method="post">'.field().'<h3>Add subject</h3>'
            .'<select name="exam">'.$eopts.'</select>'
            .'<input name="name" placeholder="Subject name" required>'
            .'<label class="mu">Questions in a full mock</label><input type="number" name="qcount" value="25">'
            .'<button class="p" name="addsub" value="1">Add subject</button></form>'
            .'<form class="card" method="post">'.field().'<h3>Add topics</h3>'.sylPick(false)
            .'<textarea name="names" rows="4" placeholder="One topic per line"></textarea>'
            .'<button class="p" name="addtops" value="1">Add topics</button></form></div>';

        foreach($E as $code=>$e){
            echo '<h2>'.h($code).' <span class="mu">'.h($e['t']).' · total '.array_sum($e['sec']).' questions</span></h2>'
                .'<form method="post" class="card inl">'.field()
                .'<input type="hidden" name="exam" value="'.h($code).'">'
                .'<label class="mu">Full mock time (minutes)</label>'
                .'<input type="number" name="minutes" value="'.$e['min'].'" style="width:110px;margin:0">'
                .'<button name="setmin" value="1">Save time</button></form>'
                .'<form method="post" class="card inl">'.field()
                .'<input type="hidden" name="exam" value="'.h($code).'">'
                .'<b>Clone this pattern for another post</b>'
                .'<input name="code" placeholder="New code e.g. OAVS-MATHS" style="width:220px;margin:0">'
                .'<input name="title" placeholder="New exam name" style="width:240px;margin:0">'
                .'<button name="cloneexam" value="1">Clone</button></form>';

            foreach($e['topics'] as $s=>$ts){
                $tot=array_sum($c[$code][$s]??[]);
                echo '<div class="card">'
                    .'<div class="inl"><b>'.h($s).'</b><span class="mu">in bank: '.$tot.'</span>'
                    .'<form method="post" class="inl" style="margin-left:auto">'.field()
                    .'<input type="hidden" name="exam" value="'.h($code).'">'
                    .'<input type="hidden" name="subject" value="'.h($s).'">'
                    .'<label class="mu">Questions in full mock</label>'
                    .'<input type="number" name="qcount" value="'.$e['sec'][$s].'" style="width:90px;margin:0">'
                    .'<button name="setcount" value="1">Save</button> '
                    .'<button name="delsub" value="1" onclick="return confirm(\'Remove subject?\')">Remove</button>'
                    .'</form></div>';

                echo '<div class="card sc"><table><tr><th>Topic</th><th>Questions</th><th></th></tr>';
                foreach($ts as $tp){
                    $n=$c[$code][$s][$tp]??0;
                    $h='<input type="hidden" name="exam" value="'.h($code).'">'
                      .'<input type="hidden" name="subject" value="'.h($s).'">'
                      .'<input type="hidden" name="topic" value="'.h($tp).'">';
                    echo '<tr>'
                        .'<td>'.h($tp).'</td>'
                        .'<td>'.($n?'<b>'.$n.'</b>':'<span style="color:var(--bad)">0 – needs questions</span>').'</td>'
                        .'<td><form method="post">'.field().$h
                        .'<button name="aitopic" value="1">🤖 Generate 10</button> '
                        .'<button name="deltop" value="1">✕</button></form></td>'
                        .'</tr>';
                }
                if(!$ts) echo '<tr><td class="mu" colspan="3">No topics yet.</td></tr>';
                echo '</table></div></div>';
            }
        }
    }

    elseif($t==='questions'){
        echo '<h2>Add question</h2><form class="card" method="post">'.field().sylPick(true)
            .'<textarea name="q" rows="2" placeholder="Question" required></textarea>'
            .'<input name="a" placeholder="Option A" required>'
            .'<input name="b" placeholder="Option B" required>'
            .'<input name="c" placeholder="Option C" required>'
            .'<input name="d" placeholder="Option D" required>'
            .'<select name="ans"><option value="0">Answer A</option><option value="1">Answer B</option><option value="2">Answer C</option><option value="3">Answer D</option></select>'
            .'<input name="exp" placeholder="Explanation shown after the test">'
            .'<button class="p">Add</button></form>';
        echo '<div class="grid2">'
            .'<form class="card" method="post" enctype="multipart/form-data">'.field()
            .'<h3>📥 Upload question set (CSV)</h3>'
            .'<p class="mu">Columns: exam, subject, topic, question, A, B, C, D, answer (A-D), explanation. '
            .'Unknown subjects/topics are added to the syllabus automatically. Duplicate questions are skipped.</p>'
            .'<p><a class="btn" href="'.$A.'?t=sample">⬇ Download CSV format</a></p>'
            .'<input type="file" name="csv" accept=".csv" required>'
            .'<input name="setname" placeholder="Optional: also create a practice set with this title">'
            .'<button class="p">Upload</button></form>'
            .'<form class="card" method="post">'.field()
            .'<h3>🤖 AI generator</h3>'.sylPick(true)
            .'<label class="mu">How many (max 20)</label>'
            .'<input type="number" name="n" value="10" max="20">'
            .'<button class="p" name="ai" value="1">Generate &amp; save</button>'
            .'<p class="mu">Uses your free AI keys and switches provider automatically. See <a href="'.$A.'?t=ai">AI status</a>.</p></form></div>';
        echo '<h2>Latest questions</h2>';
        table(['ID','Exam','Subject','Topic','Question',''],
            array_map(function($r)use($del){
                return [$r['id'],h($r['exam']),h($r['subject']),h($r['topic']),h(mb_substr($r['q'],0,80)),$del($r['id'])];
            },rows('SELECT * FROM questions ORDER BY id DESC LIMIT 50')));
    }

    elseif($t==='sets'){
        $E=exams();
        $e=$_GET['e']??(string)key($E);
        /* allow the __ANY__ sentinel through */
        if($e !== '__ANY__' && !isset($E[$e])) $e=(string)key($E);

        echo '<h2>Create a mock / practice set</h2>'
            .'<form method="get" class="card inl"><input type="hidden" name="t" value="sets">'
            .'<b>1. Exam</b><select name="e" onchange="this.form.submit()" style="width:auto;margin:0">'
            .'<option value="__ANY__" '.($e==='__ANY__'?'selected':'').'>Any exam (mixed subjects &amp; topics)</option>';
        foreach($E as $k=>$x)echo '<option value="'.h($k).'" '.($k===$e?'selected':'').'>'.h($k.' - '.$x['t']).'</option>';
        echo '</select></form>';

        if($e === '__ANY__' || isset($E[$e])){
            /* resolve subject → topic map */
            $topicMap = [];
            if($e === '__ANY__'){
                foreach(rows('SELECT subject, topic, COUNT(*) n FROM questions GROUP BY subject, topic ORDER BY subject, topic') as $r){
                    $topicMap[$r['subject']][$r['topic']] = (int)$r['n'];
                }
            }else{
                foreach($E[$e]['topics'] as $sb=>$ts){
                    $topicMap[$sb] = [];
                    foreach($ts as $tp) $topicMap[$sb][$tp] = 0;
                }
                foreach(rows('SELECT subject, topic, COUNT(*) n FROM questions WHERE exam=? GROUP BY subject, topic',[$e]) as $r){
                    $topicMap[$r['subject']][$r['topic']] = (int)$r['n'];
                }
            }

            $minDefault = $e === '__ANY__' ? 30 : $E[$e]['min'];

            echo '<form class="card" method="post">'.field()
                .'<input type="hidden" name="exam" value="'.h($e).'">'
                .'<b>2. Exam settings</b>'
                .'<input name="title" placeholder="Set title e.g. Full Mock 1" required>'
                .'<div class="grid3">'
                .'<div><label class="mu">Time (minutes)</label><input type="number" name="minutes" value="'.$minDefault.'" min="5"></div>'
                .'<div><label class="mu">Marks per correct answer</label><input type="number" step="0.25" name="marks" value="1"></div>'
                .'<div><label class="mu">Negative marking per wrong answer</label><input type="number" step="0.25" name="neg" value="0.25"></div>'
                .'</div>'
                .'<b>3. Questions: choose from each subject and topic</b>'
                .'<p class="mu">'.($e === '__ANY__'
                    ? 'You are building a mixed set. Questions are pulled from the whole bank — filter by subject/topic below. Leave a number at 0 to skip it. '
                    : 'For every subject, either type a number in "random from whole subject" or set how many questions you want from each topic. ')
                .'A question never appears twice in one set.</p>';

            /* ---- SECTION ORDER UI ---- */
            if($topicMap){
                $subjNames = array_keys($topicMap);
                echo '<b>4. Section order (drag to reorder)</b>'
                    .'<p class="mu">Sections are shown to the student in this order. Question numbers continue across sections.</p>'
                    .'<ul id="secOrder" style="list-style:none;padding:0;margin:6px 0 14px">';
                foreach($subjNames as $name){
                    echo '<li draggable="true" data-subject="'.h($name).'" '
                        .'style="display:flex;align-items:center;gap:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px;margin-bottom:6px;cursor:grab">'
                        .'<span style="color:#9ca3af;font-weight:700">≡</span>'
                        .'<b style="flex:1">'.h($name).'</b>'
                        .'<input type="hidden" name="sec_order[]" value="'.h($name).'">'
                        .'<button type="button" class="mvup" style="padding:2px 8px">▲</button>'
                        .'<button type="button" class="mvdn" style="padding:2px 8px">▼</button>'
                        .'</li>';
                }
                echo '</ul>'
                    .'<script>(function(){'
                    .'var ul=document.getElementById("secOrder");if(!ul)return;'
                    .'function reindex(){[].forEach.call(ul.children,function(li){li.querySelector("input[type=hidden]").value=li.dataset.subject;});}'
                    .'ul.addEventListener("click",function(e){'
                    .'var b=e.target.closest("button");if(!b)return;var li=b.closest("li");'
                    .'if(b.classList.contains("mvup")&&li.previousElementSibling)ul.insertBefore(li,li.previousElementSibling);'
                    .'if(b.classList.contains("mvdn")&&li.nextElementSibling)ul.insertBefore(li.nextElementSibling,li);'
                    .'reindex();'
                    .'});'
                    .'var drag=null;'
                    .'ul.addEventListener("dragstart",function(e){drag=e.target.closest("li");if(drag)drag.style.opacity=".4";});'
                    .'ul.addEventListener("dragend",function(){if(drag)drag.style.opacity="";drag=null;reindex();});'
                    .'ul.addEventListener("dragover",function(e){e.preventDefault();var t=e.target.closest("li");if(!t||t===drag)return;'
                    .'var r=t.getBoundingClientRect();var before=(e.clientY-r.top)/r.height<.5;'
                    .'ul.insertBefore(drag,before?t:t.nextSibling);'
                    .'});'
                    .'})();</script>';
            }

            if(!$topicMap){
                echo '<div class="card mu">No questions in the bank yet. Add some from the Questions tab first.</div>';
            }else{
                foreach($topicMap as $sb=>$tps){
                    $sbKey = h($sb);
                    $total = (int)array_sum($tps);
                    echo '<div class="card" style="box-shadow:none">'
                        .'<div class="inl"><b>'.h($sb).'</b><span class="mu">in bank: '.$total.'</span>'
                        .'<input type="hidden" name="sub[]" value="'.$sbKey.'">'
                        .'<label class="mu" style="margin-left:auto">Random from whole subject</label>'
                        .'<input type="number" name="rnd['.$sbKey.']" value="0" min="0" style="width:100px;margin:0"></div>'
                        .'<div class="grid" style="margin:10px 0 0">';
                    foreach($tps as $tp=>$n){
                        $tpKey = h($tp);
                        echo '<div class="inl">'
                            .'<span style="flex:1">'.h($tp).' <span class="mu">('.$n.')</span></span>'
                            .'<input type="number" name="tc['.$sbKey.']['.$tpKey.']" value="0" min="0" style="width:84px;margin:0"></div>';
                    }
                    echo '</div></div>';
                }
            }
            echo '<button class="p" name="mkpattern" value="1">Create set</button></form>';
        }

        echo '<h2>Quick set (one subject or topic)</h2><form class="card" method="post">'.field()
            .'<input name="title" placeholder="Set title" required>'.sylPick(true,true)
            .'<label class="mu">Number of questions</label><input type="number" name="n" value="20" min="5" max="100">'
            .'<label class="mu">Time (minutes)</label><input type="number" name="minutes" value="20">'
            .'<button class="p" name="mkset" value="1">Create quick set</button></form><h2>Existing sets</h2>';

        /* ---- existing sets table shows section order ---- */
        table(['Set','Exam','Questions','Minutes','Sections',''],
            array_map(function($r)use($del){
                $secs = rows(
                    'SELECT q.subject, MIN(qq.ord) AS first_ord
                     FROM qset_q qq JOIN questions q ON q.id=qq.qid
                     WHERE qq.set_id=?
                     GROUP BY q.subject
                     ORDER BY first_ord',
                    [$r['id']]
                );
                $seq = implode(' → ', array_map(function($x){return h($x['subject']);}, $secs));
                return [
                    h($r['title']),
                    h($r['exam']),
                    $r['n'],
                    $r['minutes'],
                    $seq ?: '<span class="mu">—</span>',
                    $del($r['id'])
                ];
            },rows('SELECT s.*,(SELECT COUNT(*) FROM qset_q WHERE set_id=s.id) n FROM qsets s ORDER BY id DESC')));
    }

    elseif($t==='qotd') qotdForm();

    elseif($t==='notes'){
        echo '<h2>Add note / trick</h2><form class="card" method="post">'.field()
            .'<select name="kind"><option value="note">Note</option><option value="trick">Trick</option></select>'
            .sylPick(false)
            .'<input name="title" placeholder="Title" required>'
            .'<textarea name="body" rows="5" placeholder="Content"></textarea>'
            .'<button class="p">Publish</button></form>';
        table(['Title','Exam',''],
            array_map(function($r)use($del){return [h($r['title']),h($r['exam']),$del($r['id'])];},
                rows('SELECT * FROM notes ORDER BY id DESC')));
    }

    elseif($t==='exams'){
        echo '<h2>Add exam notice</h2><form class="card" method="post">'.field()
            .'<input name="name" placeholder="Exam name" required>'
            .'<input name="date" placeholder="Date, e.g. 2027-03-15">'
            .'<input name="info" placeholder="Details">'
            .'<button class="p">Add</button></form>';
        table(['Exam','Date',''],
            array_map(function($r)use($del){return [h($r['name']),h($r['exam_date']),$del($r['id'])];},
                rows('SELECT * FROM exams ORDER BY id DESC')));
    }

    elseif($t==='pyq'){
        echo '<h2>Upload previous year paper</h2><form class="card" method="post" enctype="multipart/form-data">'.field()
            .'<input name="title" placeholder="Title" required>'
            .'<select name="exam">'.$eopts.'</select>'
            .'<input name="year" placeholder="Year">'
            .'<input type="file" name="pdf" accept="application/pdf" required>'
            .'<button class="p">Upload PDF</button></form>';
        table(['Title','Exam','Year',''],
            array_map(function($r)use($del){return [h($r['title']),h($r['exam']),h($r['year']),$del($r['id'])];},
                rows('SELECT * FROM pyq ORDER BY id DESC')));
    }

    elseif($t==='notices'){
        echo '<h2>📢 Notice board</h2>'
            .'<p class="mu">These scroll on the landing page and appear on every student dashboard.</p>'
            .'<form class="card" method="post">'.field()
            .'<div class="inl"><input name="icon" value="📢" style="width:80px;margin:0">'
            .'<input name="title" placeholder="e.g. OAVS 2027 notification is out" style="flex:1;min-width:240px;margin:0" required></div>'
            .'<input name="link" placeholder="Optional link (https://... or /exam/OAVS-CS)">'
            .'<button class="p">Publish notice</button></form>';
        table(['Notice','Status','Date',''],
            array_map(function($r)use($del){
                return [
                    h($r['icon'].' '.$r['title']),
                    $r['active']?'<span class="pill">Live</span>':'<span class="mu">Hidden</span>',
                    h(substr($r['created'],0,10)),
                    '<form method="post" style="display:inline">'.field()
                        .'<button name="toggle" value="'.$r['id'].'">'.($r['active']?'Hide':'Show').'</button></form> '
                    .$del($r['id'])
                ];
            },srows('SELECT * FROM notices ORDER BY id DESC')));
    }

    elseif($t==='inspire'){
        ensure_inspire();
        echo '<h2>🌟 Inspiration card (home page)</h2>'
            .'<p class="mu">One active card is shown at random above "Choose your exam": image on the left, quote on the right. Add several to rotate them.</p>'
            .'<form class="card" method="post" enctype="multipart/form-data">'.field()
            .'<label>Image (JPG, PNG, WEBP, GIF · max 3 MB)</label><input type="file" name="img" accept="image/*">'
            .'<label>Quote</label><textarea name="quote" rows="3" placeholder="Success is the sum of small efforts, repeated every day." required></textarea>'
            .'<label>Author / caption (optional)</label><input name="author" placeholder="e.g. Robert Collier">'
            .'<button class="p" name="addins" value="1">Add card</button></form>';

        $all=rows('SELECT * FROM inspire ORDER BY id DESC');
        echo '<h2>Your cards ('.count($all).')</h2>';
        if(!$all) echo '<div class="card mu">No cards yet. The home page hides this section until you add one.</div>';
        foreach($all as $r){
            echo '<div class="card"><div class="inl" style="align-items:flex-start;gap:16px;flex-wrap:wrap">'
                .($r['image']
                    ?'<img src="'.BASE.'/uploads/inspire/'.rawurlencode($r['image']).'" alt="" style="width:140px;height:100px;object-fit:cover;border-radius:12px">'
                    :'<div style="width:140px;height:100px;border-radius:12px;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-size:32px">🌟</div>')
                .'<form method="post" enctype="multipart/form-data" style="flex:1;min-width:260px">'.field()
                .'<span class="pill">'.($r['active']?'Live':'Hidden').'</span>'
                .'<textarea name="quote" rows="3" required>'.h($r['quote']).'</textarea>'
                .'<input name="author" value="'.h($r['author']).'" placeholder="Author / caption">'
                .'<label class="mu">Replace image (optional)</label><input type="file" name="img" accept="image/*">'
                .'<button class="p" name="saveins" value="'.$r['id'].'">Save</button> '
                .'<button name="toggle" value="'.$r['id'].'">'.($r['active']?'Hide':'Show').'</button> '
                .'<button name="del" value="'.$r['id'].'" onclick="return confirm(\'Delete this card?\')">🗑 Delete</button>'
                .'</form></div></div>';
        }
    }

    elseif($t==='feedback'){
        $cats=['General','Mock tests','Practice','AI Tutor','Design / ease of use','Bug / problem','Suggestion'];
        $fs=$_GET['f']??'all'; $fr=(int)($_GET['r']??0); $fc=trim((string)($_GET['c']??''));
        $w=' WHERE 1=1'; $par=[];
        if($fs==='unread'){ $w.=' AND is_read=0'; }
        if($fr>=1 && $fr<=5){ $w.=' AND rating=?'; $par[]=$fr; }
        if($fc!==''){ $w.=' AND category=?'; $par[]=$fc; }

        $tot =(int)val('SELECT COUNT(*) FROM feedback');
        $avg =val('SELECT ROUND(AVG(rating),1) FROM feedback');
        $week=(int)val('SELECT COUNT(*) FROM feedback WHERE created>=DATE_SUB(NOW(),INTERVAL 7 DAY)');

        echo '<h2>⭐ Website feedback</h2><p class="mu">Sent from the Feedback page by students, teachers and visitors.</p>'
            .tiles([
                ['💬',(string)$tot,'Total feedback','#4f46e5'],
                ['🆕',(string)$unreadFb,'Unread','#ef4444'],
                ['⭐',$avg!==null&&$avg!==false?(string)$avg:'—','Average rating','#f59e0b'],
                ['📅',(string)$week,'Last 7 days','#16a34a'],
            ]);

        $dist=array_column(rows('SELECT rating,COUNT(*) n FROM feedback GROUP BY rating'),'n','rating');
        if($tot){
            echo '<div class="card"><b>Ratings</b>';
            chart('fb1','bar',['1★','2★','3★','4★','5★'],[(int)($dist[1]??0),(int)($dist[2]??0),(int)($dist[3]??0),(int)($dist[4]??0),(int)($dist[5]??0)],'Feedback');
            echo '</div>';
        }

        echo '<form method="get" class="card inl" data-nolock="1"><input type="hidden" name="t" value="feedback">'
            .'<select name="f" style="width:auto;margin:0"><option value="all">All</option><option value="unread"'.($fs==='unread'?' selected':'').'>Unread only</option></select>'
            .'<select name="r" style="width:auto;margin:0"><option value="0">Any rating</option>';
        for($i=5;$i>=1;$i--) echo '<option value="'.$i.'"'.($fr===$i?' selected':'').'>'.$i.' star'.($i>1?'s':'').'</option>';
        echo '</select><select name="c" style="width:auto;margin:0"><option value="">Any topic</option>';
        foreach($cats as $c) echo '<option'.($fc===$c?' selected':'').'>'.h($c).'</option>';
        echo '</select><button class="p">Filter</button> <a class="btn" href="'.$A.'?t=feedback">Reset</a></form>';

        if($unreadFb) echo '<form method="post" style="margin:10px 0">'.field().'<button name="readall" value="1">✔ Mark all as read</button></form>';

        $list=rows('SELECT * FROM feedback'.$w.' ORDER BY is_read ASC, id DESC LIMIT 100',$par);
        if(!$list) echo '<div class="card mu">No feedback found.</div>';
        foreach($list as $r){
            $who=trim($r['name'])!==''?$r['name']:'Anonymous';
            echo '<div class="card"'.($r['is_read']?' style="opacity:.8"':'').'>'
                .'<div class="inl"><span style="color:#f59e0b;font-size:20px;letter-spacing:2px">'.str_repeat('★',(int)$r['rating']).'<span style="color:#cbd5e1">'.str_repeat('★',5-(int)$r['rating']).'</span></span>'
                .'<span class="pill">'.h($r['category']).'</span><span class="pill">'.h(ucfirst($r['role'])).'</span>'
                .($r['is_read']?'':'<span class="pill" style="background:#fee2e2;color:#991b1b">New</span>')
                .'<span class="mu" style="margin-left:auto">#'.(int)$r['id'].' · '.h(substr($r['created'],0,16)).'</span></div>'
                .'<p style="margin:10px 0;white-space:pre-wrap">'.h($r['message']).'</p>'
                .'<div class="mu">From <b>'.h($who).'</b>'
                .($r['email']!==''?' · <a href="mailto:'.h($r['email']).'?subject='.rawurlencode('Re: your feedback on '.SITE).'">'.h($r['email']).'</a>':' · no email given')
                .'</div>'
                .'<form method="post" style="margin-top:10px">'.field()
                .'<button name="toggle" value="'.(int)$r['id'].'">'.($r['is_read']?'Mark as unread':'✔ Mark as read').'</button> '
                .'<button name="del" value="'.(int)$r['id'].'" onclick="return confirm(\'Delete this feedback?\')">🗑 Delete</button></form></div>';
        }
    }

    /* ───────────── HERO CAROUSEL IMAGES (slides 1-3) ───────────── */
    elseif($t==='hero'){
        ensure_site();
        $names=[
            1=>'Slide 1: Practise like the real exam',
            2=>'Slide 2: Topic-wise practice',
            3=>'Slide 3: For teachers & schools',
        ];
        echo '<h2>🖼 Home page carousel images</h2>'
            .'<p class="mu">Each of the 3 slides can show its own image on the right side. Upload an image for one slide, two, or all three. '
            .'A slide with no image shows its default illustrated card. Best size: landscape, about 1000 × 750 px. JPG, PNG, WEBP or GIF, up to 3 MB.</p>'
            .'<div class="grid">';
        foreach($names as $n=>$label){
            $img=hero_img($n);
            $altv=site_get('hero_alt_'.$n,''); if($altv==='' && $n===1) $altv=site_get('hero_alt','');
            echo '<form class="card" method="post" enctype="multipart/form-data">'.field()
                .'<input type="hidden" name="slot" value="'.$n.'">'
                .'<b>'.h($label).'</b> <span class="pill">'.($img!==''?'Image set':'Default card').'</span>'
                .($img!==''
                    ?'<img src="'.BASE.'/uploads/hero/'.rawurlencode($img).'" alt="" style="width:100%;max-height:220px;object-fit:cover;border-radius:14px;display:block;margin:12px 0">'
                    :'<p class="mu" style="margin:12px 0">No image yet.</p>')
                .'<label>'.($img!==''?'Replace image':'Upload image').'</label>'
                .'<input type="file" name="img" accept="image/jpeg,image/png,image/webp,image/gif"'.($img!==''?'':' required').'>'
                .'<label>Short description (for accessibility)</label>'
                .'<input name="alt" maxlength="150" value="'.h($altv).'" placeholder="e.g. Students preparing for exams">'
                .'<button class="p" name="savehero" value="1">Save slide '.$n.'</button> '
                .($img!==''?'<button name="rmhero" value="1" formnovalidate onclick="return confirm(\'Remove the image of slide '.$n.'?\')">🗑 Remove</button>':'')
                .'</form>';
        }
        echo '</div>';
    }

    elseif($t==='users'){
        echo '<h2>Users</h2>';
        table(['Name','Email','Role','Verified','Points',''],
            array_map(function($r)use($del){
                return [
                    h($r['name']),h($r['email']),$r['role'],
                    $r['verified']?'✅':'❌',$r['points'],
                    ($r['verified']?'':'<form method="post" style="display:inline">'.field().'<button name="verify" value="'.$r['id'].'">Verify</button></form> ')
                    .($r['role']==='admin'?'':$del($r['id']))
                ];
            },rows('SELECT * FROM users ORDER BY id DESC LIMIT 200')));
    }

    elseif($t==='community'){
        echo '<h2>Community moderation</h2>';
        foreach(rows('SELECT p.*,u.name FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 30') as $p){
            echo '<div class="card"><b>'.h($p['title']).'</b> <span class="mu">by '.h($p['name']).'</span> '
                .$del($p['id'],'<input type="hidden" name="k" value="p">');
            foreach(rows('SELECT a.*,u.name FROM answers a JOIN users u ON u.id=a.user_id WHERE post_id=?',[$p['id']]) as $a)
                echo '<div class="mu">↳ '.h($a['name']).': '.h($a['body']).' '
                    .$del($a['id'],'<input type="hidden" name="k" value="a">').'</div>';
            echo '</div>';
        }
    }

    elseif($t==='requests'){
        $filter = $_GET['f'] ?? 'pending';
        $allowed = ['pending','fulfilled','declined','all'];
        if(!in_array($filter,$allowed,true)) $filter='pending';

        $pendingCnt   = (int) val("SELECT COUNT(*) FROM requests WHERE status='pending'");
        $fulfilledCnt = (int) val("SELECT COUNT(*) FROM requests WHERE status='fulfilled'");
        $declinedCnt  = (int) val("SELECT COUNT(*) FROM requests WHERE status='declined'");

        echo '<h2>💡 Student requests</h2>'
            .'<p class="mu">Requests submitted by students on <code>/?p=requests</code>. '
            .'Fulfil one to instantly build a set from the bank and email the student.</p>'
            .tiles([
                ['⏳', (string)$pendingCnt,   'Pending',   '#f59e0b'],
                ['✅', (string)$fulfilledCnt, 'Fulfilled', '#16a34a'],
                ['❌', (string)$declinedCnt,  'Declined',  '#ef4444'],
                ['📊', (string)($pendingCnt+$fulfilledCnt+$declinedCnt), 'All time', '#4f46e5'],
            ]);

        echo '<p style="margin:14px 0">';
        foreach(['pending'=>'⏳ Pending','fulfilled'=>'✅ Fulfilled','declined'=>'❌ Declined','all'=>'📋 All'] as $k=>$v){
            echo '<a class="btn'.($filter===$k?' p':'').'" href="'.$A.'?t=requests&f='.$k.'">'.$v.'</a> ';
        }
        echo '</p>';

        $where = $filter==='all' ? '1=1' : 'r.status=?';
        $args  = $filter==='all' ? [] : [$filter];

        $rows = rows("SELECT r.*,u.name uname,u.email uemail
                      FROM requests r JOIN users u ON u.id=r.user_id
                      WHERE $where
                      ORDER BY r.id DESC LIMIT 200", $args);

        if(!$rows){
            echo '<div class="card mu">No '.$filter.' requests yet.</div>';
        }else{
            $badge = ['pending'=>'⏳ Pending','fulfilled'=>'✅ Fulfilled','declined'=>'❌ Declined'];
            $color = ['pending'=>'#f59e0b','fulfilled'=>'#16a34a','declined'=>'#ef4444'];

            foreach($rows as $r){
                echo '<div class="card">'
                    .'<div class="inl">'
                    .'<span class="pill" style="background:'.$color[$r['status']].'22;color:'.$color[$r['status']].'">'
                    .$badge[$r['status']].'</span>'
                    .' <span class="pill">'.h($r['exam']).'</span>'
                    .($r['subject']?' <span class="pill">'.h($r['subject']).'</span>':'')
                    .($r['topic']?' <span class="pill">'.h($r['topic']).'</span>':'')
                    .'<span class="mu" style="margin-left:auto">#'.$r['id'].' · '.h(substr($r['created'],0,16)).'</span>'
                    .'</div>'

                    .'<p class="mu" style="margin:6px 0 4px"><b>'.h($r['uname']).'</b> · '.h($r['uemail']).'</p>'
                    .'<p style="margin:8px 0;white-space:pre-wrap">'.h($r['body']).'</p>'
                    .($r['want_count']?'<div class="mu">Requested '.$r['want_count'].' questions</div>':'');

                if($r['set_id']){
                    $setTitle = val('SELECT title FROM qsets WHERE id=?',[$r['set_id']]);
                    echo '<div class="mu">Linked to set <b>#'.$r['set_id'].'</b> — '.h($setTitle?:'(deleted)').'</div>';
                }
                if($r['admin_note']){
                    echo '<div class="mu" style="margin-top:8px;padding:8px 10px;background:#eef2ff;border-radius:8px">'
                        .'<b>Admin note:</b> '.h($r['admin_note']).'</div>';
                }

                echo '<form method="post" style="margin-top:10px">'.field()
                    .'<label class="mu">Note to student (optional)</label>'
                    .'<input name="note" placeholder="e.g. Added 15 extra hard Blood Relations questions" '
                    .'style="width:100%;margin:4px 0 8px">';

                if($r['status']==='pending'){
                    echo '<button class="p" name="fulfil" value="'.$r['id'].'">✅ Fulfil (auto-build set)</button> '
                        .'<button name="reply" value="'.$r['id'].'">💬 Save reply only</button> '
                        .'<button name="decline" value="'.$r['id'].'" onclick="return confirm(\'Decline this request?\')">❌ Decline</button> ';
                }else{
                    echo '<button name="reply" value="'.$r['id'].'">💬 Save reply</button> ';
                }

                echo '<span class="grow"></span>'
                    .'<button name="del" value="'.$r['id'].'" onclick="return confirm(\'Delete permanently?\')">🗑 Delete</button>'
                    .'</form>'
                    .'</div>';
            }
        }
    }
}

$body=ob_get_clean();
head('Admin panel',$links,$A,$ok);
echo $body;
foot();