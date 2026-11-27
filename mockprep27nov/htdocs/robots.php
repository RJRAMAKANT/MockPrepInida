<?php
require __DIR__.'/lib.php';header('Content-Type: text/plain');
echo "User-agent: *\nDisallow: /teacher/\nDisallow: /dashboard\nDisallow: /test\nDisallow: /submit\nDisallow: /review\nDisallow: /login\nDisallow: /verify\nAllow: /\n\nSitemap: ".baseUrl()."/sitemap.xml\n";
