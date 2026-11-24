<?php
declare(strict_types=1);

namespace App\Services;

/** The allowlisted public profile of one installation. No request host or private records. */
final class SiteProfileService
{
    private const FIELD_LIMITS = [
        'site_display_name' => 160,
        'site_summary' => 280,
        'site_story' => 1800,
        'public_contact_email' => 254,
        'public_contact_phone_1' => 32,
        'public_contact_phone_2' => 32,
        'public_hours_1_days' => 40,
        'public_hours_1_times' => 40,
        'public_hours_2_days' => 40,
        'public_hours_2_times' => 40,
    ];

    private readonly bool $reference;
    private readonly array $fields;

    public function __construct(array $settings, string $origin, mixed $legacyName = null, mixed $appName = null)
    {
        $this->reference = in_array(rtrim(strtolower($origin), '/'), [
            'https://sripanchamispiritual.com', 'https://www.sripanchamispiritual.com',
        ], true);
        $defaults = $this->reference ? require app_path('config/reference-site.php') : [];
        $fields = [];
        foreach (self::FIELD_LIMITS as $key => $limit) {
            $value = array_key_exists($key, $settings) ? $settings[$key] : ($defaults[$key] ?? '');
            $fields[$key] = self::text($value, $limit, $key === 'site_story') ?? '';
        }
        $fields['site_display_name'] = (self::text($settings['site_display_name'] ?? '', 160) ?: null)
            ?? (self::text($legacyName, 160) ?: null)
            ?? (self::text($appName, 160) ?: null)
            ?? $defaults['site_display_name'] ?? 'Website';

        if ($fields['public_contact_email'] !== '' && !filter_var($fields['public_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $fields['public_contact_email'] = '';
        }
        for ($index = 1; $index <= 2; ++$index) {
            $key = 'public_contact_phone_' . $index;
            if (self::phoneNumber($fields[$key]) === null) $fields[$key] = '';
        }
        $this->fields = $fields;
    }

    public static function current(?array $seo = null, ?string $origin = null): self
    {
        $settings = (new SettingsService())->public();
        if ($seo === null && (self::text($settings['site_display_name'] ?? '', 160) ?? '') === '') {
            $seo = (new SecretService())->all();
        }
        $config = require app_path('config/database.php');
        return new self($settings, $origin ?? $config['app_url'], $seo['seo_site_name'] ?? null, $config['app_name']);
    }

    public function name(): string { return $this->fields['site_display_name']; }
    public function summary(): string { return $this->fields['site_summary']; }
    public function isReference(): bool { return $this->reference; }
    public function hasReferenceName(): bool { return $this->reference && $this->name() === 'Sri Panchami Spiritual'; }

    public function valuesForEditor(): array { return $this->fields; }

    public function contact(): array
    {
        $phones = [];
        $hours = [];
        for ($index = 1; $index <= 2; ++$index) {
            $label = $this->fields['public_contact_phone_' . $index];
            if ($number = self::phoneNumber($label)) $phones[$number] ??= ['label' => $label, 'number' => $number];
            $days = $this->fields["public_hours_{$index}_days"];
            $times = $this->fields["public_hours_{$index}_times"];
            if ($days !== '' && $times !== '') $hours[] = ['days' => $days, 'hours' => $times];
        }
        $data = ['name' => $this->name(), 'path' => '/contact', 'phones' => array_values($phones)];
        if ($this->fields['public_contact_email'] !== '') $data['email'] = $this->fields['public_contact_email'];
        return $data + ['hours' => $hours, 'servicePath' => '/contact#contact-form'];
    }

    public function about(): array
    {
        $about = $this->reference ? require app_path('config/about.php') : [
            'headline' => $this->name(),
            'intro' => '',
            'story' => ['eyebrow' => '', 'title' => 'Our story', 'paragraphs' => []],
            'explore_title' => 'Explore the site',
            'paths' => [
                'shop' => ['title' => 'Products', 'description' => 'Browse available products.', 'label' => 'Explore products'],
                'temples' => ['title' => 'Temple guides', 'description' => 'Browse published temple guides.', 'label' => 'Browse temple guides'],
                'blog' => ['title' => 'Articles', 'description' => 'Read the latest published articles.', 'label' => 'Read articles'],
            ],
            'contact_title' => 'Contact our team',
            'contact_intro' => 'Send a message through the contact form.',
        ];
        if ($this->summary() !== '') $about['intro'] = $this->summary();
        if ($this->fields['site_story'] !== '') {
            $about['story']['paragraphs'] = preg_split('/\n[ \t]*\n/', $this->fields['site_story'], -1, PREG_SPLIT_NO_EMPTY);
        } elseif ($this->reference) {
            $about['story']['paragraphs'] = array_map(
                fn(string $paragraph): string => str_replace('{{SITE_NAME}}', $this->name(), $paragraph),
                $about['story']['paragraphs']
            );
        }
        return $about;
    }

    /** Only the dedicated form's bounded public fields can be saved by this path. */
    public static function changes(array $input): array
    {
        $changes = [];
        foreach (self::FIELD_LIMITS as $key => $limit) {
            $text = self::text($input[$key] ?? null, $limit, $key === 'site_story');
            if ($text === null) throw new \InvalidArgumentException('Fill in the profile using plain text within the field limits.');
            $changes[$key] = $text;
        }
        if ($changes['public_contact_email'] !== '' && !filter_var($changes['public_contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid public email address or leave it blank.');
        }
        $phones = [];
        for ($index = 1; $index <= 2; ++$index) {
            $label = $changes['public_contact_phone_' . $index];
            if ($label !== '') {
                $number = self::phoneNumber($label);
                if ($number === null) throw new \InvalidArgumentException('Use international phone numbers starting with + and a country code.');
                if (in_array($number, $phones, true)) throw new \InvalidArgumentException('Enter different public phone numbers.');
                $phones[] = $number;
            }
            if (($changes["public_hours_{$index}_days"] === '') !== ($changes["public_hours_{$index}_times"] === '')) {
                throw new \InvalidArgumentException('For each hours entry, provide both days and times or leave both blank.');
            }
        }
        return $changes;
    }

    private static function text(mixed $value, int $limit, bool $multiline = false): ?string
    {
        if (!is_string($value) || strlen($value) > 4 * $limit || !mb_check_encoding($value, 'UTF-8')) return null;
        $value = trim($multiline ? str_replace(["\r\n", "\r"], "\n", $value) : $value);
        $controls = $multiline ? '/[\x00-\x08\x0b-\x1f\x7f]/u' : '/[\x00-\x1f\x7f]/u';
        if (mb_strlen($value) > $limit || preg_match($controls, $value) || strpbrk($value, '<>') !== false) return null;
        return $value;
    }

    private static function phoneNumber(string $label): ?string
    {
        if (!preg_match('/^\+[0-9 ()-]+$/D', $label)) return null;
        $number = '+' . preg_replace('/[^0-9]/', '', $label);
        return preg_match('/^\+[1-9][0-9]{6,14}$/D', $number) ? $number : null;
    }
}
