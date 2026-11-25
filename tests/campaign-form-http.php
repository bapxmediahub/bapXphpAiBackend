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
    if (($input['action'] ?? null) === 'save_authored_page' && ($input['collection'] ?? null) === 'settings') {
        $record = $input['record'];
        $stored = $fixture['pages'][$record['id']]['revision'] ?? '0';
        $saved = empty($fixture['reject_page_write']) && $input['expected'] === $stored;
        unset($fixture['reject_page_write']);
        if ($saved) $fixture['pages'][$record['id']] = $record;
        file_put_contents($state, json_encode($fixture, JSON_THROW_ON_ERROR), LOCK_EX);
        echo json_encode(['success'=>true, 'saved'=>$saved], JSON_THROW_ON_ERROR);
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
$sandbox = sys_get_temp_dir().'/bapx-campaign-'.bin2hex(random_bytes(8));
$root = $sandbox.'/public';
$private = $sandbox.'/.sps-downloads';
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
        '-d', 'session.name=PHPSESSID', '-d', 'session.serialize_handler=php',
        '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=8M', '-S',$address, $router], [
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
    $gatedBody = 'Synthetic visitor download.';
    $gatedId = str_repeat('e', 32);
    $gatedFile = ['id'=>$gatedId, 'name'=>'Private guide.txt', 'mime'=>'text/plain', 'bytes'=>strlen($gatedBody)];
    $page['campaign_downloads'] = ['enabled'=>true, 'files'=>[$gatedFile]];
    mkdir($private.'/campaign', 0700, true);
    file_put_contents($private.'/campaign/'.$gatedId, $gatedBody);
    mkdir($root.'/.sps-downloads', 0700);
    file_put_contents($root.'/.sps-downloads/canary', 'synthetic direct-storage canary');
    $legacy = array_replace($page, ['id'=>'pg:legacy', 'slug'=>'legacy']);
    unset($legacy['campaign_form'], $legacy['campaign_downloads']);
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
        foreach ($query->query('//form[@action="/pages/campaign/enquiry#campaign-enquiry"]/input[@type="hidden"]') as $input) {
            $inputs[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        return $inputs;
    };

    [$status,$markup,$headers] = $request('/pages/campaign');
    $identity = $form($markup);
    $check($status === 200 && count($identity) === 3 && $identity['revision'] === $revision, 'Published form receives three server-owned identity inputs');
    $check(str_contains(strtolower($headers), 'cache-control: no-store'), 'Session tokens are outside caches');
    $downloadPath = '/pages/campaign/downloads/'.$revision.'/'.$gatedId;
    $check(str_contains($markup, 'Go to enquiry form') && !str_contains($markup, $gatedFile['name'])
        && !str_contains($markup, $gatedId), 'Gated public markup exposes no file names or identifiers');
    $check($request($downloadPath)[0] === 404 && $request('/.sps-downloads/canary')[0] === 404,
        'Anonymous resolving of gated files and direct private-path requests are blocked');
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
    $check($status === 303 && str_contains($headers, 'Location: /pages/campaign#campaign-enquiry'), 'Successful POST redirects to its receipt');
    $receipt = $request('/pages/campaign')[1];
    $check(str_contains($receipt, 'Enquiry received') && str_contains($receipt, $downloadPath), 'Accepted enquiry unlocks the revision in this session');
    [$status,$downloaded,$headers] = $request($downloadPath);
    $check($status === 200 && $downloaded === $gatedBody && str_contains($headers, 'Content-Disposition: attachment')
        && str_contains($headers, 'filename*=UTF-8\'\'Private%20guide.txt')
        && str_contains(strtolower($headers), 'cache-control: private, no-store'),
        'The PHP resolver streams an uncacheable attachment with the original name');
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
    $check($request($downloadPath)[0] === 404, 'Updating the page invalidates its old download path');
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
    $adminSession = str_repeat('a', 32);
    $adminCsrf = 'synthetic-admin-csrf';
    file_put_contents($root.'/sessions/sess_'.$adminSession, 'user|'.serialize([
        'sub'=>'fixture-admin', 'name'=>'Fixture account', 'email'=>'account@example.invalid', 'role'=>'admin',
    ]).'csrf_token|'.serialize($adminCsrf));
    curl_setopt($account, CURLOPT_COOKIE, 'PHPSESSID='.$adminSession);
    curl_setopt($account, CURLOPT_URL, 'http://'.$site.'/admin/pages/save');
    $adminSave = static function (array $change = []) use ($account, $adminCsrf): array {
        $base = ['_csrf'=>$adminCsrf, 'title'=>'Download fixtures', 'content'=>'## Visitor guide', 'slug'=>'download-fixtures',
            'status'=>'published', 'action'=>'save', 'original_slug'=>'', 'expected_revision'=>'0',
            'download_editor'=>'1', 'download_enabled'=>'1'];
        curl_setopt($account, CURLOPT_POSTFIELDS, array_filter(array_replace($base, $change), static fn($value): bool => $value !== null));
        $html = curl_exec($account);
        return [curl_getinfo($account, CURLINFO_RESPONSE_CODE), $html];
    };
    $makeUpload = static function (string $name, string $text) use ($sandbox): CURLFile {
        $path = $sandbox.'/'.$name;
        file_put_contents($path, $text);
        return new CURLFile($path, 'text/plain', $name);
    };
    $text = str_repeat("Synthetic guide line.\n", 250_000);
    $large = $makeUpload('large-guide.txt', substr($text, 0, 4_999_995));
    $over = $makeUpload('over-guide.txt', substr($text, 0, 4_999_996));
    $small = $makeUpload('notes.txt', 'Notes');
    $extra = $makeUpload('extra.txt', "X\n");
    [$status,$overMessage] = $adminSave(['download_files[0]'=>$over,'download_files[1]'=>$small]);
    $check($status === 422 && str_contains($overMessage, 'Download files must total 5,000,000 bytes')
        && !is_dir($private.'/download-fixtures'), 'One byte over the exact limit is rejected before creating files');
    $upload = ['download_files[0]'=>$large, 'download_files[1]'=>$small];
    [$status,$preview] = $adminSave(['action'=>'preview'] + $upload);
    $check($status === 200 && str_contains($preview, 'large-guide.txt') && !str_contains($preview, '/downloads/')
        && !is_dir($private.'/download-fixtures'), 'Preview displays prospective names without storing files or exposing endpoints');
    [$status,$savedMarkup] = $adminSave($upload);
    $check($status === 302, 'The exact 5,000,000-byte selection saves as one page revision, got '.$status);
    $downloadPage = $read()['pages']['pg:download-fixtures'];
    $fileRows = $downloadPage['campaign_downloads']['files'];
    $downloadRevision = $downloadPage['revision'];
    $check(array_sum(array_column($fileRows, 'bytes')) === 5_000_000 && count($fileRows) === 2,
        'Hosted manifest records the actual byte total');
    $openPath = '/pages/download-fixtures/downloads/'.$downloadRevision.'/'.$fileRows[1]['id'];
    $check($request($openPath)[1] === 'Notes' && str_contains($request('/pages/download-fixtures')[1], $openPath),
        'A published page without a form offers free downloads');
    $edit = ['original_slug'=>'download-fixtures', 'expected_revision'=>$downloadRevision];
    $positions = ['download_existing['.$fileRows[0]['id'].']'=>'2', 'download_existing['.$fileRows[1]['id'].']'=>'1'];
    $check($adminSave($edit + $positions + ['download_files[0]'=>$extra])[0] === 422
        && $read()['pages']['pg:download-fixtures']['revision'] === $downloadRevision,
        'A selection above the limit across retained and new files rejects the whole change');
    $invalid = $makeUpload('unsupported.html', '<html><title>Invalid download</title></html>');
    $check($adminSave($edit + $positions + ['download_files[0]'=>$invalid])[0] === 422, 'Unsupported files fail without changing the page');
    $check($adminSave($edit + $positions)[0] === 302, 'Owner can reorder both retained files');
    $downloadPage = $read()['pages']['pg:download-fixtures'];
    $check(array_column($downloadPage['campaign_downloads']['files'], 'id') === array_reverse(array_column($fileRows, 'id'))
        && $request($openPath)[0] === 404, 'Reordering saves a fresh revision and revokes old paths');
    $check($adminSave($edit + $positions)[0] === 409, 'Stale concurrent edits preserve the current file selection');
    $edit['expected_revision'] = $downloadPage['revision'];
    $removeLarge = array_replace($positions, ['download_existing['.$fileRows[0]['id'].']'=>'']);
    $check($adminSave($edit + $removeLarge + ['download_enabled'=>''])[0] === 422, 'Forged enable setting is rejected');
    $check($adminSave($edit + $removeLarge)[0] === 302, 'Owner can remove an attachment without dropping the retained file');
    $downloadPage = $read()['pages']['pg:download-fixtures'];
    $latestPath = '/pages/download-fixtures/downloads/'.$downloadPage['revision'].'/'.$fileRows[1]['id'];
    $removedPath = '/pages/download-fixtures/downloads/'.$downloadPage['revision'].'/'.$fileRows[0]['id'];
    $check($request($removedPath)[0] === 404 && $request($latestPath)[1] === 'Notes',
        'Removed objects do not resolve against the new published manifest');
    $edit['expected_revision'] = $downloadPage['revision'];
    $disabled = ['download_enabled'=>null] + $edit + ['download_existing['.$fileRows[1]['id'].']'=>'1'];
    $check($adminSave($disabled)[0] === 302 && $request($latestPath)[0] === 404
        && !str_contains($request('/pages/download-fixtures')[1], 'notes.txt'),
        'Disabling downloads withdraws routes and display');
    symlink($private.'/campaign', $private.'/rejected-storage');
    $check($adminSave(['slug'=>'rejected-storage','download_files[0]'=>$small])[0] === 422
        && !isset($read()['pages']['pg:rejected-storage']), 'Unavailable private storage prevents publication');
    $race = $read();
    $race['reject_page_write'] = true;
    $write($race);
    $check($adminSave(['slug'=>'rejected-save','download_files[0]'=>$small])[0] === 409
        && count(scandir($private.'/rejected-save')) === 2, 'Definitive rejected save removes new unreferenced bytes');
    echo "PASS protected download upload bounds, staging and rollback, exact revision, entitlement, cache-safe attachment and private path denial.\n";
    echo "PASS campaign HTTP routing, draft and owner isolation, CSRF, validation, receipt, replay, throttling and withdrawal.\n";
} finally {
    foreach (array_reverse($servers) as $server) { proc_terminate($server); proc_close($server); }
    if (is_dir($sandbox)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($sandbox);
    }
}
