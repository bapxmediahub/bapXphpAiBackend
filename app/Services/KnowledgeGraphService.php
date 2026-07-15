<?php
namespace App\Services;

final class KnowledgeGraphService {
    private string $root;
    private array $concepts = [];
    private array $edges = [];

    public function __construct(string $root = '') {
        $this->root = $root ?: dirname(__DIR__, 2);
    }

    public function build(): array {
        $this->concepts = [];
        $this->edges = [];

        $this->indexMarkdownFiles($this->root . '/docs', 'doc');
        $this->indexMarkdownFiles($this->root . '/content/blog/posts', 'blog');
        $this->indexSkillFiles();
        $this->indexAgentsFiles();
        $this->indexCodeConcepts();

        return ['concepts' => $this->concepts, 'edges' => $this->edges];
    }

    public function writeOkfBundle(string $outputDir): void {
        $okfDir = $outputDir . '/.okf';
        $this->rmdirRecursive($okfDir);

        foreach ($this->concepts as $id => $c) {
            $typeDir = $okfDir . '/concepts/' . $c['type'];
            if (!is_dir($typeDir)) mkdir($typeDir, 0775, true);

            $outbound = array_values(array_filter($this->edges, fn($e) => $e['from'] === $id));
            $body = $c['body'];
            if ($outbound) {
                $body .= "\n\n## Connections\n";
                foreach ($outbound as $e) {
                    $body .= "- [" . $e['relation'] . " → " . ($this->concepts[$e['to']]['title'] ?? $e['to']) . "](/{$this->concepts[$e['to']]['filePath']})\n";
                }
            }

            $frontmatter = "---\n";
            $frontmatter .= "type: " . $c['type'] . "\n";
            $frontmatter .= "title: " . $this->yamlEscape($c['title']) . "\n";
            $frontmatter .= "description: " . $this->yamlEscape($c['description']) . "\n";
            $frontmatter .= "resource: " . $c['filePath'] . "\n";
            if (!empty($c['tags'])) $frontmatter .= "tags: [" . implode(', ', $c['tags']) . "]\n";
            $frontmatter .= "---\n";

            file_put_contents($typeDir . '/' . $id . '.md', $frontmatter . "\n" . $body);
        }

        $this->writeIndexFile($okfDir, "Knowledge Bundle — bapXphp", "okf_version: \"0.1\"\n", [
            "Systematic map: [docs/systematic-map.mmd](../docs/systematic-map.mmd)",
            "Knowledge map: [docs/map.mmd](../docs/map.mmd)",
            "Concepts: " . count($this->concepts) . ", Edges: " . count($this->edges),
        ]);

        $grouped = [];
        foreach ($this->concepts as $id => $c) {
            $grouped[$c['type']][] = $id;
        }
        foreach ($grouped as $type => $ids) {
            $typeDir = $okfDir . '/concepts/' . $type;
            $this->writeIndexFile($typeDir, ucfirst($type) . " Concepts", "", array_map(fn($id) => "- [" . ($this->concepts[$id]['title'] ?? $id) . "]({$id}.md)", $ids));
        }
    }

    private function writeIndexFile(string $dir, string $title, string $extraFrontmatter, array $bodyLines): void {
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $content = "---\ntitle: {$title}\ntype: index\n{$extraFrontmatter}---\n\n# {$title}\n\n" . implode("\n", $bodyLines) . "\n";
        file_put_contents($dir . '/index.md', $content);
    }

    public function renderMermaid(): string {
        $lines = [
            'flowchart LR',
            '  classDef doc fill:#e0f2fe,stroke:#0369a1,color:#0c4a6e',
            '  classDef blog fill:#fef3c7,stroke:#b45309,color:#78350f',
            '  classDef skill fill:#ede9fe,stroke:#6d28d9,color:#3b0764',
            '  classDef agentfile fill:#fce7f3,stroke:#be185d,color:#831843',
            '  classDef controller fill:#d1fae5,stroke:#059669,color:#064e3b',
            '  classDef service fill:#a7f3d0,stroke:#047857,color:#064e3b',
            '  classDef schema fill:#dbeafe,stroke:#2563eb,color:#1e3a5f',
            '  classDef route fill:#f1f5f9,stroke:#475569,color:#1e293b',
            '',
        ];

        $grouped = [];
        foreach ($this->concepts as $id => $c) {
            $grouped[$c['type']][] = ['id' => $id, 'c' => $c];
        }

        foreach ($grouped as $type => $items) {
            $lines[] = '  subgraph ' . strtoupper($type) . '["' . ucfirst($type) . 's (' . count($items) . ')"]';
            foreach ($items as $item) {
                $id = $item['id'];
                $c = $item['c'];
                $safeId = 'kg_' . $this->stableId($id);
                $display = $c['title'] ?: $id;
                $display = str_replace(['"', '\\'], ['\"', '\\\\'], $display);
                if (mb_strlen($display) > 100) $display = mb_substr($display, 0, 97) . '...';
                $lines[] = '    ' . $safeId . '["' . $display . '"]:::' . $type;
            }
            $lines[] = '  end';
            $lines[] = '';
        }

        foreach ($this->edges as $e) {
            $fromId = 'kg_' . $this->stableId($e['from']);
            $toId = 'kg_' . $this->stableId($e['to']);
            $rel = $e['relation'];
            $lines[] = '  ' . $fromId . ' -- ' . $rel . ' --> ' . $toId;
        }

        return implode("\n", $lines) . "\n";
    }

    private function indexMarkdownFiles(string $dir, string $defaultType): void {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') continue;
            $content = file_get_contents($file->getPathname());
            $frontmatter = $this->parseFrontmatter($content);
            $body = $this->stripFrontmatter($content);
            $name = $file->getBasename('.md');
            $relativePath = str_replace($this->root . '/', '', $file->getPathname());

            $this->addConcept($name, [
                'type' => $frontmatter['type'] ?? $defaultType,
                'title' => $frontmatter['title'] ?? $frontmatter['name'] ?? $name,
                'description' => $frontmatter['description'] ?? $frontmatter['excerpt'] ?? '',
                'filePath' => $relativePath,
                'tags' => $this->extractTags($frontmatter),
                'body' => $body,
            ]);

            $this->extractLinks($body, $name);
        }
    }

    private function indexSkillFiles(): void {
        $skillsDir = $this->root . '/.agents/skills';
        if (!is_dir($skillsDir)) return;
        foreach (glob($skillsDir . '/*/SKILL.md') ?: [] as $file) {
            $name = basename(dirname($file));
            $content = file_get_contents($file);
            $frontmatter = $this->parseFrontmatter($content);
            $body = $this->stripFrontmatter($content);
            $relativePath = str_replace($this->root . '/', '', $file);

            $this->addConcept($name, [
                'type' => $frontmatter['type'] ?? 'skill',
                'title' => $name,
                'description' => $frontmatter['description'] ?? '',
                'filePath' => $relativePath,
                'tags' => ['skill'],
                'body' => $body,
            ]);

            $this->extractLinks($body, $name);
        }

        // Index reference files nested under skills
        foreach (glob($skillsDir . '/*/references/*.md') ?: [] as $file) {
            $content = file_get_contents($file);
            $frontmatter = $this->parseFrontmatter($content);
            $body = $this->stripFrontmatter($content);
            $name = basename($file, '.md');
            $relativePath = str_replace($this->root . '/', '', $file);

            $this->addConcept($name, [
                'type' => $frontmatter['type'] ?? 'doc',
                'title' => $frontmatter['title'] ?? $name,
                'description' => $frontmatter['description'] ?? '',
                'filePath' => $relativePath,
                'tags' => ['reference'],
                'body' => $body,
            ]);

            $this->extractLinks($body, $name);
        }
    }

    private function indexAgentsFiles(): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getBasename() !== 'AGENTS.md' || $file->getPathname() === $this->root . '/AGENTS.md') continue;
            $content = file_get_contents($file->getPathname());
            $frontmatter = $this->parseFrontmatter($content);
            $body = $this->stripFrontmatter($content);
            $relativePath = str_replace($this->root . '/', '', $file->getPathname());
            $name = str_replace(['/', '.md'], ['_', ''], $relativePath);

            $this->addConcept($name, [
                'type' => $frontmatter['type'] ?? 'agentfile',
                'title' => $relativePath,
                'description' => $frontmatter['description'] ?? 'Agent contract',
                'filePath' => $relativePath,
                'tags' => ['agent'],
                'body' => $body,
            ]);
        }
    }

    private function indexCodeConcepts(): void {
        $map = ProjectMapService::scan();

        foreach ($map['controllers'] as $name) {
            $this->addConcept($name, [
                'type' => 'controller', 'title' => $name,
                'description' => 'Controller: ' . $name, 'filePath' => 'app/Controllers/' . $name . '.php',
                'tags' => ['code', 'controller'], 'body' => '',
            ]);
        }
        foreach ($map['services'] as $name) {
            $this->addConcept($name, [
                'type' => 'service', 'title' => $name,
                'description' => 'Service: ' . $name, 'filePath' => 'app/Services/' . $name . '.php',
                'tags' => ['code', 'service'], 'body' => '',
            ]);
        }
        foreach ($map['schema_collections'] as $name) {
            $this->addConcept($name, [
                'type' => 'schema', 'title' => $name,
                'description' => 'Schema collection: ' . $name, 'filePath' => 'storage/schema/collections.php',
                'tags' => ['schema'], 'body' => '',
            ]);
        }

        foreach ($map['routes'] as $route) {
            $rid = self::routeId($route);
            $this->addConcept($rid, [
                'type' => 'route', 'title' => ($route['method'] ?? 'GET') . ' ' . ($route['path'] ?? ''),
                'description' => $route['name'] ?? '', 'filePath' => 'app/routes.php',
                'tags' => ['code', 'route'], 'body' => '',
            ]);

            $controller = explode('@', (string)($route['controller'] ?? ''))[0] ?? '';
            if ($controller) {
                $this->addEdge($rid, $controller, 'handled_by');
            }
            foreach ($route['services'] ?? [] as $svc) {
                $this->addEdge($controller, $svc, 'uses');
            }
        }

        $sc = ProjectMapService::serviceCollections();
        foreach ($sc as $service => $cols) {
            foreach ($cols as $col) {
                $this->addEdge($service, $col, 'stores');
            }
        }
    }

    private function addConcept(string $id, array $data): void {
        if (!isset($this->concepts[$id])) {
            $this->concepts[$id] = $data;
        }
    }

    private function addEdge(string $from, string $to, string $relation): void {
        if ($from === '' || $to === '') return;
        if (!isset($this->concepts[$from]) || !isset($this->concepts[$to])) return;
        $key = $from . '::' . $relation . '::' . $to;
        if (!isset($this->edges[$key])) {
            $this->edges[$key] = ['from' => $from, 'to' => $to, 'relation' => $relation];
        }
    }

    private function parseFrontmatter(string $content): array {
        if (!str_starts_with(trim($content), '---')) return [];
        $parts = explode('---', ltrim($content), 3);
        if (count($parts) < 3) return [];
        $yaml = trim($parts[1]);
        $result = [];
        foreach (explode("\n", $yaml) as $line) {
            if (str_contains($line, ': ')) {
                [$key, $val] = explode(': ', $line, 2);
                $key = trim($key);
                $val = trim($val);
                if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                    $result[$key] = array_map('trim', explode(',', trim($val, '[]')));
                } elseif ($val === 'true') {
                    $result[$key] = true;
                } elseif ($val === 'false') {
                    $result[$key] = false;
                } else {
                    $result[$key] = trim($val, '"\'');
                }
            }
        }
        return $result;
    }

    private function stripFrontmatter(string $content): string {
        if (!str_starts_with(trim($content), '---')) return $content;
        $parts = explode('---', ltrim($content), 3);
        return count($parts) >= 3 ? trim($parts[2]) : $content;
    }

    private function extractLinks(string $body, string $sourceId): void {
        preg_match_all('/\[([^\]]+)\]\(([^)]+\.md)\)/', $body, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $targetPath = $m[2];
            $targetName = basename($targetPath, '.md');
            if ($targetName === $sourceId) continue;
            if (isset($this->concepts[$targetName])) {
                $this->addEdge($sourceId, $targetName, 'references');
            }
        }
    }

    private function extractTags(array $frontmatter): array {
        $tags = [];
        if (!empty($frontmatter['tags']) && is_array($frontmatter['tags'])) $tags = $frontmatter['tags'];
        if (!empty($frontmatter['category'])) $tags[] = $frontmatter['category'];
        return array_values(array_unique($tags));
    }

    private static function routeId(array $route): string {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '_', ($route['method'] ?? 'GET') . '_' . ($route['path'] ?? '')), '_'));
    }

    private function stableId(string $value): string {
        $s = preg_replace('/[^a-zA-Z0-9]/', '_', $value);
        $s = preg_replace('/_+/', '_', $s);
        return strtolower(trim(substr($s, 0, 32), '_'));
    }

    private function mmdEscape(string $value): string {
        return str_replace('"', '\"', $value);
    }

    private function yamlEscape(string $value): string {
        return str_replace('"', '\"', $value);
    }

    private function rmdirRecursive(string $dir): void {
        if (!is_dir($dir)) return;
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
