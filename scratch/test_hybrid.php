<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers.php';

$t = new App\Services\ManoboHybridTranslator();
$title = "Barangay Bayogo Inter-Purok Basketball League 2026";
$body = "Opisyal sa kasim ang taunang Inter-Purok Basketball Tournament to ande maglalaban-laban ang mga purok.";

echo "--- TITLE --- \n";
print_r($t->translate($title, 'fil'));

echo "\n--- BODY --- \n";
print_r($t->translate($body, 'fil'));
