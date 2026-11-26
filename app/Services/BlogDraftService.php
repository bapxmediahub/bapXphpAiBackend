<?php
namespace App\Services;
final class BlogDraftService
{
    public static function enhancementError(?string $category): string
    {
        return match ($category) {
            'not_configured' => 'Configure an AI provider in Integrations to request a rewrite.',
            'authentication' => 'The AI provider rejected its credentials. Check Integrations.',
            'rate_limit' => 'The AI provider reached its request limit. Try again later.',
            'timeout' => 'The AI provider took too long to respond.',
            'transport' => 'The site could not reach the AI provider.',
            'provider_server' => 'The AI provider reported a service error.',
            'provider_request' => 'The AI provider rejected the request. Check the connection in Integrations.',
            'empty_response' => 'The AI provider returned no text.',
            default => 'The AI provider could not supply a rewrite.',
        };
    }

    public static function reviewableEnhancement(string $field, string $original, string $answer): string
    {
        $candidate = trim($answer);
        if ($field === 'title') {
            $candidate = trim(strip_tags($candidate), " \t\r\n\"'");
            if ($candidate === '' || str_contains($candidate, "\n") || mb_strlen($candidate) > 120) {
                throw new \InvalidArgumentException('The AI provider did not return a single usable headline.');
            }
        } elseif ($field === 'content') {
            if ($candidate === '' || strlen($candidate) > 1048576) {
                throw new \InvalidArgumentException('The AI provider did not return usable article text.');
            }
            // Compare image URLs as the public article will actually render them.
            $images = static function (string $markdown): array {
                preg_match_all('~<img\b[^>]*?\ssrc="([^"]+)"~i', (new MarkdownRenderer())->render($markdown), $matches);
                return $matches[1];
            };
            if ($images($original) !== $images($candidate)) {
                throw new \InvalidArgumentException('The AI suggestion changed the article images and was discarded.');
            }
        } else {
            throw new \InvalidArgumentException('Unknown article field.');
        }
        return $candidate;
    }

}
