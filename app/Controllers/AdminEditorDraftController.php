<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuditLogService,AuthoredPageService,BlogService,DatabaseService,EditorDraftService,SettingsService};

final class AdminEditorDraftController extends BaseController
{
    public function handle(): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user)) $this->jsonResponse(['error'=>'Sign in to recover your draft.'],401);
        try { $owner = EditorDraftService::owner($user); }
        catch (\DomainException) { $this->jsonResponse(['error'=>'Owner access required.'],403); }
        $csrf = $_POST['_csrf'] ?? null;
        $sessionCsrf = $_SESSION['csrf_token'] ?? null;
        if (!is_string($csrf) || !is_string($sessionCsrf) || $sessionCsrf === '' || !hash_equals($sessionCsrf,$csrf)) {
            $this->jsonResponse(['error'=>'Your editor session expired.'],419);
        }
        session_write_close();
        try {
            $kind = $_POST['kind'] ?? null; $target = $_POST['target'] ?? null;
            $operation = $_POST['operation'] ?? null; $revision = $_POST['revision'] ?? null;
            $expected = $_POST['expected'] ?? '';
            foreach ([$kind,$target,$operation,$revision,$expected] as $value) {
                if (!is_string($value)) throw new \InvalidArgumentException('Invalid draft request.');
            }
            EditorDraftService::identity($owner,$kind,$target);
            EditorDraftService::revision($kind,$target,$revision);
            if (!in_array($operation,['read','write','discard'],true)) throw new \InvalidArgumentException('Invalid draft operation.');
            if ($kind === 'blog' && !(new SettingsService())->moduleEnabled('blog')) {
                $this->jsonResponse(['error'=>'Blog is unavailable.'],404);
            }
            $current = $target === '' ? ($kind === 'page' ? '0' : 'new')
                : ($kind === 'page' ? ((new AuthoredPageService())->find($target)['revision'] ?? null)
                    : ((new BlogService())->find($target)['_revision'] ?? null));
            if ($operation === 'write' && $current !== $revision) {
                $this->jsonResponse(['outcome'=>'stale','currentRevision'=>$current],409);
            }
            $values = [];
            if ($operation === 'write') {
                $raw = $_POST['values'] ?? null;
                if (!is_string($raw) || strlen($raw)>2500000) throw new \InvalidArgumentException('Draft exceeds its request limit.');
                $values = json_decode($raw,true,flags:JSON_THROW_ON_ERROR);
                if (!is_array($values)) throw new \InvalidArgumentException('Invalid draft values.');
            }
            $result = (new DatabaseService())->editorDraftOperation($owner,$kind,$target,$operation,$expected,$revision,$values);
            if ($operation === 'discard' && $result['outcome'] === 'discarded' && $expected !== '') {
                (new AuditLogService())->record('discard','editor_recovery',EditorDraftService::identity($owner,$kind,$target),['kind'=>$kind],$user);
            }
            if ($operation === 'write' && $result['outcome'] === 'saved') {
                // Full recovery versions are needed only on read/conflict, not
                // for every successful autosave acknowledgment.
                $result = ['outcome'=>'saved','token'=>$result['draft']['token']];
            }
            $this->jsonResponse($result+['currentRevision'=>$current],$result['outcome']==='conflict'?409:200);
        } catch (\InvalidArgumentException|\JsonException) {
            $this->jsonResponse(['error'=>'Draft fields are invalid or exceed their limits.'],422);
        } catch (\Throwable $error) {
            error_log('Editor recovery failed ('.$error::class.')');
            $this->jsonResponse(['error'=>'Server recovery is temporarily unavailable. Keep your browser copy.'],503);
        }
    }
}
