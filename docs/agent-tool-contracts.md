---
type: doc
title: Generated Agent Tool and Instruction Contracts
description: Source-derived PHP capability schemas and instruction versions; CI rejects drift.
category: docs
---

# Agent tool and instruction contracts

Generated from the actual registries, AgentCapabilityPolicy and AgentInstructionService.
Regenerate with `php cli/agent-contracts.php` or `./bapXphp update`; validate with `php cli/agent-contracts.php --check`.
No provider calls, credentials or business-data queries are made during generation. No conversation SQL table is introduced.

Discovery is not authorization. Every invocation still passes its PHP execution policy. These registries expose only named reads; ticket creation and owner-approved draft saves remain distinct controller/service actions.
The historical `customer_ticket_lookup` activity label is not an invocable support capability. The Support bot uses local routing and scoped context; declarations do not imply that every model receives an unrestricted tool loop.

## Support

Source: `app/Services/SupportToolRegistry.php`; instruction version 2.

### Runtime instruction sources

Private runtime Markdown is not duplicated into developer documentation.

- `.bapX/supportagent.md` — SHA-256 `a64c9cff83ad19a01ee9da90d2afe8f3845f87c4146d5cdd5c704ccaedb8c9f1`
- `.bapX/bapXcl/skill.md` — SHA-256 `4acade6d2f5dfd9ae0926807364315b9400efabcad5b55deabd33254f7f64f5d`
- `.bapX/bapXcl/support/catalog.md` — SHA-256 `0b85f5605a1eee35dcb09a147d8905059508151be9d46465d226a9b0816cc9a6`
- `.bapX/bapXcl/support/policy-and-tickets.md` — SHA-256 `4d7cefdc76d5f24b56be31848b568147bb2ce3af8bfcbd65081a93c5a9740892`

Bundle SHA-256: `f8c98eff7033db666c00794e99a6306b6f5694b7e6dd71a18221ceb5e5622ba5`.

### `public_catalog`

Read published products using a customer question.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {
        "question": {
            "type": "string",
            "minLength": 1,
            "maxLength": 256
        }
    },
    "required": [
        "question"
    ]
}
```

### `public_policy`

Read the public shipping, returns, or payment policy.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {
        "key": {
            "type": "string",
            "enum": [
                "tracking",
                "returns_or_problems",
                "shipping_cost",
                "payment_methods"
            ]
        }
    },
    "required": [
        "key"
    ]
}
```

### `get_public_contact`

Read the published business phone numbers, email, opening hours and contact form link. No customer or owner records.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {},
    "additionalProperties": false
}
```

### `list_public_services`

List the existing public enquiry offerings and contact form destinations. These are enquiries, not booking or payment actions.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "additionalProperties": false,
    "properties": {
        "query": {
            "type": "string",
            "maxLength": 120
        },
        "offset": {
            "type": "integer",
            "minimum": 0,
            "maximum": 2147483647
        },
        "limit": {
            "type": "integer",
            "minimum": 1,
            "maximum": 20
        }
    }
}
```

### `own_ticket_status`

Read only the authenticated customer support-ticket metadata.

**Access:** Authenticated customer; identity comes from PHP, never tool arguments.

```json
{
    "type": "object",
    "properties": {
        "reference": {
            "type": [
                "string",
                "null"
            ],
            "pattern": "^[a-f0-9]{16}$"
        },
        "status": {
            "type": [
                "string",
                "null"
            ],
            "enum": [
                "open",
                "answered",
                "closed",
                null
            ]
        },
        "period": {
            "type": [
                "array",
                "null"
            ],
            "items": {
                "type": "string",
                "format": "date"
            },
            "minItems": 2,
            "maxItems": 2
        }
    }
}
```

## Enterprise Admin

Source: `app/Services/EnterpriseToolRegistry.php`; instruction version 2.

### Runtime instruction sources

Private runtime Markdown is not duplicated into developer documentation.

- `.bapX/enterpriseagent.md` — SHA-256 `488b908267cc25cf98136655a21c6b58b33fbbf0047a880cd3123f9ec876f198`
- `.bapX/bapXcl/skill.md` — SHA-256 `4acade6d2f5dfd9ae0926807364315b9400efabcad5b55deabd33254f7f64f5d`
- `.bapX/bapXcl/support/catalog.md` — SHA-256 `0b85f5605a1eee35dcb09a147d8905059508151be9d46465d226a9b0816cc9a6`
- `.bapX/bapXcl/support/policy-and-tickets.md` — SHA-256 `4d7cefdc76d5f24b56be31848b568147bb2ce3af8bfcbd65081a93c5a9740892`
- `.bapX/bapXcl/enterprise/reporting.md` — SHA-256 `432827fa7c6fa1d5bc7a57d6a3c77bd4f29832d35fa3dd07df6a09ab55f7f942`

Bundle SHA-256: `1fd5bf210e7c2e6249df662f169ae4582ff26600edd1f987c3f48b36e3f51108`.

### `find_order`

Look up one order by its exact full id, or the most recent orders for a customer email explicitly provided by the owner. Returns status, total, items, courier and tracking.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "order_id": {
            "type": "string",
            "description": "The full order id explicitly provided by the owner."
        },
        "customer_email": {
            "type": "string",
            "description": "Email of the customer whose orders to list."
        }
    }
}
```

### `product_status`

Stock, price, current offer and whether a product is hidden. Omit the slug to list every product.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "slug": {
            "type": "string",
            "description": "Product slug, or part of its name."
        }
    }
}
```

### `sales_summary`

Verified paid orders, net revenue after verified refunds, status counts and best sellers by units. Use dates, a number of days, or a named period.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "from": {
            "type": "string",
            "description": "Inclusive start date YYYY-MM-DD. Defaults to 29 days before today."
        },
        "to": {
            "type": "string",
            "description": "Inclusive end date YYYY-MM-DD. Defaults to today (India time)."
        },
        "days": {
            "type": "integer",
            "description": "How many calendar days to include through today. Defaults to 30."
        },
        "period": {
            "type": "string",
            "enum": [
                "this_month",
                "all_time"
            ]
        }
    }
}
```

### `commerce_report`

Read a bounded owner report: product sales by units, current stock status, anonymous customer order-frequency groups, or coupon usage. Paid-order reports use order dates and recorded refunds; they do not measure profit, cash flow, lifetime retention or promotion attribution. Stock is a current status snapshot, not inventory quantities.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "report": {
            "type": "string",
            "enum": [
                "products",
                "stock",
                "customers",
                "coupons"
            ]
        },
        "from": {
            "type": "string",
            "description": "Inclusive start date YYYY-MM-DD. Defaults to 29 days before today."
        },
        "to": {
            "type": "string",
            "description": "Inclusive end date YYYY-MM-DD. Defaults to today (India time)."
        },
        "limit": {
            "type": "integer",
            "minimum": 1,
            "maximum": 10,
            "description": "Maximum returned rows, 1 to 10; defaults to 6. Summary covers every matching row. Omit dates for stock."
        }
    },
    "required": [
        "report"
    ],
    "additionalProperties": false
}
```

### `gst_register_summary`

Read the GST invoice register for a date range, including the number of issued invoices and review flags, taxable value, and GST totals. Includes the admin report link.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "from": {
            "type": "string",
            "description": "Inclusive start date YYYY-MM-DD. Defaults to 29 days before today."
        },
        "to": {
            "type": "string",
            "description": "Inclusive end date YYYY-MM-DD. Defaults to today (India time)."
        }
    }
}
```

### `support_enquiries`

Count recorded customer support tickets by date created, topic and current status. Includes the support queue link and the keywords behind topic counts.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "from": {
            "type": "string",
            "description": "Inclusive start date YYYY-MM-DD. Defaults to 29 days before today."
        },
        "to": {
            "type": "string",
            "description": "Inclusive end date YYYY-MM-DD. Defaults to today (India time)."
        },
        "topic": {
            "type": "string",
            "enum": [
                "orders",
                "delivery",
                "payment",
                "refund",
                "product"
            ]
        },
        "status": {
            "type": "string",
            "enum": [
                "open",
                "answered",
                "closed",
                "unanswered"
            ]
        }
    }
}
```

### `store_count`

Count recorded users, products or appointments. Returns a total without record details.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "entity": {
            "type": "string",
            "enum": [
                "users",
                "products",
                "appointments"
            ]
        }
    },
    "required": [
        "entity"
    ]
}
```

### `search_articles`

Search published blog and help articles by keyword. Returns titles and their paths so an answer can point at real published content.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "query": {
            "type": "string",
            "description": "Words to search for."
        }
    },
    "required": [
        "query"
    ]
}
```

### `operator_guide`

Read the official owner instructions for a task in this store. Use this for how-to questions about the admin screens. For current business figures, use the appropriate business lookup.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "topic": {
            "type": "string",
            "enum": [
                "getting-started",
                "catalogue",
                "orders-shipping",
                "reports",
                "support-tickets",
                "enterprise-agent",
                "agent-monitoring",
                "blog-publishing",
                "media",
                "customers",
                "email",
                "settings"
            ]
        },
        "section": {
            "type": "string",
            "description": "Optional exact heading returned in sections by a previous lookup of this guide."
        }
    },
    "required": [
        "topic"
    ]
}
```

### `coupon_status`

Whether a coupon is currently usable, and why not if it is refused: expired, not started, spend limits, usage limits.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "code": {
            "type": "string",
            "description": "The coupon code."
        }
    },
    "required": [
        "code"
    ]
}
```

### `get_public_contact`

Read the published store contact channels and opening hours. This never returns owner settings or customer contacts.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {},
    "additionalProperties": false
}
```

### `list_public_services`

List existing public store enquiry offerings and their contact form links, without creating a booking or message.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "additionalProperties": false,
    "properties": {
        "query": {
            "type": "string",
            "maxLength": 120
        },
        "offset": {
            "type": "integer",
            "minimum": 0,
            "maximum": 2147483647
        },
        "limit": {
            "type": "integer",
            "minimum": 1,
            "maximum": 20
        }
    }
}
```
