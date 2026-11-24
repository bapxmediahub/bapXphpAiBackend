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
The historical `customer_ticket_lookup` activity label is not an invocable support capability. Both model surfaces use the bounded AiClient tool loop through RuntimeAgentToolRegistry. The model adapter filters declarations by the authenticated actor and adds a private read_runtime_skill activation operation, never a public MCP or arbitrary file capability. The file manifest below lists all available role documents; individual model prompts initially load only the constitution and shared runtime contract, then activate relevant skills.

## Support

Source: `app/Services/SupportToolRegistry.php`; instruction version 2.

### Runtime instruction sources

Private runtime Markdown is not duplicated into developer documentation.

- `.bapX/supportagent.md` — SHA-256 `fae2895879f617ed2aefde742d4d55e2ece190ee1645011173d1a5805eee883d`
- `.bapX/bapXcl/skill.md` — SHA-256 `bef6836de280f3118cd9e438bcac6babffc1bea6f3c1d85f5fc962c8a01043e9`
- `.bapX/bapXcl/support/catalog.md` — SHA-256 `0b85f5605a1eee35dcb09a147d8905059508151be9d46465d226a9b0816cc9a6`
- `.bapX/bapXcl/support/policy-and-tickets.md` — SHA-256 `ed732c56f5da36ac3524940e5170aab9a348c8a68babe7f9d4a55b464abf9138`

Bundle SHA-256: `2ad5de312547f533d9c9abab9df4ab9a9c0e083086c133c29ea3b1e3e48482b5`.

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
                "payment_methods",
                "delivery_within_india",
                "delivery_outside_india",
                "consultation_booking"
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

### `public_catalogue_summary`

Count all currently published products and their configured public category memberships. Use for overall catalogue counts and category overview, not a named product or private order.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {}
}
```

### `public_best_sellers`

Get up to four currently visible storefront best sellers by recorded paid, non-cancelled, non-refunded orders, all time only. No sales amounts, private orders or date filters.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {}
}
```

### `search_articles`

Search published blog and customer help articles by keyword. Returns real titles and paths; never private documents or article bodies.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {
        "query": {
            "type": "string",
            "maxLength": 120
        }
    },
    "required": [
        "query"
    ],
    "additionalProperties": false
}
```

### `public_product_details`

Read exact currently published products by raw slug; use for comparisons, follow-ups and current specifications.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {
        "slugs": {
            "type": "array",
            "items": {
                "type": "string"
            },
            "minItems": 1,
            "maxItems": 3
        }
    },
    "required": [
        "slugs"
    ],
    "additionalProperties": false
}
```

### `own_order_status`

Count matching orders and read up to five recent orders belonging only to the signed-in customer; optional exact order reference. Never accepts an email or user ID.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {
        "reference": {
            "type": "string",
            "pattern": "^[a-fA-F0-9]{16}$"
        }
    },
    "additionalProperties": false
}
```

### `own_cart`

Read this browser session cart item names, product slugs and quantities. No cart mutation.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {},
    "additionalProperties": false
}
```

### `present_contact_enquiry`

Offer the existing customer-reviewed contact form when a human enquiry is useful. Does not submit, email, create tickets or reserve appointments.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {},
    "additionalProperties": false
}
```

### `read_published_article`

Read a bounded text excerpt from an exact currently published blog/help article slug. Use after article discovery to answer content questions. No private files.

**Access:** Guest or authenticated customer; published/public data only.

```json
{
    "type": "object",
    "properties": {
        "slug": {
            "type": "string",
            "pattern": "^[a-z0-9-]{1,120}$"
        }
    },
    "required": [
        "slug"
    ],
    "additionalProperties": false
}
```

## Enterprise Admin

Source: `app/Services/EnterpriseToolRegistry.php`; instruction version 2.

### Runtime instruction sources

Private runtime Markdown is not duplicated into developer documentation.

- `.bapX/enterpriseagent.md` — SHA-256 `8f13b9e45793e3e1a1172b6d7729208020f2046cf0d9ab10e789fe786f4a30e6`
- `.bapX/bapXcl/skill.md` — SHA-256 `bef6836de280f3118cd9e438bcac6babffc1bea6f3c1d85f5fc962c8a01043e9`
- `.bapX/bapXcl/support/catalog.md` — SHA-256 `0b85f5605a1eee35dcb09a147d8905059508151be9d46465d226a9b0816cc9a6`
- `.bapX/bapXcl/support/policy-and-tickets.md` — SHA-256 `ed732c56f5da36ac3524940e5170aab9a348c8a68babe7f9d4a55b464abf9138`
- `.bapX/bapXcl/enterprise/reporting.md` — SHA-256 `432827fa7c6fa1d5bc7a57d6a3c77bd4f29832d35fa3dd07df6a09ab55f7f942`

Bundle SHA-256: `b44e10d59981bfe9388b25b35bda986cddde0e80c8ae66c19d5ca952ccc518a6`.

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

Search published blog and customer help articles by keyword. Returns real titles and paths; never private documents or article bodies.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "query": {
            "type": "string",
            "maxLength": 120
        }
    },
    "required": [
        "query"
    ],
    "additionalProperties": false
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

### `order_count`

Count all recorded orders (not just paid orders) within explicit inclusive dates. Returns status breakdown; no customer details.

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
        "period": {
            "type": "string",
            "enum": [
                "all_time",
                "this_month"
            ]
        }
    },
    "additionalProperties": false
}
```

### `read_published_article`

Read a bounded text excerpt from an exact currently published blog/help article slug. Use after article discovery to answer content questions. No private files.

**Access:** Authenticated admin, enforced server-side.

```json
{
    "type": "object",
    "properties": {
        "slug": {
            "type": "string",
            "pattern": "^[a-z0-9-]{1,120}$"
        }
    },
    "required": [
        "slug"
    ],
    "additionalProperties": false
}
```
