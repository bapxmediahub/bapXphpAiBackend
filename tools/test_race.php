<?php
$file = __DIR__ . '/../storage/data/test_race.json';
$data = json_decode(file_get_contents($file), true);
$val = $data['counter'];
echo "Read value: $val\n";
sleep(1); // Simulate processing time
$data['counter'] = $val + 1;
file_put_contents($file, json_encode($data));
echo "Wrote value: " . $data['counter'] . "\n";
