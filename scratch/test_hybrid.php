<?php
require 'app/bootstrap.php';
$t = new \App\Services\ManoboHybridTranslator(db());
$res = $t->translate('BABALA: Malakas na ulan at baha sa Barangay Bayogo', 'fil');
echo "Translated: " . ($res['translation'] ?? '') . "\n";
echo "Manobo matches: " . ($res['manoboMatches'] ?? 0) . "\n";
echo "Bisaya matches: " . ($res['bisayaFallbacks'] ?? 0) . "\n";

