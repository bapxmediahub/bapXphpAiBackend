<?php
// First matching group owns the source path. No live records, credentials or issue states.
return [
 'agents'=>['sources'=>['app/Services/Agent*.php','app/Services/*ToolRegistry.php','app/Services/Support*.php','app/Services/Ai*.php','app/Controllers/SupportController.php','.bapX/*','cli/agent-*.php','views/admin/agent*.php'],
  'references'=>['docs/agent-observability.md','docs/runtime-agents.md','docs/agent-tool-contracts.md','.agents/skills/backend-php-mysql/SKILL.md']],
 'mail'=>['sources'=>['app/Services/*Mail*.php','app/Services/Smtp*.php','views/admin/mailbox.php'],
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
