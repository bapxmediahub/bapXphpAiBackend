<?php
namespace App\Services;

final class AuthoredPageService
{
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public static function validSlug(string $slug): bool {
        return strlen($slug) <= 32 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) === 1;
    }

    /** Validate again on the database host when saving through /remotedb. */
    public static function record(array $input): array {
        $slug = $input['slug'] ?? null;
        if (!is_string($slug) || !self::validSlug($slug)) {
            throw new \InvalidArgumentException('Use up to 32 lowercase letters, digits and hyphens for the page address.');
        }
        $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
        $content = is_string($input['content'] ?? null) ? $input['content'] : '';
        if ($title === '' || mb_strlen($title) > 160 || !mb_check_encoding($title, 'UTF-8')
            || preg_match('/[\x00-\x1F\x7F]/', $title)) {
            throw new \InvalidArgumentException('Enter a single-line page title of up to 160 characters.');
        }
        if (trim($content) === '' || strlen($content) > 65536 || !mb_check_encoding($content, 'UTF-8')
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)) {
            throw new \InvalidArgumentException('Enter Markdown content up to 64 KiB.');
        }
        if (!in_array($input['status'] ?? null, ['draft','published'], true)) {
            throw new \InvalidArgumentException('Review the publication status and reload the editor if necessary.');
        }
        $record = ['id'=>'pg:'.$slug, 'slug'=>$slug, 'title'=>$title, 'content'=>$content,
            'status'=>$input['status'], 'revision'=>bin2hex(random_bytes(16))];
        if (isset($input['campaign_form'])) $record['campaign_form'] = CampaignLeadService::definition($input['campaign_form']);
        if (isset($input['campaign_downloads'])) {
            $record['campaign_downloads'] = CampaignDownloadService::definition($input['campaign_downloads']);
        }
        if (isset($input['campaign_payment'])) $record['campaign_payment'] = CampaignPaymentService::definition($input['campaign_payment']);
        $url = $input['header_image_url'] ?? '';
        if ($url === '') return $record;
        if (!MediaService::isPageHeaderPath($url)) {
            throw new \InvalidArgumentException('Choose a header image from uploaded Media Library images.');
        }
        $alt = $input['header_image_alt'] ?? '';
        if (!is_string($alt) || !mb_check_encoding($alt, 'UTF-8') || mb_strlen(trim($alt)) > 255
            || preg_match('/[\x00-\x1F\x7F]/u', $alt)) {
            throw new \InvalidArgumentException('Enter plain alternative text of up to 255 characters.');
        }
        return $record + ['header_image_url'=>$url, 'header_image_alt'=>trim($alt)];
    }

    /** Indexes and menus only need metadata. The public predicate is applied before searching. */
    private function summaries(bool $public, string $search = '', ?array $slugs = null): array {
        $extract = static fn(string $field): string => "JSON_UNQUOTE(JSON_EXTRACT(_data, '$.$field'))";
        $where = "id LIKE 'pg:%'";
        if ($public) $where .= " AND ".$extract('status')." = 'published'";
        $params = [];
        if ($slugs !== null) {
            foreach ($slugs as $slug) {
                if (is_string($slug) && self::validSlug($slug)) $params[] = 'pg:' . $slug;
            }
            if (!$params) return [];
            $where .= ' AND id IN (' . implode(',', array_fill(0, count($params), '?')) . ')';
        }
        foreach (preg_split('/\s+/u', mb_substr(trim($search), 0, 120), flags:PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $where .= ' AND LOCATE(LOWER(?), LOWER(CONCAT_WS(\' \', '.$extract('title').', '.$extract('content').'))) > 0';
            $params[] = $word;
        }
        $rows = $this->store->query("SELECT SUBSTRING(id, 4) AS slug, ".$extract('title')." AS title, "
            .$extract('status')." AS status, ".$extract('revision')." AS revision FROM settings WHERE $where ORDER BY title, id", $params);
        return array_values(array_filter($rows, static fn(array $row): bool => self::validSlug((string)$row['slug'])));
    }

    public function published(string $search = ''): array { return $this->summaries(true, $search); }
    public function publishedBySlugs(array $slugs): array { return $this->summaries(true, '', $slugs); }
    public function all(): array { return $this->summaries(false); }

    public function find(string $slug): ?array {
        if (!self::validSlug($slug)) return null;
        $page = $this->store->find('settings', 'pg:'.$slug);
        return ($page['slug'] ?? null) === $slug ? $page : null;
    }

    public function findPublic(string $slug): ?array {
        $page = $this->find($slug);
        return ($page['status'] ?? null) === 'published' ? $page : null;
    }

    public static function validRevision(mixed $revision): bool {
        return is_string($revision) && preg_match('/^[a-f0-9]{32}$/D', $revision) === 1;
    }

    public function delete(string $slug, mixed $expected): void {
        if (!self::validSlug($slug) || !self::validRevision($expected)) {
            throw new \InvalidArgumentException('Reload the page before deleting it.');
        }
        if (!$this->store->deleteAuthoredPage($slug, $expected)) {
            throw new \DomainException('The page changed or was removed. Reopen the current version before deleting.');
        }
    }

    public function save(array $input): void {
        $revision = $input['expected_revision'] ?? null;
        if ($revision !== '0' && !self::validRevision($revision)) {
            throw new \InvalidArgumentException('Reload the page editor before saving.');
        }
        if (!$this->store->saveAuthoredPage($input, $revision)) {
            throw new \DomainException('This address already exists or another editor saved a newer version. Your text is below. Copy it before reopening the current version.');
        }
    }

    public static function renderContent(string $markdown): string {
        // Text resembling HTML stays literal. MarkdownRenderer subsequently
        // generates, sanitizes and serializes its own formatting and safe URLs.
        return (new MarkdownRenderer())->render($markdown, allowHtml: false);
    }
}
