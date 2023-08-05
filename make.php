<?php
require 'src/Blacklist.php';
require 'src/Shared.php';
require 'src/Cache.php';

// Start the program
$blacklist = new Blacklist();
$blacklist->init();

$cache = new Cache();
$cache->init();

while (true) {
    echo shell_exec('php run.php');
    echo "\n\n\nFINISCHED\n\n\n";
}