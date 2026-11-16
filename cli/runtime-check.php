<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args = array_slice($argv, 1);
if (in_array('--help', $args, true)) {
    echo "Usage: ./bapXphp runtime:check [--json] [--strict]\n"
        . "Read-only current PHP and configured database requirements. No data/configuration is changed.\n"
        . "--strict exits 1 when any requirement is unmet or unavailable; default reports without gating.\n";
    exit(0);
}
if (array_diff($args, ['--json','--strict']) !== []) {
    fwrite(STDERR, "Unknown option. Use runtime:check --help.\n"); exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';
$report = (new App\Services\RuntimeHealthService())->collect();
if (in_array('--json', $args, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), "\n";
} else {
    echo "Native runtime requirements: ", $report['requirements_met'] ? 'MET' : 'NOT MET / UNAVAILABLE', "\n";
    echo 'PHP '.$report['php']['version'].' ('.$report['php']['sapi'].') — requires '.$report['php']['minimum']."+\n";
    echo 'Database '.$report['database']['engine'].' '.$report['database']['version'].' — requires MySQL '.$report['database']['minimum']."+\n";
    echo 'Missing required extensions: '.($report['extensions']['missing'] === [] ? 'none' : implode(', ', $report['extensions']['missing']))."\n";
    echo "Current process only; CLI PHP can differ from web PHP. No migration or data change was performed.\n";
}
exit(in_array('--strict', $args, true) && !$report['requirements_met'] ? 1 : 0);
