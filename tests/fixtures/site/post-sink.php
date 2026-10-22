<?php

// Records every request that reaches it. Integration tests assert that nothing but GET/HEAD
// arrives here, i.e. that wcag-scout never sends form data or other writes to a site.
$log = getenv('WCAG_SCOUT_SINK_LOG') ?: sys_get_temp_dir() . '/wcag-scout-sink.log';
file_put_contents($log, $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND | LOCK_EX);

header('Content-Type: text/plain');
echo 'recorded';
