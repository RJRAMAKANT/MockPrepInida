
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/lib.php';

echo "<pre>";

echo "1. BASE URL:\n";
var_dump(baseUrl());

echo "\n2. PU FUNCTION:\n";
var_dump(pu('about'));

echo "\n3. EXAMS FUNCTION:\n";
var_dump(exams());

echo "\n4. PUX FUNCTION:\n";

$examList = exams();

foreach ($examList as $key => $exam) {
    var_dump(pux($key));
}

echo "\nALL TESTS COMPLETED";

echo "</pre>";
