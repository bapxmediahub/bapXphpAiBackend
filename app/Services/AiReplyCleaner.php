<?php
namespace App\Services;

/**
 * Strips a model's working notes from its answer.
 *
 * gemma-4-31b-it restates its role, context and constraints before answering, so a
 * one-sentence reply arrives wrapped in ten bullets of scaffold. That plan is working
 * memory, not output — no assistant should show it to a user.
 *
 * The rules lived privately in SupportBotService, so the admin agent could not reuse
 * them and printed everything raw. One implementation, used by both.
 */
final class AiReplyCleaner
{
    /** Labels the model uses to restate the brief. Only count with a colon after. */
    private const LABELS = [
        'role', 'context', 'constraint', 'constraints', 'question', 'input data',
        'customer context', 'customer question', 'task', 'goal', 'plan', 'reasoning',
        'thinking', 'output format', 'format', 'persona', 'instruction', 'instructions',
    ];

    /** Commentary about the request rather than an answer to it. */
    private const META_OPENERS = [
        'the user is asking', 'the user wants', 'the user asked', 'the customer is asking',
        'the prompt asks', 'the question asks', 'i need to', 'i should', 'i must',
        'as an ai', 'maintain the persona', 'maintain a persona', 'answer only',
        'list the', 'step 1', 'step 2', 'first, i', 'so, the answer',
    ];

    /** Bare style reminders the model echoes back. */
    private const NOISE = [
        'concise', 'markdown', 'be concise', 'in markdown', 'short', 'brief',
        'plain text', 'no markdown', 'direct', 'done',
    ];

    /** A reply made only of these has leaked internals and should not be shown. */
    private const INTERNAL_MARKERS = '/\b(signed_in|customer context|context json|site\.pages|wallet_transactions|support_scope|generationconfig|tool call|the user said|the bot should|the bot needs|allowed scope)\b/i';

    public function clean(string $reply, string $fallback = ''): string
    {
        $reply = preg_replace('/<thought\b[^>]*>.*?<\/thought>/is', '', $reply) ?? $reply;
        // Some models fence their plan; keep fenced code the author intended, drop
        // fences that only wrap the scaffold.
        $reply = preg_replace('/^\s*```(?:thinking|thought|plan)[\s\S]*?```/im', '', $reply) ?? $reply;

        $kept = [];
        foreach (preg_split('/\R/', $reply) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') { $kept[] = ''; continue; }

            $bare = preg_replace('/^\s*(?:[*\-\x{2022}\x{00B7}]|\d+[.)])\s*/u', '', $line) ?? $line;
            $bare = trim($bare, " \t`*");

            // "Direct answer: X" is scaffold wrapping a real answer — keep X.
            if (preg_match('/^(?:direct answer|final answer|answer)\s*:\s*(.+)$/i', $bare, $m)) {
                $kept[] = trim($m[1]);
                continue;
            }

            if ($this->isScaffold($bare)) continue;

            $kept[] = $bare;
        }

        $text = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $kept)) ?? '');

        // A model that answered only inside quotes: take the longest quoted span.
        if ($text === '' && preg_match_all('/"([^"]{20,700})"/', $reply, $matches) && !empty($matches[1])) {
            $text = trim(end($matches[1]));
        }

        return $text !== '' ? $text : $fallback;
    }

    /** True when the reply is still internals and should be replaced. */
    public function looksInternal(string $reply): bool
    {
        return (bool)preg_match(self::INTERNAL_MARKERS, $reply);
    }

    private function isScaffold(string $line): bool
    {
        $lower = strtolower(rtrim($line, " .:;"));
        if ($lower === '') return true;

        // "Label: ..." — the model restating the brief.
        foreach (self::LABELS as $label) {
            if (str_starts_with($lower, $label)) {
                $rest = ltrim(substr($line, strlen($label)));
                if ($rest === '' || str_starts_with($rest, ':')) return true;
            }
        }

        // Commentary about the request. Kept to sentence openers so ordinary prose
        // that merely contains these words survives.
        foreach (self::META_OPENERS as $opener) {
            if (str_starts_with($lower, $opener)) return true;
        }

        // A line that is only a style reminder, e.g. "Concise." or "Markdown."
        if (in_array($lower, self::NOISE, true)) return true;

        return false;
    }
}
