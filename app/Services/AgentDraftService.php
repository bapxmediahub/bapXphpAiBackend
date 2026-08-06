<?php
namespace App\Services;

/**
 * Turns "/create-blog rudraksha benefits" into a filled-in form the owner can check.
 *
 * The agent does not create anything. It drafts, and hands the owner a form pre-filled
 * with what it wrote, posting to the same save route the normal admin screens use.
 * Nothing reaches the site until the owner reads it and presses Save — an agent that
 * published on its own would put unreviewed text on a live shop.
 *
 * It also answers "what do you need from me?": with no model configured, or when the
 * model returns nothing usable, the form still comes back with every field the owner
 * has to fill and which of them are required.
 */
final class AgentDraftService
{
    /**
     * A field the owner will see. `required` marks what a save genuinely needs;
     * `hint` is what the agent could not know and the owner must supply.
     */
    private const BLOG_FIELDS = [
        ['name' => 'title',       'label' => 'Title',        'type' => 'text',     'required' => true],
        ['name' => 'slug',        'label' => 'URL slug',     'type' => 'text',     'required' => true],
        ['name' => 'category',    'label' => 'Category',     'type' => 'text',     'required' => true],
        ['name' => 'excerpt',     'label' => 'Excerpt',      'type' => 'textarea', 'required' => false],
        ['name' => 'content',     'label' => 'Content',      'type' => 'markdown', 'required' => true],
        ['name' => 'seo_title',   'label' => 'SEO title',    'type' => 'text',     'required' => false],
        ['name' => 'seo_description', 'label' => 'SEO description', 'type' => 'textarea', 'required' => false],
        ['name' => 'og_image',    'label' => 'Image',        'type' => 'media',    'required' => false,
         'hint' => 'Pick from the media library, or write @filename in the chat.'],
    ];

    public function __construct(
        private ?AiClient $ai = null,
        private ?SchemaService $schema = null
    ) {
        $this->ai ??= new AiClient();
        $this->schema ??= new SchemaService();
    }

    /** True when the message opens with a draft command. */
    public static function command(string $message): ?string
    {
        if (preg_match('/^\s*\/(create-blog|add-product)\b/i', $message, $m)) {
            return strtolower($m[1]);
        }
        return null;
    }

    /** The subject after the command, e.g. "/create-blog rudraksha" => "rudraksha". */
    public static function subject(string $message): string
    {
        return trim((string)preg_replace('/^\s*\/(?:create-blog|add-product)\b/i', '', $message));
    }

    /**
     * @return array{kind:string, action:string, title:string, fields:array, note:string}
     */
    public function draft(string $command, string $subject, string $attachments = ''): array
    {
        $isBlog = $command === 'create-blog';
        $fields = $isBlog ? self::BLOG_FIELDS : $this->productFields();
        $values = [];
        $note = '';

        if ($subject === '') {
            $example = $isBlog ? 'benefits of rudraksha' : 'brass oil lamp';
            $note = 'Tell me what to write about and I will fill this in — for example "/'
                . $command . ' ' . $example . '". Or fill the form in yourself.';
        } elseif (!$this->ai->configured()) {
            $note = 'No AI key is configured, so I could not draft this. The fields below are what a save needs.';
        } else {
            $answer = $this->ai->completeOrNull($this->prompt($isBlog, $fields, $subject, $attachments), 1600, 0.4);
            $values = $answer !== null ? $this->parse($answer, $fields) : [];
            $note = $values === []
                ? 'The model did not return anything I could use. The fields below are what a save needs.'
                : 'Here is a draft. Read it, change anything you want, then press Save — nothing is published until you do.';
        }

        if ($isBlog && ($values['slug'] ?? '') === '' && ($values['title'] ?? '') !== '') {
            $values['slug'] = $this->slugify($values['title']);
        }

        foreach ($fields as &$field) {
            $field['value'] = (string)($values[$field['name']] ?? '');
        }
        unset($field);

        return [
            'kind' => $isBlog ? 'blog' : 'product',
            'action' => $isBlog ? '/admin/blog/save' : '/admin/products/save',
            'title' => $isBlog ? 'Draft blog post' : 'Draft product',
            'fields' => $fields,
            'note' => $note,
        ];
    }

    /** Product fields come from the schema, so the form cannot drift from the real one. */
    private function productFields(): array
    {
        $labels = [
            'slug' => 'URL slug', 'name' => 'Name', 'description' => 'Description',
            'category' => 'Category', 'image_url' => 'Main image', 'image_urls' => 'More images',
            'price' => 'Price (₹)', 'offer_price' => 'Offer price (₹)', 'hsn_code' => 'HSN code',
            'gst_rate' => 'GST rate (%)', 'stock_status' => 'Stock status',
        ];
        $required = ['name', 'slug', 'price', 'category'];
        // Only the owner knows these; the model must not invent them.
        $ownerOnly = [
            'price' => 'Set the price yourself.',
            'offer_price' => 'Optional. Set it yourself.',
            'hsn_code' => 'Tax code — set it yourself.',
            'gst_rate' => 'Tax rate — set it yourself.',
            'image_url' => 'Pick from the media library, or write @filename in the chat.',
            'image_urls' => 'Pick from the media library, or write @filename in the chat.',
        ];
        $fields = [];
        foreach ($this->schema->adminFields('products') as $name) {
            $field = [
                'name' => $name,
                'label' => $labels[$name] ?? ucfirst(str_replace('_', ' ', $name)),
                'type' => $name === 'description' ? 'textarea' : (isset($ownerOnly[$name]) && str_contains($name, 'image') ? 'media' : 'text'),
                'required' => in_array($name, $required, true),
            ];
            if (isset($ownerOnly[$name])) $field['hint'] = $ownerOnly[$name];
            $fields[] = $field;
        }
        return $fields;
    }

    private function prompt(bool $isBlog, array $fields, string $subject, string $attachments): string
    {
        $keys = [];
        foreach ($fields as $field) {
            // Prices, tax codes and image paths are the owner's to set. A model guessing
            // a price would put a wrong number in front of a shopper.
            if (isset($field['hint'])) continue;
            $keys[] = $field['name'];
        }
        $what = $isBlog
            ? 'a blog post for a Hindu spiritual products and astrology shop'
            : 'a product listing for a Hindu spiritual products shop';

        return "Write {$what} about: {$subject}\n"
            . "Return ONLY a JSON object, no prose and no code fence, with exactly these keys: "
            . implode(', ', $keys) . ".\n"
            . ($isBlog
                ? "content must be markdown, at least four paragraphs, with ## subheadings. Do not put a title heading in content.\n"
                  . "slug must be lowercase words joined by hyphens. Write warmly and respectfully about the tradition.\n"
                : "description must be two or three sentences a shopper would find useful. slug must be lowercase words joined by hyphens.\n")
            . "Never invent a price, a tax code or an image path.\n"
            . $attachments
            . "\nJSON:";
    }

    /** @return array<string,string> */
    private function parse(string $answer, array $fields): array
    {
        // Models fence JSON even when told not to.
        $answer = trim((string)preg_replace('/^\s*```(?:json)?|```\s*$/m', '', $answer));
        $start = strpos($answer, '{');
        $end = strrpos($answer, '}');
        if ($start === false || $end === false || $end <= $start) return [];
        $decoded = json_decode(substr($answer, $start, $end - $start + 1), true);
        if (!is_array($decoded)) return [];

        $allowed = array_column($fields, 'name');
        $values = [];
        foreach ($decoded as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            if (is_array($value)) $value = implode(', ', array_filter(array_map('strval', $value)));
            if (!is_scalar($value)) continue;
            $values[$key] = trim((string)$value);
        }
        return array_filter($values, fn(string $v): bool => $v !== '');
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
        return $slug !== '' ? $slug : 'untitled';
    }
}
