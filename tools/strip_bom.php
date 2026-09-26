<?php
$root = realpath(__DIR__ . '/../');
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$count = 0;
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if ($file->getExtension() !== 'php') continue;
    $p = $file->getPathname();
    $s = file_get_contents($p);
    // remove UTF-8 BOM
    $ns = preg_replace('/^\x{FEFF}/u', '', $s);
    if ($ns !== $s) {
        file_put_contents($p, $ns);
        echo "stripped BOM: " . substr($p, strlen($root) + 1) . "\n";
        $count++;
    }
}
if ($count === 0) {
    echo "No BOMs found.\n";
} else {
    echo "Stripped BOMs: $count files.\n";
}
