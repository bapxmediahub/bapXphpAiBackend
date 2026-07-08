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
            '    AGENTS_FILES["AGENTS.md docs"]',
            '    SYSTEMATIC_MAP["docs/systematic-map.mmd"]',
            '    COLLECTIONS_SCHEMA["storage/schema/collections.json"]',
            '    DESIGN_SYSTEM["Design.md"]',
            '',
            '    ### CLI (bapXphp)',
            '    CLI_ORIENTATION["help / understand / context"]',
            '    CLI_DEV["test / lint / check / serve / smoke"]',
            '    CLI_SCHEMA["schema list / show / add / remove"]',
            '    CLI_TOOLS["tool list / add"]',
            '    CLI_DB["db tables / describe / query / find / raw / init / sync / tunnel"]',
            '    CLI_GIT["status / logs / issue / pr / merge"]',
            '    CLI_OPS["deploy / ssh / mail:process / images:optimize / route:list"]',
            '    CLI_MAP["map / map:gen / map:val"]',
            '    CLI_DOCS["docsmap / bloggen"]',
            '    CLI_SKILLS["skills"]',
            '',
            '    ### Agent Skills',
            '    SKILL_ADMIN_UI[".agents/skills/admin-ui"]',
            '    SKILL_BACKEND_JSON[".agents/skills/backend-json"]',
            '    SKILL_DEPLOYMENT[".agents/skills/deployment"]',
            '    SKILL_DOCS[".agents/skills/docs"]',
            '    SKILL_FRONTEND_PHP[".agents/skills/frontend-php"]',
            '    SKILL_PHP_JSON_BACKEND[".agents/skills/php-json-backend"]',
            '    SKILL_SCHEMA[".agents/skills/schema"]',
            '',
            '    ### Documentation Sources',
            '    DOCS_README["README.md"]',
            '    DOCS_DESIGN["Design.md"]',
            '    DOCS_SYSTEMMAP["docs/systematic-map.mmd"]',
            '    DOCS_KNOWLEDGEMAP["docs/KnowledgeMap.mmd"]',
            '    DOCS_AGENTS["AGENTS.md chain (root → child)"]',
            '',
            '    ### Blog & Content (GitHub-sourced)',
            '    BLOG_INDEX["GitHub raw → blog index JSON"]',
            '    BLOG_POSTS["GitHub raw → .md posts"]',
            '    BLOG_CATEGORIES["GitHub raw → categories JSON"]',
            '    RELEASE_NOTES["GitHub raw → release .md"]',
            '    FEATURE_UPDATES["GitHub raw → features .md"]',
            '    CACHE["storage/cache/*.md"]',
            '',
            '    ### Application Architecture',
            '    PHP_BOOTSTRAP["app/bootstrap.php"]',
            '    PHP_ROUTER["app/Router.php"]',
            '    PHP_CONTROLLERS["app/Controllers/"]',
            '    PHP_SERVICES["app/Services/"]',
            '    PHP_VIEWS["views/ (layouts + public + account + admin)"]',
            '    PHP_API["api/index.php"]',
            '    PHP_INTEGRATIONS["integrations/"]',
            '',
            '    ### Data Layer',
            '    JSON_COLLECTIONS["storage/data/*.json"]',
            '    SCHEMA_DEF["storage/schema/collections.json"]',
            '    MEDIA_FILES["assets/images/media/"]',
            '    MEDIA_INDEX["storage/data/media_files.json"]',
            '    BACKUPS["storage/backups/"]',
            '',
            '    ### Admin Panel',
            '    ADMIN_DASHBOARD["/admin — CRUD, settings, media, env"]',
            '    ADMIN_AUDIT["/admin/audit-log"]',
            '    ADMIN_INTEGRATIONS["/admin/integrations"]',
            '    ADMIN_PROJECT_MAP["/admin/developer/project-map"]',
            '',
            '    ### External Integrations',
            '    INTEG_GOOGLE_OAUTH["GoogleOAuthClient"]',
            '    INTEG_RAZORPAY["RazorpayClient"]',
            '    INTEG_STRIPE["StripeClient"]',
            '    INTEG_META_PIXEL["MetaPixelClient"]',
            '    INTEG_GOOGLE_SITEKIT["GoogleSiteKitClient"]',
        ];

        $docFiles = $this->findDocFiles();
        if ($docFiles) {
            $lines[] = '';
            $lines[] = '    ### Discovered Documentation Files';
            foreach ($docFiles as $file) {
                $id = 'DOCFILE_' . substr(md5($file), 0, 8);
                $lines[] = "    {$id}[\"{$file}\"]";
            }
        }

        $agentsFiles = $this->findAgentsFiles();
        if ($agentsFiles) {
            $lines[] = '';
            $lines[] = '    ### AGENTS.md Chain';
            foreach ($agentsFiles as $file) {
                $id = 'AGENTFILE_' . substr(md5($file), 0, 8);
                $lines[] = "    {$id}[\"{$file}\"]";
            }
        }

        return implode("\n", $lines) . "\n";
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
}
