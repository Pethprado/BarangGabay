<?php
require __DIR__ . '/../app/bootstrap.php';

echo "base_url() : " . base_url() . "\n";
echo "app_url()  : " . app_url() . "\n";
echo "route('')  : " . route('') . "\n";
echo "route('login') : " . route('login') . "\n";
echo "route('admin') : " . route('admin') . "\n";
echo "asset('assets/css/main.css') : " . asset('assets/css/main.css') . "\n";