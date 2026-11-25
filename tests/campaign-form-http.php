<?php
declare(strict_types=1);

// Use the real HTTP router, controller, sessions, admission state and remote
// transport. The loopback peer records valid mutation calls. The MySQL suite
// separately exercises the full transaction and Inbox projection.
if (PHP_SAPI === 'cli-server') {
    $state = (string)getenv('BAPX_CAMPAIGN_TEST_STATE');
    $input = json_decode((string)file_get_contents('php://input'), true);
    header('Content-Type: application/json');
    if ($state === '' || ($input['password'] ?? null) !== 'synthetic-campaign-test') {
        http_response_code(404);
        exit;
    }
    $fixture = json_decode(file_get_contents($state), true, flags: JSON_THROW_ON_ERROR);
    if (isset($input['query'])) {
        $query = $input['query'];
        $id = $input['params'][0] ?? null;
        $record = match ($query) {
            'SELECT * FROM settings WHERE id = ?' => $fixture['pages'][$id] ?? null,
            'SELECT * FROM contact_submissions WHERE id = ?' => $fixture['contacts'][$id] ?? null,
            default => null,
        };
        $records = $query === 'SELECT * FROM mail_inbox' ? $fixture['inbox'] : ($record === null ? [] : [$record]);
        $rows = array_values(array_map(static fn(array $row): array => [
            'id'=>$row['id'], '_data'=>json_encode($row, JSON_THROW_ON_ERROR),
        ], $records));
        echo json_encode(['success'=>true, 'data'=>$rows], JSON_THROW_ON_ERROR);
        exit;
    }
    if (($input['action'] ?? null) === 'save_campaign_lead' && ($input['collection'] ?? null) === 'contact_submissions') {
        $page = $fixture['pages']['pg:'.$input['slug']];
        $outcome = $fixture['next_outcome'] ?? 'received';
        unset($fixture['next_outcome']);
        if ($outcome === 'stale') {
            unset($page['campaign_form']);
            $page['revision'] = str_repeat('f', 32);
            $fixture['pages'][$page['id']] = $page;
        } elseif ($outcome === 'received') {
            $fixture['contacts'][$input['id']] = ['id'=>$input['id'], 'campaign_slug'=>$input['slug'],
                'campaign_revision'=>$input['revision'], 'name'=>$input['answers']['name'], 'email'=>$input['answers']['email']];
        }
        $fixture['calls']++;
        file_put_contents($state, json_encode($fixture, JSON_THROW_ON_ERROR), LOCK_EX);
        echo json_encode(['success'=>true, 'outcome'=>$outcome], JSON_THROW_ON_ERROR);
        exit;
    }
    http_response_code(400);
    exit;
}
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$repo = dirname(__DIR__);
$root = sys_get_temp_dir().'/bapx-campaign-'.bin2hex(random_bytes(8));
$servers = [];
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$listen = static function (): string {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    return $address;
};
$start = static function (string $address, string $router, array $env) use ($root, $check, &$servers): void {
    $log = $root.'/server-'.count($servers).'.log';
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path='.$root.'/sessions',
        '-d', 'session.name=PHPSESSID', '-d', 'session.serialize_handler=php', '-S',$address, $router], [
        0=>['file','/dev/null','r'], 1=>['file',$log,'a'], 2=>['file',$log,'a'],
    ], $pipes, $root, $env);
    $check(is_resource($server), 'Start isolated loopback server');
    $servers[] = $server;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
        if ($socket) { fclose($socket); return; }
        usleep(10000);
    }
    throw new RuntimeException('Loopback server not ready');
};

try {
    mkdir($root.'/app', 0700, true);
    mkdir($root.'/sessions', 0700);
    copy($repo.'/index.php', $root.'/index.php');
    copy($repo.'/app/bootstrap.php', $root.'/app/bootstrap.php');
    copy($repo.'/app/routes.php', $root.'/app/routes.php');
    foreach (['app/Controllers','app/Services','app/Router.php','assets','config','integrations','views'] as $path) {
        symlink($repo.'/'.$path, $root.'/'.$path);
    }
    file_put_contents($root.'/.env', '');
    $bridge = $listen();
    $state = $root.'/synthetic.json';
    $env = [
        'APP_URL'=>'http://example.invalid',
        'BAPX_MYSQL_HOST'=>'127.0.0.1', 'BAPX_MYSQL_PORT'=>'1', 'BAPX_MYSQL_DB'=>'synthetic',
        'BAPX_MYSQL_USER'=>'synthetic', 'BAPX_MYSQL_PASS'=>'synthetic-campaign-test',
        'BAPX_REMOTE_DB_URL'=>'http://'.$bridge.'/remotedb', 'BAPX_CAMPAIGN_TEST_STATE'=>$state, 'BAPX_TEST_MODE'=>'0',
    ];
    $revision = str_repeat('a', 32);
    $page = ['id'=>'pg:campaign', 'slug'=>'campaign', 'title'=>'Campaign fixture', 'content'=>'## Published campaign',
        'status'=>'published', 'revision'=>$revision, 'campaign_form'=>[
            ['id'=>'detail_2', 'type'=>'text', 'label'=>'Question', 'required'=>true],
        ]];
    $legacy = array_replace($page, ['id'=>'pg:legacy', 'slug'=>'legacy']);
    unset($legacy['campaign_form']);
    $draft = array_replace($page, ['id'=>'pg:draft', 'slug'=>'draft', 'status'=>'draft', 'content'=>'Private campaign phrase']);
    $snapshot = ['pages'=>[$page['id']=>$page, $legacy['id']=>$legacy, $draft['id']=>$draft],
        'contacts'=>[], 'calls'=>0, 'inbox'=>[
            ['id'=>'in_lead_'.str_repeat('c', 24), 'source'=>'contact_submissions', 'campaign_slug'=>'campaign',
                'campaign_revision'=>$revision, 'status'=>'unread', 'subject'=>'Campaign enquiry',
                'from_email'=>'inbox-fixture@example.invalid', 'body'=>'Private fixture <note>'],
            ['id'=>'in_legacy', 'source'=>'contact_submissions', 'status'=>'unread', 'subject'=>'Regular contact note'],
        ]];
    $read = static fn(): array => json_decode(file_get_contents($state), true, flags: JSON_THROW_ON_ERROR);
    $write = static fn(array $next): int => file_put_contents($state, json_encode($next, JSON_THROW_ON_ERROR), LOCK_EX);
    $write($snapshot);
    $start($bridge, __FILE__, $env);
    $site = $listen();
    $start($site, $root.'/index.php', $env);

    $browser = curl_init();
    curl_setopt_array($browser, [CURLOPT_COOKIEFILE=>'', CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true,
        CURLOPT_HTTPHEADER=>['Host: localhost'], CURLOPT_TIMEOUT=>5]);
    $request = static function (string $path, ?array $body = null) use ($browser, $site): array {
        curl_setopt($browser, CURLOPT_URL, 'http://'.$site.$path);
        if ($body === null) curl_setopt($browser, CURLOPT_HTTPGET, true);
        else curl_setopt($browser, CURLOPT_POSTFIELDS, http_build_query($body));
        $response = curl_exec($browser);
        if (!is_string($response)) throw new RuntimeException('Loopback request failed.');
        $size = (int)curl_getinfo($browser, CURLINFO_HEADER_SIZE);
        return [(int)curl_getinfo($browser, CURLINFO_RESPONSE_CODE), substr($response, $size), substr($response, 0, $size)];
    };
    $form = static function (string $markup): array {
        $doc = new DOMDocument();
        $doc->loadHTML($markup, LIBXML_NOERROR | LIBXML_NOWARNING);
        $query = new DOMXPath($doc);
        $inputs = [];
        foreach ($query->query('//form[@action="/pages/campaign/enquiry"]/input[@type="hidden"]') as $input) {
            $inputs[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        return $inputs;
    };

    [$status,$markup,$headers] = $request('/pages/campaign');
    $identity = $form($markup);
    $check($status === 200 && count($identity) === 3 && $identity['revision'] === $revision, 'Published form receives three server-owned identity inputs');
    $check(str_contains(strtolower($headers), 'cache-control: no-store'), 'Session tokens are outside caches');
    $check(!str_contains($request('/pages/legacy')[1], 'campaign-enquiry'), 'Legacy page stays article-only');
    $check($request('/pages/legacy/enquiry', $identity)[0] === 404, 'Legacy page has no enquiry endpoint');
    [$status,$markup] = $request('/pages/draft');
    $check($status === 404 && !str_contains($markup, 'Private campaign phrase'), 'Draft remains private');
    $check($request('/pages/draft/enquiry', $identity)[0] === 404, 'A draft has no public submit endpoint');
    $check(in_array($request('/admin/email-inbox')[0], [302,303], true), 'Guests cannot see the owner Inbox');
    $answers = ['name'=>'Fixture visitor', 'email'=>'fixture@example.invalid', 'detail_2'=>'Fixture question'];
    $post = $identity + compact('answers');
    $submit = static fn(array $change = []): array => $request('/pages/campaign/enquiry', array_replace($post, $change));
    $check($submit(['_csrf'=>['forged']])[0] === 419, 'Malformed CSRF is rejected');
    $check($submit(['revision'=>str_repeat('b', 32)])[0] === 409, 'Stale submission must review the current form');
    $check($submit(['submission_token'=>$identity['submission_token'].'0'])[0] === 419, 'Forged submit token is rejected');
    [$status,$invalid] = $submit(['answers'=>['name'=>'Fixture visitor','email'=>'wrong','forged'=>'unexpected']]);
    $check($status === 422 && str_contains($invalid, 'aria-invalid="true"') && str_contains($invalid, 'Fixture visitor'),
        'Validation errors preserve accepted field text and expose accessible feedback');
    $check(!str_contains($invalid, 'unexpected') && $read()['calls'] === 0, 'Unexpected answers stay out of the form and storage');
    [$status,,$headers] = $submit();
    $check($status === 303 && preg_match('#^Location: /pages/campaign\r?$#mi', $headers) === 1, 'Successful POST redirects to its receipt');
    $check(str_contains($request('/pages/campaign')[1], 'Enquiry received'), 'Native GET shows a receipt without customer answers');
    $check($submit(['answers'=>['name'=>'Changed on replay']])[0] === 303 && $read()['calls'] === 1, 'Repeating the accepted token creates no new mutation');
    $check(str_contains($request('/pages/campaign')[1], 'Enquiry received'), 'Replay shows the same acknowledgement');
    $saved = array_values($read()['contacts'])[0];
    $check($saved['name'] === $answers['name'] && $saved['email'] === $answers['email']
        && $saved['campaign_revision'] === $revision && $saved['campaign_slug'] === 'campaign', 'Typed remote write retains contact identity and origin');

    $newSubmit = static function (string $email) use ($request, $form, $answers): array {
        $identity = $form($request('/pages/campaign')[1]);
        return $request('/pages/campaign/enquiry', $identity + ['answers'=>array_replace($answers, ['email'=>$email])]);
    };
    $check($newSubmit('fixture@example.invalid')[0] === 303, 'A new form admits a second enquiry from the same email');
    $request('/pages/campaign');
    [$status,$limited] = $newSubmit('fixture@example.invalid');
    $check($status === 429 && $read()['calls'] === 2, 'Recipient limit blocks a new receipt without a write');
    $pendingWithdrawal = $read();
    $pendingWithdrawal['next_outcome'] = 'stale';
    $write($pendingWithdrawal);
    [$status,$closed] = $newSubmit('second@example.invalid');
    $check($status === 409 && str_contains($closed, 'enquiry form has closed') && $form($closed) === [],
        'A form withdrawn during submission produces feedback without a new active form');
    $check($request('/pages/campaign/enquiry', $form($limited) + compact('answers'))[0] === 409 && $read()['calls'] === 3,
        'A withdrawn form cannot receive another enquiry');
    $check($submit()[0] === 303 && str_contains($request('/pages/campaign')[1], 'Enquiry received') && $read()['calls'] === 3,
        'An already accepted receipt can still be acknowledged after the form closes');
    $limiter = file_get_contents($root.'/storage/logs/contact-admission.json');
    $check(!str_contains($limiter, 'fixture@example.invalid') && !str_contains($limiter, 'Fixture question'),
        'Admission state contains no visitor text');
    $account = curl_init();
    curl_setopt_array($account, [CURLOPT_URL=>'http://'.$site.'/admin/email-inbox', CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Host: localhost'], CURLOPT_TIMEOUT=>5]);
    foreach (['admin','customer'] as $role) {
        $id = str_repeat($role === 'admin' ? 'a' : 'b', 32);
        file_put_contents($root.'/sessions/sess_'.$id, 'user|'.serialize([
            'sub'=>'fixture-'.$role, 'name'=>'Fixture account', 'email'=>'account@example.invalid', 'role'=>$role,
        ]));
        curl_setopt($account, CURLOPT_COOKIE, 'PHPSESSID='.$id);
        $inbox = curl_exec($account);
        $status = curl_getinfo($account, CURLINFO_RESPONSE_CODE);
        if ($role === 'customer') {
            $check($status === 302 && !str_contains((string)$inbox, 'Private fixture'), 'Customer cannot read owner campaign answers');
        } else {
            $check($status === 200 && str_contains((string)$inbox, 'Campaign campaign') && str_contains((string)$inbox, $revision),
                'Owner Inbox exposes the bounded campaign source');
            $check(str_contains((string)$inbox, 'Private fixture &lt;note&gt;') && str_contains((string)$inbox, 'Regular contact note'),
                'Owner can read escaped campaign answers alongside ordinary contact messages');
        }
    }
    echo "PASS campaign HTTP routing, draft and owner isolation, CSRF, validation, receipt, replay, throttling and withdrawal.\n";
} finally {
    foreach (array_reverse($servers) as $server) { proc_terminate($server); proc_close($server); }
    if (is_dir($root)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }
}
