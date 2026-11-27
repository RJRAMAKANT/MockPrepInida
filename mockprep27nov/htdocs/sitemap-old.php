/*<?php
require __DIR__.'/lib.php';header('Content-Type: application/xml; charset=utf-8');
$u=['/'];foreach(['register','about','notes','exams','leaderboard'] as $x)$u[]=pu($x);foreach(exams() as $k=>$e)$u[]=pux($k);
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($u as $x)echo '<url><loc>'.htmlspecialchars(baseUrl().$x).'</loc><changefreq>weekly</changefreq></url>';echo '</urlset>';*/



<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/lib.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(baseUrl(), '/');

$urls = ['/'];

foreach (['register', 'about', 'notes', 'exams', 'leaderboard'] as $page) {
    $urls[] = pu($page);
}

foreach (exams() as $key => $exam) {
    $urls[] = pux($key);
}

echo '<?xml version="1.0" encoding="UTF-8"?>';

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

foreach (array_unique($urls) as $path) {

    $url = $base . '/' . ltrim($path, '/');

    echo '<url>';
    echo '<loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    echo '<changefreq>weekly</changefreq>';
    echo '</url>';
}

echo '</urlset>';

