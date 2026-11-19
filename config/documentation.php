<?php
// First matching group owns the source path. No live records, credentials or issue states.
$rules = [
 'http-errors'=>['sources'=>['app/bootstrap.php','app/Services/RequestFailureService.php','tests/http-response-boundary.php'],
  'dependencies'=>['index.php','api/index.php','app/Controllers/BaseController.php'],
  'references'=>['docs/request-failures.md','docs/architecture.md','docs/deployment-hostinger.md','.agents/skills/backend-php-mysql/SKILL.md']],

 'google-tags'=>['sources'=>['integrations/google-site-kit/*','app/Controllers/AdminGoogleReportingController.php','views/admin/google-reporting.php','tests/google-reporting.php'],
  'dependencies'=>['views/layouts/app.php','views/admin/dashboard.php','views/admin/integrations.php','app/Services/SecretService.php'],
  'references'=>['docs/pages/integrations.md','content/help/admin/settings.md','.agents/skills/frontend-php/SKILL.md']],

 'sales-channels'=>['sources'=>['app/Services/MerchantFeedService.php','cli/merchant.php','views/admin/google-merchant.php','views/public/merchant-feed.php'],
  'dependencies'=>['app/Services/ProductService.php','app/Services/SettingsService.php','app/Services/MediaService.php'],
  'references'=>['docs/google-sales-channels.md','content/help/admin/catalogue.md','.agents/skills/bapxphp-cli/SKILL.md']],

 'agents'=>['sources'=>['app/Services/Agent*.php','app/Services/*ToolRegistry.php','app/Services/Support*.php','app/Services/Ai*.php','app/Controllers/SupportController.php','.bapX/*','cli/agent-*.php','views/admin/agent*.php'],
  'references'=>['docs/agent-observability.md','docs/runtime-agents.md','docs/agent-tool-contracts.md','.agents/skills/backend-php-mysql/SKILL.md']],
 'mail'=>['sources'=>['app/Services/*Mail*.php','app/Services/Smtp*.php','views/admin/mailbox.php','views/admin/email-templates.php'],
  'dependencies'=>['app/Controllers/AuthController.php','app/Controllers/AccountController.php','app/Controllers/AdminController.php'],
  'references'=>['docs/email-setup.md','.agents/skills/backend-php-mysql/SKILL.md']],
 'content'=>['sources'=>['app/Services/Blog*.php','app/Services/SeoService.php','app/Services/MarkdownRenderer.php','app/Controllers/BlogController.php','views/public/blog*.php','views/admin/blog*.php','content/blog/*'],
  'references'=>['docs/pages/blog.md','docs/modules/content-and-seo.md','.agents/skills/docs/SKILL.md']],
 'commerce'=>['sources'=>['app/Services/Order*.php','app/Services/Product*.php','app/Services/Category*.php','app/Services/Cart*.php','app/Services/Coupon*.php','app/Services/StoreStats*.php','app/Services/Gst*.php','app/Services/Tax*.php','app/Services/Razorpay*.php','app/Controllers/CommerceController.php'],
  'references'=>['docs/modules/gst-commerce.md','docs/modules/orders.md','docs/modules/catalog.md','.agents/skills/backend-php-mysql/SKILL.md']],
 'auth'=>['sources'=>['app/Services/Auth*.php','app/Services/EnvService.php','app/Services/Address*.php','app/Services/GoogleOAuth*.php','app/Controllers/AuthController.php','app/Controllers/AccountController.php'],
  'references'=>['docs/modules/auth.md','docs/modules/google-oauth.md','.agents/skills/backend-php-mysql/SKILL.md']],
 'media'=>['sources'=>['app/Services/Media*.php','app/Services/StoragePermissionService.php','views/admin/media.php'],
  'references'=>['docs/engineering/media-deletion-verification.md','docs/architecture.md','.agents/skills/admin-ui/SKILL.md']],
 'workflow'=>['sources'=>['cli/*','.github/*','.agents/*','config/documentation.php','app/Services/*MapService.php','app/Services/KnowledgeGraphService.php','AGENTS.md'],
  'references'=>['docs/development-workflow.md','docs/README.md','.agents/skills/git/SKILL.md','.agents/skills/docs/SKILL.md']],
 'interface'=>['sources'=>['views/*','assets/*','Design.md','content/help/*','app/Services/*Help*.php'],
  'references'=>['docs/admin-guide.md','docs/site-motion.md','Design.md','.agents/skills/frontend-php/SKILL.md','.agents/skills/admin-ui/SKILL.md']],
 'foundation'=>['sources'=>['*'],
  'references'=>['docs/architecture.md','docs/json-storage.md','docs/deployment-hostinger.md','.agents/skills/backend-php-mysql/SKILL.md']],
];

// Operator help needs its actual feature dependencies, not just its own prose/templates.
// This is static allowlisted metadata; no private guide body, runtime record or issue state is read.
require_once __DIR__.'/../app/Services/AdminHelpService.php';
$guideReferences=array_merge(\App\Services\AdminHelpService::references(),\App\Services\AdminHelpService::publicReferences());
$dependencies=['app/Controllers/AdminController.php','app/Controllers/AuthController.php','app/Controllers/AccountController.php',
    'app/Services/ProjectMapService.php','app/Services/KnowledgeGraphService.php','app/Services/MarkdownRenderer.php',
    'app/Services/BlogService.php','app/Services/SettingsService.php','views/layouts/admin.php','views/layouts/app.php','index.php','.htaccess'];
$references=['docs/admin-help.md','.agents/skills/admin-ui/SKILL.md','.agents/skills/docs/SKILL.md'];
foreach($guideReferences as $guide){$dependencies=array_merge($dependencies,$guide['sources']);$references[]=$guide['file'];}
return ['operator-help'=>['sources'=>['app/Services/AdminHelpService.php','content/help/admin/*','views/admin/help.php'],
    'dependencies'=>array_values(array_unique($dependencies)),'references'=>array_values(array_unique($references))]]+$rules;
