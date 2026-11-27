<?php
/* ═══════════════════════════════════════════════════════════════
   lib_class.php — class / teacher-test helpers + AI helpers
   Loaded AFTER lib.php by index.php, admin/index.php and teacher/index.php.
   ═══════════════════════════════════════════════════════════════ */

/* open / close times are compared with time(), so use the school's timezone */
date_default_timezone_set('Asia/Kolkata');

/* correct-answer parser for CSV: A/B/C/D or 1-4  (0 is NOT accepted any more) */
function ans_index($v){
    $v = strtoupper(trim((string)$v));
    $v = preg_replace('/^(OPTION|ANS|ANSWER)\s*/', '', $v);
    $v = trim($v, " .)(");
    if($v==='') return false;
    if(in_array($v,['A','1'],true)) return 0;
    if(in_array($v,['B','2'],true)) return 1;
    if(in_array($v,['C','3'],true)) return 2;
    if(in_array($v,['D','4'],true)) return 3;
    return false;
}

/* "ABC School — Class 8-A" */
function classLabel($c){
    $s = trim((string)($c['school'] ?? ''));
    return ($s!=='' ? $s.' — ' : '').$c['name'];
}

/* ───────────── schema (runs once, then skipped) ───────────── */
function migrate_class(){
    static $ran=false;
    if($ran) return;
    $ran=true;
    if((int)sval("SELECT v FROM stats WHERE k='schema_class'") >= 2) return;

    $err=[];
    $safe=function($sql) use (&$err){
        try{ db()->exec($sql); }
        catch(Exception $e){
            $m=$e->getMessage();
            if(stripos($m,'Duplicate column')===false && stripos($m,'already exists')===false)
                $err[]=substr($m,0,170);
        }
    };

    $safe("ALTER TABLE users ADD COLUMN class_id INT NULL");

    $safe("CREATE TABLE IF NOT EXISTS classes(
      id INT AUTO_INCREMENT PRIMARY KEY,
      teacher_id INT NOT NULL,
      school VARCHAR(150) NOT NULL DEFAULT '',
      name VARCHAR(120) NOT NULL,
      code VARCHAR(12) NOT NULL UNIQUE,
      created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY(teacher_id), KEY(code)
    ) DEFAULT CHARSET=utf8mb4");
    $safe("ALTER TABLE classes ADD COLUMN school VARCHAR(150) NOT NULL DEFAULT ''");

    $safe("CREATE TABLE IF NOT EXISTS papers(
      id INT AUTO_INCREMENT PRIMARY KEY,
      teacher_id INT NOT NULL,
      class_id INT NULL,
      exam VARCHAR(20) NOT NULL,
      title VARCHAR(200) NOT NULL,
      code VARCHAR(12) NOT NULL UNIQUE,
      minutes INT NOT NULL DEFAULT 30,
      share ENUM('public','class','code') NOT NULL DEFAULT 'class',
      created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY(teacher_id), KEY(class_id), KEY(code)
    ) DEFAULT CHARSET=utf8mb4");

    $safe("CREATE TABLE IF NOT EXISTS paper_q(
      id INT AUTO_INCREMENT PRIMARY KEY,
      paper_id INT NOT NULL,
      q VARCHAR(500) NOT NULL,
      a VARCHAR(255) NOT NULL, b VARCHAR(255) NOT NULL,
      c VARCHAR(255) NOT NULL, d VARCHAR(255) NOT NULL,
      ans TINYINT NOT NULL,
      exp TEXT NULL,
      ord INT NOT NULL DEFAULT 0,
      KEY(paper_id)
    ) DEFAULT CHARSET=utf8mb4");

    /* teacher's own question table */
    $safe("CREATE TABLE IF NOT EXISTS tq(
      id INT AUTO_INCREMENT PRIMARY KEY,
      teacher_id INT NOT NULL,
      class_id INT NULL,
      exam VARCHAR(20) NOT NULL DEFAULT '',
      subject VARCHAR(100) DEFAULT '',
      topic VARCHAR(100) DEFAULT '',
      q TEXT NOT NULL,
      a VARCHAR(500) NOT NULL, b VARCHAR(500) NOT NULL,
      c VARCHAR(500) NOT NULL, d VARCHAR(500) NOT NULL,
      ans TINYINT NOT NULL,
      exp TEXT NULL,
      source ENUM('manual','csv','bank') NOT NULL DEFAULT 'manual',
      created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY(teacher_id), KEY(class_id)
    ) DEFAULT CHARSET=utf8mb4");

    $safe("CREATE TABLE IF NOT EXISTS tests(
      id INT AUTO_INCREMENT PRIMARY KEY,
      teacher_id INT NOT NULL,
      class_id INT NULL,
      title VARCHAR(200) NOT NULL,
      code VARCHAR(12) NOT NULL UNIQUE,
      exam VARCHAR(20) NOT NULL DEFAULT '',
      minutes INT NOT NULL DEFAULT 30,
      opens_at DATETIME NULL,
      closes_at DATETIME NULL,
      always_open TINYINT NOT NULL DEFAULT 0,
      share ENUM('class','code','public') NOT NULL DEFAULT 'class',
      created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY(teacher_id), KEY(class_id), KEY(code)
    ) DEFAULT CHARSET=utf8mb4");

    $safe("CREATE TABLE IF NOT EXISTS test_q(
      id INT AUTO_INCREMENT PRIMARY KEY,
      test_id INT NOT NULL,
      tq_id INT NOT NULL,
      ord INT NOT NULL DEFAULT 0,
      KEY(test_id), KEY(tq_id)
    ) DEFAULT CHARSET=utf8mb4");

    $safe("CREATE TABLE IF NOT EXISTS test_attempts(
      id INT AUTO_INCREMENT PRIMARY KEY,
      test_id INT NOT NULL,
      user_id INT NOT NULL,
      total INT NOT NULL DEFAULT 0,
      correct INT NOT NULL DEFAULT 0,
      wrong INT NOT NULL DEFAULT 0,
      score INT NOT NULL DEFAULT 0,
      marks DECIMAL(8,2) NOT NULL DEFAULT 0,
      maxm DECIMAL(8,2) NOT NULL DEFAULT 0,
      secs INT NOT NULL DEFAULT 0,
      answers TEXT NULL,
      created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY(test_id), KEY(user_id)
    ) DEFAULT CHARSET=utf8mb4");

    $safe("CREATE TABLE IF NOT EXISTS class_doubts(
      id INT AUTO_INCREMENT PRIMARY KEY,
      class_id INT NOT NULL,
      user_id INT NOT NULL,
      test_id INT NULL,
      tq_id INT NULL,
      question TEXT NOT NULL,
      answer TEXT NULL,
      answered_by INT NULL,
      answered_at DATETIME NULL,
      created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY(class_id), KEY(user_id), KEY(test_id)
    ) DEFAULT CHARSET=utf8mb4");

    /* database-level lock: ONE attempt per student per test */
    try{
        $has=(int)val("SELECT COUNT(*) FROM information_schema.statistics
                       WHERE table_schema=DATABASE() AND table_name='test_attempts' AND index_name='uniq_test_user'");
        if(!$has){
            db()->exec("DELETE a FROM test_attempts a JOIN test_attempts b
                        ON a.test_id=b.test_id AND a.user_id=b.user_id AND a.id>b.id");
            db()->exec("ALTER TABLE test_attempts ADD UNIQUE KEY uniq_test_user (test_id,user_id)");
        }
    }catch(Exception $e){ $err[]=substr($e->getMessage(),0,170); }

    if(!$err){
        try{
            db()->exec("INSERT IGNORE INTO stats(k,v) VALUES('schema_class',0)");
            db()->exec("UPDATE stats SET v=2 WHERE k='schema_class'");
        }catch(Exception $e){}
    }else{
        $GLOBALS['migerr']=array_merge($GLOBALS['migerr'] ?? [],$err);
    }
}

/* ───────────── teacher-test helpers (student side) ───────────── */

/* Is the test inside its open/close window?  → [bool, message] */
function testWindow($t){
    if($t['always_open']) return [true,''];
    $now=time();
    if($t['opens_at']  && strtotime($t['opens_at'])  > $now)     return [false,'Test not open yet.'];
    if($t['closes_at'] && strtotime($t['closes_at']) < $now-120) return [false,'Test closed.'];   // 2 min grace for the final submit
    return [true,''];
}
/* A student may take a test if it belongs to his class, or he unlocked it with the test code */
function canTakeTest($t,$u){
    if(!$t) return false;
    if(!empty($u['class_id']) && (int)$t['class_id']===(int)$u['class_id']) return true;
    return in_array((int)$t['id'], $_SESSION['tcodes'] ?? [], true);
}
/* the single saved attempt of a student for a teacher test */
function myTAttempt($tid,$uid){
    return row('SELECT * FROM test_attempts WHERE test_id=? AND user_id=? ORDER BY id LIMIT 1',[$tid,$uid]);
}
/* review data: question, options, right answer, student's pick, explanation */
function ttReview($tid,$answers){
    $qs=rows('SELECT q.* FROM test_q tq JOIN tq q ON q.id=tq.tq_id WHERE tq.test_id=? ORDER BY tq.ord',[$tid]);
    return array_map(function($r) use ($answers){
        return ['q'=>$r['q'],'o'=>[$r['a'],$r['b'],$r['c'],$r['d']],
                'ans'=>(int)$r['ans'],'pick'=>$answers[$r['id']]??null,'exp'=>$r['exp']];
    },$qs);
}

/* table for the home-page inspiration card (image + quote) */
function ensure_inspire(){
    static $d=false; if($d) return; $d=true;
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS inspire(
          id INT AUTO_INCREMENT PRIMARY KEY,
          image VARCHAR(120) NOT NULL DEFAULT '',
          quote TEXT NOT NULL,
          author VARCHAR(120) NOT NULL DEFAULT '',
          active TINYINT NOT NULL DEFAULT 1,
          created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4");
    }catch(Exception $e){}
}

/* ═══════════════════════════════════════════════════════════════
   AI: many free providers, automatic switching.
   Keys go in config.php: GEMINI_KEY, GROQ_KEY, OPENROUTER_KEY,
   CEREBRAS_KEY, HF_KEY, MISTRAL_KEY  (use as many as you have).
   Models are DISCOVERED from each provider (no hard-coded names that
   can be retired). If a model is busy (503/429) the next-best model of
   the same provider is tried, then the next provider.
   ═══════════════════════════════════════════════════════════════ */
function cfg($n,$d=''){ return defined($n)?constant($n):$d; }

function ensure_ai(){
    static $d=false; if($d) return; $d=true;
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS ai_log(
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
            created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY(user_id,created)) DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS ai_cool(
            name VARCHAR(30) PRIMARY KEY, until_ts INT NOT NULL DEFAULT 0, last_err VARCHAR(255) NULL) DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS ai_models(
            name VARCHAR(30) PRIMARY KEY, model VARCHAR(150) NOT NULL, until_ts INT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS ai_mlist(
            name VARCHAR(30) PRIMARY KEY, models VARCHAR(500) NOT NULL, until_ts INT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4");
    }catch(Exception $e){}
}

/* [name, type, url, key, explicit-model-or-''] in the order they are tried */
function ai_providers(){
    $gk=cfg('GEMINI_KEY'); if($gk==='' && AI_PROVIDER==='gemini') $gk=AI_KEY;   // old single-key config still works
    $defs=[
      ['gemini','gemini','',$gk,cfg('GEMINI_MODEL')],
      ['groq','openai','https://api.groq.com/openai/v1/chat/completions',cfg('GROQ_KEY'),cfg('GROQ_MODEL')],
      ['openrouter','openai','https://openrouter.ai/api/v1/chat/completions',cfg('OPENROUTER_KEY'),cfg('OPENROUTER_MODEL')],
      ['cerebras','openai','https://api.cerebras.ai/v1/chat/completions',cfg('CEREBRAS_KEY'),cfg('CEREBRAS_MODEL')],
      ['huggingface','openai','https://router.huggingface.co/v1/chat/completions',cfg('HF_KEY'),cfg('HF_MODEL')],
      ['mistral','openai','https://api.mistral.ai/v1/chat/completions',cfg('MISTRAL_KEY'),cfg('MISTRAL_MODEL')],
    ];
    $P=[]; $keys=[];
    foreach($defs as $d) if($d[3]!==''){ $P[]=$d; $keys[]=$d[3]; }
    if(AI_KEY!=='' && AI_PROVIDER!=='gemini' && !in_array(AI_KEY,$keys,true))   // old single-provider config
        $P[]=['legacy',AI_PROVIDER==='anthropic'?'anthropic':'openai',AI_URL,AI_KEY,AI_MODEL];
    return $P;
}

function ai_http_get($url,$hd){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_HTTPHEADER=>$hd,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>10]);
    $raw=curl_exec($ch); curl_close($ch);
    return $raw===false?'':$raw;
}

/* best N usable chat models from a provider's own list, best first */
function ai_rank($name,$ids,$limit=3){
    $bad=['embed','whisper','guard','tts','audio','image','imagen','veo','speech','orpheus','playai','rerank',
          'moderation','safeguard','transcribe','live','robotics','computer','aqa','gemma','learnlm','lyria','ocr','diffusion'];
    $sc=[];
    foreach($ids as $id){
        $l=strtolower((string)$id); if($l==='') continue;
        foreach($bad as $b) if(strpos($l,$b)!==false) continue 2;
        if($name==='openrouter' && substr($l,-5)!==':free') continue;
        $s=0;
        if($name==='gemini'){
            if(preg_match('/gemini-(\d+(?:\.\d+)?)/',$l,$m)) $s+=(float)$m[1]*100;
            if(strpos($l,'flash')!==false) $s+=60;
            if(strpos($l,'lite')!==false)  $s-=25;
            if(strpos($l,'pro')!==false)   $s-=40;       // pro models usually have no free quota
        }else{
            foreach(['70b'=>50,'120b'=>55,'large'=>40,'maverick'=>45,'scout'=>35,'qwen'=>30,'gpt-oss'=>25,'llama'=>20,'small'=>15,'8b'=>5] as $k=>$w)
                if(strpos($l,$k)!==false) $s=max($s,$w);
        }
        $sc[$id]=$s;
    }
    arsort($sc);
    return array_slice(array_keys($sc),0,$limit);
}

function ai_discover($p){
    list($name,$type,$url,$key)=$p;
    if($type==='anthropic') return [];
    if($type==='gemini'){
        $r=json_decode(ai_http_get('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200',['x-goog-api-key: '.$key]),true);
        $ids=[];
        foreach(($r['models']??[]) as $m){
            if(!in_array('generateContent',$m['supportedGenerationMethods']??[],true)) continue;
            $ids[]=preg_replace('~^models/~','',$m['name']??'');
        }
        return ai_rank($name,$ids);
    }
    $r=json_decode(ai_http_get(preg_replace('~/chat/completions$~','/models',$url),['Authorization: Bearer '.$key]),true);
    return ai_rank($name,array_column($r['data']??[],'id'));
}

/* ordered list of models to try for a provider (cached 24 h) */
function ai_models($p,$force=false){
    $name=$p[0];
    if(!$force){
        $c=sval('SELECT models FROM ai_mlist WHERE name=? AND until_ts>?',[$name,time()],'');
        if($c){ $l=array_values(array_filter(explode(',',$c))); if($l) return $l; }
        if($p[4]!=='') return [$p[4]];
    }
    $l=ai_discover($p);
    if($l){
        try{ q('REPLACE INTO ai_mlist(name,models,until_ts) VALUES(?,?,?)',[$name,implode(',',$l),time()+86400]); }catch(Exception $e){}
        return $l;
    }
    $fb=['gemini'=>'gemini-flash-latest','groq'=>'llama-3.3-70b-versatile','openrouter'=>'meta-llama/llama-3.3-70b-instruct:free',
         'cerebras'=>'llama-3.3-70b','huggingface'=>'openai/gpt-oss-120b','mistral'=>'mistral-small-latest'];
    $one=$p[4]!==''?$p[4]:($fb[$name]??'');
    return $one!==''?[$one]:[];
}

/* one attempt on one provider+model → [text, httpCode, error] */
function ai_one($p,$system,$messages,$json,$temp){
    list($name,$type,$url,$key,$model)=$p;
    $hd=['Content-Type: application/json'];
    if($type==='gemini'){
        $url='https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
        $hd[]='x-goog-api-key: '.$key;                       // key in header, not in the URL
        $contents=[];
        foreach($messages as $m) $contents[]=['role'=>$m['role']==='assistant'?'model':'user','parts'=>[['text'=>$m['content']]]];
        $body=['contents'=>$contents,'generationConfig'=>['temperature'=>$temp,'maxOutputTokens'=>8192]];
        if($system!=='') $body['systemInstruction']=['parts'=>[['text'=>$system]]];
        if($json) $body['generationConfig']['responseMimeType']='application/json';
    }elseif($type==='anthropic'){
        $url='https://api.anthropic.com/v1/messages';
        $hd[]='x-api-key: '.$key; $hd[]='anthropic-version: 2023-06-01';
        $body=['model'=>$model,'max_tokens'=>4000,'messages'=>$messages];
        if($system!=='') $body['system']=$system;
    }else{
        $hd[]='Authorization: Bearer '.$key;
        $msgs=$system!==''?array_merge([['role'=>'system','content'=>$system]],$messages):$messages;
        $body=['model'=>$model,'messages'=>$msgs,'temperature'=>$temp,'max_tokens'=>4096];
    }
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($body),
                           CURLOPT_HTTPHEADER=>$hd,CURLOPT_TIMEOUT=>$json?60:30,CURLOPT_CONNECTTIMEOUT=>8]);
    $raw=curl_exec($ch);
    if($raw===false){ $e=curl_error($ch); curl_close($ch); return ['',0,'connection failed: '.$e]; }
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $r=json_decode($raw,true); $t='';
    if($type==='gemini'){
        foreach(($r['candidates'][0]['content']['parts']??[]) as $x) if(!empty($x['text']) && empty($x['thought'])) $t.=$x['text'];
    }elseif($type==='anthropic') $t=$r['content'][0]['text']??'';
    else $t=$r['choices'][0]['message']['content']??'';
    $t=trim((string)$t);
    if($t===''){
        $em=$r['error']['message']??($r['error']??$raw);
        if(is_array($em)) $em=json_encode($em);
        return ['',$code?:200,'HTTP '.$code.' ['.$model.'] '.substr((string)$em,0,160)];
    }
    return [$t,$code,''];
}

/* One provider: its best model, then the next-best models if busy/limited,
   then give up on it and pause it briefly. Returns text or ''. */
function ai_try($p,$system,$messages,$json,$temp,&$errs){
    $models=ai_models($p); $i=0; $forced=false; $code=0; $err='no model available';
    while($i<count($models) && $i<3){
        $p[4]=$models[$i];
        list($t,$code,$err)=ai_one($p,$system,$messages,$json,$temp);
        if($t!=='') return $t;
        if(in_array($code,[400,404],true) && preg_match('/model/i',$err) && !$forced){   // retired model → rediscover
            $forced=true; $m2=ai_models($p,true);
            if($m2){ $models=$m2; $i=0; continue; }
        }
        if(in_array($code,[401,403],true)) break;       // bad key: other models will not help
        $i++;                                           // 429 / 503 / timeout → next model
    }
    $errs[]=$p[0].': '.$err;
    /* quota → 10 min; bad key / model → 1 h; server busy or timeout → 90 s */
    $pause=($code===429||$code===402)?600:(in_array($code,[401,403,404],true)?3600:(($code>=500||$code===0)?90:0));
    if($pause){ try{ q('REPLACE INTO ai_cool(name,until_ts,last_err) VALUES(?,?,?)',[$p[0],time()+$pause,substr($err,0,250)]); }catch(Exception $e){} }
    return '';
}

/* Tries every provider that has a key; paused providers are skipped first,
   and tried again as a last resort. Returns the reply text, or ''
   (reason in $GLOBALS['aierr']; who answered in $GLOBALS['aiused']). */
function ai_call($system,$messages,$json=false,$temp=0.7){
    @set_time_limit(120);
    ensure_ai();
    $GLOBALS['aierr']=''; $GLOBALS['aiused']='';
    $P=ai_providers();
    if(!$P){ $GLOBALS['aierr']='No AI key set in config.php (GEMINI_KEY, GROQ_KEY, ...)'; return ''; }
    $errs=[]; $skipped=[];
    foreach($P as $p){
        if((int)sval('SELECT until_ts FROM ai_cool WHERE name=?',[$p[0]],0) > time()){ $skipped[]=$p; continue; }
        $t=ai_try($p,$system,$messages,$json,$temp,$errs);
        if($t!==''){ $GLOBALS['aiused']=$p[0]; return $t; }
    }
    foreach($skipped as $p){
        $t=ai_try($p,$system,$messages,$json,$temp,$errs);
        if($t!==''){ $GLOBALS['aiused']=$p[0]; return $t; }
    }
    $GLOBALS['aierr']=implode(' | ',$errs);
    return '';
}

/* Generate MCQs in the site's format → [['q'=>..,'o'=>[A,B,C,D],'a'=>0-3,'x'=>explanation], ...] */
function aiGenerate($exam,$sub,$n,$topic){
    $ex=exams()[$exam]['t']??$exam;
    /* show the AI what we already have, so it does not repeat it */
    $old=array_column(srows('SELECT q FROM questions WHERE exam=? AND subject=?'.($topic?' AND topic=?':'').' ORDER BY id DESC LIMIT 40',
         $topic?[$exam,$sub,$topic]:[$exam,$sub]),'q');
    $avoid=$old?"\n  Do NOT repeat or rephrase any of these existing questions:\n".implode("\n",array_map(function($s){return '  * '.mb_substr($s,0,120);},$old)):'';
    $p="You are an expert question-setter for Indian government and school-entrance exams.\n"
      ."Write $n ORIGINAL multiple-choice questions for the \"$ex\" exam.\n"
      ."Subject: \"$sub\"".($topic?"\nTopic: \"$topic\"":'')."\n\n"
      ."Rules:\n"
      ."- Real-exam difficulty: a mix of easy, medium and hard.\n"
      ."- Exactly 4 options, exactly ONE correct. Distractors must be plausible and different from each other. No 'all of the above' / 'none of these'.\n"
      ."- No duplicate or near-duplicate questions.".$avoid."\n"
      ."- For reasoning, maths and logic: solve each question step by step BEFORE choosing the answer, and make sure the answer index matches your working.\n"
      ."- Explanation: 1-3 short sentences showing the working or the reason.\n\n"
      ."Return ONLY a JSON array, no markdown: [{\"q\":\"question\",\"o\":[\"A\",\"B\",\"C\",\"D\"],\"a\":0,\"x\":\"explanation\"}] where a is the index (0-3) of the correct option.";
    $t=ai_call('',[['role'=>'user','content'=>$p]],true,0.6);
    if($t==='') return [];
    $t=preg_replace('/```(json)?/','',$t);
    $i=strpos($t,'['); $j=strrpos($t,']');
    if($i===false||$j===false){ $GLOBALS['aierr']='AI reply was not valid JSON.'; return []; }
    $o=json_decode(substr($t,$i,$j-$i+1),true);
    if(!is_array($o)){ $GLOBALS['aierr']='AI reply was not valid JSON.'; return []; }
    $out=[]; $seen=[];
    foreach($o as $x){
        if(!is_array($x)||empty($x['q'])||!isset($x['o'])||!is_array($x['o'])||count($x['o'])<4) continue;
        $opts=array_map(function($v){return trim((string)$v);},array_slice(array_values($x['o']),0,4));
        if(in_array('',$opts,true)||count(array_unique($opts))<4) continue;
        $a=$x['a']??-1;
        if(is_string($a)&&preg_match('/^[A-Da-d]$/',trim($a))) $a=ord(strtoupper(trim($a)))-65;
        $a=(int)$a;
        if($a<0||$a>3) continue;
        $k=qnorm($x['q']); if(isset($seen[$k])) continue; $seen[$k]=1;     // no repeats inside one AI batch
        $out[]=['q'=>trim((string)$x['q']),'o'=>$opts,'a'=>$a,'x'=>trim((string)($x['x']??''))];
    }
    return $out;
}


/* feedback from visitors / students / teachers */
function ensure_feedback(){
    static $d=false; if($d) return; $d=true;
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS feedback(
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NULL,
          role VARCHAR(20) NOT NULL DEFAULT 'guest',
          name VARCHAR(80) NOT NULL DEFAULT '',
          email VARCHAR(150) NOT NULL DEFAULT '',
          rating TINYINT NOT NULL DEFAULT 0,
          category VARCHAR(40) NOT NULL DEFAULT 'General',
          message TEXT NOT NULL,
          is_read TINYINT NOT NULL DEFAULT 0,
          ip_hash CHAR(64) NOT NULL DEFAULT '',
          created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY(is_read), KEY(created), KEY(ip_hash)
        ) DEFAULT CHARSET=utf8mb4");
    }catch(Exception $e){}
}


/* tiny key/value store for site settings (hero image etc.) */
function ensure_site(){
    static $d=false; if($d) return; $d=true;
    try{ db()->exec("CREATE TABLE IF NOT EXISTS site_settings(
        k VARCHAR(40) PRIMARY KEY, v TEXT NOT NULL) DEFAULT CHARSET=utf8mb4"); }catch(Exception $e){}
}
function site_get($k,$d=''){
    ensure_site();
    $v=sval('SELECT v FROM site_settings WHERE k=?',[$k],'');
    return ($v===false||$v===null)?$d:$v;
}
function site_set($k,$v){
    ensure_site();
    q('REPLACE INTO site_settings(k,v) VALUES(?,?)',[$k,(string)$v]);
}


/* ═══════════ HERO CAROUSEL IMAGES (slides 1-3) ═══════════ */
function hero_dir(){ return __DIR__.'/uploads/hero/'; }

/* image file name for slide $n ('' = none). Slide 1 also reads the old single "hero_image" setting. */
function hero_img($n){
    $v = site_get('hero_image_'.(int)$n, '');
    if($v==='' && (int)$n===1) $v = site_get('hero_image','');
    return basename((string)$v);
}
function hero_alt($n){
    $v = site_get('hero_alt_'.(int)$n, '');
    if($v==='' && (int)$n===1) $v = site_get('hero_alt','');
    return $v!=='' ? $v : 'MockPrep India';
}
function hero_delete_file($name){
    $name = basename((string)$name);
    if($name!=='' && is_file(hero_dir().$name)) @unlink(hero_dir().$name);
}
/* validates + saves an uploaded image. Returns the new file name, '' if nothing uploaded or on error ($err set). */
function hero_save_upload($f,&$err){
    $err = '';
    if(!is_array($f) || !isset($f['error']) || $f['error']===UPLOAD_ERR_NO_FILE) return '';
    if($f['error']!==UPLOAD_ERR_OK){
        $err = 'Upload failed (error '.$f['error'].'). The file may be bigger than the server allows.';
        return '';
    }
    if(!is_uploaded_file($f['tmp_name'])){ $err='Invalid upload.'; return ''; }
    if($f['size']>3*1024*1024){ $err='Image must be 3 MB or smaller.'; return ''; }
    $info = @getimagesize($f['tmp_name']);
    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$info['mime'] ?? ''] ?? null;
    if(!$ext){ $err='Image must be JPG, PNG, WEBP or GIF.'; return ''; }
    $dir = hero_dir();
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    $name = bin2hex(random_bytes(6)).'.'.$ext;
    if(!@move_uploaded_file($f['tmp_name'],$dir.$name)){
        $err='Could not save the image. Make sure the folder uploads/hero is writable.';
        return '';
    }
    return $name;
}