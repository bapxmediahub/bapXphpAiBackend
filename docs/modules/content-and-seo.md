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

`BlogDraftService` builds a deterministic scaffold from template/title/source
URL. It is not itself a model client. `AdminController::aiDraftBlog` returns
that scaffold for review. `enhanceBlogTitle` and `enhanceBlogContent` separately
call the configured model and return a suggestion for only the requested field.
The owner reviews and applies it before saving. The model supplies plain text
or Markdown. PHP validates the returned field, compares article image URLs
against the original and builds the JSON response. Schema-constrained output
depends on provider/model support. This editing flow uses the site's configured
text interface across both providers. [Google structured outputs](https://ai.google.dev/gemini-api/docs/structured-output)
and [OpenAI structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs).

A provider failure leaves the original text available for editing. If the
browser can simplify headline spacing/capitalization or separate the sentences
of a plain paragraph, it shows a separately labelled local formatting preview.
Applying a suggestion checks the original field and open article again. Neither endpoint
proves the content was saved or published. The owner must review, explicitly
Save, and select the publication state. Images remain manually uploaded or
selected from existing media. The article image picker sets the cover and inserts the chosen image into the body. The editor requests a JSON receipt from the shared Media Library upload route and uses its returned URL. The Media Library form redirects after an upload. The editor keeps an unsaved browser copy of
editorial fields for the same owner and article. Reopen the editor and choose
Restore draft to recover it. When the saved revision has changed, download the
browser copy for comparison. Save confirms persistence in the app. Browser
recovery never selects publication or subscriber email on the owner's behalf.

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

The authored-page editor also saves unsent title, address, Markdown and header
selection locally in the current browser, scoped to the signed-in owner and original page address.
It offers explicit restore/discard for the same revision and Markdown download
rather than restore when the saved revision changed. Publication status is
never autosaved or restored. Browser drafts are not server-side drafts or a
distributed edit lock. A competing tab can prevent a local snapshot replacement;
the existing MySQL revision check remains the authoritative save guard.

Editing and publishing stay with the existing owner role. The workflows do
not delegate drafts to a separate editor or require a second publisher. Add
granular editorial permissions when a delegated editing or approval workflow
is needed. Customer accounts have no editorial controls.
