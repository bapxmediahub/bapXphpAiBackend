<?php
/**
 * Browser Agent — PHP-native browser automation tool
 *
 * HTTP mode: cURL + DOMDocument (pure PHP, shared hosting).
 *
 * Usage: php cli/browser-agent.php <command> [args...]
 *
 * Session persisted to .agents/temp/browser-session.json between CLI calls.
 * Snapshot = AI-readable YAML page structure.
 *
 * ── Core ──────────────────────────────────────────────────────
 *   open/goto <url>     fetch page, YAML snapshot
 *   click/dblclick <sel> follow link or submit button
 *   hover <sel>          show element attributes
 *   fill <sel> <text>   store form field value
 *   submit [url]        POST form, snapshot result
 *   snapshot            YAML page structure (AI-readable)
 *   screenshot [file]   YAML snapshot
 *   html                raw HTML
 *   type <text>         store typed text
 *   press <key>         Enter = submit
 *
 * ── Navigation ────────────────────────────────────────────────
 *   go-back / go-forward / reload
 *
 * ── Smoke / Debug ─────────────────────────────────────────────
 *   smoke <url>         HTTP, links, image smoke test
 *   console <url>       HTTP metadata
 *   close               reset session
 */

require __DIR__ . '/../app/bootstrap.php';

define('SESS_FILE', __DIR__ . '/../.agents/temp/browser-session.json');
define('COOKIE_JAR', __DIR__ . '/../.agents/temp/browser-cookies.txt');
define('CONFIG_FILE', __DIR__ . '/../.agents/temp/browser-config.json');
define('LOG_FILE', __DIR__ . '/../.agents/temp/browser-audit.log');

// Local binaries (Linux x86_64 for cPanel production)
define('CHROME_BIN', __DIR__ . '/../storage/.bin/chrome-linux/chrome');
define('CHROME_LAUNCHER', __DIR__ . '/../.bin/launch-chrome.sh');
define('KITTENTTS_MODEL', __DIR__ . '/../storage/.bin/kitten-tts/model_quantized.onnx');

const USER_AGENTS = [
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Safari/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:135.0) Gecko/20100101 Firefox/135.0',
];

function config_load(): array {
    if (is_file(CONFIG_FILE)) {
        $d = json_decode(file_get_contents(CONFIG_FILE), true);
        if ($d) return $d;
    }
    return ['request_delay_ms'=>0,'timeout'=>30,'connect_timeout'=>10,'tracing'=>true,'cdp_ws'=>''];
}

function config_save(array $c): void {
    file_put_contents(CONFIG_FILE, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

function log_event(string $event, string $detail = ''): void {
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $line = date('Y-m-d\TH:i:s\Z') . " {$event}" . ($detail ? " {$detail}" : '') . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function req_headers(): array {
    $cfg = config_load();
    $ua = USER_AGENTS[array_rand(USER_AGENTS)];
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
    ];
    if ($cfg['tracing'] ?? false) {
        $rid = bin2hex(random_bytes(8));
        $headers[] = "X-Request-Id: ba-{$rid}";
        $headers[] = "X-Browser-Agent: bapXphp/1.0";
    }
    return [$ua, $headers];
}

function req_delay(): void {
    $cfg = config_load();
    $ms = $cfg['request_delay_ms'] ?? 0;
    if ($ms > 0) usleep($ms * 1000);
}

function sess_load(): array {
    if (is_file(SESS_FILE)) {
        $d = json_decode(file_get_contents(SESS_FILE), true);
        if ($d && isset($d['url'])) return $d;
    }
    return ['url'=>null,'html'=>null,'dom'=>null,'form_data'=>[],'history'=>[],'history_pos'=>-1,'cookies'=>[],'trace'=>[]];
}

function sess_save(array $s): void {
    $dir = dirname(SESS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $save = $s;
    unset($save['dom']); // DOMDocument not serializable
    file_put_contents(SESS_FILE, json_encode($save, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

function cookie_init(): void {
    $dir = dirname(COOKIE_JAR);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (!is_file(COOKIE_JAR)) file_put_contents(COOKIE_JAR, '');
}

function http_get(string $url, array &$s, bool $store = true, int $retries = 2): string {
    cookie_init();
    req_delay();
    $cfg = config_load();
    $timeout = $cfg['timeout'] ?? 30;
    $connect_timeout = $cfg['connect_timeout'] ?? 10;
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        [$ua, $hdrs] = req_headers();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connect_timeout,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_COOKIEFILE => COOKIE_JAR,
            CURLOPT_COOKIEJAR => COOKIE_JAR,
            CURLOPT_HTTPHEADER => $hdrs,
        ]);
        $start = microtime(true);
        $html = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $elapsed = round((microtime(true) - $start) * 1000);
        $http_code = $info['http_code'] ?? 0;
        log_event("GET {$http_code}", "{$url} ({$elapsed}ms) attempt={$attempt}");
        if ($html !== false && $http_code >= 200 && $http_code < 500) {
            $s['url'] = $info['url'];
            $s['html'] = $html;
            $dom = new DOMDocument(); libxml_use_internal_errors(true); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); libxml_clear_errors();
            $s['dom'] = $dom;
            if ($store) {
                $s['history'] = array_slice($s['history'], 0, $s['history_pos'] + 1);
                $s['history'][] = $info['url'];
                $s['history_pos'] = count($s['history']) - 1;
            }
            return $html;
        }
        if ($attempt < $retries) {
            $backoff = $attempt * 1000000;
            usleep($backoff);
        }
    }
    fwrite(STDERR, "Error after {$retries} attempts: {$error} (HTTP {$http_code})\n");
    exit(1);
}

// ── CDP (Chrome DevTools Protocol) Client ──────────────────────
function cdp_connect(string $ws_url): ?array {
    $ctx = stream_context_create(['socket'=>['connect_timeout'=>10]]);
    $fp = @stream_socket_client($ws_url, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { fwrite(STDERR, "CDP connect failed: {$errstr} ({$errno})\n"); return null; }
    stream_set_blocking($fp, true);
    $key = base64_encode(random_bytes(16));
    $req = "GET / HTTP/1.1\r\nHost: " . parse_url($ws_url, PHP_URL_HOST) . "\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n\r\n";
    fwrite($fp, $req);
    $resp = '';
    while (!feof($fp)) { $resp .= fread($fp, 1024); if (str_contains($resp, "\r\n\r\n")) break; }
    if (!str_contains($resp, '101 Switching Protocols')) { fwrite(STDERR, "CDP handshake failed\n"); fclose($fp); return null; }
    return ['fp'=>$fp, 'id'=>1];
}

function cdp_send(array $conn, string $method, array $params = []): ?array {
    $msg = json_encode(['id'=>$conn['id']++,'method'=>$method,'params'=>$params]);
    $frame = chr(0x81) . chr(strlen($msg)) . $msg;
    fwrite($conn['fp'], $frame);
    $buf = '';
    while (!feof($conn['fp'])) {
        $buf .= fread($conn['fp'], 1024);
        if (strlen($buf) >= 2) {
            $len = ord($buf[1]) & 127;
            if (strlen($buf) >= 2 + $len) break;
        }
    }
    if ($buf === '') return null;
    $payload = substr($buf, 2);
    return json_decode($payload, true);
}

function cdp_close(array $conn): void {
    fclose($conn['fp']);
}

// ── API Mode (remote API endpoints) ───────────────────────────────
function api_request(string $base, string $cmd, array $args): void {
    $cfg = config_load();
    $token = $cfg['api_token'] ?? '';
    
    $endpoints = [
        'search'    => ['POST', 'search', ['query', 'engine']],
        'open'      => ['POST', 'open', ['url']],
        'click'     => ['POST', 'click', ['selector']],
        'fill'      => ['POST', 'fill', ['selector', 'text']],
        'snapshot'  => ['POST', 'snapshot', ['max_elements', 'max_depth']],
        'links'     => ['POST', 'links', []],
        'forms'     => ['POST', 'forms', []],
        'captcha'   => ['POST', 'captcha', []],
        'smoke'     => ['POST', 'smoke', ['url']],
        'cdp'       => ['POST', 'cdp', ['ws_url', 'method', 'params']],
        'cdp_launch'=> ['POST', 'cdp_launch', ['port', 'headless', 'no_sandbox']],
        'status'    => ['GET', 'status', []],
    ];
    
    if (!isset($endpoints[$cmd])) {
        fwrite(STDERR, "Unknown API command: $cmd\n");
        exit(1);
    }
    
    [$method, $endpoint, $argNames] = $endpoints[$cmd];
    $url = rtrim($base, '/') . '/' . $endpoint;
    
    $data = [];
    foreach ($argNames as $i => $name) {
        $data[$name] = $args[$i] ?? '';
    }
    // Handle boolean args
    foreach (['headless', 'no_sandbox'] as $boolArg) {
        if (isset($data[$boolArg]) && in_array(strtolower($data[$boolArg]), ['true', '1', 'yes'], true)) {
            $data[$boolArg] = true;
        } elseif (isset($data[$boolArg])) {
            $data[$boolArg] = false;
        }
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $token ? ['Authorization: Bearer ' . $token] : []),
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($resp === false) {
        fwrite(STDERR, "API request failed\n");
        exit(1);
    }
    
    $result = json_decode($resp, true);
    if ($code >= 400) {
        fwrite(STDERR, "API error ($code): " . ($result['error'] ?? $resp) . "\n");
        exit(1);
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function api_config(string $key, string $value = ''): void {
    $cfg = config_load();
    if ($value !== '') {
        $cfg[$key] = $value;
        config_save($cfg);
        echo "$key = $value\n";
    } else {
        echo ($cfg[$key] ?? '') . "\n";
    }
}

// ── CDP Commands ────────────────────────────────────────────────
function cdp_cmd(array $args): void {
    $cfg = config_load();
    $ws = $args[0] ?? ($cfg['cdp_ws'] ?? '');
    if (!$ws) { fwrite(STDERR, "Usage: browser-agent cdp <ws_url|command> [args...]\nSet default: browser-agent config set cdp_ws ws://host:port/devtools/browser/xxx\n"); exit(1); }
    if (str_starts_with($ws, 'ws')) {
        $conn = cdp_connect($ws);
        if (!$conn) exit(1);
        $cmd = $args[1] ?? 'version';
        $out = cdp_send($conn, $cmd, array_slice($args, 2));
        echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
        cdp_close($conn);
    } else {
        $conn = cdp_connect($cfg['cdp_ws'] ?? '');
        if (!$conn) exit(1);
        $out = cdp_send($conn, $ws, array_slice($args, 1));
        echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
        cdp_close($conn);
    }
}

// ── CDP Launch (start local Chrome binary in background) ───────────
function cdp_launch(array $args): void {
    $binDir = __DIR__ . '/../.bin/chrome-linux';
    $chrome = $binDir . '/chrome';
    if (!is_file($chrome) || !is_executable($chrome)) {
        fwrite(STDERR, "Chrome binary not found at $chrome\nRun php .bin/download-chrome.php to install\n");
        exit(1);
    }

    // Verify binary can run on this architecture
    $test = @shell_exec("$chrome --version 2>&1");
    if ($test === false || $test === '' || str_contains($test, 'cannot execute') || str_contains($test, 'Exec format error')) {
        fwrite(STDERR, "Chrome binary is Linux x86_64 only — won't run on this machine (macOS ARM64).\n");
        fwrite(STDERR, "For local testing, run Chrome on a Linux x86_64 server and use remote CDP:\n");
        fwrite(STDERR, "  .bin/launch-chrome.sh --port=9222  # on Linux server\n");
        fwrite(STDERR, "  browser-agent config set cdp_ws ws://your-server:9222/devtools/browser/\n");
        fwrite(STDERR, "  browser-agent cdp Target.getTargets\n");
        exit(1);
    }

    $port = 9222;
    $userDataDir = null;
    $headless = true;
    $noSandbox = true;

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--port=')) $port = (int) substr($arg, 7);
        elseif (str_starts_with($arg, '--user-data-dir=')) $userDataDir = substr($arg, 16);
        elseif ($arg === '--headless=false') $headless = false;
        elseif ($arg === '--no-sandbox=false') $noSandbox = false;
    }

    $cmd = [$chrome];
    if ($headless) $cmd[] = '--headless=new';
    if ($noSandbox) $cmd[] = '--no-sandbox';
    $cmd[] = '--disable-gpu';
    $cmd[] = '--disable-dev-shm-usage';
    $cmd[] = '--remote-debugging-port=' . $port;
    $cmd[] = '--remote-debugging-address=0.0.0.0';
    $cmd[] = '--user-data-dir=' . ($userDataDir ?: sys_get_temp_dir() . '/chrome-cdp-' . uniqid());

    // Start Chrome in background
    $cmdStr = implode(' ', array_map('escapeshellarg', $cmd)) . ' > /dev/null 2>&1 &';
    $pid = shell_exec($cmdStr);
    if (!$pid) { fwrite(STDERR, "Failed to start Chrome\n"); exit(1); }

    echo "Started Chrome on port $port (PID: $pid)\n";
    echo "CDP endpoint: ws://localhost:$port/devtools/browser/\n";
    echo "Use 'browser-agent cdp Target.getTargets' to connect\n";
}

function text_of(DOMNode $node): string {
    $t = '';
    foreach ($node->childNodes as $c) {
        if ($c instanceof DOMText) $t .= $c->wholeText;
        elseif ($c instanceof DOMElement) $t .= ' ' . text_of($c);
    }
    return trim(preg_replace('/\s+/', ' ', $t));
}

function snapshot_yaml(array &$s, int $max_e = 500, int $max_d = 10, ?string $root_ref = null): string {
    if (!$s['dom']) return "error: no page loaded\n";
    $title = '';
    $t = $s['dom']->getElementsByTagName('title');
    if ($t->length > 0) $title = trim(str_replace("\n", ' ', $t->item(0)->textContent));
    $y = "page_url: {$s['url']}\npage_title: {$title}\n";
    if ($root_ref) $y .= "refocus: {$root_ref}\n";
    $y .= "\n";

    // Resolve root node: body, or drill into a specific ref
    $body = $s['dom']->getElementsByTagName('body')->item(0);
    if (!$body) { $y .= "elements: []\n"; return $y; }

    $root_node = $body;
    if ($root_ref) {
        $idx = (int) ltrim($root_ref, 'e');
        // Walk preserving DOMDocument tree order (stops libxml encoding issues)
        $dom_refs = [];
        $ri = 0;
        $w2 = function(DOMElement $n) use (&$w2, &$dom_refs, &$ri, $max_e) {
            if ($ri > $max_e) return;
            foreach ($n->childNodes as $c) {
                if ($ri > $max_e) return;
                if (!$c instanceof DOMElement) continue;
                $tag = strtolower($c->tagName);
                if (in_array($tag, ['script','style','noscript','iframe','svg'], true)) continue;
                $ri++;
                $dom_refs[$ri] = $c;
                $w2($c);
            }
        };
        $w2($body);
        if (isset($dom_refs[$idx])) {
            $root_node = $dom_refs[$idx];
        }
    }

    $ref = 0;
    $walk = function(DOMElement $n, int $d, ?string $pref) use (&$walk, &$ref, $max_d, $max_e): array {
        if ($d > $max_d || $ref >= $max_e) return [];
        $elems = [];
        foreach ($n->childNodes as $c) {
            if ($ref >= $max_e) break;
            if (!$c instanceof DOMElement) continue;
            $tag = strtolower($c->tagName);
            if (in_array($tag, ['script','style','noscript','iframe','svg'], true)) continue;
            $ref++;
            $rid = "e{$ref}";
            $e = ['ref' => $rid, 'tag' => $tag];
            if ($pref) $e['p'] = $pref;

            $txt = text_of($c);
            $txt = mb_substr($txt, 0, 200);
            if ($txt !== '') $e['text'] = $txt;

            $attr_map = ['id'=>'id','role'=>'role','href'=>'url','src'=>'src','alt'=>'alt','name'=>'name','type'=>'type','class'=>'class','method'=>'method','action'=>'action','data-testid'=>'data-testid','aria-label'=>'aria-label','placeholder'=>'placeholder','title'=>'title','for'=>'for'];
            foreach ($attr_map as $a => $k) {
                $v = $c->getAttribute($a);
                if ($v !== '') $e[$k] = mb_substr($v, 0, 120);
            }

            $val = $c->getAttribute('value');
            if ($val !== '' && in_array($tag, ['input','button','option','textarea'], true)) $e['value'] = mb_substr($val, 0, 80);

            if ($tag === 'input') {
                $inp_type = strtolower($c->getAttribute('type') ?: 'text');
                if (in_array($inp_type, ['checkbox','radio'], true)) {
                    $chk = $c->getAttribute('checked');
                    $e['checked'] = ($chk !== null && $chk !== '') ? true : false;
                }
            }
            if ($tag === 'option') {
                $sel = $c->getAttribute('selected');
                $e['selected'] = ($sel !== null && $sel !== '') ? true : false;
            }

            $children = $walk($c, $d + 1, $rid);
            if ($children) $e['children'] = $children;
            $elems[] = $e;
        }
        return $elems;
    };

    $top = $walk($root_node, 0, null);
    if ($root_ref) {
        // Wrap in a single element to show context
        $wrapper = ['tag' => 'body', 'ref' => $root_ref, 'children' => $top];
        $y .= "elements:\n" . arr_yaml([$wrapper], 0);
    } else {
        $y .= "elements:\n" . arr_yaml($top, 0);
    }
    $y .= "\n({$ref} elements shown, use --max-e=N for more, --ref=eN for drill-down)\n";
    return $y;
}

function arr_yaml(array $items, int $indent = 0): string {
    $y = ''; $p = str_repeat('  ', $indent);
    foreach ($items as $item) {
        $tag = $item['tag']; $txt = $item['text'] ?? ''; $rid = $item['ref'] ?? '';
        $y .= "{$p}- {$tag}";
        if ($rid) $y .= " [{$rid}]";
        if (isset($item['p'])) $y .= " p:{$item['p']}";
        if ($txt) $y .= " \"" . str_replace('"', '\"', $txt) . '"';
        $y .= "\n";
        $attrs = ['id','role','url','src','alt','name','type','value','class','data-testid','aria-label','placeholder','title','for'];
        foreach ($attrs as $a) {
            if (isset($item[$a])) $y .= "{$p}  {$a}: {$item[$a]}\n";
        }
        if (isset($item['checked'])) $y .= "{$p}  checked: " . ($item['checked'] ? 'true' : 'false') . "\n";
        if (isset($item['selected'])) $y .= "{$p}  selected: " . ($item['selected'] ? 'true' : 'false') . "\n";
        if (isset($item['children'])) $y .= arr_yaml($item['children'], $indent + 1);
    }
    return $y;
}

function css2xpath(string $css): string {
    $css = trim($css);
    if (preg_match('/^#([\w-]+)$/', $css, $m)) return "//*[@id='{$m[1]}']";
    if (preg_match('/^\.([\w-]+)$/', $css, $m)) return "//*[contains(concat(' ',normalize-space(@class),' '),' {$m[1]} ')]";
    if (preg_match('/^(\w+)\.([\w-]+)$/', $css, $m)) return "//{$m[1]}[contains(concat(' ',normalize-space(@class),' '),' {$m[2]} ')]";
    if (preg_match('/^(\w+)#([\w-]+)$/', $css, $m)) return "//{$m[1]}[@id='{$m[2]}']";
    if (preg_match('/^(\w+)\[([\w-]+)=["\']?([^"\'\]]+)["\']?\]$/', $css, $m)) return "//{$m[1]}[@{$m[2]}='{$m[3]}']";
    if (preg_match('/^\[([\w-]+)=["\']?([^"\'\]]+)["\']?\]$/', $css, $m)) return "//*[@{$m[1]}='{$m[2]}']";
    if (preg_match('/^[a-z][a-z0-9]*$/i', $css)) return "//{$css}";
    return "//{$css}";
}

function resolve_url(string $href, string $base): string {
    if (parse_url($href, PHP_URL_SCHEME)) return $href;
    if (str_starts_with($href, '//')) return 'https:' . $href;
    if (str_starts_with($href, '/')) { $p = parse_url($base); return ($p['scheme']??'https') . '://' . ($p['host']??'') . $href; }
    if (str_ends_with($base, '/')) return $base . ltrim($href, '/');
    return dirname($base) . '/' . ltrim($href, '/');
}

function find_elem(string $sel, DOMDocument $dom): ?DOMElement {
    $x = new DOMXPath($dom); $nodes = $x->query(css2xpath($sel));
    return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
}

function dom_from_session(array &$s): void {
    if (!isset($s['dom']) && $s['html']) {
        $dom = new DOMDocument(); libxml_use_internal_errors(true); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $s['html']); libxml_clear_errors();
        $s['dom'] = $dom;
    }
}

function detect_captcha(DOMDocument $dom, string $html): array {
    $result = ['detected' => false, 'type' => null, 'sitekey' => null, 'elements' => []];
    $xpath = new DOMXPath($dom);

    // reCAPTCHA v2/v3 — iframe, api.js, g-recaptcha div
    if (preg_match('/recaptcha\/api\.js/', $html) || preg_match('/g-recaptcha/', $html) || preg_match('/data-sitekey=["\']([^"\']+)["\']/', $html, $m)) {
        $result['detected'] = true;
        $result['type'] = 'recaptcha';
        if (!empty($m[1])) $result['sitekey'] = $m[1];
        if (preg_match_all('/data-sitekey=["\']([^"\']+)["\']/', $html, $mm)) $result['sitekey'] = $mm[1][0] ?? null;
        $nodes = $xpath->query("//*[contains(@class,'g-recaptcha')]");
        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $n) {
                $e = ['tag' => strtolower($n->tagName), 'ref' => ''];
                $sk = $n->getAttribute('data-sitekey');
                if ($sk) $e['sitekey'] = $sk;
                $result['elements'][] = $e;
            }
        }
    }

    // hCaptcha
    if (preg_match('/hcaptcha\.com\/1\.api\.js/', $html) || preg_match('/h-captcha/', $html)) {
        $result['detected'] = true;
        $result['type'] = 'hcaptcha';
        if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $html, $m)) $result['sitekey'] = $m[1];
        $nodes = $xpath->query("//*[contains(@class,'h-captcha')]");
        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $n) {
                $e = ['tag' => strtolower($n->tagName), 'ref' => ''];
                $sk = $n->getAttribute('data-sitekey');
                if ($sk) $e['sitekey'] = $sk;
                $result['elements'][] = $e;
            }
        }
    }

    // Cloudflare Turnstile
    if (preg_match('/turnstile/', $html) && preg_match('/challenges\.cloudflare\.com/', $html)) {
        $result['detected'] = true;
        if (!$result['type']) $result['type'] = 'turnstile';
        if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $html, $m)) $result['sitekey'] = $m[1];
    }

    // Text / image captcha in forms
    $captcha_inputs = $xpath->query("//input[contains(translate(@name,'C','c'),'captcha') or contains(translate(@id,'C','c'),'captcha') or contains(translate(@placeholder,'C','c'),'captcha')]");
    if ($captcha_inputs && $captcha_inputs->length > 0) {
        $result['detected'] = true;
        if (!$result['type']) $result['type'] = 'text_captcha';
        foreach ($captcha_inputs as $inp) {
            $e = ['tag' => 'input', 'ref' => '', 'name' => $inp->getAttribute('name'), 'id' => $inp->getAttribute('id')];
            $result['elements'][] = $e;
        }
    }

    if ($result['detected'] && !$result['type']) $result['type'] = 'unknown';
    return $result;
}

function parse_ddg_results(DOMDocument $dom): array {
    $results = [];
    $xpath = new DOMXPath($dom);
    // DDG HTML: h2.result__title > a.result__a with href=//duckduckgo.com/l/?uddg=<encoded>
    // Each result is in div.result > div.result__body
    $headings = $xpath->query("//h2[contains(@class,'result__title')]");
    foreach ($headings as $h2) {
        $link = $xpath->query(".//a[contains(@class,'result__a')]", $h2)->item(0);
        if (!$link) continue;
        $href = $link->getAttribute('href');
        if (!$href) continue;
        // Decode DDG redirect URL
        if (preg_match('/uddg=([^&]+)/', $href, $m)) $href = urldecode($m[1]);
        $title = trim($link->textContent);
        if (!$title) continue;

        // Snippet is in a sibling div.result__snippet
        $snippet = '';
        $body = $h2->parentNode;
        while ($body && !str_contains($body->getAttribute('class') ?? '', 'result__body')) {
            $body = $body->parentNode;
        }
        if ($body) {
            $sn = $xpath->query(".//div[contains(@class,'result__snippet')]", $body)->item(0);
            if ($sn) $snippet = trim($sn->textContent);
        }

        $results[] = ['title' => $title, 'url' => $href, 'snippet' => mb_substr($snippet, 0, 300)];
    }
    return $results;
}

function parse_google_results(DOMDocument $dom, string $html): array {
    $results = [];
    $xpath = new DOMXPath($dom);

    // Try modern Google structure (2024+): h3 inside a link inside a div.g
    $h3_nodes = $xpath->query("//h3");
    foreach ($h3_nodes as $h3) {
        $link = null;
        // Walk up to find the enclosing <a>
        $p = $h3->parentNode;
        while ($p && $p->nodeType === XML_ELEMENT_NODE) {
            if (strtolower($p->tagName) === 'a' && $p->getAttribute('href')) {
                $link = $p;
                break;
            }
            $p = $p->parentNode;
        }
        if (!$link) {
            // Maybe h3 is inside a link
            $link = $h3->parentNode;
            while ($link && $link->nodeType === XML_ELEMENT_NODE && strtolower($link->tagName) !== 'a') {
                $link = $link->parentNode;
            }
            if (!$link || !$link->getAttribute('href') || !str_starts_with($link->getAttribute('href'), 'http')) {
                $link = null;
            }
        }
        if (!$link) continue;
        $href = $link->getAttribute('href');
        // Skip google internal links
        if (str_contains($href, '/search?') || str_contains($href, 'google.com/')) continue;
        $title = trim($h3->textContent);
        if (!$title) continue;

        // Get snippet: the next sibling div after the parent of h3
        $snippet = '';
        $parent = $link->parentNode;
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            $nxt = $parent->nextSibling;
            while ($nxt && $nxt->nodeType !== XML_ELEMENT_NODE) $nxt = $nxt->nextSibling;
            if ($nxt) {
                $spans = $nxt->getElementsByTagName('span');
                foreach ($spans as $sp) {
                    $st = trim($sp->textContent);
                    if (strlen($st) > 30 && !str_starts_with($st, 'http')) { $snippet = mb_substr($st, 0, 300); break; }
                }
            }
            // Also try div[data-sncf]
            if (!$snippet) {
                $divs = $parent->getElementsByTagName('div');
                foreach ($divs as $dv) {
                    if ($dv->getAttribute('data-sncf') !== '') {
                        $snippet = mb_substr(trim($dv->textContent), 0, 300);
                        break;
                    }
                }
            }
            break;
        }

        $results[] = ['title' => $title, 'url' => $href, 'snippet' => $snippet];
    }

    // Also try alternative selectors
    if (empty($results)) {
        // Fallback: look for div[class~="g"] with a <a> inside
        $divs = $xpath->query("//div[contains(@class,'g')]");
        foreach ($divs as $div) {
            $links = $div->getElementsByTagName('a');
            foreach ($links as $a) {
                $h = $a->getAttribute('href');
                if ($h && str_starts_with($h, 'http') && !str_contains($h, 'google.com/')) {
                    $h3s = $a->getElementsByTagName('h3');
                    if ($h3s->length > 0) {
                        $title = trim($h3s->item(0)->textContent);
                        $txt = mb_substr(trim($div->textContent), 0, 300);
                        $txt = str_replace($title, '', $txt);
                        $results[] = ['title' => $title, 'url' => $h, 'snippet' => trim($txt)];
                    }
                    break;
                }
            }
        }
    }

    return $results;
}

function extract_links(DOMDocument $dom, string $base_url): array {
    $links = [];
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//a[@href]");
    $host = parse_url($base_url, PHP_URL_HOST);
    foreach ($nodes as $n) {
        $href = $n->getAttribute('href');
        if (!$href || $href === '#' || str_starts_with($href, 'javascript:')) continue;
        $txt = trim(text_of($n));
        $abs = resolve_url($href, $base_url);
        $internal = $host && str_contains($abs, $host);
        $links[] = ['url' => $abs, 'text' => mb_substr($txt, 0, 120), 'internal' => $internal];
    }
    return $links;
}

function extract_forms(DOMDocument $dom): array {
    $forms = [];
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//form");
    foreach ($nodes as $f) {
        $form = [
            'action' => $f->getAttribute('action') ?: '(current)',
            'method' => strtoupper($f->getAttribute('method') ?: 'GET'),
            'id' => $f->getAttribute('id') ?: '',
            'fields' => [],
        ];
        $inputs = $f->getElementsByTagName('input');
        foreach ($inputs as $inp) {
            $t = strtolower($inp->getAttribute('type') ?: 'text');
            if (in_array($t, ['submit', 'button', 'hidden'])) continue;
            $form['fields'][] = [
                'name' => $inp->getAttribute('name'),
                'type' => $t,
                'placeholder' => $inp->getAttribute('placeholder') ?: '',
                'required' => $inp->hasAttribute('required'),
            ];
        }
        $textareas = $f->getElementsByTagName('textarea');
        foreach ($textareas as $ta) {
            $form['fields'][] = [
                'name' => $ta->getAttribute('name'),
                'type' => 'textarea',
                'placeholder' => $ta->getAttribute('placeholder') ?: '',
                'required' => $ta->hasAttribute('required'),
            ];
        }
        $selects = $f->getElementsByTagName('select');
        foreach ($selects as $sel) {
            $form['fields'][] = [
                'name' => $sel->getAttribute('name'),
                'type' => 'select',
                'placeholder' => '',
                'required' => $sel->hasAttribute('required'),
            ];
        }
        $forms[] = $form;
    }
    return $forms;
}

// ── Main ───────────────────────────────────────────────────────
$args = $argv; array_shift($args);
$apiMode = false;
if (($args[0] ?? '') === '--api') {
    $apiMode = true;
    array_shift($args);
}
$cmd = $args[0] ?? 'help';

try {
    if ($apiMode) {
        $apiBase = config_load()['api_base'] ?? 'http://localhost/api/browser';
        api_request($apiBase, $cmd, array_slice($args, 1));
        exit(0);
    }

    // HTTP mode — load session
    $session = sess_load();
    if (!empty($session['html'])) dom_from_session($session);

    switch ($cmd) {
        case 'cdp':
            cdp_cmd(array_slice($args, 1));
            break;

        case 'cdp_launch':
            cdp_launch(array_slice($args, 1));
            break;

        case 'open': case 'goto':
            $url = $args[1] ?? '';
            if (!$url) { fwrite(STDERR, "Usage: browser-agent open <url>\n"); exit(1); }
            http_get($url, $session);
            sess_save($session);
            echo snapshot_yaml($session);
            break;

        case 'click': case 'dblclick':
            $sel = $args[1] ?? '';
            if (!$sel) { fwrite(STDERR, "Usage: browser-agent click <selector>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded. Use 'open' first.\n"); exit(1); }
            $node = find_elem($sel, $session['dom']);
            if (!$node) { fwrite(STDERR, "Not found: $sel\n"); exit(1); }

            $href = $node->getAttribute('href');
            if ($href) {
                http_get(resolve_url($href, $session['url']), $session);
                sess_save($session);
                echo snapshot_yaml($session);
                break;
            }

            $btn_type = strtolower($node->getAttribute('type') ?: '');
            $tag_name = strtolower($node->tagName);
            $is_submit = ($btn_type === 'submit' || $tag_name === 'button');

            if ($is_submit) {
                // Look for parent form to get action URL
                $form = $node->parentNode;
                while ($form && $form->nodeType === XML_ELEMENT_NODE && strtolower($form->tagName) !== 'form') {
                    $form = $form->parentNode;
                }
                $action = '';
                if ($form) {
                    $action = $form->getAttribute('action');
                    $method = strtoupper($form->getAttribute('method') ?: 'GET');
                }
                $submit_url = $action ? resolve_url($action, $session['url']) : $session['url'];

                $form_data = [];
                // Collect all input values from the form
                if ($form) {
                    $inputs = $form->getElementsByTagName('input');
                    foreach ($inputs as $inp) {
                        $n = $inp->getAttribute('name');
                        if ($n) {
                            $t = strtolower($inp->getAttribute('type') ?: 'text');
                            if ($t === 'checkbox' || $t === 'radio') {
                                if ($inp->getAttribute('checked') !== null) $form_data[$n] = $inp->getAttribute('value') ?: 'on';
                            } else {
                                $form_data[$n] = $inp->getAttribute('value') ?: '';
                            }
                        }
                    }
                    $textareas = $form->getElementsByTagName('textarea');
                    foreach ($textareas as $ta) {
                        $n = $ta->getAttribute('name');
                        if ($n) $form_data[$n] = text_of($ta);
                    }
                    $selects = $form->getElementsByTagName('select');
                    foreach ($selects as $sel_el) {
                        $n = $sel_el->getAttribute('name');
                        if ($n) {
                            $opts = $sel_el->getElementsByTagName('option');
                            foreach ($opts as $opt) {
                                if ($opt->getAttribute('selected') !== null) {
                                    $form_data[$n] = $opt->getAttribute('value') ?: text_of($opt);
                                    break;
                                }
                            }
                        }
                    }
                }
                // Override with stored form_data
                foreach ($session['form_data'] as $k => $v) { $form_data[$k] = $v; }
                $name = $node->getAttribute('name') ?: '';
                $form_data[$name ?: '_submit'] = $node->getAttribute('value') ?: '1';

                if ($method === 'GET') {
                    $qs = http_build_query($form_data);
                    $sep = str_contains($submit_url, '?') ? '&' : '?';
                    http_get($submit_url . $sep . $qs, $session);
                } else {
                    req_delay();
                    [$ua, $hdrs] = req_headers();
                    $cfg = config_load();
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $submit_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($form_data),
                        CURLOPT_TIMEOUT => $cfg['timeout'] ?? 30,
                        CURLOPT_USERAGENT => $ua,
                        CURLOPT_HTTPHEADER => $hdrs,
                        CURLOPT_COOKIEFILE => COOKIE_JAR,
                        CURLOPT_COOKIEJAR => COOKIE_JAR,
                    ]);
                    $html = curl_exec($ch);
                    $session['url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                    $session['html'] = $html;
                    $dom = new DOMDocument(); libxml_use_internal_errors(true); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); libxml_clear_errors();
                    $session['dom'] = $dom;
                }
                $session['form_data'] = [];
                sess_save($session);
                echo snapshot_yaml($session);
                break;
            }

            fwrite(STDERR, "Element not clickable (no href, not a button): $sel\n");
            exit(1);

        case 'hover':
            $sel = $args[1] ?? '';
            if (!$sel) { fwrite(STDERR, "Usage: browser-agent hover <selector>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded.\n"); exit(1); }
            $node = find_elem($sel, $session['dom']);
            if (!$node) { fwrite(STDERR, "Not found: $sel\n"); exit(1); }
            $tag = strtolower($node->tagName); $txt = text_of($node); $href = $node->getAttribute('href');
            echo "<{$tag}>" . ($txt ? " \"{$txt}\"" : '') . "\n";
            if ($href) echo "  href: {$href}\n";
            foreach (['id'=>'id','src'=>'src','alt'=>'alt','name'=>'name','type'=>'type','class'=>'class','title'=>'title','role'=>'role','data-testid'=>'data-testid','aria-label'=>'aria-label','placeholder'=>'placeholder'] as $a=>$k) {
                $v = $node->getAttribute($a);
                if ($v !== '') echo "  {$k}: {$v}\n";
            }
            break;

        case 'fill':
            $sel = $args[1] ?? ''; $val = $args[2] ?? '';
            if (!$sel || !$val) { fwrite(STDERR, "Usage: browser-agent fill <selector> <text>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded.\n"); exit(1); }
            $node = find_elem($sel, $session['dom']);
            if (!$node) { fwrite(STDERR, "Not found: $sel\n"); exit(1); }
            $name = $node->getAttribute('name') ?: $sel;
            $session['form_data'][$name] = $val;
            sess_save($session);
            echo "Set {$name} = {$val}\n";
            break;

        case 'type':
            $text = $args[1] ?? '';
            if (!$text) { fwrite(STDERR, "Usage: browser-agent type <text>\n"); exit(1); }
            $session['form_data']['_typed'] = $text;
            sess_save($session);
            echo "Typed: {$text}\n";
            break;

        case 'press':
            $key = $args[1] ?? '';
            if (!$key) { fwrite(STDERR, "Usage: browser-agent press <key>\n"); exit(1); }
            if (strtolower($key) === 'enter') {
                if (!$session['url']) { fwrite(STDERR, "No page to submit to.\n"); exit(1); }
                goto submit_form;
            }
            echo "Key stored: {$key}\n";
            break;

        case 'submit': submit_form:
            $url = $args[1] ?? $session['url'] ?? '';
            if (!$url) { fwrite(STDERR, "No URL to submit to.\n"); exit(1); }
            req_delay();
            [$ua, $hdrs] = req_headers();
            $cfg = config_load();
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($session['form_data']),
                CURLOPT_TIMEOUT => $cfg['timeout'] ?? 30,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => $hdrs,
                CURLOPT_COOKIEFILE => COOKIE_JAR,
                CURLOPT_COOKIEJAR => COOKIE_JAR,
            ]);
            $html = curl_exec($ch);
            $session['url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $session['html'] = $html;
            $dom = new DOMDocument(); libxml_use_internal_errors(true); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); libxml_clear_errors();
            $session['dom'] = $dom;
            $session['form_data'] = [];
            sess_save($session);
            echo snapshot_yaml($session);
            break;

        case 'search':
            $query = implode(' ', array_slice($args, 1));
            if (!$query) { fwrite(STDERR, "Usage: browser-agent search <query>\n"); exit(1); }
            $engine = 'ddg'; // duckduckgo is most bot-friendly
            $parsed = [];
            // Parse --engine=flag
            foreach (array_slice($args, 1) as $a) {
                if (str_starts_with($a, '--engine=')) {
                    $engine = substr($a, 9);
                    // Remove from query
                    $query = str_replace($a, '', $query);
                    $query = preg_replace('/\s+/', ' ', trim($query));
                }
            }

            if ($engine === 'google') {
                // Try no-JS Google
                $tlds = ['com', 'co.uk', 'ca', 'com.au'];
                $url = 'https://www.google.' . $tlds[array_rand($tlds)] . '/search?q=' . urlencode($query) . '&hl=en&gbv=1';
                http_get($url, $session);
                $html = $session['html'];
                if (preg_match('/<meta\s+http-equiv="refresh"[^>]*url=([^"\s>]+)/i', $html, $m)) {
                    http_get(resolve_url(htmlspecialchars_decode($m[1]), $session['url']), $session);
                }
                if (preg_match('/consent\.google/', $html)) {
                    http_get('https://www.google.com/search?q=' . urlencode($query) . '&hl=en&gbv=1&consent=ACCEPT', $session);
                }
                sess_save($session);
                $parsed = parse_google_results($session['dom'], $session['html']);
            } else {
                // DuckDuckGo HTML — most bot-friendly
                $url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
                http_get($url, $session);
                sess_save($session);
                $parsed = parse_ddg_results($session['dom']);
            }

            echo "search_query: {$query}\nengine: {$engine}\nsearch_results: " . count($parsed) . "\n\n";
            foreach ($parsed as $i => $r) {
                echo "  - result #" . ($i + 1) . "\n";
                echo "    title: \"" . str_replace('"', '\"', $r['title']) . "\"\n";
                echo "    url: {$r['url']}\n";
                if ($r['snippet']) echo "    snippet: \"" . str_replace('"', '\"', $r['snippet']) . "\"\n";
            }
            if (empty($parsed)) {
                $captcha = detect_captcha($session['dom'], $session['html']);
                if ($captcha['detected']) {
                    echo "  (search blocked: {$captcha['type']} detected)\n";
                } else {
                    echo "  (no results found)\n";
                }
            }
            break;

        case 'snapshot':
            if (!$session['dom']) { echo "error: no page loaded\n"; exit(1); }
            $max_e = 500; $max_d = 10; $root_ref = null; $out_file = null;
            foreach (array_slice($args, 1) as $a) {
                if (str_starts_with($a, '--max-e=')) $max_e = (int) substr($a, 8);
                elseif (str_starts_with($a, '--max-d=')) $max_d = (int) substr($a, 8);
                elseif (str_starts_with($a, '--ref=')) $root_ref = substr($a, 6);
                elseif (str_starts_with($a, '--output=')) $out_file = substr($a, 9);
                elseif (str_starts_with($a, '-o=')) $out_file = substr($a, 4);
            }
            $captcha = detect_captcha($session['dom'], $session['html']);
            $output = '';
            if ($captcha['detected']) {
                $output .= "captcha: {$captcha['type']}" . ($captcha['sitekey'] ? " (sitekey: {$captcha['sitekey']})" : '') . "\n\n";
            }
            $output .= snapshot_yaml($session, $max_e, $max_d, $root_ref);
            if ($out_file) {
                file_put_contents($out_file, $output);
                echo "Snapshot saved to {$out_file}\n";
            } else {
                echo $output;
            }
            break;

        case 'screenshot':
            if (!$session['dom']) { echo "error: no page loaded\n"; exit(1); }
            $max_e = 500; $max_d = 10; $root_ref = null;
            foreach (array_slice($args, 1) as $a) {
                if (str_starts_with($a, '--max-e=')) $max_e = (int) substr($a, 8);
                elseif (str_starts_with($a, '--max-d=')) $max_d = (int) substr($a, 8);
                elseif (str_starts_with($a, '--ref=')) $root_ref = substr($a, 6);
            }
            echo "# Screenshot (YAML snapshot — AI-readable)\n";
            $captcha = detect_captcha($session['dom'], $session['html']);
            if ($captcha['detected']) {
                echo "# captcha: {$captcha['type']}" . ($captcha['sitekey'] ? " ({$captcha['sitekey']})" : '') . "\n";
            }
            echo snapshot_yaml($session, $max_e, $max_d, $root_ref);
            break;

        case 'count':
            if (!$session['dom']) { echo "error: no page loaded\n"; exit(1); }
            $tag_filter = $args[1] ?? '*';
            $x = new DOMXPath($session['dom']);
            $q = $tag_filter === '*' ? '//*' : "//{$tag_filter}";
            $nodes = $x->query($q);
            $count = $nodes ? $nodes->length : 0;
            echo "{$tag_filter}: {$count}\n";
            // Also show tag breakdown
            if ($tag_filter === '*') {
                $tags = [];
                foreach ($nodes as $n) {
                    $t = strtolower($n->tagName);
                    $tags[$t] = ($tags[$t] ?? 0) + 1;
                }
                arsort($tags);
                foreach (array_slice($tags, 0, 30) as $t => $c) {
                    echo "  {$t}: {$c}\n";
                }
            }
            break;

        case 'html':
            echo ($session['html'] ?? "(no page loaded)") . "\n";
            break;

        case 'eval':
            echo "eval: JavaScript not available in HTTP mode.\n";
            break;

        case 'go-back':
            if ($session['history_pos'] > 0) {
                $session['history_pos']--;
                http_get($session['history'][$session['history_pos']], $session, false);
                sess_save($session);
                echo snapshot_yaml($session);
            } else { fwrite(STDERR, "No back history.\n"); exit(1); }
            break;

        case 'go-forward':
            if ($session['history_pos'] < count($session['history']) - 1) {
                $session['history_pos']++;
                http_get($session['history'][$session['history_pos']], $session, false);
                sess_save($session);
                echo snapshot_yaml($session);
            } else { fwrite(STDERR, "No forward history.\n"); exit(1); }
            break;

        case 'reload':
            if (!$session['url']) { fwrite(STDERR, "No page to reload.\n"); exit(1); }
            http_get($session['url'], $session, false);
            sess_save($session);
            echo snapshot_yaml($session);
            break;

        case 'smoke':
            $url = $args[1] ?? ($session['url'] ?? '');
            if (!$url) { fwrite(STDERR, "Usage: browser-agent smoke <url>\n"); exit(1); }
            echo "Smoke: {$url}\n" . str_repeat('-', 50) . "\n";
            req_delay();
            [$ua, $hdrs] = req_headers();
            $cfg = config_load();
            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>$cfg['timeout']??30, CURLOPT_CONNECTTIMEOUT=>$cfg['connect_timeout']??10, CURLOPT_HEADER=>true, CURLOPT_NOBODY=>false, CURLOPT_USERAGENT=>$ua, CURLOPT_HTTPHEADER=>$hdrs]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $err = curl_error($ch);
            $st = $code === 200 ? 'PASS' : ($code >= 301 && $code <= 308 ? 'REDIRECT' : 'FAIL');
            echo "  HTTP {$code}: {$st}\n";
            if ($err) echo "  Error: {$err}\n";
            if ($ct) echo "  Type: {$ct}\n";

            $dom = new DOMDocument(); libxml_use_internal_errors(true); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $body); libxml_clear_errors();
            $ti = ''; $t = $dom->getElementsByTagName('title'); if ($t->length > 0) $ti = trim($t->item(0)->textContent);
            echo "  Title: {$ti}\n";
            $links = $dom->getElementsByTagName('a'); $int = 0; $ext = 0;
            $host = parse_url($url, PHP_URL_HOST);
            $base_url = $code >= 300 && $code < 400 ? curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) : $url;
            foreach ($links as $l) { $h = $l->getAttribute('href'); if (str_starts_with($h,'http') && !str_contains($h, $host)) $ext++; elseif ($h !== '' && $h !== '#') $int++; }
            echo "  Links: {$int} int, {$ext} ext\n";
            $imgs = $dom->getElementsByTagName('img'); $broken = 0; $total = 0;
            $base_parts = parse_url($base_url);
            $base_dir = isset($base_parts['path']) ? dirname($base_parts['path']) : '/';
            foreach ($imgs as $img) {
                $src = $img->getAttribute('src');
                if ($src && !str_starts_with($src,'data:')) {
                    $total++;
                    if (str_starts_with($src,'http')) $iu = $src;
                    elseif (str_starts_with($src,'//')) $iu = 'https:' . $src;
                    elseif (str_starts_with($src,'/')) $iu = ($base_parts['scheme']??'https') . '://' . ($base_parts['host']??'') . $src;
                    else $iu = ($base_parts['scheme']??'https') . '://' . ($base_parts['host']??'') . $base_dir . '/' . $src;
                    $c = curl_init(); curl_setopt_array($c, [CURLOPT_URL=>$iu, CURLOPT_RETURNTRANSFER=>false, CURLOPT_NOBODY=>false, CURLOPT_TIMEOUT=>8, CURLOPT_CONNECTTIMEOUT=>5]); curl_exec($c);
                    $img_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
                    if ($img_code !== 200) $broken++;
                }
            }
            if ($total > 0) echo "  Images: {$total}, {$broken} broken\n";
            echo str_repeat('-', 50) . "\n" . (($code===200&&$broken===0) ? "SMOKE PASSED\n" : "SMOKE ISSUES\n");
            break;

        case 'console':
            $url = $args[1] ?? ($session['url'] ?? '');
            if (!$url) { fwrite(STDERR, "Usage: browser-agent console <url>\n"); exit(1); }
            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HEADER=>true, CURLOPT_USERAGENT=>'bapXphp-browser-agent/1.0']); $resp = curl_exec($ch);
            echo "HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
            echo "Content-Type: " . curl_getinfo($ch, CURLINFO_CONTENT_TYPE) . "\n";
            if ($r = curl_getinfo($ch, CURLINFO_REDIRECT_URL)) echo "Redirect: {$r}\n";
            echo "Body: " . strlen($resp) . " bytes\n";
            break;

        case 'mousemove': case 'mousedown': case 'mouseup': case 'mousewheel':
        case 'drag': case 'drop': case 'tab-list': case 'tab-new': case 'tab-close': case 'tab-select':
            fwrite(STDERR, "{$cmd}: not available in HTTP mode.\n"); exit(1);

        case 'config':
            $cfg = config_load();
            $sub = $args[1] ?? '';
            if ($sub === 'set' && $args[2] ?? '') {
                $key = $args[2]; $val = $args[3] ?? '';
                if (is_numeric($val)) $val = (int) $val;
                elseif (in_array($val, ['true','false'], true)) $val = $val === 'true';
                $cfg[$key] = $val;
                config_save($cfg);
                echo "config {$key} = {$val}\n";
            } else {
                foreach ($cfg as $k => $v) echo "  {$k}: {$v}\n";
            }
            break;

        case 'log':
            if (is_file(LOG_FILE)) {
                $lines = (int) ($args[1] ?? 20);
                $all = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $tail = array_slice($all, -$lines);
                echo implode("\n", $tail) . "\n";
            } else { echo "(no log entries)\n"; }
            break;

        case 'cookies':
            if (is_file(COOKIE_JAR)) {
                $cookies = file_get_contents(COOKIE_JAR);
                echo $cookies ?: "(empty)\n";
            } else { echo "(no cookie jar)\n"; }
            break;

        case 'links':
            if (!$session['dom']) { echo "error: no page loaded\n"; exit(1); }
            $links = extract_links($session['dom'], $session['url']);
            echo "total_links: " . count($links) . "\n";
            $internal = array_filter($links, fn($l) => $l['internal']);
            $external = array_filter($links, fn($l) => !$l['internal']);
            echo "internal: " . count($internal) . "\n";
            echo "external: " . count($external) . "\n\n";
            foreach (array_slice($links, 0, 50) as $l) {
                echo "  - " . ($l['internal'] ? '[int]' : '[ext]') . " {$l['url']}";
                if ($l['text']) echo " \"{$l['text']}\"";
                echo "\n";
            }
            if (count($links) > 50) echo "  ... (" . (count($links) - 50) . " more, use 'html' to see all)\n";
            break;

        case 'forms':
            if (!$session['dom']) { echo "error: no page loaded\n"; exit(1); }
            $forms = extract_forms($session['dom']);
            echo "total_forms: " . count($forms) . "\n\n";
            foreach ($forms as $i => $f) {
                echo "  - form #" . ($i + 1) . " [{$f['method']}] {$f['action']}\n";
                if ($f['id']) echo "    id: {$f['id']}\n";
                foreach ($f['fields'] as $fd) {
                    $req = $fd['required'] ? ' *' : '';
                    echo "    field: {$fd['name']} ({$fd['type']}){$req}";
                    if ($fd['placeholder']) echo " placeholder=\"{$fd['placeholder']}\"";
                    echo "\n";
                }
            }
            break;

        case 'captcha':
            if (!$session['dom']) { echo "error: no page loaded\n"; exit(1); }
            $captcha = detect_captcha($session['dom'], $session['html']);
            if ($captcha['detected']) {
                echo "captcha_detected: true\n";
                echo "type: {$captcha['type']}\n";
                if ($captcha['sitekey']) echo "sitekey: {$captcha['sitekey']}\n";
                if ($captcha['elements']) echo "elements: " . count($captcha['elements']) . "\n";
                echo "\nNote: Automated captcha solving requires a service (2captcha, capsolver, etc.)\n";
                echo "or manual intervention via a real browser.\n";
            } else {
                echo "captcha_detected: false\n";
                echo "No captcha found on this page.\n";
            }
            break;

        case 'close':

        case 'help': default:
            echo "bapXphp browser-agent — PHP browser automation\n\n";
            echo "HTTP mode (default, pure PHP):\n";
            echo "  open <url>          fetch page → YAML snapshot (max 500 elems, depth 10)\n";
            echo "  search <query>      web search (DuckDuckGo by default), parse organic results\n";
            echo "    --engine=ddg      DuckDuckGo (default, most bot-friendly)\n";
            echo "    --engine=google   Google search (may be blocked by anti-bot)\n";
            echo "  click <sel>         follow link / submit button\n";
            echo "  hover <sel>         show element attributes\n";
            echo "  fill <sel> <text>   set form field\n";
            echo "  submit [url]        POST form, snapshot result\n";
            echo "  snapshot [flags]    YAML page structure with refs, parent refs, key attrs\n";
            echo "    --max-e=N         max elements (default 500)\n";
            echo "    --max-d=N         max depth (default 10)\n";
            echo "    --ref=eN          drill into subtree of element eN\n";
            echo "    --output=FILE     save snapshot to file\n";
            echo "  screenshot [flags]  YAML snapshot\n";
            echo "  smoke <url>         HTTP + links + images + title test\n";
            echo "  count [tag]         count all elements or specific tag\n";
            echo "  links               extract all links (internal/external) from current page\n";
            echo "  forms               list all forms with fields, types, required status\n";
            echo "  captcha             detect captcha (reCAPTCHA, hCaptcha, Turnstile, text)\n";
            echo "  config [set k v]    show or set config (request_delay_ms, timeout, cdp_ws)\n";
            echo "  log [N]             show last N audit log entries (default 20)\n";
            echo "  cookies             show cookie jar contents\n";
            echo "\nCDP mode (remote Chrome via DevTools Protocol):\n";
            echo "  cdp <ws_url|cmd>    connect to Chrome CDP websocket\n";
            echo "  cdp_launch          start local Chrome from .bin/chrome-linux/\n";
            echo "    --port=N          debugging port (default 9222)\n";
            echo "    --user-data-dir=  custom profile dir\n";
            echo "    --headless=false  show browser window\n";
            echo "  config set cdp_ws ws://host:9222/devtools/browser/xxx  set default CDP endpoint\n";
            echo "  cdp Target.getTargets                                   list targets\n";
            echo "  cdp Page.navigate '{\"url\":\"https://example.com\"}'    navigate\n";
            echo "  cdp Runtime.evaluate '{\"expression\":\"document.title\"}' eval JS\n";
            echo "\n";
            echo "Snapshots include: id, class, role, href, src, alt, name, type, value,\n";
            echo "  method, action, data-testid, aria-label, placeholder, checked, selected\n\n";
            echo "Captcha detection: reCAPTCHA, hCaptcha, Cloudflare Turnstile, text captchas\n";
            echo "Config: request_delay_ms, timeout, connect_timeout, tracing, cdp_ws\n";
            echo "UA pool: Chrome/Mac, Chrome/Win, Chrome/Linux, Safari, Firefox (rotated per request)\n";
            echo "Session persists across calls in .agents/temp/browser-session.json\n";
            echo "  go-back/forward     history navigation (doesn't re-push)\n";
            echo "  reload              re-fetch current page\n";
            echo "  close               reset session (cookies + log)\n\n";
            echo "Snapshots include: id, class, role, href, src, alt, name, type, value,\n";
            echo "  method, action, data-testid, aria-label, placeholder, checked, selected\n\n";
            echo "Captcha detection: reCAPTCHA, hCaptcha, Cloudflare Turnstile, text captchas\n";
            echo "Config: request_delay_ms, timeout, connect_timeout, tracing\n";
echo "UA pool: Chrome/Mac, Chrome/Win, Chrome/Linux, Safari, Firefox (rotated per request)\n";
echo "Session persists across calls in .agents/temp/browser-session.json\n";
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
