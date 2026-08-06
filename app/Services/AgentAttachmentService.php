<?php
namespace App\Services;

/**
 * Resolves `@name` in an agent message to something the model can actually read.
 *
 * The admin used to have no way to point the agent at a specific document. Asking
 * "tighten the refund wording in the terms" meant pasting the whole file into the chat,
 * and asking about an uploaded image was impossible — the agent had no idea it existed.
 *
 * `@terms`, `@privacy` and `@any-blog-slug` bring that document's text into the prompt.
 * `@filename.jpg` brings a media file's path, so the agent can put it in a draft.
 *
 * Visibility is inherited, never re-implemented: markdown posts come from
 * BlogService::all(), which already withholds unpublished posts and posts whose module
 * the owner has switched off. A hidden category stays hidden here, so the agent cannot
 * quote a document the public cannot see.
 */
final class AgentAttachmentService
{
    /** Characters allowed after @ — a slug or a filename. */
    private const TOKEN = '/@([A-Za-z0-9][A-Za-z0-9._-]{1,80})/';

    /** Never let one large document crowd the rest of the prompt out. */
    private const MAX_CHARS = 6000;

    public function __construct(
        private ?BlogService $blog = null,
        private ?MediaService $media = null
    ) {
        $this->blog ??= new BlogService();
        $this->media ??= new MediaService();
    }

    /** @return string[] The @tokens in a message, without the @, in order, deduped. */
    public function tokens(string $message): array
    {
        if (!preg_match_all(self::TOKEN, $message, $m)) return [];
        return array_values(array_unique($m[1]));
    }

    /**
     * Prompt text for every @token that resolves, and the names that did not.
     *
     * @return array{context: string, resolved: string[], missing: string[]}
     */
    public function resolve(string $message): array
    {
        $context = '';
        $resolved = [];
        $missing = [];

        foreach ($this->tokens($message) as $token) {
            $found = $this->legalPage($token) ?? $this->blogPost($token) ?? $this->mediaFile($token);
            if ($found === null) { $missing[] = $token; continue; }
            $resolved[] = $token;
            $context .= "\n\n--- @{$token} ({$found['kind']}) ---\n" . $found['body'];
        }

        return ['context' => $context, 'resolved' => $resolved, 'missing' => $missing];
    }

    /** Everything the admin may write @ against, for the chat's autocomplete. */
    public function catalogue(): array
    {
        $out = [];
        foreach (['terms', 'privacy'] as $slug) {
            if (is_file($this->legalPath($slug))) $out[] = ['name' => $slug, 'kind' => 'page'];
        }
        foreach ($this->blog->all(false) as $post) {
            $slug = (string)($post['slug'] ?? '');
            if ($slug !== '') $out[] = ['name' => $slug, 'kind' => 'article'];
        }
        foreach ($this->mediaIndex() as $name => $path) {
            $out[] = ['name' => $name, 'kind' => 'image', 'path' => $path];
        }
        return $out;
    }

    private function legalPath(string $slug): string
    {
        return app_path('content/legal/' . $slug . '.md');
    }

    /** @return array{kind:string, body:string}|null */
    private function legalPage(string $token): ?array
    {
        $slug = strtolower($token);
        if (!in_array($slug, ['terms', 'privacy'], true)) return null;
        $file = $this->legalPath($slug);
        if (!is_file($file)) return null;
        $raw = (string)@file_get_contents($file);
        return ['kind' => 'legal page', 'body' => $this->trim($this->stripFrontmatter($raw))];
    }

    /** @return array{kind:string, body:string}|null */
    private function blogPost(string $token): ?array
    {
        $slug = strtolower($token);
        foreach ($this->blog->all(false) as $post) {
            if (strtolower((string)($post['slug'] ?? '')) !== $slug) continue;
            $body = trim((string)($post['content'] ?? $post['body'] ?? ''));
            if ($body === '') {
                $file = app_path('content/blog/posts/' . $slug . '.md');
                $body = is_file($file) ? $this->stripFrontmatter((string)@file_get_contents($file)) : '';
            }
            $title = (string)($post['title'] ?? $slug);
            return ['kind' => 'article', 'body' => $this->trim("# {$title}\n\n" . $body)];
        }
        return null;
    }

    /** @return array{kind:string, body:string}|null */
    private function mediaFile(string $token): ?array
    {
        $index = $this->mediaIndex();
        $key = strtolower($token);
        if (!isset($index[$key])) return null;
        // An image is a path the agent can put in a draft, not text it can read.
        return ['kind' => 'image', 'body' => 'Image path: ' . $index[$key]];
    }

    /** @return array<string,string> lowercased name (with and without extension) => url */
    private function mediaIndex(): array
    {
        $index = [];
        try {
            foreach ($this->media->all() as $item) {
                $path = (string)($item['url'] ?? $item['path'] ?? '');
                if ($path === '') continue;
                $base = basename($path);
                $index[strtolower($base)] = $path;
                $index[strtolower(pathinfo($base, PATHINFO_FILENAME))] = $path;
            }
        } catch (\Throwable $e) {
            error_log('Agent media index failed: ' . $e->getMessage());
        }
        return $index;
    }

    /** Frontmatter is instructions to the renderer, never content for a reader. */
    private function stripFrontmatter(string $raw): string
    {
        return trim((string)preg_replace('/\A---\R.*?\R---\R?/s', '', $raw));
    }

    private function trim(string $body): string
    {
        return mb_strlen($body) > self::MAX_CHARS
            ? mb_substr($body, 0, self::MAX_CHARS) . "\n\n[truncated]"
            : $body;
    }
}
