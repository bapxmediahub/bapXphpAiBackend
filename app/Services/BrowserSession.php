<?php
namespace App\Services;

final class BrowserSession {
    private $sessionFile;
    private $cookieJar;
    private $configFile;
    private $session;

    public function __construct() {
        $this->sessionFile = __DIR__ . "/../.agents/temp/browser-session.json";
        $this->cookieJar = __DIR__ . "/../.agents/temp/browser-cookies.txt";
        $this->configFile = __DIR__ . "/../.agents/temp/browser-config.json";
        $this->loadSession();
    }

    private function loadSession(): void {
        if (is_file($this->sessionFile)) {
            $this->session = json_decode(file_get_contents($this->sessionFile), true);
            if (!$this->session) $this->session = $this->emptySession();
        } else {
            $this->session = $this->emptySession();
        }
        $this->initCookieJar();
    }

    private function emptySession(): array {
        return [
            "url" => null,
            "html" => null,
            "dom" => null,
            "form_data" => [],
            "history" => [],
            "history_pos" => -1,
            "cookies" => [],
            "trace" => []
        ];
    }

    private function initCookieJar(): void {
        $dir = dirname($this->cookieJar);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        if (!is_file($this->cookieJar)) file_put_contents($this->cookieJar, "");
    }

    private function saveSession(): void {
        $dir = dirname($this->sessionFile);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $save = $this->session;
        unset($save["dom"]);
        file_put_contents($this->sessionFile, json_encode($save, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    private function configLoad(): array {
        if (is_file($this->configFile)) {
            $c = json_decode(file_get_contents($this->configFile), true);
            if ($c) return $c;
        }
        return ["request_delay_ms"=>0,"timeout"=>30,"connect_timeout"=>10,"tracing"=>true,"cdp_ws"=>""];
    }

    private function configSave(array $c): void {
        file_put_contents($this->configFile, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }

    private function reqHeaders(): array {
        $cfg = $this->configLoad();
        $uas = [
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36",
            "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Safari/605.1.15",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:135.0) Gecko/20100101 Firefox/135.0",
        ];
        $ua = $uas[array_rand($uas)];
        $hdrs = [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-US,en;q=0.9",
        ];
        if ($cfg["tracing"] ?? false) {
            $rid = bin2hex(random_bytes(8));
            $hdrs[] = "X-Request-Id: ba-{$rid}";
            $hdrs[] = "X-Browser-Agent: bapXphp/1.0";
        }
        return [$ua, $hdrs];
    }

    private function reqDelay(): void {
        $cfg = $this->configLoad();
        $ms = $cfg["request_delay_ms"] ?? 0;
        if ($ms > 0) usleep($ms * 1000);
    }

    private function cookieInit(): void {
        $dir = dirname($this->cookieJar);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        if (!is_file($this->cookieJar)) file_put_contents($this->cookieJar, "");
    }

    public function search(string $query, string $engine = "ddg"): array {
        $url = $engine === "google"
            ? "https://www.google.com/search?q=" . urlencode($query)
            : "https://html.duckduckgo.com/html/?q=" . urlencode($query);
        $html = $this->httpGet($url);
        if ($engine === "google") return $this->parseGoogleResults($this->session["dom"]);
        return $this->parseDdgResults($this->session["dom"]);
    }

    public function open(string $url): array {
        $this->httpGet($url);
        $this->saveSession();
        return $this->snapshotYaml();
    }

    public function click(string $selector): array {
        if (!$this->session["dom"]) return ["error" => "No page loaded"];
        $node = $this->findElem($selector, $this->session["dom"]);
        if (!$node) return ["error" => "Not found: $selector"];
        $href = $node->getAttribute("href");
        if ($href) {
            $this->httpGet($this->resolveUrl($href, $this->session["url"]));
            $this->saveSession();
            return $this->snapshotYaml();
        }
        return ["error" => "Element has no href"];
    }

    public function fill(string $selector, string $text): array {
        if (!$this->session["dom"]) return ["error" => "No page loaded"];
        $node = $this->findElem($selector, $this->session["dom"]);
        if (!$node) return ["error" => "Not found: $selector"];
        $this->session["form_data"][$selector] = $text;
        $this->saveSession();
        return $this->snapshotYaml();
    }

    public function snapshot(int $maxE = 500, int $maxD = 10): array {
        return $this->snapshotYaml($maxE, $maxD);
    }

    public function extractLinks(): array {
        if (!$this->session["dom"]) return [];
        $links = [];
        $xpath = new \DOMXPath($this->session["dom"]);
        foreach ($xpath->query("//a[@href]") as $a) {
            $href = $a->getAttribute("href");
            $text = trim($a->textContent);
            if ($href !== "") $links[] = ["href" => $href, "text" => $text];
        }
        return $links;
    }

    public function extractForms(): array {
        if (!$this->session["dom"]) return [];
        $forms = [];
        $xpath = new \DOMXPath($this->session["dom"]);
        foreach ($xpath->query("//form") as $i => $f) {
            $action = $f->getAttribute("action") ?: $this->session["url"];
            $method = strtoupper($f->getAttribute("method") ?: "GET");
            $fields = [];
            foreach ($xpath->query(".//input|.//select|.//textarea", $f) as $inp) {
                $name = $inp->getAttribute("name");
                if ($name === "") continue;
                $type = $inp->getAttribute("type") ?: ($inp->tagName === "select" ? "select" : ($inp->tagName === "textarea" ? "textarea" : "text"));
                $required = $inp->hasAttribute("required");
                $placeholder = $inp->getAttribute("placeholder");
                $fields[] = ["name" => $name, "type" => $type, "required" => $required, "placeholder" => $placeholder];
            }
            $forms[] = ["index" => $i, "action" => $action, "method" => $method, "fields" => $fields];
        }
        return $forms;
    }

    public function detectCaptcha(): array {
        if (!$this->session["dom"]) return ["detected" => false];
        return $this->detectCaptchaInternal($this->session["dom"], $this->session["html"]);
    }

    public function smoke(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => "bapXphp-browser-agent/1.0",
        ]);
        $resp = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return [
            "http_code" => $info["http_code"] ?? 0,
            "content_type" => $info["content_type"] ?? "",
            "redirect_url" => $info["redirect_url"] ?? "",
            "size" => strlen($resp),
            "timestamp" => date("c"),
        ];
    }

    public function cdp(string $wsUrl, string $method, array $params = []): ?array {
        $conn = $this->cdpConnect($wsUrl);
        if (!$conn) return ["error" => "CDP connect failed"];
        $result = $this->cdpSend($conn, $method, $params);
        $this->cdpClose($conn);
        return $result;
    }

    public function cdpLaunch(int $port = 9222, bool $headless = true, bool $noSandbox = true): array {
        $binDir = __DIR__ . "/../../.bin/chrome-linux";
        $chrome = $binDir . "/chrome";
        
        if (!is_file($chrome) || !is_executable($chrome)) {
            return ["error" => "Chrome binary not found at $chrome"];
        }
        
        $test = @shell_exec("$chrome --version 2>&1");
        if ($test === false || $test === "" || str_contains($test, "cannot execute") || str_contains($test, "Exec format error")) {
            return ["error" => "Chrome binary is Linux x86_64 only. Won't run on this architecture."];
        }

        $cmd = [$chrome];
        if ($headless) $cmd[] = "--headless=new";
        if ($noSandbox) $cmd[] = "--no-sandbox";
        $cmd[] = "--disable-gpu";
        $cmd[] = "--disable-dev-shm-usage";
        $cmd[] = "--remote-debugging-port=$port";
        $cmd[] = "--remote-debugging-address=0.0.0.0";
        $cmd[] = "--user-data-dir=" . sys_get_temp_dir() . "/chrome-cdp-" . uniqid();

        $cmdStr = implode(" ", array_map("escapeshellarg", $cmd)) . " > /dev/null 2>&1 &";
        $pid = shell_exec($cmdStr);
        
        if (!$pid) return ["error" => "Failed to start Chrome"];
        
        return [
            "started" => true,
            "port" => $port,
            "pid" => trim($pid),
            "ws_url" => "ws://localhost:$port/devtools/browser/",
        ];
    }

    public function getStatus(): array {
        return [
            "session_file" => $this->sessionFile,
            "has_session" => $this->session["url"] !== null,
            "current_url" => $this->session["url"],
            "history_length" => count($this->session["history"]),
            "cookie_jar" => $this->cookieJar,
            "config" => $this->configLoad(),
        ];
    }

    // ... Private helper methods
    private function httpGet(string $url, bool $store = true, int $retries = 2): string {
        $this->cookieInit();
        $this->reqDelay();
        $cfg = $this->configLoad();
        $timeout = $cfg["timeout"] ?? 30;
        $connectTimeout = $cfg["connect_timeout"] ?? 10;
        
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            [$ua, $hdrs] = $this->reqHeaders();
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_COOKIEFILE => $this->cookieJar,
                CURLOPT_COOKIEJAR => $this->cookieJar,
                CURLOPT_HTTPHEADER => $hdrs,
            ]);
            $start = microtime(true);
            $html = curl_exec($ch);
            $info = curl_getinfo($ch);
            $error = curl_error($ch);
            $elapsed = round((microtime(true) - $start) * 1000);
            $httpCode = $info["http_code"] ?? 0;
            
            error_log("GET $httpCode $url ({$elapsed}ms) attempt=$attempt");
            
            if ($html !== false && $httpCode >= 200 && $httpCode < 500) {
                $this->session["url"] = $info["url"];
                $this->session["html"] = $html;
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadHTML("<?xml encoding=\"utf-8\" ?>$html");
                libxml_clear_errors();
                $this->session["dom"] = $dom;
                if ($store) {
                    $this->session["history"] = array_slice($this->session["history"], 0, $this->session["history_pos"] + 1);
                    $this->session["history"][] = $info["url"];
                    $this->session["history_pos"] = count($this->session["history"]) - 1;
                }
                return $html;
            }
            if ($attempt < $retries) usleep($attempt * 1000000);
        }
        throw new \RuntimeException("Error after $retries attempts: $error (HTTP $httpCode)");
    }

    private function parseDdgResults(\DOMDocument $dom): array {
        $results = [];
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query("//a[contains(@class,'result__snippet') or contains(@class,'result__url')]") as $node) {
            $href = $node->getAttribute("href");
            $text = trim($node->textContent);
            if ($href && $text) $results[] = ["url" => $href, "text" => $text];
        }
        if (empty($results)) {
            foreach ($xpath->query("//a[@class='result__snippet']") as $node) {
                $href = $node->getAttribute("href");
                $text = trim($node->textContent);
                if ($href && $text) $results[] = ["url" => $href, "text" => $text];
            }
        }
        return array_slice($results, 0, 10);
    }

    private function parseGoogleResults(\DOMDocument $dom, string $html): array {
        $results = [];
        if (preg_match_all('/"url"\s*:\s*"([^"]+)"/', $html, $m)) {
            foreach ($m[1] as $u) {
                $u = stripcslashes($u);
                if (str_starts_with($u, "http")) $results[] = ["url" => $u, "text" => ""];
            }
        }
        return array_slice($results, 0, 10);
    }

    private function findElem(string $sel, \DOMDocument $dom): ?\DOMElement {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//a[contains(@class,'$sel') or @id='$sel' or contains(@class,'$sel')]");
        if ($nodes->length === 0) {
            $nodes = $xpath->query("//*[@id='$sel']");
        }
        if ($nodes->length === 0) {
            $nodes = $xpath->query("//a[contains(translate(text(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'$sel')]");
        }
        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function resolveUrl(string $href, string $base): string {
        if (str_starts_with($href, "http")) return $href;
        $parts = parse_url($base);
        $basePath = $parts["path"] ?? "/";
        if ($href === "") return $base;
        if ($href[0] === "/") return $parts["scheme"] . "://" . $parts["host"] . $href;
        return $parts["scheme"] . "://" . $parts["host"] . dirname($basePath) . "/" . $href;
    }

    private function detectCaptchaInternal(\DOMDocument $dom, string $html): array {
        $types = [];
        if (preg_match('/recaptcha/i', $html)) $types[] = "reCAPTCHA";
        if (preg_match('/hcaptcha/i', $html)) $types[] = "hCaptcha";
        if (preg_match('/turnstile/i', $html)) $types[] = "Turnstile";
        $xpath = new \DOMXPath($dom);
        if ($xpath->query("//input[@type='text' and contains(@id,'captcha') or contains(@name,'captcha')]")->length > 0) {
            $types[] = "Text Captcha";
        }
        return ["detected" => !empty($types), "type" => implode(", ", $types) ?: "none", "elements" => count($types)];
    }

    private function cdpConnect(string $wsUrl): ?array {
        $ctx = stream_context_create(["socket"=>["connect_timeout"=>10]]);
        $fp = @stream_socket_client($wsUrl, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) return null;
        stream_set_blocking($fp, true);
        $key = base64_encode(random_bytes(16));
        $req = "GET / HTTP/1.1\r\nHost: " . parse_url($wsUrl, PHP_URL_HOST) . "\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n\r\n";
        fwrite($fp, $req);
        $resp = "";
        while (!feof($fp)) { $resp .= fread($fp, 1024); if (str_contains($resp, "\r\n\r\n")) break; }
        if (!str_contains($resp, "101 Switching Protocols")) { fclose($fp); return null; }
        return ["fp" => $fp, "id" => 1];
    }

    private function cdpSend(array $conn, string $method, array $params = []): ?array {
        $msg = json_encode(["id" => $conn["id"]++, "method" => $method, "params" => $params]);
        $frame = chr(0x81) . chr(strlen($msg)) . $msg;
        fwrite($conn["fp"], $frame);
        $buf = "";
        while (!feof($conn["fp"])) {
            $buf .= fread($conn["fp"], 1024);
            if (strlen($buf) >= 2) {
                $len = ord($buf[1]) & 127;
                if (strlen($buf) >= 2 + $len) break;
            }
        }
        if ($buf === "") return null;
        return json_decode(substr($buf, 2), true);
    }

    private function cdpClose(array $conn): void {
        fclose($conn["fp"]);
    }

    private function snapshotYaml(int $maxE = 500, int $maxD = 10, ?string $rootRef = null): array {
        if (!$this->session["dom"]) return ["error" => "no page loaded"];
        $title = "";
        $t = $this->session["dom"]->getElementsByTagName("title");
        if ($t->length > 0) $title = trim(str_replace("\n", " ", $t->item(0)->textContent));
        $y = "page_url: {$this->session["url"]}\npage_title: $title\n";
        if ($rootRef) $y .= "refocus: $rootRef\n";
        $y .= "\n";
        $body = $this->session["dom"]->getElementsByTagName("body")->item(0);
        if (!$body) { $y .= "elements: []\n"; return ["yaml" => $y]; }
        $rootNode = $body;
        if ($rootRef) {
            $idx = (int) ltrim($rootRef, "e");
            $domRefs = []; $ri = 0;
            $w2 = function(\DOMElement $n) use (&$w2, &$domRefs, &$ri, $maxE) {
                if ($ri > $maxE) return;
                foreach ($n->childNodes as $c) {
                    if ($ri > $maxE) return;
                    if (!$c instanceof \DOMElement) continue;
                    $ri++; $domRefs[$ri] = $c; $w2($c);
                }
            };
            $w2($body);
            if (isset($domRefs[$idx])) $rootNode = $domRefs[$idx];
        }
        $walk = function(\DOMElement $n, int $d, ?string $pref) use (&$walk, &$ref, $maxD, $maxE): array {
            if ($d > $maxD || $ref >= $maxE) return [];
            $ref++;
            $attrs = [];
            foreach (["id","class","role","href","src","alt","name","type","value","method","action","data-testid","aria-label","placeholder","checked","selected"] as $attr) {
                $v = $n->getAttribute($attr);
                if ($v !== "") $attrs[$attr] = $v;
            }
            $eid = "e" . $ref;
            $entry = ["id" => $eid, "tag" => strtolower($n->tagName), "attrs" => $attrs];
            $children = [];
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMElement) $children[] = $walk($c, $d + 1, $pref);
            }
            if (!empty($children)) $entry["children"] = $children;
            $y = $eid . " " . strtolower($n->tagName);
            if ($attrs) $y .= " " . implode(" ", array_map(fn($k,$v) => "$k=\"$v\"", array_keys($attrs), $attrs));
            $out = ["yaml" => $y, "children" => $children];
            return $out;
        };
        
        $ref = 0;
        $result = $walk($rootNode, 0, null);
        
        // Build YAML output
        $yaml = $y;
        $lines = [];
        $stack = [[$result, 0]];
        while (!empty($stack)) {
            [$node, $depth] = array_pop($stack);
            if (!is_array($node)) continue;
            if (isset($node["yaml"])) {
                $lines[] = str_repeat("  ", $depth) . "- " . $node["yaml"];
            }
            if (!empty($node["children"])) {
                foreach (array_reverse($node["children"]) as $child) {
                    $stack[] = [$child, $depth + 1];
                }
            }
        }
        $yaml .= implode("\n", $lines) . "\n";
        return ["yaml" => $yaml];
    }
}
