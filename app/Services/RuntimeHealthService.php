<?php
namespace App\Services;

/** Read-only, bounded evidence for the native product's declared runtime requirements. */
final class RuntimeHealthService
{
    public const MIN_PHP = '8.3.0';
    public const MIN_MYSQL = '8.4.0';
    public const REQUIRED_EXTENSIONS = ['curl','json','mbstring','pdo_mysql','openssl'];

    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function collect(): array
    {
        $database = null;
        try {
            $rows = $this->store->query('SELECT VERSION() AS version, @@version_comment AS version_comment');
            if (isset($rows[0]) && is_array($rows[0])) $database = $rows[0];
        } catch (\Throwable) {
            // Raw transport/SQL exception text may contain a host or credential.
            // An unavailable source is represented explicitly, never as a healthy database.
        }
        $result = self::assess(PHP_VERSION, $database, get_loaded_extensions(), PHP_SAPI);
        $result['observed_at'] = gmdate('c');
        return $result;
    }

    public static function assess(string $phpVersion, ?array $database, array $extensions, string $sapi): array
    {
        $php = self::numericVersion($phpVersion);
        $phpPrerelease = (bool)preg_match('/(?:alpha|beta|RC|dev)/i', $phpVersion);
        $phpOk = $php !== null && version_compare($phpVersion, self::MIN_PHP, '>=') && !$phpPrerelease;
        $raw = is_string($database['version'] ?? null) ? $database['version'] : '';
        $comment = is_string($database['version_comment'] ?? null) ? $database['version_comment'] : '';
        $identity = strtolower(substr($raw.' '.$comment, 0, 300));
        $engine = match (true) {
            str_contains($identity, 'mariadb') => 'mariadb',
            (bool)preg_match('/percona|aurora|tidb|vitess|oceanbase/', $identity) => 'other',
            (bool)preg_match('/\bmysql\b/', $identity) => 'mysql',
            default => 'unknown',
        };
        $dbVersion = self::numericVersion($raw);
        if ($engine === 'mariadb' && preg_match('/(\d{1,2}\.\d{1,3}\.\d{1,3})-MariaDB/i', $raw, $match)) $dbVersion = $match[1];
        $databaseStatus = match (true) {
            $database === null || $dbVersion === null => 'unavailable',
            $engine === 'unknown' => 'engine_unknown',
            $engine !== 'mysql' => 'engine_mismatch',
            (bool)preg_match('/(?:alpha|beta|RC|dev)/i', $raw) => 'unsupported_prerelease',
            version_compare($dbVersion, self::MIN_MYSQL, '<') => 'below_minimum',
            default => 'meets_requirement',
        };
        $present = array_map('strtolower', array_values(array_filter($extensions, 'is_string')));
        $missing = array_values(array_diff(self::REQUIRED_EXTENSIONS, $present));
        return [
            'schema_version' => 1,
            'requirements_met' => $phpOk && $databaseStatus === 'meets_requirement' && $missing === [],
            'php' => [
                'version' => $php ?? 'unknown', 'minimum' => self::MIN_PHP,
                'status' => $php === null ? 'unavailable' : ($phpPrerelease ? 'unsupported_prerelease' : ($phpOk ? 'meets_requirement' : 'below_minimum')),
                'sapi' => in_array($sapi, ['cli','cli-server','fpm-fcgi','apache2handler','litespeed','cgi-fcgi'], true) ? $sapi : 'other',
                'scope' => 'current_php_process',
            ],
            'database' => [
                'engine' => $engine, 'version' => $dbVersion ?? 'unknown',
                'required_engine' => 'mysql', 'minimum' => self::MIN_MYSQL,
                'status' => $databaseStatus, 'scope' => 'configured_business_database',
            ],
            'extensions' => [
                'required' => self::REQUIRED_EXTENSIONS, 'missing' => $missing,
                'status' => $missing === [] ? 'meets_requirement' : 'missing_required_extensions',
            ],
            'limits' => [
                'read_only' => true, 'migrations_performed' => false,
                'database_version_is_not_backup_or_query_parity_proof' => true,
                'requirements_not_met_is_not_an_outage_claim' => true,
            ],
        ];
    }

    private static function numericVersion(string $value): ?string
    {
        if (strlen($value) > 128 || !preg_match('/^(\d{1,2}\.\d{1,3}\.\d{1,3})(?:[-+.a-zA-Z0-9_]*)$/D', $value, $match)) return null;
        return $match[1];
    }
}
