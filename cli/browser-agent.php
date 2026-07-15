<?php
/**
 * Browser Agent — PHP-native browser automation tool
 *
 * Follows playwright-cli command structure and snapshot format.
 * HTTP mode: cURL + DOMDocument (pure PHP, works on shared hosting).
 * Playwright mode: shells out to playwright-cli (requires Node.js).
 * 
 * Usage: php cli/browser-agent.php [--pw] <command> [args...]
 *
 * ── Core ──────────────────────────────────────────────────────────
 *   open <url>         — fetch page, return YAML snapshot
 *   goto <url>         — navigate to URL
 *   click <sel>        — follow link / click button by CSS selector
 *   dblclick <sel>     — double-click (HTTP: same as click)
 *   hover <sel>        — show element details (HTTP: info only)
 *   fill <sel> <text>  — store form field value
 *   submit [url]       — POST form with stored values
 *   type <text>        — type text into focused field
 *   press <key>        — press key (HTTP: submit on Enter)
 *   snapshot           — print current page as YAML snapshot
 *   screenshot [file]  — save raw HTML (HTTP) or screenshot (PW)
 *   html               — print raw HTML of current page
 *   eval <expr>        — evaluate JS expression (HTTP: not supported)
 * 
 * ── Mouse ─────────────────────────────────────────────────────────
 *   mousemove <x> <y>  — HTTP: not supported (PW only)
 *   mousedown [btn]    — HTTP: not supported (PW only)
 *   mouseup [btn]      — HTTP: not supported (PW only)
 *   mousewheel <dx> <dy> — HTTP: not supported (PW only)
 *   drag <from> <to>   — HTTP: not supported (PW only)
 *   drop <sel> --file  — HTTP: not supported (PW only)
 *
 * ── Navigation ────────────────────────────────────────────────────
 *   go-back            — navigate back in history
 *   go-forward         — navigate forward in history
 *   reload             — re-fetch current URL
 *
 * ── Tabs ──────────────────────────────────────────────────────────
 *   tab-list           — HTTP: no tabs (PW only)
 *   tab-new [url]      — HTTP: new session (PW only)
 *   tab-close [idx]    — HTTP: not supported (PW only)
 *   tab-select <idx>   — HTTP: not supported (PW only)
 *
 * ── Smoke / Debug ─────────────────────────────────────────────────
 *   smoke <url>        — run smoke tests (status, links, images)
 *   console <url>      — show page HTTP metadata
 *   close              — reset session state
 *   help               — show this help
 */

require __DIR__ . '/../app/bootstrap.php';

$session = [
    'url' => null, 'html' => null, 'dom' => null,
    'form_data' => [], 'history' => [], 'history_pos' => -1,
    'cookies' => [], 'hovered' => null,
];

function http_get(string $url, array &$s, bool $store = true): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'bapXphp-browser-agent/1.0 (PHP)',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
        ],
    ]);
    $html = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    if ($html === false) { fwrite(STDERR, "Error: $error\n"); exit(1); }
    $s['url'] = $info['url'];
    $s['html'] = $html;
    $dom = new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); libxml_clear_errors();
    $s['dom'] = $dom;
    if ($store) { $s['history'] = array_slice($s['history'], 0, $s['history_pos'] + 1); $s['history'][] = $info['url']; $s['history_pos'] = count($s['history']) - 1; }
    $s['hovered'] = null;
    return $html;
}

function text_of(DOMNode $node): string {
    $t = ''; foreach ($node->childNodes as $c) { if ($c instanceof DOMText) $t .= $c->wholeText; elseif ($c instanceof DOMElement) $t .= ' ' . text_of($c); }
    return trim(preg_replace('/\s+/', ' ', $t));
}

function snapshot_yaml(array &$s): string {
    if (!$s['dom']) return "error: no page loaded\n";
    $title = ''; $t = $s['dom']->getElementsByTagName('title'); if ($t->length > 0) $title = trim(str_replace("\n", ' ', $t->item(0)->textContent));
    $y = "# Page\n- Page URL: {$s['url']}\n- Page Title: {$title}\n- HTTP Status: 200\n\n# Snapshot\n- Page URL: {$s['url']}\n- Page Title: {$title}\n\n";
    $body = $s['dom']->getElementsByTagName('body')->item(0); if (!$body) { $y .= "  (no body)\n"; return $y; }
    $ref = 0; $max_d = 6; $max_e = 80;
    $walk = function(DOMElement $n, int $d) use (&$walk, &$ref, $max_d, $max_e): array {
        if ($d > $max_d || $ref > $max_e) return []; $elems = [];
        foreach ($n->childNodes as $c) {
            if ($ref > $max_e) break; if (!$c instanceof DOMElement) continue;
            $tag = strtolower($c->tagName); if (in_array($tag, ['script','style','noscript','iframe','svg'])) continue;
            $ref++; $e = ['ref' => "e{$ref}", 'tag' => $tag];
            $txt = text_of($c); $txt = mb_substr($txt, 0, 120); if ($txt !== '') $e['text'] = $txt;
            foreach (['role' => 'role', 'href' => 'url', 'src' => 'src', 'alt' => 'alt', 'name' => 'name', 'type' => 'type', 'class' => 'class'] as $a => $k) {
                $v = $c->getAttribute($a); if ($v !== '') $e[$k] = mb_substr($v, 0, 120);
            }
            $val = $c->getAttribute('value'); if ($val !== '' && in_array($tag, ['input','button','option'])) $e['value'] = mb_substr($val, 0, 80);
            $children = $walk($c, $d + 1); if ($children) $e['children'] = $children;
            $elems[] = $e;
        } return $elems;
    };
    $y .= "  elements:\n" . arr_yaml($walk($body, 0), 4);
    return $y;
}

function arr_yaml(array $items, int $indent = 0): string {
    $y = ''; $p = str_repeat(' ', $indent);
    foreach ($items as $item) {
        $tag = $item['tag']; $txt = $item['text'] ?? ''; $ref = $item['ref'] ?? '';
        $y .= "{$p}- {$tag}" . ($ref ? " ({$ref})" : '') . ($txt ? ' "' . str_replace('"', '\"', $txt) . '"' : '') . "\n";
        foreach (['role','url','src','alt','name','type','value','class'] as $a) { if (isset($item[$a])) $y .= "{$p}  {$a}: {$item[$a]}\n"; }
        if (isset($item['children'])) $y .= arr_yaml($item['children'], $indent + 2);
    } return $y;
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

function find_elem(string $sel, array &$s): ?DOMElement {
    $x = new DOMXPath($s['dom']); $nodes = $x->query(css2xpath($sel)); return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
}

function cmd_smoke(string $url): void {
    echo "Smoke testing: $url\n"; echo str_repeat('-', 50) . "\n";
    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_HEADER=>true, CURLOPT_NOBODY=>true, CURLOPT_USERAGENT=>'bapXphp-browser-agent/1.0']); curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); $err = curl_error($ch);
    $st = $code === 200 ? 'PASS' : ($code === 301 || $code === 302 ? 'REDIRECT' : 'FAIL');
    echo "  HTTP {$code}: {$st}\n"; if ($err) echo "  Error: {$err}\n"; if ($ct) echo "  Type: {$ct}\n";

    $body = http_get($url, $sess = ['url'=>null,'html'=>null,'dom'=>null,'form_data'=>[],'history'=>[],'history_pos'=>-1,'cookies'=>[],'hovered'=>null], false);
    $dom = $sess['dom']; $ti = ''; $t = $dom->getElementsByTagName('title'); if ($t->length > 0) $ti = trim($t->item(0)->textContent);
    echo "  Title: {$ti}\n";
    $links = $dom->getElementsByTagName('a'); $int = 0; $ext = 0;
    foreach ($links as $l) { $h = $l->getAttribute('href'); if (str_starts_with($h,'http') && !str_contains($h, parse_url($url,PHP_URL_HOST))) $ext++; elseif ($h !== '' && $h !== '#') $int++; }
    echo "  Links: {$int} internal, {$ext} external\n";
    $imgs = $dom->getElementsByTagName('img'); $broken = 0; $total = 0;
    foreach ($imgs as $img) { $src = $img->getAttribute('src'); if ($src && !str_starts_with($src,'data:')) { $total++; $iu = str_starts_with($src,'http') ? $src : rtrim($url,'/').'/'.ltrim($src,'/'); $c=curl_init(); curl_setopt_array($c,[CURLOPT_URL=>$iu,CURLOPT_RETURNTRANSFER=>true,CURLOPT_NOBODY=>true,CURLOPT_TIMEOUT=>5,CURLOPT_CONNECTTIMEOUT=>3]); curl_exec($c); if (curl_getinfo($c,CURLINFO_HTTP_CODE)!==200) $broken++; }}
    if ($total > 0) echo "  Images: {$total} total, {$broken} broken\n";
    echo str_repeat('-',50) . "\n"; echo ($code===200&&$broken===0) ? "SMOKE PASSED\n" : "SMOKE ISSUES FOUND\n";
}

function cmd_help(): void {
    echo "bapXphp browser-agent — PHP browser automation (playwright-cli format)\n\n";
    echo "HTTP mode (default, pure PHP, no Node needed):\n";
    echo "  open <url>         goto <url>         — fetch page, YAML snapshot\n";
    echo "  click <sel>        dblclick <sel>     — follow link / double-click\n";
    echo "  hover <sel>                           — show element details\n";
    echo "  fill <sel> <text>  type <text>        — fill form field / type\n";
    echo "  submit [url]       press <key>        — submit form / press Enter\n";
    echo "  snapshot           screenshot [file]  — YAML snapshot / save HTML\n";
    echo "  html               eval <expr>        — raw HTML / JS eval (N/A)\n";
    echo "  go-back            go-forward         — history navigation\n";
    echo "  reload                                — re-fetch current URL\n";
    echo "  smoke <url>        console <url>      — smoke tests / HTTP metadata\n";
    echo "  close             help                — reset session / this help\n\n";
    echo "Playwright mode (--pw prefix, needs Node):\n";
    echo "  --pw click <ref>   --pw dblclick <ref> [btn]\n";
    echo "  --pw hover <ref>   --pw drag <from> <to>\n";
    echo "  --pw drop <ref> --path=<file>\n";
    echo "  --pw mousemove <x> <y>   --pw mousedown [btn]\n";
    echo "  --pw mouseup [btn]       --pw mousewheel <dx> <dy>\n";
    echo "  --pw press <key>   --pw keydown <key>  --pw keyup <key>\n";
    echo "  --pw screenshot [--filename=f.png]\n";
    echo "  --pw tab-list      --pw tab-new [url]  --pw tab-close [idx]\n";
    echo "  --pw tab-select <idx>\n\n";
    echo "Examples:\n";
    echo "  bapXphp browser-agent open https://example.com\n";
    echo "  bapXphp browser-agent smoke https://sripanchamispiritual.com\n";
    echo "  bapXphp browser-agent --pw open https://example.com --headed\n";
}

// ── Main ───────────────────────────────────────────────────────
$args = $argv; array_shift($args);
$pw = false; if (($args[0] ?? '') === '--pw') { $pw = true; array_shift($args); }
$cmd = $args[0] ?? 'help';

try {
    if ($pw) {
        $pw_cmd = 'playwright-cli ' . implode(' ', array_map('escapeshellarg', array_slice($args, 1)));
        $out = shell_exec($pw_cmd . ' 2>&1');
        if ($out === null) { fwrite(STDERR, "playwright-cli not found. Install: npm install -g @playwright/cli@latest\n"); exit(1); }
        echo $out; exit;
    }

    switch ($cmd) {
        case 'open': case 'goto':
            $url = $args[1] ?? ''; if (!$url) { fwrite(STDERR, "Usage: browser-agent open <url>\n"); exit(1); }
            http_get($url, $session); echo snapshot_yaml($session); break;

        case 'click': case 'dblclick':
            $sel = $args[1] ?? ''; if (!$sel) { fwrite(STDERR, "Usage: browser-agent click <selector>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded.\n"); exit(1); }
            $node = find_elem($sel, $session);
            if (!$node) { fwrite(STDERR, "Not found: $sel\n"); exit(1); }
            $href = $node->getAttribute('href');
            if ($href) { http_get(resolve_url($href, $session['url']), $session); echo snapshot_yaml($session); }
            else { $btn = strtolower($node->getAttribute('type') ?: ''); $name = $node->getAttribute('name') ?: ''; $val = $node->getAttribute('value') ?: '';
                if ($btn === 'submit' || $tag = strtolower($node->tagName) === 'button') {
                    $session['form_data'][$name ?: '_submit'] = $val ?: '1'; goto submit_form;
                } else { fwrite(STDERR, "Element not clickable (no href): $sel\n"); exit(1); }
            } break;

        case 'hover':
            $sel = $args[1] ?? ''; if (!$sel) { fwrite(STDERR, "Usage: browser-agent hover <selector>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded.\n"); exit(1); }
            $node = find_elem($sel, $session);
            if (!$node) { fwrite(STDERR, "Not found: $sel\n"); exit(1); }
            $tag = strtolower($node->tagName); $txt = text_of($node); $href = $node->getAttribute('href');
            echo "Element: <{$tag}> " . ($txt ? "\"{$txt}\"" : '') . "\n";
            if ($href) echo "  href: {$href}\n";
            foreach (['src'=>'src','alt'=>'alt','name'=>'name','type'=>'type','class'=>'class','id'=>'id','title'=>'title'] as $a=>$k) {
                $v = $node->getAttribute($a); if ($v !== '') echo "  {$k}: {$v}\n";
            } break;

        case 'fill':
            $sel = $args[1] ?? ''; $val = $args[2] ?? '';
            if (!$sel || !$val) { fwrite(STDERR, "Usage: browser-agent fill <selector> <text>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded.\n"); exit(1); }
            $node = find_elem($sel, $session); if (!$node) { fwrite(STDERR, "Not found: $sel\n"); exit(1); }
            $name = $node->getAttribute('name') ?: $sel; $session['form_data'][$name] = $val;
            echo "Set {$name} = {$val}\n"; break;

        case 'type':
            $sel = $args[1] ?? ''; if (!$sel) { fwrite(STDERR, "Usage: browser-agent type <text>\n"); exit(1); }
            if (!$session['dom']) { fwrite(STDERR, "No page loaded.\n"); exit(1); }
            $session['form_data']['_typed'] = $sel; echo "Typed: {$sel}\n"; break;

        case 'press':
            $key = $args[1] ?? ''; if (!$key) { fwrite(STDERR, "Usage: browser-agent press <key>\n"); exit(1); }
            if (strtolower($key) === 'enter') goto submit_form;
            else { echo "Key pressed (stored): {$key}\n"; } break;

        case 'submit': submit_form:
            $url = $args[1] ?? $session['url'] ?? ''; if (!$url) { fwrite(STDERR, "No URL to submit to.\n"); exit(1); }
            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($session['form_data']), CURLOPT_TIMEOUT=>15, CURLOPT_USERAGENT=>'bapXphp-browser-agent/1.0']);
            $html = curl_exec($ch); $session['url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); $session['html'] = $html;
            $dom = new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); libxml_clear_errors(); $session['dom'] = $dom;
            $session['form_data'] = []; echo snapshot_yaml($session); break;

        case 'snapshot': echo snapshot_yaml($session); break;

        case 'screenshot':
            $file = $args[1] ?? ''; $html = $session['html'] ?? '';
            if (!$html) { $html = "<html><body><p>No page loaded</p></body></html>"; }
            if ($file) { file_put_contents($file, $html); echo "Saved: {$file}\n"; }
            else { echo "---HTML SNIPPET---\n" . mb_substr($html, 0, 2000) . "\n---\n"; }
            break;

        case 'html': echo ($session['html'] ?? "(no page loaded)") . "\n"; break;

        case 'eval': echo "eval: JS execution not available in HTTP mode. Use --pw mode.\n"; break;

        case 'go-back':
            if ($session['history_pos'] > 0) { $session['history_pos']--; http_get($session['history'][$session['history_pos']], $session); echo snapshot_yaml($session); }
            else { fwrite(STDERR, "No history to go back to.\n"); exit(1); } break;

        case 'go-forward':
            if ($session['history_pos'] < count($session['history']) - 1) { $session['history_pos']++; http_get($session['history'][$session['history_pos']], $session); echo snapshot_yaml($session); }
            else { fwrite(STDERR, "No forward history.\n"); exit(1); } break;

        case 'reload':
            if (!$session['url']) { fwrite(STDERR, "No page to reload.\n"); exit(1); }
            http_get($session['url'], $session); echo snapshot_yaml($session); break;

        case 'smoke':
            $url = $args[1] ?? ($session['url'] ?? ''); if (!$url) { fwrite(STDERR, "Usage: browser-agent smoke <url>\n"); exit(1); }
            cmd_smoke($url); break;

        case 'console':
            $url = $args[1] ?? ($session['url'] ?? ''); if (!$url) { fwrite(STDERR, "Usage: browser-agent console <url>\n"); exit(1); }
            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HEADER=>true, CURLOPT_USERAGENT=>'bapXphp-browser-agent/1.0']); $resp = curl_exec($ch);
            echo "HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
            echo "Content-Type: " . curl_getinfo($ch, CURLINFO_CONTENT_TYPE) . "\n";
            if ($r = curl_getinfo($ch, CURLINFO_REDIRECT_URL)) echo "Redirect: {$r}\n";
            echo "Body size: " . strlen($resp) . " bytes\n"; break;

        case 'mousemove': case 'mousedown': case 'mouseup': case 'mousewheel':
        case 'drag': case 'drop': case 'tab-list': case 'tab-new': case 'tab-close': case 'tab-select':
            fwrite(STDERR, "{$cmd}: not available in HTTP mode. Use --pw prefix for Playwright mode.\n"); exit(1);

        case 'close':
            $session = ['url'=>null,'html'=>null,'dom'=>null,'form_data'=>[],'history'=>[],'history_pos'=>-1,'cookies'=>[],'hovered'=>null];
            echo "Session reset\n"; break;

        case 'help': default: cmd_help(); break;
    }
} catch (Throwable $e) { fwrite(STDERR, "Error: " . $e->getMessage() . "\n"); exit(1); }
