---
type: doc
title: Blog Authoring, Markdown and SEO Services
description: Source-backed developer responsibilities for blog drafts, explicit publishing, metadata and rendered content.
category: module
---

# Content authoring and metadata

`AdminController::blog/saveBlog` and `views/admin/blog.php` implement the owner
editor. `BlogService` reads/writes local Markdown with YAML frontmatter in
`content/blog/posts/`; categories use article frontmatter and the standard labels in `BlogService::categories()`. Public
`BlogController` filters unpublished and retired content and renders through
the shared public templates. The route registry gates public Blog access in
both web and JSON dispatch. There is no runtime `users.json`, remote
GitHub article index or database-backed blog body.

## Drafts and enhancement

The editor's empty-body generation route, `AdminController::aiDraftBlog`, calls
the configured Chat Completions provider through `AgentDraftService`. It no
longer returns deterministic article scaffolding. The Enterprise commands
`/create-blog` and `/add-product` with a subject require a successful model
transfer and usable structured fields; an empty command is explicitly a
manual form, not inference. Provider failures and invalid drafts are recorded
as failed requests, not successful generated drafts. The owner must supply
actual price, tax, stock and image choices. A blog proposal saves as unpublished
with the normal create-only revision contract.

`enhanceBlogTitle` and `enhanceBlogContent` call the configured provider and
return a reviewable suggestion for one field. PHP validates the returned field
and preserves existing article images. The empty-body generation action is
separate from rewriting existing content. On failure, no local AI substitute
or automatic edit is offered. Applying a suggestion checks the original
field, open article, and (for new body generation) its title. Save and
publication remain explicit owner actions. Images remain manually uploaded or
selected from existing media. The article image picker sets the cover and inserts the chosen image into the body. The editor requests a JSON receipt from the shared Media Library upload route and uses its returned URL. The Media Library form redirects after an upload. The editor keeps an unsaved browser copy of
editorial fields for the same owner and article. Reopen the editor and choose
Restore draft to recover it. When the saved revision has changed, download the
browser copy for comparison. Save confirms persistence in the app. Browser and server
recovery never select publication or subscriber email on the owner's behalf.
See the shared editorial recovery contract below.

The interactive `./bapXphp write blog [slug]` command uses the same article revision check as the editor. New articles start as drafts. Existing slugs, publication state and unedited metadata survive an edit. The command exits when another author has saved a newer version.

`BlogService::resolvedImageUrl` verifies same-site image files/persistent media
before selecting the existing fallback. `BlogController` supplies an absolute
configured-origin OpenGraph image and article schema base. Original image
frontmatter is preserved so a missing asset can be supplied later.

## Markdown and metadata

`MarkdownRenderer` converts Markdown into article HTML and is reused for drafts
and blog bodies. Any change must preserve safe text, links, tables, code and
images while enforcing HTML/URL safety; permission to author content does not
make arbitrary attributes executable by visitors. Use malicious-input fixtures
locally, never a production article, when testing rendering boundaries.

`SeoService::page` merges page defaults with controller overrides and supplies
canonical/OpenGraph/Twitter fields. `BaseController` composes those fields and
public JSON-LD. `BlogController` has explicit canonical/image overrides; generic
page origin and product availability come from `SeoService` and must be checked
against configured `APP_URL` and actual product data. The layout consumes these
resolved fields; it must not rebuild metadata from `HTTP_HOST` or the request's
TLS header. SEO markup is descriptive metadata, not proof of search ranking or
measured traffic.

Developer sources: `app/Services/BlogService.php`, `BlogDraftService.php`,
`MarkdownRenderer.php`, `SeoService.php`, `app/Controllers/BlogController.php`,
`AdminController.php`, `views/admin/blog.php`, `views/public/blog*.php`.
Current defects, acceptance status and release evidence belong to GitHub issues,
not a duplicate list in this guide.

## Rendered HTML safety

Markdown output is parsed with DOMDocument and rebuilt from an allowlist of
formatting/table/link/image tags. Event handlers, script/foreign elements,
arbitrary IDs/classes/styles and unsafe URL schemes are not retained. The only
style retained is bounded table-cell text alignment; heading IDs and language
classes have fixed patterns. Links accept approved web/root/anchor/mail forms;
images accept root paths or HTTPS, never data/script URLs. Code spans/fences
are protected as literal text before inline formatting. If DOM is unavailable,
the renderer returns escaped text rather than potentially active HTML.
Input is bounded to 1 MiB and sanitizer traversal to 64 levels. This is not a
full CommonMark implementation and is not a substitute for role authorization.

The safety change does not publish or edit stored articles. Test hostile
fixtures locally and safe actual articles after release. PHP's strip_tags
manual warns it must not be used alone for XSS prevention; OWASP similarly
separates HTML sanitization and URL/attribute handling from plain text escaping.

## Configured-origin and product metadata contract

`SeoService` reads the canonical base from the existing database/environment
configuration (`APP_URL`), never from a request Host header. An optional explicit
constructor URL is for isolated test/configuration injection. The base must be
an absolute HTTP(S) URL without credentials, query or fragment. Defaults use
that origin with the current request path and meaningful query; a valid
controller canonical override is preserved and also becomes the default OG URL.
Unsafe URL schemes fall back to the configured page URL. Product route segments
are URL-encoded once, preserving existing spaced/Unicode slugs.

A default local social image must exist and be readable. On the Sri Panchami
reference origin, the fallback is the bundled `og-image.jpg`, then
`hero-temple-bg.webp`. An independent deployment without an approved image
omits the image tags. Actual local/persistent images resolve to absolute URLs. Valid
external HTTP(S) image overrides remain supported without server-side fetching,
so the service does **not** certify external image reachability or ownership.
The same resolved image feeds default OpenGraph and Twitter metadata. The Public
Site Profile supplies the default title brand, organization name and OpenGraph
site name. With no saved display name, the legacy SEO name and configured
application name serve as fallbacks. Organization and Contact metadata use the
same public contact projection as the Contact page. The optional Twitter handle
is normalized to an @username for the twitter:site card tag.

Product JSON-LD reflects the same purchasable stock states as checkout:
`in_stock` and legacy `active` produce Schema.org `InStock`; other/missing
states produce `OutOfStock`. ProductService has already resolved offer windows;
SEO does not change prices, quantities, visibility or inventory. Admin product
preview still removes the Offer markup and remains noindex. Organization and
sitemap origins follow the configured base, and sitemap slugs are encoded.
Inline service-generated JSON-LD uses JSON-safe tag encoding so schema data
cannot terminate its script element.

Test untrusted Host/HTTPS values, absolute request forms, legitimate canonical
and image overrides, missing assets, all supported stock states, encoded slugs
and account/admin noindex behavior. Read actual public product data for browser
parity checks, never change stock to obtain a test result. Google may choose a
different canonical and does not guarantee rich results or rankings merely
because markup is valid.

Primary references: Google Search Central canonical URL documentation
(https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
and product snippet guidance
(https://developers.google.com/search/docs/appearance/structured-data/product-snippet),
plus Schema.org ItemAvailability (https://schema.org/ItemAvailability).

Blog publication dates now gate visibility through the shared BlogService
predicate, not just sorting. Draft/scheduled/raw-file routes cannot bypass it.
See `docs/pages/blog.md` for timezone, revision-match and no-delayed-newsletter
contracts. Public article metadata cannot make a withheld article public.


## Authored pages

Owners use **Pages** in the admin sidebar to write Markdown, preview current
edits, and save a draft or publish. Publishing makes the page available at its
fixed `/pages/{slug}` address and lists it in site search and the sitemap.
Returning a page to Draft withdraws it from all three. The public renderer
treats HTML tags as literal text. Existing About, Terms and Privacy routes
keep their existing templates and URLs.

**Header image** accepts an existing uploaded raster image or a new upload
through Media Library. Add alternative text if the image carries information,
or leave it blank for a decorative image. **Preview** uses the same PHP
presentation and sanitized Markdown as the published page. This follows the
server-side rendering pattern of deriving the display from validated content
and attributes at request time.
[WordPress dynamic rendering reference](https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/)

The image and its alternative text participate in the same revision check as
the Markdown. Select **No header image** and save to clear them. Page rows
created without a header render the original text-only layout. The upload
must belong to the current deployment's Media Library. The database host
serializes attachment and metadata deletion for the same upload. A saved
reference from a draft or published page keeps its upload in the library until
the page clears or replaces that image. Media Library image URLs serve the
uploaded assets while the authored page and its unsaved preview remain under
their existing publication and owner access rules.

**Enquiry form** adds a native form after the Markdown, in the same reading
column. Name and email are required. The owner can add up to three fields with
fixed slot IDs, choose short text, long text or phone number, and mark each
included field required.
An enabled form with no additional fields collects only name and email. A page
without a saved form keeps its original article. Owner preview shows the
current draft fields with disabled inputs.

The published page and its form share a revision. Each rendered form has a
session-bound submission token valid for 30 minutes. The server checks CSRF,
the publication revision, and the configured fields before applying the
shared public contact admission limits. A revised form asks the visitor to
review the current fields. A withdrawn page no longer accepts enquiries.
Successful submissions create a Contact and its Inbox entry together under
one receipt ID. The records keep the campaign slug and exact revision.
Both records keep the original timestamp with its timezone offset in JSON.
The database binds UTC values in the SQL datetime columns on create and update.
[MariaDB date and time literals](https://mariadb.com/docs/server/reference/sql-structure/sql-language-structure/date-and-time-literals)
Repeating an accepted submission preserves the existing receipt. Raw answers
stay in the owner records. The public acknowledgement does not enqueue email.
The form and receipt use native navigation so the status can receive
autofocus. The error summary links focus the named fields.
[HTML Standard autofocus processing](https://html.spec.whatwg.org/multipage/interaction.html#the-autofocus-attribute)

**Enable downloads** adds a file list beneath the published article and enquiry
form. In Pages, add files, choose the positions of saved files, or select
**Remove**. The ordered selection can contain up to 20 PDF, ZIP, TXT, PNG,
JPG or WebP files totalling at most 5,000,000 bytes. Preview displays the
file list without active links or stored uploads. New files must be selected
again after preview. The page revision atomically owns the validated list.

With payment disabled, visitors can download from a published page without an
enquiry form. If the page has a form, a confirmed receipt unlocks downloads for
that browser session for one hour. Each PHP link requires the current published revision
and an exact file in the enabled selection. Editing, disabling or withdrawing
the page invalidates old public download links and revision grants. Files are
attachments with private, non-cacheable responses. The original file location
remains outside the web root. This adapts the server-controlled delivery and
direct-path protection in the
[WooCommerce downloadable product guide](https://woocommerce.com/document/digital-downloadable-product-handling/#general-settings),
with the bapX editor's combined byte limit and revision-based access rules.

**Enable payment** adds an independent booking section. Enter the INR amount
with up to two decimal places, then preview or save the page. Preview shows the
amount and keeps the payment action disabled. Published visits can review a
booking even when the enquiry form is disabled. Free downloads follow the
page's enquiry settings. When payment and downloads are both enabled, the
booking status page makes the selected files available after verified capture.

The booking attempt keeps the published revision, amount in integer paise,
Razorpay mode, and enabled file versions. A stale page needs a fresh review.
An existing booking can still confirm against its saved price and files after
the owner updates the page or the provider link expires. The browser and actor
that started the booking can reopen its status and download the confirmed files
while that session is available.

Initialize the `campaign_bookings` collection with `./bapXphp db init` before
opening campaign checkout. Razorpay Standard Payment Links use the configured
test or live mode. Configure the matching API key pair and webhook secret. Subscribe the
existing `/api/payments/razorpay-webhook` endpoint to Payment Link events. A signed
notification triggers a fresh API check of the reference, amount, currency and
captured payment before the database records a receipt. After a lost provider
response, status uses the same payment reference for recovery.
A confirmed API credential or merchant access rejection lets the same browser
retry from the campaign page after configuration is repaired. The retry
keeps the published revision, mode, price, file versions and payment reference.
An ambiguous response, including a duplicate reference, stays in status
recovery. When the provider confirms a terminal unpaid link with no payment
records, its status page offers **Start a new booking payment**. This is an
explicit confirmation, not an automatic retry. The new receipt retains the
same published revision, price, payment mode and file versions and uses a
separate provider reference. Pending, uncertain, and previously paid attempts
do not offer a replacement. Any recorded payment, including a failed one,
requires confirmation before a separate charge. If an old receipt settles
later, the status page stops presenting new payment links and preserves the
confirmed receipt; multiple confirmed payments require business review rather
than an automatic refund. See [payment recovery](../remote-commerce.md).
Booking receipts live in `campaign_bookings` and are independent of product
orders and tax reports.
[Razorpay Payment Link events](https://razorpay.com/docs/webhooks/payment-links)

The dedicated About route uses the deployment-owned public story descriptor
in config/about.php and a reusable PHP editorial view, with semantic theme
tokens, approved image dimensions, and existing contact and module services.
The template does not contain client names or domain literals. Rebrand by
reviewing the descriptor, shared brand/SEO settings, published contact data
and assets; this is not an owner-editable page builder or multi-tenant
provisioner. Do not invent milestones, certifications, testimonials or
founder identities. The shared SeoService supplies AboutPage JSON-LD from
the same description, configured origin and organization as the page.


Each page occupies a dedicated `pg:<slug>` row in the hosted MySQL `settings`
table. Slugs are fixed after creation. Creation requires a free slug, and
updates compare the revision opened by the editor on the database host using
the same contract for direct MySQL and `/remotedb`. A conflict returns HTTP
409 and keeps the submitted text for comparison. Owners can permanently delete a page after confirming the operation. Deletion
compares the current revision and removes only the corresponding page row.
Recreating the same address generates a new revision so a stale form cannot
edit or delete the replacement.

Both article and authored-page editors keep a browser-local fallback and use
the owner-only `POST /admin/editor-draft` endpoint for server recovery. The
record identity is scoped to the signed-in owner, editor kind and original
address (or new-item editor). Each acknowledged snapshot is bound to the
canonical revision opened by the editor, with a separate random CAS token.
The existing MySQL settings table stores private `ed:<digest>` records with
up to three versions and a 2.5 MB encoded recovery-history bound. Ordinary
configuration reads exclude that namespace. Canonical articles remain
Markdown; pages remain revision-checked `pg:<slug>` rows.

The endpoint accepts only allowlisted editable text/settings fields, never
publication status, subscriber mail, upload bytes or arbitrary metadata.
It checks owner identity, CSRF, input bounds and the canonical revision.
After a short edit debounce, server writes/discards compare the recovery
token, so two browsers cannot silently overwrite one another. A changed
canonical revision disables restore but leaves the recovery downloadable.
Current unsaved fields and prior server versions can be downloaded separately;
restoring over a changed editor requires explicit confirmation. Failed server
reads/writes keep the browser copy. Only the normal explicit save route
publishes or modifies canonical content; a successful save clears its matching
acknowledged recovery token, never a newer snapshot.

Source-derived contract: WordPress 7.1.4's
`wp_create_post_autosave` and `wp_get_post_autosave` isolate autosaves by
post and author; `wp_autosave` verifies nonce and edit permission before choosing
the save route; `wp_check_post_lock` / `wp_set_post_lock` manage a timestamped
editing-presence window. bapX adopts per-editor recovery, role checks and
conflict safety without WordPress post/revision tables. Unlike WordPress's
same-author unlocked draft overwrite, bapX never autosaves into the canonical
record, even when unpublished. Optimistic CAS guards conflicts; an active
cross-user heartbeat/presence lease is not implemented or claimed.

Acceptance uses the real owner HTTP controller and isolated bridge
(`tests/campaign-form-http.php`), input/history and disposable MySQL concurrent
writers (`tests/editor-draft.php`), and actual PHP editor markup with Chromium
at 1280px and 375px (`tests/editor-server-recovery-browser.cjs`).
These fixtures do not establish authorized production recovery.

Editing and publishing stay with the existing owner role. The workflows do
not delegate drafts to a separate editor or require a second publisher. Add
granular editorial permissions when a delegated editing or approval workflow
is needed. Customer accounts have no editorial controls.
