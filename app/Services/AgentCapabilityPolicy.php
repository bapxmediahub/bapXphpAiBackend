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
        'public_catalog', 'public_policy', 'get_public_contact', 'list_public_services', 'own_ticket_status',
        'public_catalogue_summary', 'public_best_sellers', 'search_articles', 'public_product_details', 'own_order_status', 'own_cart', 'present_contact_enquiry', 'read_published_article',
    ];
    private const ENTERPRISE_TOOLS = [
        'find_order', 'product_status', 'sales_summary', 'commerce_report', 'gst_register_summary',
        'support_enquiries', 'store_count', 'search_articles', 'operator_guide', 'coupon_status',
        'get_public_contact', 'list_public_services', 'order_count', 'read_published_article',
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
        if ($agent === self::SUPPORT && in_array($tool,['own_ticket_status','own_order_status'],true)) {
            return trim((string)($actor['email'] ?? '')) !== '';
        }
        return $agent === self::SUPPORT;
    }
}
