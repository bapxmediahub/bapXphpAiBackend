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
        $this->indexImageFiles();
        $this->indexMissingReferencedImages();
        $this->indexSkillFiles();
        $this->indexAgentsFiles();
        $this->indexCodeConcepts();

        return ['concepts' => $this->concepts, 'edges' => $this->edges];
    }

    public function writeYamlIndex(string $outputFile): void {
        $concepts = [];
        foreach ($this->concepts as $id => $concept) {
            $row = [
                'id' => $id,
                'type' => $concept['type'],
                'title' => $concept['title'],
                'description' => $concept['description'],
                'resource' => $concept['filePath'],
                'tags' => array_values($concept['tags'] ?? []),
            ];
            foreach (['filename', 'mime', 'header_image', 'exists', 'status', 'usage_count', 'used_in', 'data_source', 'collection', 'schema_file', 'method', 'path', 'controller', 'services', 'view'] as $field) {
                if (array_key_exists($field, $concept)) $row[$field] = $concept[$field];
            }
            $concepts[] = $row;
        }
        usort($concepts, fn(array $a, array $b): int => [$a['type'], $a['id']] <=> [$b['type'], $b['id']]);
        $edges = array_values($this->edges);
        usort($edges, fn(array $a, array $b): int => [$a['from'], $a['relation'], $a['to']] <=> [$b['from'], $b['relation'], $b['to']]);

        $appUrl = rtrim((string)((require $this->root . '/config/database.php')['app_url'] ?? ''), '/');
        $index = [
            'format' => 'bapx-project-knowledge-index',
            'version' => '1.0',
            'generated_by' => 'cli/generate-okf-bundle.php',
            'authoritative_sources' => [
                'runtime_data' => $appUrl . '/remotedb',
                'schema' => 'storage/schema/collections.php',
                'blogs' => 'content/blog/posts/*.md',
                'images' => 'assets/images/**/*',
                'code_map' => 'map.mmd',
                'documentation_map' => 'docs/map.mmd',
            ],
            'rules' => [
                'Concepts are original project resources; this index never copies blog bodies or runtime records.',
                'Products, categories, consultants, temples and other editable records are queried from remotedb.',
                'Blog Markdown and image binaries remain local files.',
                'Read discovery first; query only the relevant section or resource. Do not load this entire index into agent context.',
                'Use at most three discovery hops: entry instructions, exact index match, original source. Avoid broad listings and recursive scans unless the target is absent.',
            ],
            'discovery' => [
                ['question' => 'Does a route, controller, service, view or collection exist?', 'use' => 'docs/project-index.json'],
                ['question' => 'How are routes, controllers, services and collections connected?', 'use' => 'map.mmd'],
                ['question' => 'What is the live product, category, consultant, temple or admin-editable value?', 'use' => $appUrl . '/remotedb'],
                ['question' => 'Where is a blog article and what frontmatter does it declare?', 'use' => 'concepts filtered by type=blog, then read its resource'],
                ['question' => 'Where is an image and which source files reference it?', 'use' => 'concepts filtered by type=image; query by filename or resource'],
                ['question' => 'What repository instructions or skill apply?', 'use' => 'AGENTS.md, CLAUDE.md, then .agents/skills or .claude/skills'],
            ],
            'query_examples' => [
                'sed -n \'1,90p\' index.yaml',
                'rg -n -B4 -A14 \'id: "<concept-id>"\' index.yaml',
                'rg -n -A12 \'type: "blog"\' index.yaml',
                'rg -n -B4 -A12 \'filename: "<name>"\' index.yaml',
                'rg -n -B4 -A14 \'path: "/shop"\' index.yaml',
                'rg -n -B4 -A12 \'type: "skill"\' index.yaml',
                'rg -n \'<term>\' docs/project-index.json map.mmd index.yaml',
            ],
            'summary' => ['concepts' => count($concepts), 'relationships' => count($edges), 'by_type' => array_count_values(array_column($concepts, 'type'))],
            'concepts' => $concepts,
            'relationships' => $edges,
        ];
        file_put_contents($outputFile, $this->toYaml($index));
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

            $conceptType = (string)($frontmatter['type'] ?? $defaultType);
            $conceptId = $conceptType . ':' . $name;
            $this->addConcept($conceptId, [
                'type' => $conceptType,
                'title' => $frontmatter['title'] ?? $frontmatter['name'] ?? $name,
                'description' => $frontmatter['description'] ?? $frontmatter['excerpt'] ?? '',
                'filePath' => $relativePath,
                'filename' => $file->getFilename(),
                'header_image' => $frontmatter['og_image'] ?? $frontmatter['image_url'] ?? '',
                'tags' => $this->extractTags($frontmatter),
                'body' => $body,
            ]);

            $this->extractLinks($body, $conceptId);
        }
    }

    private function indexImageFiles(): void {
        $imageRoot = $this->root . '/assets/images';
        if (!is_dir($imageRoot)) return;
        $usageFiles = $this->usageFiles();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($imageRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) continue;
            $relativePath = str_replace($this->root . '/', '', $file->getPathname());
            $usedIn = [];
            foreach ($usageFiles as $usagePath => $content) {
                if (str_contains($content, $relativePath) || str_contains($content, '/' . $relativePath)) $usedIn[] = $usagePath;
            }
            $this->addConcept('image:' . $relativePath, [
                'type' => 'image',
                'title' => $file->getFilename(),
                'description' => $usedIn ? 'Image referenced by ' . count($usedIn) . ' project resource(s).' : 'Image with no static repository reference detected.',
                'filePath' => $relativePath,
                'filename' => $file->getFilename(),
                'mime' => $this->imageMime($file->getExtension()),
                'usage_count' => count($usedIn),
                'used_in' => $usedIn,
                'tags' => ['image', $usedIn ? 'used' : 'unreferenced'],
                'body' => '',
            ]);
        }
    }

    private function usageFiles(): array {
        $result = [];
        foreach (['content/blog/posts', 'views', 'app', 'assets/css', 'assets/js'] as $relativeDir) {
            $dir = $this->root . '/' . $relativeDir;
            if (!is_dir($dir)) continue;
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['md', 'php', 'css', 'js'], true)) continue;
                $content = @file_get_contents($file->getPathname());
                if ($content !== false) $result[str_replace($this->root . '/', '', $file->getPathname())] = $content;
            }
        }
        return $result;
    }

    private function imageMime(string $extension): string {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', default => 'application/octet-stream',
        };
    }

    private function indexMissingReferencedImages(): void {
        foreach ($this->concepts as $concept) {
            if (($concept['type'] ?? '') !== 'blog') continue;
            $headerImage = trim((string)($concept['header_image'] ?? ''));
            if ($headerImage === '') continue;
            $urlPath = (string)(parse_url($headerImage, PHP_URL_PATH) ?: $headerImage);
            $relativePath = ltrim($urlPath, '/');
            if (!str_starts_with($relativePath, 'assets/images/')) continue;
            if (is_file($this->root . '/' . $relativePath)) continue;
            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            $this->addConcept('image:' . $relativePath, [
                'type' => 'image',
                'title' => basename($relativePath),
                'description' => 'Referenced local image binary is missing.',
                'filePath' => $relativePath,
                'filename' => basename($relativePath),
                'mime' => $this->imageMime($extension),
                'exists' => false,
                'status' => 'missing',
                'usage_count' => 1,
                'used_in' => [$concept['filePath']],
                'tags' => ['image', 'missing', 'referenced'],
                'body' => '',
            ]);
        }
    }

    private function indexSkillFiles(): void {
        $skillsDir = $this->root . '/.claude/skills';
        if (!is_dir($skillsDir)) return;
        foreach (glob($skillsDir . '/*/SKILL.md') ?: [] as $file) {
            $name = basename(dirname($file));
            $content = file_get_contents($file);
            $frontmatter = $this->parseFrontmatter($content);
            $body = $this->stripFrontmatter($content);
            $relativePath = str_replace($this->root . '/', '', $file);

            $conceptId = 'skill:' . $name;
            $this->addConcept($conceptId, [
                'type' => $frontmatter['type'] ?? 'skill',
                'title' => $name,
                'description' => $frontmatter['description'] ?? '',
                'filePath' => $relativePath,
                'tags' => ['skill'],
                'body' => $body,
            ]);

            $this->extractLinks($body, $conceptId);
        }

        // Index reference files nested under skills
        foreach (glob($skillsDir . '/*/references/*.md') ?: [] as $file) {
            $content = file_get_contents($file);
            $frontmatter = $this->parseFrontmatter($content);
            $body = $this->stripFrontmatter($content);
            $name = basename($file, '.md');
            $relativePath = str_replace($this->root . '/', '', $file);

            $conceptId = 'doc:skill-reference:' . basename(dirname(dirname($file))) . ':' . $name;
            $this->addConcept($conceptId, [
                'type' => $frontmatter['type'] ?? 'doc',
                'title' => $frontmatter['title'] ?? $name,
                'description' => $frontmatter['description'] ?? '',
                'filePath' => $relativePath,
                'tags' => ['reference'],
                'body' => $body,
            ]);

            $this->extractLinks($body, $conceptId);
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

            $this->addConcept('agentfile:' . $name, [
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
            $this->addConcept('controller:' . $name, [
                'type' => 'controller', 'title' => $name,
                'description' => 'Controller: ' . $name, 'filePath' => 'app/Controllers/' . $name . '.php',
                'tags' => ['code', 'controller'], 'body' => '',
            ]);
        }
        foreach ($map['services'] as $name) {
            $this->addConcept('service:' . $name, [
                'type' => 'service', 'title' => $name,
                'description' => 'Service: ' . $name, 'filePath' => 'app/Services/' . $name . '.php',
                'tags' => ['code', 'service'], 'body' => '',
            ]);
        }
        foreach ($map['schema_collections'] as $name) {
            $this->addConcept('schema:' . $name, [
                'type' => 'schema', 'title' => $name,
                'description' => 'Schema collection: ' . $name, 'filePath' => 'storage/schema/collections.php',
                'data_source' => 'remotedb', 'collection' => $name, 'schema_file' => 'storage/schema/collections.php',
                'tags' => ['schema'], 'body' => '',
            ]);
        }

        foreach ($map['routes'] as $route) {
            $rid = self::routeId($route);
            $this->addConcept($rid, [
                'type' => 'route', 'title' => ($route['method'] ?? 'GET') . ' ' . ($route['path'] ?? ''),
                'description' => $route['name'] ?? '', 'filePath' => 'app/routes.php',
                'method' => $route['method'] ?? 'GET', 'path' => $route['path'] ?? '',
                'controller' => $route['controller'] ?? '', 'services' => array_values($route['services'] ?? []),
                'view' => $route['page'] ?? '',
                'tags' => ['code', 'route'], 'body' => '',
            ]);

            $controller = explode('@', (string)($route['controller'] ?? ''))[0] ?? '';
            if ($controller) {
                $this->addEdge($rid, 'controller:' . $controller, 'handled_by');
            }
            foreach ($route['services'] ?? [] as $svc) {
                $this->addEdge('controller:' . $controller, 'service:' . $svc, 'uses');
            }
        }

        $sc = ProjectMapService::serviceCollections();
        foreach ($sc as $service => $cols) {
            foreach ($cols as $col) {
                $this->addEdge('service:' . $service, 'schema:' . $col, 'stores');
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
            foreach (['doc:' . $targetName, 'blog:' . $targetName, 'skill:' . $targetName] as $targetId) {
                if ($targetId === $sourceId) continue;
                if (isset($this->concepts[$targetId])) {
                    $this->addEdge($sourceId, $targetId, 'references');
                    break;
                }
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
        return 'route:' . strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '_', ($route['method'] ?? 'GET') . '_' . ($route['path'] ?? '')), '_'));
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

    private function toYaml(mixed $value, int $indent = 0): string {
        if (!is_array($value)) return str_repeat(' ', $indent) . $this->yamlScalar($value) . "\n";
        $yaml = '';
        $isList = array_is_list($value);
        foreach ($value as $key => $item) {
            $prefix = str_repeat(' ', $indent) . ($isList ? '- ' : $key . ': ');
            if (is_array($item)) {
                if ($item === []) {
                    $yaml .= $prefix . "[]\n";
                } elseif ($isList && !array_is_list($item)) {
                    $firstKey = array_key_first($item);
                    $firstValue = $item[$firstKey];
                    if (!is_array($firstValue)) {
                        $yaml .= $prefix . $firstKey . ': ' . $this->yamlScalar($firstValue) . "\n";
                        unset($item[$firstKey]);
                        $yaml .= $this->toYaml($item, $indent + 2);
                    } else {
                        $yaml .= rtrim($prefix) . "\n" . $this->toYaml($item, $indent + 2);
                    }
                } else {
                    $yaml .= rtrim($prefix) . "\n" . $this->toYaml($item, $indent + 2);
                }
            } else {
                $yaml .= $prefix . $this->yamlScalar($item) . "\n";
            }
        }
        return $yaml;
    }

    private function yamlScalar(mixed $value): string {
        if ($value === null) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_int($value) || is_float($value)) return (string)$value;
        return json_encode((string)$value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
