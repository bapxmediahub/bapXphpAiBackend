<?php
namespace App\Services;

final class DocsMapService
{
    private string $root;

    public function __construct(string $root = '')
    {
        $this->root = $root ?: dirname(__DIR__, 2);
    }

    public function generate(): string
    {
        $lines = [
            'mindmap',
            '  root(("Knowledge Map — bapXphp"))',
            '',
            '    ### Repository Intelligence',
            '    AGENTS_FILES["Root AGENTS.md contract"]',
            '    SYSTEMATIC_MAP["docs/systematic-map.mmd"]',
            '    COLLECTIONS_SCHEMA["storage/schema/collections.php"]',
            '    DESIGN_SYSTEM["Design.md"]',
            '',
        ];

        $lines = array_merge($lines, $this->cliSection());
        $lines = array_merge($lines, $this->skillsSection());
        $lines = array_merge($lines, $this->docSourcesSection());
        $lines = array_merge($lines, $this->blogSection());
        $lines = array_merge($lines, $this->architectureSection());
        $lines = array_merge($lines, $this->dataLayerSection());
        $lines = array_merge($lines, $this->adminSection());
        $lines = array_merge($lines, $this->integrationsSection());

        $lines = array_merge($lines, $this->controllersSection());
        $lines = array_merge($lines, $this->servicesSection());
        $lines = array_merge($lines, $this->collectionsSection());

        $docFiles = $this->findDocFiles();
        if ($docFiles) {
            $lines[] = '';
            $lines[] = '    ### Discovered Documentation Files';
            foreach ($docFiles as $file) {
                $id = 'DOCFILE_' . $this->stableId($file);
                $lines[] = "    {$id}[\"{$file}\"]";
            }
        }

        $agentsFiles = $this->findAgentsFiles();
        if ($agentsFiles) {
            $lines[] = '';
            $lines[] = '    ### Agent Contract';
            foreach ($agentsFiles as $file) {
                $id = 'AGENTFILE_' . $this->stableId($file);
                $lines[] = "    {$id}[\"{$file}\"]";
            }
        }

        $gaps = $this->findCoverageGaps();
        if ($gaps) {
            $lines[] = '';
            $lines[] = '    ### Coverage Gaps';
            foreach ($gaps as $label) {
                $id = 'GAP_' . $this->stableId($label);
                $lines[] = "    {$id}[\"{$label}\"]";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function cliSection(): array
    {
        $lines = ["    ### CLI (bapXphp)"];
        $cliFiles = $this->phpBasenames($this->root . '/cli');
        foreach ($cliFiles as $f) {
            $lines[] = "    CLI_" . $this->stableId($f) . '["' . $this->label($f) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function skillsSection(): array
    {
        $lines = ["    ### Agent Skills"];
        $dirs = glob($this->root . '/.agents/skills/*/SKILL.md') ?: [];
        foreach ($dirs as $skillFile) {
            $name = basename(dirname($skillFile));
            $lines[] = '    SKILL_' . $this->stableId($name) . '["' . $this->label($name) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function docSourcesSection(): array
    {
        $lines = ["    ### Documentation Sources"];
        $docs = glob($this->root . '/docs/*.md') ?: [];
        foreach ($docs as $f) {
            $name = basename($f);
            $lines[] = '    DOCS_' . $this->stableId($name) . '["' . $this->label('docs/' . $name) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function blogSection(): array
    {
        return [
            "    ### Blog & Content (GitHub-sourced)",
            '    BLOG_INDEX["GitHub raw → blog index JSON"]',
            '    BLOG_POSTS["GitHub raw → .md posts"]',
            '    BLOG_CATEGORIES["GitHub raw → categories JSON"]',
            '    RELEASE_NOTES["GitHub raw → release .md"]',
            '    FEATURE_UPDATES["GitHub raw → features .md"]',
            '    CACHE["storage/cache/*.md"]',
            '',
        ];
    }

    private function architectureSection(): array
    {
        return [
            "    ### Application Architecture",
            '    PHP_BOOTSTRAP["app/bootstrap.php"]',
            '    PHP_ROUTER["app/Router.php"]',
            '    PHP_CONTROLLERS["app/Controllers/"]',
            '    PHP_SERVICES["app/Services/"]',
            '    PHP_VIEWS["views/ (layouts + public + account + admin)"]',
            '    PHP_API["api/index.php"]',
            '    PHP_INTEGRATIONS["integrations/"]',
            '',
        ];
    }

    private function dataLayerSection(): array
    {
        return [
            "    ### Data Layer",
            '    MYSQL_TABLES["MySQL Database — primary runtime store"]',
            '    SCHEMA_DEF["storage/schema/collections.php"]',
            '    MEDIA_FILES["assets/images/media/"]',
            '    MEDIA_INDEX["MySQL media_files table"]',
            '    BACKUPS["storage/backups/"]',
            '',
        ];
    }

    private function adminSection(): array
    {
        $lines = ["    ### Admin Panel"];
        $routes = \App\Services\ProjectMapService::registry()['routes'] ?? [];
        foreach ($routes as $route) {
            if (str_starts_with($route['path'] ?? '', '/admin') && ($route['method'] ?? 'GET') === 'GET') {
                $id = 'ADMIN_' . $this->stableId($route['path']);
                $lines[] = '    ' . $id . '["' . $this->label($route['method'] . ' ' . $route['path']) . '"]';
            }
        }
        $lines[] = '';
        return $lines;
    }

    private function integrationsSection(): array
    {
        $lines = ["    ### External Integrations"];
        $integrations = \App\Services\ProjectMapService::phpBasenames($this->root . '/integrations');
        foreach ($integrations as $name) {
            $lines[] = '    INTEG_' . $this->stableId($name) . '["' . $this->label($name) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function controllersSection(): array
    {
        $lines = ["    ### Controllers"];
        $scan = ProjectMapService::scan();
        foreach ($scan['controllers'] ?? [] as $name) {
            $lines[] = '    CTRL_' . $this->stableId($name) . '["' . $this->label($name) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function servicesSection(): array
    {
        $lines = ["    ### Services"];
        $scan = ProjectMapService::scan();
        foreach ($scan['services'] ?? [] as $name) {
            $lines[] = '    SVC_' . $this->stableId($name) . '["' . $this->label($name) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function collectionsSection(): array
    {
        $lines = ["    ### Schema Collections"];
        $scan = ProjectMapService::scan();
        foreach ($scan['schema_collections'] ?? [] as $name) {
            $lines[] = '    COL_' . $this->stableId($name) . '["' . $this->label($name) . '"]';
        }
        $lines[] = '';
        return $lines;
    }

    private function phpBasenames(string $dir): array
    {
        if (!is_dir($dir)) return [];
        $names = [];
        foreach (glob($dir . '/*.php') ?: [] as $f) {
            $names[] = basename($f, '.php');
        }
        sort($names);
        return $names;
    }

    private function label(string $value): string
    {
        return str_replace(['"', '\\'], ['\"', '\\\\'], $value);
    }

    private function findDocFiles(): array
    {
        $docDir = $this->root . '/docs';
        if (!is_dir($docDir)) {
            return [];
        }
        $files = glob($docDir . '/*.md') ?: [];
        $files = array_map(fn($f) => basename($f), $files);
        sort($files);
        return $files;
    }

    private function findAgentsFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
        );
        $files = [];
        foreach ($iterator as $file) {
            if ($file->getBasename() === 'AGENTS.md') {
                $relative = str_replace($this->root . '/', '', $file->getPathname());
                $files[] = $relative;
            }
        }
        sort($files);
        return $files;
    }

    private function findCoverageGaps(): array
    {
        $gaps = [];
        try {
            $scan = ProjectMapService::scan();

            $scKeys = array_keys(ProjectMapService::serviceCollections());
            $allScCollections = array_values(array_unique(array_merge(...array_values(ProjectMapService::serviceCollections()))));

            $routeServices = array_values(array_unique(array_merge(...array_map(fn($r) => $r['services'] ?? [], $scan['routes']))));
            $unmappedServices = array_diff($routeServices, $scKeys, ProjectMapService::SHARED_SERVICES);
            if ($unmappedServices) {
                $gaps[] = 'Services without schema collection mapping: ' . implode(', ', $unmappedServices);
            }

            $unwiredCollections = array_diff($scan['schema_collections'], $allScCollections, ProjectMapService::KNOWN_UNWIRED_COLLECTIONS);
            if ($unwiredCollections) {
                $gaps[] = 'Schema collections without service: ' . implode(', ', $unwiredCollections);
            }
        } catch (\Throwable $e) {
            $gaps[] = 'Gap analysis unavailable: ' . $e->getMessage();
        }
        return $gaps;
    }

    private function stableId(string $value): string {
        $stable = preg_replace('/[^a-zA-Z0-9]/', '_', $value);
        $stable = preg_replace('/_+/', '_', $stable);
        $stable = trim($stable, '_');
        return strtolower(substr($stable, 0, 48));
    }
}
