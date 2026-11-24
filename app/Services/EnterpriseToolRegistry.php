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
                'prompt'=>'/help enterprise-agent'],
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

    /** Resolve explicit help requests before sending a question to an external provider. */
    public function guideAnswer(string $question): ?string {
        if (!AgentCapabilityPolicy::allows(AgentCapabilityPolicy::ENTERPRISE, 'operator_guide', $_SESSION['user'] ?? null)) return null;
        $question = trim($question);
        if (preg_match('~^(?:/capabilities|what can you (?:do|help(?: me)? with)(?: (?:for me|in (?:my|this) store))?|how can you help(?: me)?|what kinds? of (?:store|business|admin) tasks can you help(?: me)? with)[?!. ]*$~iD', $question)) {
            $lines = ['I can help with these owner tasks:'];
            foreach (self::capabilities($_SESSION['user'] ?? null) as $task) {
                $lines[] = '- ' . $task['title'] . ': ' . $task['description'];
            }
            $lines[] = "\n" . self::capabilityLimits();
            $lines[] = 'Use /create-blog or /add-product to prepare an editable draft.';
            return implode("\n", $lines);
        }
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
