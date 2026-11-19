<?php
namespace App\Services;

final class SettingsService {
    public const SETTINGS_ID = 'app_settings';

    public const HEADER_MENU_ID = 'header_menu';
    public const DEFAULT_MENU = ['shop', 'temples', 'blog', 'search', 'about', 'contact'];
    public const MENU_TARGETS = [
        'shop' => ['path'=>'/shop', 'label'=>'Shop', 'module'=>'shop'],
        'temples' => ['path'=>'/temples', 'label'=>'Temples'],
        'blog' => ['path'=>'/blog', 'label'=>'Blog', 'module'=>'blog'],
        'search' => ['path'=>'/search', 'label'=>'Search'],
        'about' => ['path'=>'/about', 'label'=>'About SPS'],
        'contact' => ['path'=>'/contact', 'label'=>'Contact'],
        'terms' => ['path'=>'/terms', 'label'=>'Terms'],
        'privacy' => ['path'=>'/privacy', 'label'=>'Privacy'],
    ];

    /** Modules that can be switched off from Admin → Site Settings. */
    public const MODULES = [
        'shop'    => 'Ecommerce',
        'blog'    => 'Blog',
    ];

    /** Request-level memo. DatabaseService has no caching and every read is a full table scan. */
    private static ?array $cache = null;
    private static array $menuRecord = [];
    private static bool $readFailed = false;

    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    private function load(bool $requireReadable = false): array {
        if (self::$cache !== null && (!$requireReadable || !self::$readFailed)) return self::$cache;
        try {
            // Authored page bodies share the table; ordinary settings requests only need configuration.
            $rows = $this->store->query("SELECT id, _data FROM settings WHERE id NOT LIKE 'pg:%'");
        } catch (\Throwable $error) {
            self::$readFailed = true;
            if ($requireReadable) throw $error;
            return self::$cache = [];
        }
        // Always prefer the canonical record. `read()` returns an unordered SELECT *,
        // so `[0]` can be a stale duplicate row with different (or plaintext) values.
        self::$readFailed = false;
        $settings = null;
        foreach ($rows as $record) {
            $id = (string)$record['id'];
            $row = ['id'=>$id] + (json_decode($record['_data'], true, 512, JSON_THROW_ON_ERROR) ?: []);
            if ($id === self::HEADER_MENU_ID) {
                self::$menuRecord = $row;
            } elseif ($id === self::SETTINGS_ID || $settings === null) {
                $settings = $row;
            }
        }
        return self::$cache = $settings ?? [];
    }

    public static function flushCache(): void {
        self::$cache = null;
        self::$menuRecord = [];
        self::$readFailed = false;
    }

    public function headerMenu(bool $requireReadable = false): array {
        $this->load($requireReadable);
        $items = self::$menuRecord['header_menu'] ?? self::DEFAULT_MENU;
        try { self::validateMenuItems($items); }
        catch (\InvalidArgumentException) { $items = self::DEFAULT_MENU; }
        return ['items'=>$items, 'revision'=>(int)(self::$menuRecord['menu_revision'] ?? 0)];
    }

    /** Only published authored titles are offered as new choices in the owner editor. */
    public function headerMenuTargets(?array $selection = null): array {
        $targets = self::MENU_TARGETS;
        $pages = new AuthoredPageService($this->store);
        if ($selection === null) {
            $published = $pages->published();
        } else {
            $slugs = [];
            foreach ($selection as $id) if (str_starts_with($id, 'pg:')) $slugs[] = substr($id, 3);
            $published = $pages->publishedBySlugs($slugs);
        }
        foreach ($published as $page) {
            $slug = $page['slug'];
            $targets['pg:' . $slug] = ['path'=>'/pages/' . rawurlencode($slug), 'label'=>$page['title']];
        }
        return $targets;
    }

    public function publicHeaderMenu(): array {
        $links = [];
        $modules = $this->modules();
        $items = $this->headerMenu()['items'];
        // Keep the default menu independent of page discovery. Withheld pages have no public target.
        $targets = $this->headerMenuTargets($items);
        foreach ($items as $id) {
            $link = $targets[$id] ?? null;
            if ($link === null || (isset($link['module']) && !$modules[$link['module']])) continue;
            $links[$id] = $link;
        }
        return $links;
    }

    public static function validateMenuItems(mixed $items): void {
        if (!is_array($items) || !array_is_list($items) || count($items) > count(self::DEFAULT_MENU)) {
            throw new \InvalidArgumentException('Choose up to six header links.');
        }
        foreach ($items as $item) {
            if (!is_string($item) || (!isset(self::MENU_TARGETS[$item])
                && (!str_starts_with($item, 'pg:') || !AuthoredPageService::validSlug(substr($item, 3))))) {
                throw new \InvalidArgumentException('Choose a listed page for every link.');
            }
        }
        if (count(array_unique($items)) !== count($items)) throw new \InvalidArgumentException('Each page can appear only once in the header.');
    }

    public function saveHeaderMenu(mixed $slots, mixed $revision): void {
        if (!is_string($revision) || !preg_match('/^(?:0|[1-9][0-9]{0,8})$/D', $revision)) {
            throw new \InvalidArgumentException('Reload the menu editor before saving.');
        }
        if (!is_array($slots) || !array_is_list($slots) || count($slots) !== count(self::DEFAULT_MENU)) {
            throw new \InvalidArgumentException('The menu form was incomplete. Reload and try again.');
        }
        $items = array_values(array_filter($slots, static fn($item) => $item !== ''));
        self::validateMenuItems($items);
        $addedPages = array_diff($items, array_keys(self::MENU_TARGETS), $this->headerMenu(true)['items']);
        if ($addedPages && array_diff($addedPages, array_keys($this->headerMenuTargets($addedPages)))) {
            throw new \InvalidArgumentException('A selected page is no longer published. Reload the editor and choose an available page.');
        }
        if (!$this->store->saveHeaderMenu($items, (int)$revision)) {
            throw new \DomainException('Another editor saved the header menu. Review the current order before saving again.');
        }
        self::flushCache();
    }

    public function public(): array {
        $settings = $this->load();
        return $settings ?: ['shipping_mode' => 'free', 'flat_rate' => 0];
    }

    public function admin(): array { return $this->load(); }

    /**
     * Module on/off flags. Absent means enabled, so existing installs are unaffected.
     * @return array<string,bool>
     */
    public function modules(bool $requireReadable = false): array {
        $settings = $this->load($requireReadable);
        $modules = [];
        foreach (array_keys(self::MODULES) as $key) {
            $modules[$key] = (string)($settings['module_' . $key] ?? '1') !== '0';
        }
        return $modules;
    }

    public function moduleEnabled(string $key): bool {
        return $this->modules()[$key] ?? true;
    }

    public function savePublic(array $settings): void {
        $settings['id'] = self::SETTINGS_ID;
        $this->store->upsert('settings', $settings, 'id');
        self::flushCache();
    }
}
