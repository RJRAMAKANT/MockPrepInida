<?php
ob_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/xml; charset=utf-8');

try {
    require_once __DIR__ . '/lib.php';

    $base = rtrim(baseUrl(), '/');

    $urls = [
        '/',
        pu('register'),
        pu('about'),
        pu('notes'),
        pu('exams'),
        pu('leaderboard')
    ];

    foreach (exams() as $key => $exam) {
        $urls[] = pux($key);
    }

    $urls = array_unique($urls);

    $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $path) {
        $url = $base . '/' . ltrim($path, '/');

        $xml .= "  <url>\n";
        $xml .= '    <loc>' .
            htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') .
            "</loc>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    ob_clean();
    echo $xml;

} catch (Throwable $e) {
    ob_clean();
    error_log('Sitemap Error: ' . $e->getMessage());
    http_response_code(500);
}

ob_end_flush();