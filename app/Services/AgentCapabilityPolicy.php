<?php
namespace App\Services;

/**
 * Capabilities belong to the authenticated actor, never to a role supplied by a model
 * or browser. Tool discovery is only UX; execution must enforce the same policy.
 */
final class AgentCapabilityPolicy {
    public const SUPPORT = 'support';
    public const ENTERPRISE = 'enterprise';

    private const SUPPORT_TOOLS = [
        'public_catalog', 'public_policy', 'own_ticket_status', 'customer_ticket_lookup',
    ];
    private const ENTERPRISE_TOOLS = [
        'find_order', 'product_status', 'sales_summary', 'gst_register_summary',
        'support_enquiries', 'store_count', 'search_articles', 'coupon_status',
    ];

    public static function tools(string $agent): array {
        return match ($agent) {
            self::SUPPORT => self::SUPPORT_TOOLS,
            self::ENTERPRISE => self::ENTERPRISE_TOOLS,
            default => [],
        };
    }

    public static function allows(string $agent, string $tool, ?array $actor): bool {
        if (!in_array($tool, self::tools($agent), true)) return false;
        if ($agent === self::ENTERPRISE) {
            return ($actor['role'] ?? '') === 'admin' || !empty($actor['is_admin']);
        }
        if ($agent === self::SUPPORT && $tool === 'own_ticket_status') {
            return trim((string)($actor['email'] ?? '')) !== '';
        }
        return $agent === self::SUPPORT;
    }
}
