<?php
namespace App\Services;

/**
 * Distinct admin capability surface; inherited query operations stay read-only.
 * Server-side AdminController authentication is still mandatory.
 */
final class EnterpriseToolRegistry extends AgentToolRegistry {
    public function declarations(): array {
        return array_values(array_filter(
            parent::declarations(),
            static fn(array $entry): bool => in_array(
                $entry['name'], AgentCapabilityPolicy::tools(AgentCapabilityPolicy::ENTERPRISE), true
            )
        ));
    }

    /** Resolve explicit help requests before sending a question to an external provider. */
    public function guideAnswer(string $question): ?string {
        if (!AgentCapabilityPolicy::allows(AgentCapabilityPolicy::ENTERPRISE, 'operator_guide', $_SESSION['user'] ?? null)) return null;
        $question = trim($question);
        $draftHelp = false;
        if (preg_match('~^/help(?:\s+([a-z-]+))?$~iD', $question, $matches)) {
            $topic = strtolower($matches[1] ?? '');
            $topics = array_column(AdminHelpService::references(), 'slug');
            if (!in_array($topic, $topics, true)) return 'Choose a guide with /help followed by one of these topics: ' . implode(', ', $topics) . '.';
        } elseif (preg_match('~^how (?:do I|can I|to) (?:draft|prepare|write|create|save) (?:a |an |my )?(?:(?:new|unpublished|draft|blog) )*(?:article|post)(?: as a draft| without publishing(?: it)?)?[?!. ]*$~iD', $question)) {
            $draftHelp = true;
            $topic = 'blog-publishing';
        } else {
            return null;
        }
        $args = ['topic'=>$topic];
        if ($draftHelp) $args['section'] = 'Prepare an unpublished article';
        $result = $this->run('operator_guide', $args);
        if (isset($result['error'])) return null;
        if ($draftHelp) return $result['instructions'];
        $answer = $result['title'] . "\n\n" . $result['instructions'];
        $otherSections = array_diff($result['sections'], [$result['section']]);
        if ($otherSections) $answer .= "\n\nAlso in this guide:\n- " . implode("\n- ", $otherSections);
        return $answer;
    }

    public function run(string $name, array $args): array {
        if (!AgentCapabilityPolicy::allows(AgentCapabilityPolicy::ENTERPRISE, $name, $_SESSION['user'] ?? null)) {
            return ['error' => 'This lookup is not available to the current actor.'];
        }
        return parent::run($name, $args);
    }
}
