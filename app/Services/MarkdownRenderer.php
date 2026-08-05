<?php
namespace App\Services;

final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $html = $markdown;
        $html = $this->renderCodeBlocks($html);
        $html = $this->renderHeadings($html);
        $html = $this->renderHorizontalRules($html);
        $html = $this->renderTables($html);
        $html = $this->renderUnorderedLists($html);
        $html = $this->renderOrderedLists($html);
        $html = $this->renderInlineFormatting($html);
        $html = $this->renderParagraphs($html);
        $html = $this->sanitize($html);
        return $html;
    }


    /**
     * GitHub-style pipe tables. Runs after headings and rules but before lists and
     * paragraphs, so a table block is already wrapped in <table> by the time
     * renderParagraphs() sees it and is left alone.
     *
     *   | Name | Price |
     *   |------|------:|
     *   | Mala |   499 |
     *
     * The delimiter row sets alignment: :--- left, ---: right, :---: centre.
     */
    private function renderTables(string $html): string
    {
        $lines = explode("\n", $html);
        $out = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $header = trim($lines[$i]);
            $delimiter = trim($lines[$i + 1] ?? '');

            $looksLikeTable = $header !== '' && str_contains($header, '|')
                && $delimiter !== '' && preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', $delimiter) === 1;

            if (!$looksLikeTable) { $out[] = $lines[$i]; continue; }

            $headers = $this->splitTableRow($header);
            $aligns = array_map(static function (string $spec): string {
                $spec = trim($spec);
                $left = str_starts_with($spec, ':');
                $right = str_ends_with($spec, ':');
                if ($left && $right) return ' style="text-align:center"';
                if ($right) return ' style="text-align:right"';
                return '';
            }, $this->splitTableRow($delimiter));

            if (count($headers) === 0) { $out[] = $lines[$i]; continue; }

            $body = [];
            $j = $i + 2;
            while ($j < $count && trim($lines[$j]) !== '' && str_contains($lines[$j], '|')) {
                $body[] = $this->splitTableRow(trim($lines[$j]));
                $j++;
            }

            $table = '<table><thead><tr>';
            foreach ($headers as $index => $cell) {
                $table .= '<th' . ($aligns[$index] ?? '') . '>' . $cell . '</th>';
            }
            $table .= '</tr></thead><tbody>';
            foreach ($body as $row) {
                $table .= '<tr>';
                foreach ($headers as $index => $_) {
                    $table .= '<td' . ($aligns[$index] ?? '') . '>' . ($row[$index] ?? '') . '</td>';
                }
                $table .= '</tr>';
            }
            $table .= '</tbody></table>';

            $out[] = $table;
            $i = $j - 1;
        }

        return implode("\n", $out);
    }

    /** Splits a pipe row into cells, ignoring the optional leading and trailing pipes. */
    private function splitTableRow(string $row): array
    {
        $row = preg_replace('/^\||\|$/', '', trim($row)) ?? $row;
        return array_map('trim', explode('|', $row));
    }

    private function sanitize(string $html): string
    {
        return strip_tags($html, '<p><br><strong><em><b><i><u><s><ol><ul><li><h1><h2><h3><h4><h5><h6><pre><code><blockquote><hr><a><img><table><thead><tbody><tr><th><td><div><span><sub><sup><del><ins><mark><figure><figcaption><cite><q><dl><dt><dd><abbr><address>');
    }

    private function renderCodeBlocks(string $html): string
    {
        return preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) {
            $lang = $m[1] ? ' class="language-' . e($m[1]) . '"' : '';
            $code = e($m[2]);
            return '<pre><code' . $lang . '>' . $code . '</code></pre>';
        }, $html);
    }

    private function renderHeadings(string $html): string
    {
        return preg_replace_callback('/^(#{1,6})\s+(.+)$/m', function ($m) {
            $level = strlen($m[1]);
            $text = trim($m[2]);
            $id = 'heading-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
            $id = trim($id, '-');
            return '<h' . $level . ' id="' . $id . '">' . e($text) . '</h' . $level . '>';
        }, $html);
    }

    private function renderHorizontalRules(string $html): string
    {
        return preg_replace('/^---$/m', '<hr>', $html);
    }

    private function renderUnorderedLists(string $html): string
    {
        $html = preg_replace_callback('/^(?:[-*+]\s+.*(?:\n|$))+/m', function ($m) {
            $items = preg_split('/\n[-*+]\s+/', trim($m[0]));
            $lis = '';
            foreach ($items as $i => $item) {
                if ($i === 0) {
                    $item = preg_replace('/^[-*+]\s+/', '', $item);
                }
                $lis .= '<li>' . e(trim($item)) . '</li>';
            }
            return '<ul>' . $lis . '</ul>';
        }, $html);
        return $html;
    }

    private function renderOrderedLists(string $html): string
    {
        $html = preg_replace_callback('/^(?:\d+\.\s+.*(?:\n|$))+/m', function ($m) {
            $items = preg_split('/\n\d+\.\s+/', trim($m[0]));
            $lis = '';
            foreach ($items as $i => $item) {
                if ($i === 0) {
                    $item = preg_replace('/^\d+\.\s+/', '', $item);
                }
                $lis .= '<li>' . e(trim($item)) . '</li>';
            }
            return '<ol>' . $lis . '</ol>';
        }, $html);
        return $html;
    }

    private function renderInlineFormatting(string $html): string
    {
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        $html = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = $m[2];
            if (str_starts_with($url, 'http')) {
                return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($m[1]) . '</a>';
            }
            return '<a href="' . e($url) . '">' . e($m[1]) . '</a>';
        }, $html);
        $html = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
            $alt = e($m[1] ?: '');
            $src = e($m[2]);
            if (str_starts_with($src, 'http')) {
                return '<img src="' . $src . '" alt="' . $alt . '" loading="lazy">';
            }
            return '<img src="' . e($this->resolveAssetPath($src)) . '" alt="' . $alt . '" loading="lazy">';
        }, $html);
        return $html;
    }

    private function renderParagraphs(string $html): string
    {
        $blocks = preg_split('/\n{2,}/', $html);
        $result = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;
            if (preg_match('/^<(h[1-6]|ul|ol|li|pre|blockquote|hr|div|table|p)/', $block)) {
                $result[] = $block;
            } else {
                $result[] = '<p>' . $block . '</p>';
            }
        }
        return implode("\n", $result);
    }

    private function resolveAssetPath(string $path): string
    {
        return $path;
    }
}
