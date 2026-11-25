<?php
namespace App\Services;

/**
 * Distinct admin capability surface; inherited query operations stay read-only.
 * Server-side AdminController authentication is still mandatory.
 */
final class EnterpriseToolRegistry extends AgentToolRegistry {
    /** Task guidance is filtered by executable capabilities, never by a browser-selected role. */
    public static function capabilities(?array $actor): array {
        $tasks = [
            ['tool'=>'sales_summary','title'=>'Paid sales','description'=>'Paid orders, recorded refunds and best sellers for a stated period.',
                'prompt'=>'What were paid sales and paid orders in the last 30 days?'],
            ['tool'=>'commerce_report','title'=>'Product performance','description'=>'Rank products by units and compare gross and allocated net line value.',
                'prompt'=>'Show the product performance report for the last 30 days.'],
            ['tool'=>'commerce_report','title'=>'Current stock status','description'=>'Read current availability status and prices. Quantities are not recorded.',
                'prompt'=>'Show the current stock status report.'],
            ['tool'=>'commerce_report','title'=>'Customer patterns','description'=>'Anonymous order-frequency groups within a period, without customer identities.',
                'prompt'=>'Show customer order-frequency groups for the last 30 days.'],
            ['tool'=>'commerce_report','title'=>'Coupon performance','description'=>'Recorded uses, discounts and associated order value; not marketing attribution.',
                'prompt'=>'Show coupon performance for the last 30 days.'],
            ['tool'=>'find_order','title'=>'An identified order','description'=>'Provide the exact order ID or customer email in your question.',
                'prompt'=>null],
            ['tool'=>'product_status','title'=>'Product visibility and offers','description'=>'Check whether a named product is hidden, its price, and its current offer.',
                'prompt'=>null],
            ['tool'=>'gst_register_summary','title'=>'GST register','description'=>'Issued invoices, tax totals and review counts with a report link.',
                'prompt'=>'Show the GST register summary for the last 30 days.'],
            ['tool'=>'support_enquiries','title'=>'Support enquiries','description'=>'Ticket counts by current status and keyword topic for a stated period.',
                'prompt'=>'How many unanswered support tickets were created in the last 30 days?'],
            ['tool'=>'coupon_status','title'=>'A named coupon','description'=>'Check dates, usage and general configuration. Checkout still validates the actual cart.',
                'prompt'=>null],
            ['tool'=>'store_count','title'=>'Business record counts','description'=>'Count user accounts, products or retained historical appointments.',
                'prompt'=>'How many product records are there?'],
            ['tool'=>'search_articles','title'=>'Published articles','description'=>'Find published articles and help references by topic.',
                'prompt'=>null],
            ['tool'=>'get_public_contact','title'=>'Published contact information','description'=>'Read the business contact channels customers see.',
                'prompt'=>'What business contact information do customers see?'],
            ['tool'=>'list_public_services','title'=>'Service enquiry options','description'=>'Read the published enquiry options. This does not create a booking.',
                'prompt'=>'What service enquiries can customers submit?'],
            ['tool'=>'operator_guide','title'=>'Admin task guidance','description'=>'Get the official steps for a supported admin task.',
                'prompt'=>'What does the Enterprise Agent guide say about reopening chats?'],
        ];
        return array_values(array_filter($tasks, static fn(array $task): bool =>
            AgentCapabilityPolicy::allows(AgentCapabilityPolicy::ENTERPRISE, $task['tool'], $actor)));
    }

    public static function capabilityLimits(): string {
        return 'Reports describe recorded data, not profit, cash flow or marketing attribution. Stock has no on-hand quantities. '
            . 'Appointments are historical records; live availability and booking are not available. '
            . 'Drafts and site changes still need your review and the normal save form.';
    }

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
