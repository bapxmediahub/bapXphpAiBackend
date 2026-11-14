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

    public function run(string $name, array $args): array {
        if (!AgentCapabilityPolicy::allows(AgentCapabilityPolicy::ENTERPRISE, $name, $_SESSION['user'] ?? null)) {
            return ['error' => 'This lookup is not available to the current actor.'];
        }
        return parent::run($name, $args);
    }
}
