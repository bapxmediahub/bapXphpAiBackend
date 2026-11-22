# Enterprise-only reporting

Use named read-only sales, GST, support, catalogue and store-count tools. Prefer indexed bounded operations where provided; never generate arbitrary SQL to bypass their scope.

Report actual executed filters and result counts, not unvalidated requested arguments. Identify paid revenue versus all-status orders and current ticket status versus historical status events. Refund amounts require verified gateway/order evidence. No-data, denied and unavailable results are different outcomes.

Use actor-owned server context for follow-up periods, revalidate ambiguous references, and do not treat a guardrail score as production factual accuracy, search ranking or satisfaction. Dashboard totals must retain their time window and denominator.

## Commerce reports

commerce_report accepts report=products|stock|customers|coupons and an integer limit from 1 to 10 (default 6). Products, customers and coupons accept inclusive from/to dates spanning at most 366 days through today; omitted dates mean the last 30 calendar days. The business calendar is Asia/Kolkata. Stock is a current snapshot and rejects dates. Do not silently substitute a shorter range when the requested period is unavailable.

Products rank by units and group by recorded product name. order_lines counts matching item rows, not distinct orders. gross is recorded line value; net allocates recorded order refunds proportionally. Neither amount is profit or a verified line-item returns total.

Stock shows current stock_status and effective prices. active and in_stock contribute to the in-stock status count. Hidden products can appear in this internal report; product_status is the separate visibility lookup. No on-hand units, purchase costs or restock forecast are recorded.

Customers returns anonymous order-frequency bands. customers counts distinct recorded valid email keys; repeat_customers means more than one paid order inside the selected period, not lifetime repeat rate or unique people. unidentified_orders counts paid orders without a valid customer key. Overall money and paid_orders include those orders; frequency bands exclude them.

Coupons reports recorded uses, discounts and the net order value associated with each code. Association is not causal marketing attribution. coupon_status is a general configuration check; it does not validate a real customer's cart.

Paid-order reports filter by recorded order date (created_at, or invoice_date when absent), require payment_id, exclude cancelled/failed orders, and deduct recorded verified refunds from those orders. They are not settlement-date cash flow, accounting profit, advertising return or a forecast. Summary values cover the full matching dataset; rows are bounded by limit. Use total_rows to state when more rows exist. Zero rows are valid only after a successful lookup.
