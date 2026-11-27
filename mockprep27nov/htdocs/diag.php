<?php
require __DIR__.'/config.php';
header('Content-Type: text/plain');
echo "=== DB ===\n";
try{
    $p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    echo "connected OK\n";
    echo "exam_defs rows: ".$p->query('SELECT COUNT(*) FROM exam_defs')->fetchColumn()."\n";
    echo "exam_defs codes: ".implode(', ',array_column($p->query('SELECT code FROM exam_defs')->fetchAll(),'code'))."\n";
    echo "questions rows: ".$p->query('SELECT COUNT(*) FROM questions')->fetchColumn()."\n";
    echo "users rows: ".$p->query('SELECT COUNT(*) FROM users')->fetchColumn()."\n";
    echo "schema version: ".$p->query("SELECT v FROM stats WHERE k='schema'")->fetchColumn()."\n";
}catch(Throwable $e){ echo "DB ERROR: ".$e->getMessage()."\n"; }

echo "\n=== SESSION ===\n";
session_start();
echo "session id: ".session_id()."\n";
echo "uid: ".($_SESSION['uid']??'—')."\n";
echo "csrf: ".($_SESSION['csrf']??'—')."\n";
echo "gc_maxlifetime: ".ini_get('session.gc_maxlifetime')."\n";
echo "cookie_lifetime: ".ini_get('session.cookie_lifetime')."\n";
echo "cookie params: ".json_encode(session_get_cookie_params())."\n";

echo "\n=== CONFIG ===\n";
echo "BASE='".BASE."'\n";
echo "SMTP_HOST='".SMTP_HOST."'\n";
echo "MAIL_WEBHOOK_URL set: ".(MAIL_WEBHOOK_URL!==''?'yes':'no')."\n";
echo "REQUIRE_VERIFY: ".(REQUIRE_VERIFY?'true':'false')."\n";

echo "\n=== FILES ===\n";
foreach(['lib.php','index.php','assets/app.js','mp-control-admin/index.php'] as $f){
    $full=__DIR__.'/'.$f;
    echo $f.": ".(file_exists($full)?'exists, '.filesize($full).' bytes, mtime '.date('Y-m-d H:i:s',filemtime($full)):'MISSING')."\n";
}
echo "\napp.js contains 'credentials:'same-origin''? ";
$js=@file_get_contents(__DIR__.'/assets/app.js');
echo (strpos((string)$js,"credentials:'same-origin'")!==false?'YES':'NO — OLD FILE')."\n";
echo "index.php contains 'certificate'? ";
$ix=@file_get_contents(__DIR__.'/index.php');
echo (strpos((string)$ix,"'certificate'")!==false?'YES':'NO — OLD FILE')."\n";
echo "index.php contains 'hash_hmac'? ";
echo (strpos((string)$ix,'hash_hmac')!==false?'YES':'NO — OLD FILE')."\n";
echo "lib.php contains 'certificateHtml'? ";
$lb=@file_get_contents(__DIR__.'/lib.php');
echo (strpos((string)$lb,'certificateHtml')!==false?'YES':'NO — OLD FILE')."\n";