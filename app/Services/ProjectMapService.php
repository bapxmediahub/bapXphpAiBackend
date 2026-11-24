<?php
namespace App\Services;
final class ProjectMapService {
    private const RUNTIME_JSON_STORES = [
        'test_race',
    ];

    public const SHARED_CONTROLLERS = ['BaseController'];
    public const SHARED_SERVICES = ['SeoService', 'SmtpMailer', 'ImageOptimizerService', 'DocsMapService', 'GitHubDocService', 'RateLimiter', 'KnowledgeGraphService'];
    public const SHARED_VIEWS = ['account/_nav', 'layouts/admin', 'layouts/app', 'public/404', 'admin/environment', 'public/home-collections', 'public/home-product-card', 'public/home-rail-controls', 'admin/_commerce-overview', 'admin/list-controls'];
    public const KNOWN_UNWIRED_COLLECTIONS = ['wallet_transactions'];

    public static function registry(): array {
        $routes = [
            ['method'=>'GET','path'=>'/','name'=>'home','page'=>'public/home','controller'=>'PublicController@home','services'=>['ProductService','TempleService','CategoryService']],
            ['method'=>'GET','path'=>'/search','name'=>'search','page'=>'public/search','controller'=>'PublicController@search','services'=>['AuthoredPageService','ProductService','BlogService','TempleService']],
            ['method'=>'GET','path'=>'/pages/{slug}','name'=>'pages.show','page'=>'public/authored-page','controller'=>'PublicController@authoredPage','services'=>['AuthoredPageService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/about','name'=>'about','page'=>'public/about','controller'=>'PublicController@about','services'=>[]],
            ['method'=>'GET','path'=>'/ai-assistants','name'=>'ai-assistants','page'=>'public/ai-assistants','controller'=>'PublicController@aiAssistants','services'=>['PublicMcpService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/llms.txt','name'=>'ai-assistants.text','page'=>'public/ai-assistants','controller'=>'PublicController@assistantInstructions','services'=>['PublicMcpService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/sri-panchami-spiritual','name'=>'spiritual','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/spiritual','name'=>'spiritual.short','page'=>'public/spiritual','controller'=>'PublicController@spiritual','services'=>[]],
            ['method'=>'GET','path'=>'/terms','name'=>'terms','page'=>'public/terms','controller'=>'PublicController@terms','services'=>[]],
            ['method'=>'GET','path'=>'/privacy','name'=>'privacy','page'=>'public/privacy','controller'=>'PublicController@privacy','services'=>[]],

            ['method'=>'GET','path'=>'/consult','name'=>'consult','page'=>'public/shop','controller'=>'PublicController@consult','services'=>[]],
            ['method'=>'GET','path'=>'/consult/{slug}','name'=>'consult.show','page'=>'public/shop','controller'=>'PublicController@consultant','services'=>[]],
            ['method'=>'GET','path'=>'/temples','name'=>'temples','page'=>'public/temples','controller'=>'PublicController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/temples/{slug}','name'=>'temple.show','page'=>'public/temple','controller'=>'PublicController@temple','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/shop','name'=>'shop','page'=>'public/shop','controller'=>'PublicController@shop','services'=>['ProductService','CategoryService']],
            ['method'=>'GET','path'=>'/categories','name'=>'categories','page'=>'public/categories','controller'=>'PublicController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/product/{slug}','name'=>'product.show','page'=>'public/product','controller'=>'PublicController@product','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/api','name'=>'api.index','page'=>'public/404','controller'=>'ApiController@index','services'=>[]],
            ['method'=>'GET','path'=>'/is-sha-valid','name'=>'deployment.verify','page'=>'public/404','controller'=>'ApiController@isShaValid','services'=>['DeploymentRevisionService']],
            ['method'=>'GET','path'=>'/api/is-sha-valid','name'=>'api.deployment.verify','page'=>'public/404','controller'=>'ApiController@isShaValid','services'=>['DeploymentRevisionService']],
            ['method'=>'GET','path'=>'/api/shop','name'=>'api.shop','page'=>'public/404','controller'=>'ApiController@shop','services'=>['ProductService','CategoryService']],
            ['method'=>'GET','path'=>'/api/categories','name'=>'api.categories','page'=>'public/404','controller'=>'ApiController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/api/product/{slug}','name'=>'api.product','page'=>'public/404','controller'=>'ApiController@product','services'=>['ProductService']],
            ['method'=>'GET','path'=>'/api/temples','name'=>'api.temples','page'=>'public/404','controller'=>'ApiController@temples','services'=>['TempleService']],
            ['method'=>'GET','path'=>'/api/agent-tools','name'=>'api.agent-tools','page'=>'public/404','controller'=>'PublicAgentDiscoveryController@index','services'=>['PublicAgentDiscoveryService','PublicMcpService','PublicSiteUrlService']],
            ['method'=>'GET','path'=>'/api/knowledge/articles','name'=>'api.knowledge.articles','page'=>'public/404','controller'=>'ApiController@knowledgeArticles','services'=>['PublicKnowledgeService','BlogService']],
            ['method'=>'GET','path'=>'/api/knowledge/products','name'=>'api.knowledge.products','page'=>'public/404','controller'=>'ApiController@knowledgeProducts','services'=>['PublicKnowledgeService','ProductService']],
            ['method'=>'GET','path'=>'/api/knowledge/contact','name'=>'api.knowledge.contact','page'=>'public/404','controller'=>'ApiController@knowledgeContact','services'=>['PublicKnowledgeService']],
            ['method'=>'GET','path'=>'/api/knowledge/services','name'=>'api.knowledge.services','page'=>'public/404','controller'=>'ApiController@knowledgeServices','services'=>['PublicKnowledgeService']],
            ['method'=>'GET','path'=>'/api/knowledge/a2ui','name'=>'api.knowledge.a2ui','page'=>'public/404','controller'=>'ApiController@knowledgeA2ui','services'=>['PublicA2uiService','PublicKnowledgeService']],
            ['method'=>'POST','path'=>'/api/mcp','name'=>'api.mcp','page'=>'public/mcp-app','controller'=>'PublicMcpController@handle','services'=>['PublicMcpService','PublicKnowledgeService','PublicA2uiService','RemoteCartService','RemoteCheckoutService','RemoteCheckoutStateService','VerifiedOrderService']],
            ['method'=>'GET','path'=>'/api/mcp','name'=>'api.mcp.method','page'=>'public/404','controller'=>'PublicMcpController@handle','services'=>['PublicMcpService']],
            ['method'=>'DELETE','path'=>'/api/mcp','name'=>'api.mcp.delete','page'=>'public/404','controller'=>'PublicMcpController@handle','services'=>['PublicMcpService']],
            ['method'=>'OPTIONS','path'=>'/api/mcp','name'=>'api.mcp.options','page'=>'public/404','controller'=>'PublicMcpController@handle','services'=>['PublicMcpService']],
            ['method'=>'GET','path'=>'/cart','name'=>'cart','page'=>'public/cart','controller'=>'PublicController@cart','services'=>['CartService','ProductService','SiteTelemetryService']],
            ['method'=>'GET','path'=>'/cart/agent-checkout','name'=>'cart.agent-checkout','page'=>'public/agent-checkout','controller'=>'CommerceController@agentCheckout','services'=>['RemoteCartService','PublicKnowledgeService','ProductService','DatabaseService']],
            ['method'=>'POST','path'=>'/cart/agent-checkout','name'=>'cart.agent-checkout.import','page'=>'public/agent-checkout','controller'=>'CommerceController@importAgentCheckout','services'=>['RemoteCartService','CartService','DatabaseService']],
            ['method'=>'GET','path'=>'/checkout','name'=>'checkout','page'=>'public/checkout','controller'=>'PublicController@checkout','services'=>['CartService','ProductService','SecretService','AddressService','SettingsService']],
            ['method'=>'GET','path'=>'/checkout/payment','name'=>'checkout.payment-handoff','page'=>'public/agent-payment','controller'=>'RemoteCheckoutController@payment','services'=>['RemoteCheckoutService','DatabaseService']],
            ['method'=>'POST','path'=>'/api/payments/razorpay-webhook','name'=>'checkout.payment-webhook','response'=>'json','controller'=>'RemoteCheckoutController@webhook','services'=>['RemoteCheckoutService','PaymentService','VerifiedOrderService','DatabaseService','SecretService']],
            ['method'=>'GET','path'=>'/feeds/google-products.xml','name'=>'merchant.feed','page'=>'public/merchant-feed','controller'=>'PublicController@merchantFeed','services'=>['MerchantFeedService','ProductService','SettingsService']],
            ['method'=>'GET','path'=>'/sitemap.xml','name'=>'sitemap','page'=>'public/sitemap','controller'=>'PublicController@sitemap','services'=>['BlogService','ProductService','AuthoredPageService']],
            ['method'=>'GET','path'=>'/contact','name'=>'contact','page'=>'public/contact','controller'=>'PublicController@contact','services'=>['PublicKnowledgeService']],
            ['method'=>'POST','path'=>'/contact','name'=>'contact.post','page'=>'public/contact','controller'=>'PublicController@contact','services'=>['ContactService','PublicKnowledgeService']],
            ['method'=>'GET','path'=>'/login','name'=>'login','page'=>'public/login','controller'=>'PublicController@login','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/forgot-password','name'=>'forgot-password','page'=>'public/forgot-password','controller'=>'AuthController@forgotPassword','services'=>['PasswordResetService']],
            ['method'=>'POST','path'=>'/forgot-password','name'=>'forgot-password.post','page'=>'public/forgot-password','controller'=>'AuthController@forgotPasswordPost','services'=>['PasswordResetService']],
            ['method'=>'GET','path'=>'/reset-password','name'=>'reset-password','page'=>'public/reset-password','controller'=>'AuthController@resetPassword','services'=>['PasswordResetService']],
            ['method'=>'POST','path'=>'/reset-password','name'=>'reset-password.post','page'=>'public/reset-password','controller'=>'AuthController@resetPasswordPost','services'=>['PasswordResetService']],
            ['method'=>'GET','path'=>'/auth/google','name'=>'auth.google','page'=>'public/login','controller'=>'AuthController@googleRedirect','services'=>['SecretService']],
            ['method'=>'GET','path'=>'/auth/google/callback','name'=>'auth.google.callback','page'=>'public/login','controller'=>'AuthController@callback','services'=>['SecretService','DatabaseService']],
            ['method'=>'GET','path'=>'/register','name'=>'register','page'=>'public/register','controller'=>'AuthController@register','services'=>[]],
            ['method'=>'POST','path'=>'/register','name'=>'register.post','page'=>'public/register','controller'=>'AuthController@registerPost','services'=>['DatabaseService']],
            ['method'=>'POST','path'=>'/login','name'=>'login.post','page'=>'public/login','controller'=>'AuthController@loginPost','services'=>['DatabaseService']],
            ['method'=>'GET','path'=>'/logout','name'=>'logout','page'=>'public/login','controller'=>'AuthController@logout','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/dashboard','name'=>'account.dashboard','page'=>'account/orders','controller'=>'AccountController@dashboard','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/dashboard/orders','name'=>'account.dashboard.orders','page'=>'account/orders','controller'=>'AccountController@orders','services'=>['AuthService','OrderService','ReviewService']],
            ['method'=>'GET','path'=>'/account/dashboard/sessions','name'=>'account.dashboard.sessions','page'=>'account/orders','controller'=>'AccountController@bookings','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/dashboard/install','name'=>'account.dashboard.install','page'=>'account/install','controller'=>'AccountController@install','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/dashboard/profile','name'=>'account.dashboard.profile','page'=>'account/profile','controller'=>'AccountController@profile','services'=>['AuthService','DatabaseService']],
            ['method'=>'POST','path'=>'/account/dashboard/profile','name'=>'account.profile.save','page'=>'account/profile','controller'=>'AccountController@saveProfile','services'=>['AuthService','DatabaseService']],
            ['method'=>'POST','path'=>'/account/dashboard/profile/password','name'=>'account.profile.password','page'=>'account/profile','controller'=>'AccountController@changePassword','services'=>['AuthService','DatabaseService','PasswordResetService']],
            ['method'=>'GET','path'=>'/account/dashboard/addresses','name'=>'account.dashboard.addresses','page'=>'account/addresses','controller'=>'AccountController@addresses','services'=>['AuthService','AddressService','TaxService']],
            ['method'=>'POST','path'=>'/account/dashboard/addresses/save','name'=>'account.addresses.save','page'=>'account/addresses','controller'=>'AccountController@saveAddress','services'=>['AuthService','AddressService']],
            ['method'=>'POST','path'=>'/account/dashboard/addresses/delete','name'=>'account.addresses.delete','page'=>'account/addresses','controller'=>'AccountController@deleteAddress','services'=>['AuthService','AddressService']],
            ['method'=>'GET','path'=>'/account/orders','name'=>'account.orders.legacy','page'=>'account/orders','controller'=>'AccountController@legacyOrders','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/orders','name'=>'orders.legacy','page'=>'account/orders','controller'=>'AccountController@legacyOrders','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/account/orders/{orderId}/invoice','name'=>'account.invoice','page'=>'account/invoice','controller'=>'AccountController@invoice','services'=>['AuthService','OrderService','SettingsService','TaxService']],
            ['method'=>'GET','path'=>'/account/orders/{orderId}','name'=>'account.order','page'=>'account/order','controller'=>'AccountController@order','services'=>['AuthService','OrderService','SettingsService']],
            ['method'=>'GET','path'=>'/account/bookings','name'=>'account.bookings.legacy','page'=>'account/orders','controller'=>'AccountController@legacyBookings','services'=>['AuthService']],
            ['method'=>'POST','path'=>'/api/consultations/{id}/status','name'=>'api.consultation.status','page'=>'admin/list','controller'=>'ConsultationController@status','services'=>['AuthService','ConsultationService']],
            ['method'=>'GET','path'=>'/admin','name'=>'admin.dashboard.legacy','page'=>'admin/dashboard','controller'=>'AdminController@legacyDashboard','services'=>['AuthService']],
            ['method'=>'GET','path'=>'/admin/','name'=>'admin.dashboard','page'=>'admin/dashboard','controller'=>'AdminController@dashboard','services'=>['ResourceService','OrderService','StoreStatsService','SiteInsightsService']],
            ['method'=>'GET','path'=>'/admin/system-health','name'=>'admin.system_health','page'=>'admin/system-health','controller'=>'AdminController@systemHealth','services'=>['AuthService','RuntimeHealthService','DatabaseService']],
            ['method'=>'GET','path'=>'/admin/docs','name'=>'admin.docs','page'=>'admin/help','controller'=>'AdminController@helpIndex','services'=>['AdminHelpService','AuthService']],
            ['method'=>'GET','path'=>'/admin/docs/{slug}','name'=>'admin.docs.guide','page'=>'admin/help','controller'=>'AdminController@helpGuide','services'=>['AdminHelpService','MarkdownRenderer','AuthService']],
            ['method'=>'GET','path'=>'/admin/mcp-clients','name'=>'admin.mcp.clients.settings','page'=>'admin/mcp-clients','controller'=>'AdminMcpClientController@settings','services'=>['McpClientService','AuthService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/mcp-clients/issue','name'=>'admin.mcp.clients.issue','page'=>'admin/mcp-clients','controller'=>'AdminMcpClientController@issue','services'=>['McpClientService','AuthService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/mcp-clients/revoke','name'=>'admin.mcp.clients.revoke','page'=>'admin/mcp-clients','controller'=>'AdminMcpClientController@revoke','services'=>['McpClientService','AuthService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/google-reporting','name'=>'admin.google.reporting.settings','page'=>'admin/google-reporting','controller'=>'AdminGoogleReportingController@settings','services'=>['SecretService','AuthService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/google-reporting/connect','name'=>'admin.google.reporting.connect','page'=>'admin/google-reporting','controller'=>'AdminGoogleReportingController@connect','services'=>['SecretService','AuthService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/google-reporting/callback','name'=>'admin.google.reporting.callback','page'=>'admin/google-reporting','controller'=>'AdminGoogleReportingController@callback','services'=>['SecretService','AuthService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/google-reporting/select','name'=>'admin.google.reporting.select','page'=>'admin/google-reporting','controller'=>'AdminGoogleReportingController@select','services'=>['SecretService','AuthService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/google-reporting/disconnect','name'=>'admin.google.reporting.disconnect','page'=>'admin/google-reporting','controller'=>'AdminGoogleReportingController@disconnect','services'=>['SecretService','AuthService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/google-merchant','name'=>'admin.merchant','page'=>'admin/google-merchant','controller'=>'AdminController@googleMerchant','services'=>['MerchantFeedService','ProductService','SettingsService']],
            ['method'=>'GET','path'=>'/admin/products','name'=>'admin.products','page'=>'admin/product-form','controller'=>'AdminController@products','services'=>['ProductService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/categories','name'=>'admin.categories','page'=>'admin/resource','controller'=>'AdminController@categories','services'=>['CategoryService']],
            ['method'=>'GET','path'=>'/admin/collections','name'=>'admin.collections','page'=>'admin/collections','controller'=>'AdminController@collections','services'=>['CategoryService','ProductService']],
            ['method'=>'GET','path'=>'/admin/coupons','name'=>'admin.coupons','page'=>'admin/resource','controller'=>'AdminController@coupons','services'=>['CouponService']],
            ['method'=>'GET','path'=>'/admin/users','name'=>'admin.users','page'=>'admin/users','controller'=>'AdminController@users','services'=>['ResourceService','OrderService','UserOrderHistoryService']],
            ['method'=>'GET','path'=>'/admin/orders','name'=>'admin.orders','page'=>'admin/list','controller'=>'AdminController@orders','services'=>['OrderService']],
            ['method'=>'GET','path'=>'/admin/orders/{id}/invoice','name'=>'admin.order.invoice','page'=>'account/invoice','controller'=>'AdminController@orderInvoice','services'=>['OrderService']],
            ['method'=>'GET','path'=>'/admin/orders/{id}','name'=>'admin.order.show','page'=>'admin/detail','controller'=>'AdminController@order','services'=>['OrderService','ShippingService','TaxService']],
            ['method'=>'POST','path'=>'/admin/orders/{id}/status','name'=>'admin.order.status','page'=>'admin/detail','controller'=>'AdminController@saveOrderStatus','services'=>['OrderService','MailQueueService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/orders/{id}/refund','name'=>'admin.order.refund','page'=>'admin/detail','controller'=>'AdminController@importOrderRefund','services'=>['OrderService','SecretService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/orders/{id}/tax-review','name'=>'admin.order.tax-review','page'=>'admin/detail','controller'=>'AdminController@resolveOrderTaxReview','services'=>['OrderService','TaxService','SettingsService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/shipping','name'=>'admin.shipping','page'=>'admin/shipping','controller'=>'AdminController@shipping','services'=>['ShippingService']],
            ['method'=>'POST','path'=>'/admin/shipping/preview','name'=>'admin.shipping.preview','page'=>'admin/shipping','controller'=>'AdminController@previewShipping','services'=>['ShippingService']],
            ['method'=>'GET','path'=>'/admin/astrologers','name'=>'admin.astrologers','page'=>'admin/dashboard','controller'=>'AdminController@astrologers','services'=>[]],
            ['method'=>'GET','path'=>'/admin/appointments','name'=>'admin.appointments','page'=>'admin/list','controller'=>'AdminController@appointments','services'=>['AppointmentService']],
            ['method'=>'GET','path'=>'/admin/consultation-analytics','name'=>'admin.consultation-analytics','page'=>'admin/consultation-analytics','controller'=>'AdminController@consultationAnalytics','services'=>['ConsultationService']],
            ['method'=>'GET','path'=>'/admin/tax-report','name'=>'admin.tax-report','page'=>'admin/tax-report','controller'=>'AdminController@taxReport','services'=>['OrderService','ProductService','StoreStatsService','TaxService','GstRegisterService']],
            ['method'=>'GET','path'=>'/admin/temples','name'=>'admin.temples','page'=>'admin/temples','controller'=>'AdminController@temples','services'=>['TempleService','SchemaService']],
            ['method'=>'GET','path'=>'/admin/settings','name'=>'admin.settings','page'=>'admin/settings','controller'=>'AdminController@settings','services'=>['SettingsService']],
            ['method'=>'POST','path'=>'/admin/settings/save','name'=>'admin.settings.save','page'=>'admin/settings','controller'=>'AdminController@saveSettings','services'=>['SettingsService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/settings/admin-credentials','name'=>'admin.settings.admin-credentials','page'=>'admin/settings','controller'=>'AdminController@saveAdminCredentials','services'=>['EnvService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/integrations','name'=>'admin.integrations','page'=>'admin/integrations','controller'=>'AdminController@integrations','services'=>['SettingsService','PaymentService','SecretService']],
            ['method'=>'GET','path'=>'/admin/agent','name'=>'admin.agent','page'=>'admin/agent','controller'=>'AdminController@agent','services'=>['SecretService','DatabaseService']],
            ['method'=>'GET','path'=>'/admin/agent/history','name'=>'admin.agent.history','page'=>'admin/agent','controller'=>'AdminController@agentHistory','services'=>['AgentConversationService']],
            ['method'=>'GET','path'=>'/admin/agent/history/{id}','name'=>'admin.agent.history.show','page'=>'admin/agent','controller'=>'AdminController@agentHistoryShow','services'=>['AgentConversationService']],
            ['method'=>'POST','path'=>'/admin/agent/history/{id}/delete','name'=>'admin.agent.history.delete','page'=>'admin/agent','controller'=>'AdminController@agentHistoryDelete','services'=>['AgentConversationService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/agent/review/{id}','name'=>'admin.agent.review','page'=>'admin/agent-review','controller'=>'AdminController@agentReview','services'=>['AuthService','AgentConversationService','AgentEvaluationService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/agent/review/{id}','name'=>'admin.agent.review.save','page'=>'admin/agent-review','controller'=>'AdminController@agentReviewSave','services'=>['AuthService','AgentConversationService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/agent/conversation/{id}','name'=>'admin.agent.conversation','page'=>'admin/agent-monitoring','controller'=>'AdminController@agentConversation','services'=>['AgentConversationService']],
            ['method'=>'GET','path'=>'/admin/agent/monitoring','name'=>'admin.agent.monitoring','page'=>'admin/agent-monitoring','controller'=>'AdminController@agentMonitoring','services'=>['AuditLogService','DatabaseService','AgentConversationService','AgentEvaluationService','SiteTelemetryService']],
            ['method'=>'POST','path'=>'/admin/agent/ask','name'=>'admin.agent.ask','page'=>'admin/agent','controller'=>'AdminController@agentAsk','services'=>['SecretService','DatabaseService','AuditLogService','AgentAttachmentService','AgentDraftService','AgentInstructionService','AgentToolRegistry','EnterpriseToolRegistry','AgentConversationService','AgentTraceService','AgentEvaluationService','AgentTranscriptSanitizer','AgentCapabilityPolicy','AgentContextService','GstRegisterService','AiReplyCleaner','AiClient']],
            ['method'=>'GET','path'=>'/admin/contact-submissions','name'=>'admin.contact-submissions','page'=>'admin/resource','controller'=>'AdminController@contactSubmissions','services'=>['ContactService']],
            ['method'=>'POST','path'=>'/admin/contact_submissions/save','name'=>'admin.contact-submissions.save','page'=>'admin/resource','controller'=>'AdminController@saveContactSubmission','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/contact_submissions/delete','name'=>'admin.contact-submissions.delete','page'=>'admin/resource','controller'=>'AdminController@deleteContactSubmission','services'=>['ResourceService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/email-templates','name'=>'admin.email-templates','page'=>'admin/email-templates','controller'=>'AdminController@emailTemplates','services'=>['MailTemplateService','MailQueueService','SecretService']],
            ['method'=>'POST','path'=>'/admin/email-templates/test','name'=>'admin.email-templates.test','page'=>'admin/email-templates','controller'=>'AdminController@testEmailTemplate','services'=>['MailTemplateService','MailQueueService','SecretService']],
            ['method'=>'GET','path'=>'/admin/email-inbox','name'=>'admin.email-inbox','page'=>'admin/mailbox','controller'=>'AdminController@emailInbox','services'=>['MailStorageService']],
            ['method'=>'GET','path'=>'/admin/email-outbox','name'=>'admin.email-outbox','page'=>'admin/mailbox','controller'=>'AdminController@emailOutbox','services'=>['MailStorageService','MailQueueService']],
            ['method'=>'GET','path'=>'/admin/support-tickets','name'=>'admin.support-tickets','page'=>'admin/list','controller'=>'AdminController@supportTickets','services'=>['DatabaseService','SupportTicketService']],
            ['method'=>'POST','path'=>'/admin/support-tickets/save','name'=>'admin.support-tickets.save','page'=>'admin/list','controller'=>'AdminController@saveSupportTicket','services'=>['SupportTicketService','DatabaseService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/appearance','name'=>'admin.appearance','page'=>'admin/appearance','controller'=>'AdminController@appearance','services'=>['SettingsService']],
            ['method'=>'GET','path'=>'/admin/appearance/menu','name'=>'admin.appearance.menu','page'=>'admin/header-menu','controller'=>'AdminController@headerMenu','services'=>['SettingsService','AuthoredPageService']],
            ['method'=>'POST','path'=>'/admin/appearance/menu','name'=>'admin.appearance.menu.save','page'=>'admin/header-menu','controller'=>'AdminController@saveHeaderMenu','services'=>['SettingsService','AuthoredPageService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/appearance/save','name'=>'admin.appearance.save','page'=>'admin/appearance','controller'=>'AdminController@saveAppearance','services'=>['SettingsService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/media','name'=>'admin.media','page'=>'admin/media','controller'=>'AdminController@media','services'=>['MediaService']],
            ['method'=>'POST','path'=>'/admin/media/upload','name'=>'admin.media.upload','page'=>'admin/media','controller'=>'AdminController@uploadMedia','services'=>['MediaService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/media/delete','name'=>'admin.media.delete','page'=>'admin/media','controller'=>'AdminController@deleteMedia','services'=>['MediaService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/products/import','name'=>'admin.products.import','page'=>'admin/product-import','controller'=>'AdminController@importProducts','services'=>['ProductImportService','DatabaseService']],
            ['method'=>'POST','path'=>'/admin/products/import','name'=>'admin.products.import.submit','page'=>'admin/product-import','controller'=>'AdminController@importProducts','services'=>['ProductImportService','DatabaseService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/products/save','name'=>'admin.products.save','page'=>'admin/product-form','controller'=>'AdminController@saveProduct','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/products/delete','name'=>'admin.products.delete','page'=>'admin/product-form','controller'=>'AdminController@deleteProduct','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/categories/save','name'=>'admin.categories.save','page'=>'admin/resource','controller'=>'AdminController@saveCategory','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/collections/save','name'=>'admin.collections.save','page'=>'admin/collections','controller'=>'AdminController@saveCollection','services'=>['ResourceService','CategoryService','ProductService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/collections/delete','name'=>'admin.collections.delete','page'=>'admin/collections','controller'=>'AdminController@deleteCollection','services'=>['ResourceService','CategoryService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/categories/delete','name'=>'admin.categories.delete','page'=>'admin/resource','controller'=>'AdminController@deleteCategory','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/coupons/save','name'=>'admin.coupons.save','page'=>'admin/resource','controller'=>'AdminController@saveCoupon','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/coupons/delete','name'=>'admin.coupons.delete','page'=>'admin/resource','controller'=>'AdminController@deleteCoupon','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/temples/save','name'=>'admin.temples.save','page'=>'admin/resource','controller'=>'AdminController@saveTemple','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/temples/delete','name'=>'admin.temples.delete','page'=>'admin/resource','controller'=>'AdminController@deleteTemple','services'=>['ResourceService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/integrations/test-email','name'=>'admin.integrations.test-email','page'=>'admin/integrations','controller'=>'AdminController@testEmail','services'=>['SecretService','SmtpMailer','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/integrations/test-ai','name'=>'admin.integrations.test-ai','page'=>'admin/integrations','controller'=>'AdminController@testAi','services'=>['SecretService','AiClient','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/integrations/save','name'=>'admin.integrations.save','page'=>'admin/integrations','controller'=>'AdminController@saveIntegrations','services'=>['SecretService','AuditLogService']],
            ['method'=>'POST','path'=>'/cart/add','name'=>'cart.add','page'=>'public/cart','controller'=>'CommerceController@addToCart','services'=>['CartService','ProductService']],
            ['method'=>'POST','path'=>'/cart/remove','name'=>'cart.remove','page'=>'public/cart','controller'=>'CommerceController@removeFromCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/cart/update','name'=>'cart.update','page'=>'public/cart','controller'=>'CommerceController@updateCart','services'=>['CartService']],
            ['method'=>'POST','path'=>'/checkout/create-order','name'=>'checkout.create-order','page'=>'public/checkout','controller'=>'CommerceController@createOrder','services'=>['SecretService','PaymentService','SiteTelemetryService']],
            ['method'=>'POST','path'=>'/payment/verify','name'=>'payment.verify','page'=>'public/checkout','controller'=>'CommerceController@verifyPayment','services'=>['SecretService','PaymentService','VerifiedOrderService','DatabaseService','TaxService','SettingsService']],
            ['method'=>'GET','path'=>'/payment/stripe/return','name'=>'payment.stripe.return','page'=>'account/order','controller'=>'CommerceController@completeStripe','services'=>['SecretService','DatabaseService','TaxService','SettingsService','MailQueueService']],
            ['method'=>'POST','path'=>'/create-order','name'=>'api.checkout.create-order','page'=>'public/checkout','controller'=>'CommerceController@createOrder','services'=>['SecretService','PaymentService']],
            ['method'=>'POST','path'=>'/verify-payment','name'=>'api.payment.verify','page'=>'public/checkout','controller'=>'CommerceController@verifyPayment','services'=>['SecretService','PaymentService','VerifiedOrderService','DatabaseService','TaxService','SettingsService']],
            ['method'=>'POST','path'=>'/consultation/initiate','name'=>'consultation.initiate','page'=>'public/contact','controller'=>'ConsultationController@initiate','services'=>['AuthService']],
            ['method'=>'POST','path'=>'/reviews/product','name'=>'reviews.product','page'=>'account/orders','controller'=>'ReviewController@saveProduct','services'=>['ReviewService']],
            ['method'=>'GET','path'=>'/support','name'=>'support.page','page'=>'public/support','controller'=>'SupportController@page','services'=>['SeoService']],
            ['method'=>'POST','path'=>'/support/ask','name'=>'support.ask','page'=>'public/support','controller'=>'SupportController@ask','services'=>['SupportBotService','AgentContextService','SupportTicketService','SupportToolRegistry','AgentConversationService','AgentTraceService','AgentEvaluationService','AuditLogService','AiClient','AiReplyCleaner']],
            ['method'=>'POST','path'=>'/support/feedback','name'=>'support.feedback','page'=>'public/support','controller'=>'SupportController@feedback','services'=>['AgentConversationService','RateLimiter']],

            ['method'=>'GET','path'=>'/docs','name'=>'docs.index','page'=>'public/blog','controller'=>'PublicController@docs','services'=>[]],
            ['method'=>'GET','path'=>'/help/{slug}','name'=>'help.show','page'=>'public/blog-post','controller'=>'PublicController@doc','services'=>[]],
            ['method'=>'POST','path'=>'/remotedb','name'=>'api.remotedb','page'=>'public/404','controller'=>'RemoteDbController@__invoke','services'=>['DatabaseService','SecretService']],
            ['method'=>'GET','path'=>'/blog','name'=>'blog.index','page'=>'public/blog','controller'=>'BlogController@index','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/blog/{slug}','name'=>'blog.show','page'=>'public/blog-post','controller'=>'BlogController@show','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/blog/category/{slug}','name'=>'blog.category','page'=>'public/blog','controller'=>'BlogController@category','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'GET','path'=>'/admin/pages','name'=>'admin.pages','page'=>'admin/pages','controller'=>'AdminController@pages','services'=>['AuthoredPageService']],
            ['method'=>'POST','path'=>'/admin/pages/delete','name'=>'admin.pages.delete','page'=>'admin/pages','controller'=>'AdminController@deleteAuthoredPage','services'=>['AuthoredPageService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/pages/save','name'=>'admin.pages.save','page'=>'admin/pages','controller'=>'AdminController@saveAuthoredPage','services'=>['AuthoredPageService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/blog','name'=>'admin.blog','page'=>'admin/blog','controller'=>'AdminController@blog','services'=>['BlogService']],
            ['method'=>'POST','path'=>'/admin/blog/save','name'=>'admin.blog.save','page'=>'admin/blog','controller'=>'AdminController@saveBlog','services'=>['BlogService','AuditLogService']],
            ['method'=>'GET','path'=>'/admin/blog/history/{slug}','name'=>'admin.blog.history','page'=>'admin/blog-history','controller'=>'AdminController@blogHistory','services'=>['BlogService']],
            ['method'=>'POST','path'=>'/admin/blog/history/{slug}/restore','name'=>'admin.blog.restore','page'=>'admin/blog-history','controller'=>'AdminController@restoreBlogRevision','services'=>['BlogService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/blog/toggle','name'=>'admin.blog.toggle','page'=>'admin/blog','controller'=>'AdminController@toggleBlog','services'=>['BlogService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/blog/delete','name'=>'admin.blog.delete','page'=>'admin/blog','controller'=>'AdminController@deleteBlog','services'=>['BlogService','AuditLogService']],
            ['method'=>'POST','path'=>'/admin/blog/preview','name'=>'admin.blog.preview','page'=>'admin/blog','controller'=>'AdminController@previewBlog','services'=>['BlogService','MarkdownRenderer']],
            ['method'=>'POST','path'=>'/admin/blog/enhance-title','name'=>'admin.blog.enhance-title','page'=>'admin/blog','controller'=>'AdminController@enhanceBlogTitle','services'=>['SecretService']],
            ['method'=>'POST','path'=>'/admin/blog/enhance-content','name'=>'admin.blog.enhance-content','page'=>'admin/blog','controller'=>'AdminController@enhanceBlogContent','services'=>['SecretService']],
            ['method'=>'POST','path'=>'/admin/blog/ai-draft','name'=>'admin.blog.ai-draft','page'=>'admin/blog','controller'=>'AdminController@aiDraftBlog','services'=>['BlogService','BlogDraftService']],


        ];
        foreach ($routes as &$route) {
            if ((str_starts_with($route['path'], '/admin') || str_starts_with($route['path'], '/reviews')) && !in_array('AuthService', $route['services'], true) && !str_starts_with($route['path'], '/admin/sw.js') && !str_starts_with($route['path'], '/admin/manifest.json')) {
                $route['services'][] = 'AuthService';
            }
        }
        unset($route);
        return ['routes'=>$routes];
    }
    public static function validate(array $map): array {
        $services = $map['services'] ?? self::phpBasenames(app_path('app/Services'));
        $schema = require app_path('storage/schema/collections.php');
        $collections = $map['collections'] ?? array_keys($schema['collections'] ?? []);
        $missingRouteMappings = array_values(array_filter($map['routes'] ?? [], self::missingRouteMapping(...)));
        $used = array_unique(array_merge(...array_map(fn($r) => $r['services'] ?? [], $map['routes'] ?? [])));
        $missingServices = array_values(array_diff($used, $services));
        return ['missing_route_mappings'=>$missingRouteMappings,'missing_services'=>$missingServices,'missing_collections'=>[]];
    }

    private static function missingRouteMapping(array $route): bool {
        // A declared JSON handler has no HTML view. Missing controllers and
        // undeclared/misspelled response kinds still fail the mapping gate.
        return empty($route['controller']) || (empty($route['page']) && ($route['response'] ?? null) !== 'json');
    }

    public static function scan(): array {
        $map = self::registry();
        $schema = require app_path('storage/schema/collections.php');
        $schemaCollections = array_keys($schema['collections'] ?? []);

        $controllers = self::phpBasenames(app_path('app/Controllers'));
        $services = self::phpBasenames(app_path('app/Services'));
        $views = self::viewNames(app_path('views'));
        $integrations = self::phpBasenames(app_path('integrations'));
        $tools = self::phpBasenames(app_path('cli'));
        if (is_file(app_path('cli/bapXphp'))) $tools[] = 'bapXphp';
        $storageFiles = self::jsonBasenames(app_path('storage/data'));

        $routeControllers = array_values(array_unique(array_map(
            fn($route) => explode('@', (string)($route['controller'] ?? ''))[0] ?? '',
            $map['routes']
        )));
        $routeControllers = array_values(array_filter($routeControllers));
        $routeServices = array_values(array_unique(array_merge(...array_map(fn($route) => $route['services'], $map['routes']))));
        $routeViews = array_values(array_unique(array_filter(array_map(fn($route) => $route['page'] ?? '', $map['routes']))));
        $navigation = self::navigationPaths();
        $getRoutePaths = array_values(array_unique(array_map(
            fn($route) => (string)$route['path'],
            array_filter($map['routes'], fn($route) => ($route['method'] ?? 'GET') === 'GET')
        )));

        $sharedControllers = self::SHARED_CONTROLLERS;
        $sharedServices = self::SHARED_SERVICES;
        $sharedViews = self::SHARED_VIEWS;
        $knownUnwiredCollections = self::KNOWN_UNWIRED_COLLECTIONS;
        $sourceRelationships = self::sourceRelationships();
        $referencedServices = [];
        foreach ($sourceRelationships as $edge) {
            if (str_starts_with($edge['to'], 'service:')) $referencedServices[] = substr($edge['to'], 8);
        }

        $adminPostRoutes = array_values(array_filter($map['routes'], fn($r) => str_starts_with($r['path'] ?? '', '/admin') && ($r['method'] ?? 'GET') === 'POST' && !str_contains($r['path'] ?? '', 'sw.js') && !str_contains($r['path'] ?? '', 'manifest.json') && !str_contains($r['path'] ?? '', '/agent/ask') && !str_contains($r['path'] ?? '', '/blog/preview') && !str_contains($r['path'] ?? '', '/blog/ai-draft')));
        $allScCollections = array_values(array_unique(array_merge(...array_values(self::serviceCollections()))));

        $gaps = [
            'missing_route_mappings' => array_values(array_filter($map['routes'], self::missingRouteMapping(...))),
            'missing_controller_files' => array_values(array_diff($routeControllers, $controllers)),
            'missing_service_files' => array_values(array_diff($routeServices, $services)),
            'missing_view_files' => array_values(array_diff($routeViews, $views)),
            'navigation_without_get_route' => array_values(array_diff($navigation, $getRoutePaths)),
            'unwired_controllers' => array_values(array_diff($controllers, $routeControllers, $sharedControllers)),
            // A candidate for inspection, not proof of unused code: function-only
            // entrypoints, injected objects and dynamic dispatch are outside this scan.
            'unreferenced_services' => array_values(array_diff($services, $routeServices, $sharedServices, $referencedServices)),
            'unwired_views' => array_values(array_diff($views, $routeViews, $sharedViews)),
            'admin_mutations_without_audit' => array_values(array_filter($adminPostRoutes, fn($r) => !in_array('AuditLogService', $r['services'] ?? [], true))),
            'unwired_schema_collections' => array_values(array_diff($schemaCollections, $allScCollections, $knownUnwiredCollections)),
        ];

        return [
            'routes' => $map['routes'],
            'controllers' => $controllers,
            'services' => $services,
            'views' => $views,
            'navigation' => $navigation,
            'integrations' => $integrations,
            'schema_collections' => $schemaCollections,
            'storage_files' => $storageFiles,
            'tools' => $tools,
            'relationship_provenance' => self::relationshipProvenance(),
            'source_relationships' => $sourceRelationships,
            'gaps' => $gaps,
            'summary' => [
                'routes' => count($map['routes']),
                'controllers' => count($controllers),
                'services' => count($services),
                'views' => count($views),
                'integrations' => count($integrations),
                'schema_collections' => count($schemaCollections),
                'storage_files' => count($storageFiles),
                'tools' => count($tools),
                'gaps' => array_sum(array_map('count', $gaps)),
            ],
        ];
    }

    public static function relationshipProvenance(): array {
        return [
            'route_registry' => 'Registered handlers; services and related views are declared associations, not proof of direct calls or rendered responses.',
            'source_token' => 'Named class extends, literal new and static class references (including ::class) in controller, service and integration PHP files. Source presence is not runtime execution or working autoload.',
            'collection_reference' => 'Bounded textual collection scan plus explicit overrides; no read/write direction or runtime reachability is inferred.',
            'coverage' => 'No dynamic dispatch, variable class names, anonymous class bodies, dependency injection, trait propagation or function-only entrypoints. Unreferenced entries are inspection candidates within this limited coverage, not proven dead code.',
        ];
    }

    /** Read source only; never autoload a class, run a constructor or infer a call from a route name. */
    public static function sourceRelationships(): array {
        $known = $parsed = [];
        foreach (['controller'=>'app/Controllers', 'service'=>'app/Services', 'integration'=>'integrations'] as $kind=>$directory) {
            foreach (self::phpFiles(app_path($directory)) as $file) {
                $source = self::sourceReferences((string)file_get_contents($file));
                $path = substr($file, strlen(app_path()) + 1);
                foreach ($source['classes'] as $class) {
                    $name = substr($class, (int)strrpos('\\' . $class, '\\'));
                    // The inventory is file-based; other named classes in a file
                    // remain outside it rather than acquiring a guessed node.
                    if ($name === basename($file, '.php')) $known[strtolower($class)] = $kind . ':' . $name;
                }
                $parsed[$path] = $source['references'];
            }
        }
        $edges = [];
        foreach ($parsed as $path=>$references) {
            foreach ($references as $reference) {
                $from = $known[strtolower($reference['from'])] ?? null;
                $to = $known[strtolower($reference['to'])] ?? null;
                if ($from === null || $to === null || $from === $to) continue;
                $key = $from . '|' . $reference['relation'] . '|' . $to;
                $edges[$key] ??= ['from'=>$from, 'to'=>$to, 'relation'=>$reference['relation'],
                    'provenance'=>'source_token', 'source'=>$path, 'line'=>$reference['line']];
            }
        }
        ksort($edges, SORT_STRING);
        return array_values($edges);
    }

    /**
     * A small lexical inventory, not a PHP AST or method call graph. Resolve
     * namespace/import names only at literal extends/new/Class:: sites.
     * Comments, strings, unused imports and dynamic targets cannot create edges.
     */
    public static function sourceReferences(string $source): array {
        if (strlen($source) > 2 * 1024 * 1024) throw new \LengthException('Map source exceeds the 2 MiB per-file scan budget.');
        $tokens = array_values(array_filter(token_get_all($source), static fn($token): bool =>
            !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $text = static fn($token): string => is_array($token) ? $token[1] : (string)$token;
        $id = static fn($token): ?int => is_array($token) ? $token[0] : null;
        $isName = static fn($token): bool => is_array($token)
            && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true);
        $blockEnd = static function(int $open) use ($tokens, $id): int {
            $depth = 1;
            for ($i = $open + 1, $count = count($tokens); $i < $count; $i++) {
                if ($tokens[$i] === '{' || in_array($id($tokens[$i]), [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) $depth++;
                if ($tokens[$i] === '}' && --$depth === 0) return $i;
            }
            throw new \UnexpectedValueException('Map source has an unclosed class body.');
        };
        $bodyStart = static function(int $start) use ($tokens, $id): ?int {
            $parens = $brackets = 0;
            for ($i = $start, $count = count($tokens); $i < $count; $i++) {
                $value = is_string($tokens[$i]) ? $tokens[$i] : ($id($tokens[$i]) === T_ATTRIBUTE ? '#[' : '');
                if ($value === '(') $parens++;
                elseif ($value === ')') $parens--;
                elseif ($value === '[' || $value === '#[') $brackets++;
                elseif ($value === ']') $brackets--;
                elseif ($value === '{' && $parens === 0 && $brackets === 0) return $i;
                elseif ($value === ';' && $parens === 0 && $brackets === 0) return null;
            }
            return null;
        };
        $namespace = '';
        $aliases = $classes = $references = [];
        $resolve = static function(string $name) use (&$namespace, &$aliases): ?string {
            if (in_array(strtolower($name), ['self', 'parent', 'static'], true)) return null;
            if (str_starts_with($name, '\\')) return ltrim($name, '\\');
            if (str_starts_with(strtolower($name), 'namespace\\')) return ($namespace === '' ? '' : $namespace . '\\') . substr($name, 10);
            $parts = explode('\\', $name, 2);
            if (isset($aliases[strtolower($parts[0])])) return $aliases[strtolower($parts[0])] . (isset($parts[1]) ? '\\' . $parts[1] : '');
            return ($namespace === '' ? '' : $namespace . '\\') . $name;
        };
        $addImport = static function(array $part, string $prefix) use (&$aliases, $text, $id): void {
            if ($part === [] || in_array($id($part[0]), [T_FUNCTION, T_CONST], true)) return;
            $name = $alias = '';
            $as = false;
            foreach ($part as $token) {
                if ($id($token) === T_AS) { $as = true; continue; }
                if ($as) $alias .= $text($token); else $name .= $text($token);
            }
            $name = ltrim($prefix . $name, '\\');
            if ($name !== '') $aliases[strtolower($alias ?: substr($name, (int)strrpos('\\' . $name, '\\')))] = $name;
        };
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if ($id($tokens[$i]) === T_NAMESPACE) {
                $namespace = '';
                $aliases = [];
                while (++$i < $count && !in_array($text($tokens[$i]), [';', '{'], true)) $namespace .= $text($tokens[$i]);
                continue;
            }
            if ($id($tokens[$i]) === T_USE && $text($tokens[$i + 1] ?? '') !== '(') {
                $prefix = '';
                $part = [];
                $ignore = in_array($id($tokens[$i + 1] ?? null), [T_FUNCTION, T_CONST], true);
                while (++$i < $count && $text($tokens[$i]) !== ';') {
                    $value = $text($tokens[$i]);
                    if ($ignore) continue;
                    if ($value === '{') { $prefix = implode('', array_map($text, $part)); $part = []; }
                    elseif ($value === ',' || $value === '}') { $addImport($part, $prefix); $part = []; }
                    else $part[] = $tokens[$i];
                }
                if (!$ignore) $addImport($part, $prefix);
                continue;
            }
            if (!in_array($id($tokens[$i]), [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true)
                || $id($tokens[$i - 1] ?? null) === T_DOUBLE_COLON) continue;
            $open = $bodyStart($i + 1);
            if ($open === null) continue;
            $close = $blockEnd($open);
            if ($id($tokens[$i]) !== T_CLASS || $id($tokens[$i + 1] ?? null) !== T_STRING) { $i = $close; continue; }
            $class = ($namespace === '' ? '' : $namespace . '\\') . $text($tokens[$i + 1]);
            $classes[] = $class;
            for ($j = $i + 2; $j < $close; $j++) {
                $token = $tokens[$j];
                $kind = null;
                $target = null;
                if ($j < $open && $id($token) === T_EXTENDS && $isName($tokens[$j + 1] ?? null)) {
                    $kind = 'extends'; $target = $resolve($text($tokens[$j + 1]));
                } elseif ($j > $open && in_array($id($token), [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true)
                    && $id($tokens[$j - 1] ?? null) !== T_DOUBLE_COLON) {
                    // Nested/anonymous classes have their own scope; do not
                    // attribute their bodies to the enclosing named class.
                    $nested = $bodyStart($j + 1);
                    if ($nested !== null) $j = $blockEnd($nested);
                    continue;
                } elseif ($j > $open && $id($token) === T_NEW && $isName($tokens[$j + 1] ?? null)
                    && $id($tokens[$j + 2] ?? null) !== T_DOUBLE_COLON) {
                    $kind = 'constructs'; $target = $resolve($text($tokens[$j + 1]));
                } elseif ($j > $open && $isName($token) && $id($tokens[$j + 1] ?? null) === T_DOUBLE_COLON
                    && !in_array($id($tokens[$j - 1] ?? null), [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                    $kind = 'static_reference'; $target = $resolve($text($token));
                }
                if ($target !== null) $references[] = ['from'=>$class, 'to'=>$target, 'relation'=>$kind, 'line'=>$token[2]];
            }
            $i = $close;
        }
        return ['classes'=>$classes, 'references'=>$references];
    }

    private static function routeDesc(string $path, string $method = 'GET'): string {
        $methodPath = $path . '|' . $method;
        $descs = [
            '/docs'           => 'Documentation landing — blog categories overview',
            '/blog'           => 'Blog listing — all posts with category filters',
            '/blog/{slug}'    => 'Blog post — rendered from GitHub-sourced markdown',
            '/blog/category/{slug}' => 'Blog listing filtered by category',
            '/'                => 'Home page — hero, categories, featured products, temple guide',
            '/about'           => 'About SPS — story, values, CTA',
            '/sri-panchami-spiritual' => 'Sri Panchami Spiritual landing page',
            '/spiritual'        => 'Short spiritual landing redirect-equivalent page',
            '/terms'           => 'Terms of Service — 15 sections, legal',
            '/privacy'         => 'Privacy Policy — 14 sections, data handling',
            '/consult'         => 'Retired consultation URL — redirects to product shop',
            '/consult/{slug}'  => 'Retired consultant URL — redirects to product shop',
            '/search'          => 'Search published storefront content',
            '/temples'         => 'Temple listing page',
            '/temples/{slug}'  => 'Temple detail page',
            '/shop'            => 'Product shop — grid with pill actions',
            '/categories'      => 'Products filtered by category',
            '/product/{slug}'  => 'Product detail — gallery, add-to-cart, buy-now',
            '/contact'         => 'Contact form GET',
            '/contact|POST'    => 'Contact form POST submission',
            '/login'           => 'Login page — email/username + Google OAuth',
            '/logout'          => 'Logout — session destroy, redirect',
            '/register'        => 'Register page — identity, default delivery address, password, terms',
            '/register|POST'   => 'Register POST — create user and default address, accept terms',
            '/login|POST'      => 'Login POST — authenticate, session start',
            '/forgot-password' => 'Forgot password page',
            '/forgot-password|POST' => 'Forgot password POST — generate reset token',
            '/reset-password'  => 'Reset password page with token',
            '/reset-password|POST'  => 'Reset password POST — update hash',
            '/auth/google'     => 'Google OAuth redirect',
            '/auth/google/callback' => 'Google OAuth callback — upsert user',
            '/cart'            => 'Cart page — items, qty, totals',
            '/cart/agent-checkout' => 'Anonymous cart review — explicit CSRF-protected browser cart replacement',
            '/checkout'        => 'Checkout page — address, Razorpay payment',
            '/cart/add'        => 'Cart — add item',
            '/cart/remove'     => 'Cart — remove item',
            '/cart/update'     => 'Cart — update quantity',
            '/checkout/create-order' => 'Checkout — create Razorpay order',
            '/payment/verify'  => 'Checkout — verify payment, create order',
            '/payment/stripe/return' => 'Checkout — verify Stripe session, create order, show confirmation',
            '/create-order'    => 'API checkout — Razorpay order',
            '/verify-payment'  => 'API checkout — verify payment',
            '/account/dashboard' => 'Account dashboard entry — redirects to orders',
            '/account/dashboard/orders' => 'My Orders — product reviews',
            '/account/dashboard/sessions' => 'Retired session URL — redirects to orders',
            '/account/dashboard/install' => 'Install App — customer installation guidance',
            '/account/orders'  => 'Legacy account orders redirect',
            '/orders'          => 'Legacy order history URL — requires login and redirects to account orders',
            '/account/bookings' => 'Legacy account booking URL — redirects to orders',
            '/api/consultations/{id}/status|POST' => 'API — update appointment status',
            '/consultation/initiate|POST' => 'Retired consultation submission — redirects to contact',
            '/reviews/product|POST'    => 'Submit product review',
            '/support/ask|POST' => 'Support bot — AI-powered Q&A',


            '/admin'            => 'Admin dashboard — commerce analytics and operations',
            '/admin/products'   => 'Admin — manage products',
            '/admin/categories' => 'Admin — manage categories',
            '/admin/coupons'    => 'Admin — manage coupons',
            '/admin/orders'     => 'Admin — order list',
            '/admin/orders/{id}' => 'Admin — order detail, status update',
            '/admin/orders/{id}/status|POST' => 'Admin — update order status + email',
            '/admin/orders/{id}/tax-review|POST' => 'Admin — correct paid order state and issue reviewed invoice',
            '/admin/shipping'   => 'Admin — shipping settings',
            '/admin/astrologers' => 'Retired admin profile URL — redirects to dashboard',
            '/admin/appointments' => 'Admin — session list',
            '/admin/consultation-analytics' => 'Admin — consultation metrics',
            '/admin/temples'    => 'Admin — manage temples',
            '/admin/settings'   => 'Admin — site settings',
            '/admin/settings/save|POST' => 'Admin — save settings',
            '/admin/settings/admin-credentials|POST' => 'Admin — save admin login',
            '/admin/integrations' => 'Admin — API keys, integrations',
            '/admin/backups'    => 'Admin — backup list',
            '/admin/audit-log'  => 'Admin — audit log',
            '/admin/contact-submissions' => 'Admin — contact form entries',
            '/admin/contact_submissions/save|POST' => 'Admin — save contact submission',
            '/admin/contact_submissions/delete|POST' => 'Admin — delete contact submission',
            '/admin/support-tickets' => 'Admin — support tickets',
            '/admin/appearance' => 'Admin — logo & favicon',
            '/admin/appearance/save|POST' => 'Admin — save logo/favicon',
            '/admin/media'      => 'Admin — media library',
            '/admin/media/upload|POST' => 'Admin — upload media',
            '/admin/environment/fix-permissions|POST' => 'Admin — fix storage permissions',
            '/admin/products/import' => 'Admin — import product CSV',
            '/admin/products/import|POST' => 'Admin — review or confirm product CSV',
            '/admin/products/save|POST' => 'Admin — create/update product',
            '/admin/products/delete|POST' => 'Admin — delete product',
            '/admin/categories/save|POST' => 'Admin — create/update category',
            '/admin/categories/delete|POST' => 'Admin — delete category',
            '/admin/coupons/save|POST' => 'Admin — create/update coupon',
            '/admin/coupons/delete|POST' => 'Admin — delete coupon',
            '/admin/temples/save|POST' => 'Admin — create/update temple',
            '/admin/temples/delete|POST' => 'Admin — delete temple',
            '/admin/integrations/save|POST' => 'Admin — save integration secrets',
            '/remotedb|POST' => 'Remote DB query endpoint — proxies SQL to production MySQL',

        ];
        if (isset($descs[$methodPath])) return $descs[$methodPath];
        if (isset($descs[$path])) return $descs[$path];
        return '';
    }

    public static function renderSystematicMermaid(): string {
        $scan = self::scan();
        $lines = [
            'flowchart LR',
            '  %% Generated static inventory, not a runtime call graph. See docs/development-workflow.md.',
            '  classDef gap fill:#fee2e2,stroke:#b91c1c,color:#7f1d1d',
            '  classDef route fill:#e0f2fe,stroke:#0369a1,color:#0c4a6e',
            '  classDef code fill:#ecfdf5,stroke:#047857,color:#064e3b',
            '  classDef data fill:#fef3c7,stroke:#b45309,color:#78350f',
            '  classDef tool fill:#ede9fe,stroke:#6d28d9,color:#3b0764',
            '  classDef arch fill:#f1f5f9,stroke:#475569,color:#1e293b',
            '  classDef nav fill:#fff7ed,stroke:#c2410c,color:#7c2d12',
            '',
        ];
        $lines[] = '  subgraph PROVENANCE["Relationship evidence"]';
        $lines[] = '    legend_declared["Declared: registry services, related views and manual associations"]:::arch';
        $lines[] = '    legend_source["Source: named class extends, literal new and static references"]:::arch';
        $lines[] = '    legend_limits["No execution, autoload or dynamic reachability proof; gaps are inspection candidates"]:::arch';
        $lines[] = '  end';
        $lines[] = '';

        $summary = $scan['summary'];
        $lines[] = '  subgraph ARCH["Architecture Overview — ' . $summary['routes'] . ' routes, ' . $summary['controllers'] . ' controllers, ' . $summary['services'] . ' services, ' . $summary['views'] . ' views, ' . $summary['storage_files'] . ' data files, ' . $summary['tools'] . ' tools"]';
        $lines[] = '    arch_routes["> Routes: ' . $summary['routes'] . ' (Public + Auth + Payment + Admin + Support)"]:::arch';
        $lines[] = '    arch_ctrl["> Controllers: ' . $summary['controllers'] . '"]:::arch';
        $lines[] = '    arch_svc["> Services: ' . $summary['services'] . '"]:::arch';
        $lines[] = '    arch_views["> Views: ' . $summary['views'] . '"]:::arch';
        $lines[] = '    arch_data["> Collections: ' . $summary['schema_collections'] . ' schema | ' . $summary['storage_files'] . ' files"]:::arch';
        $lines[] = '    arch_tools["> Tools: ' . $summary['tools'] . '"]:::arch';
        $lines[] = '    arch_gaps["> Gaps: ' . $summary['gaps'] . '"]:::arch';
        $lines[] = '    arch_routes -.-> arch_ctrl -.-> arch_svc -.-> arch_data';
        $lines[] = '    arch_ctrl -.-> arch_views';
        $lines[] = '  end';
        $lines[] = '';

        foreach (['PUBLIC', 'AUTH', 'PAYMENT', 'SUPPORT', 'ADMIN'] as $domain) {
            $routes = array_values(array_filter($scan['routes'], fn($route) => self::routeDomain($route) === $domain));
            $lines[] = '  subgraph ROUTES_' . $domain . '["' . $domain . ' Routes"]';
            foreach ($routes as $route) {
                $id = self::routeId($route);
                $mp = ($route['method'] ?? 'GET') . ' ' . ($route['path'] ?? '');
                $desc = self::routeDesc($route['path'] ?? '', $route['method'] ?? 'GET');
                $label = $desc ? $mp . ' — ' . $desc : $mp;
                $lines[] = '    ' . $id . '["' . self::label($label) . '"]:::route';
            }
            $lines[] = '  end';
            $lines[] = '';
        }

        $lines[] = '  subgraph NAVIGATION["Navigation Paths"]';
        foreach ($scan['navigation'] as $path) {
            $lines[] = '    ' . self::navId($path) . '["' . self::label($path) . '"]:::nav';
        }
        $lines[] = '  end';
        $lines[] = '';

        $groups = [
            'CONTROLLERS' => ['Controllers', $scan['controllers'], 'controllerId', 'code'],
            'SERVICES' => ['Services', $scan['services'], 'serviceId', 'code'],
            'VIEWS' => ['Views', $scan['views'], 'viewId', 'code'],
            'INTEGRATIONS' => ['Integrations', $scan['integrations'], 'integrationId', 'code'],
            'SCHEMA' => ['Schema Collections', $scan['schema_collections'], 'collectionId', 'data'],
            'STORAGE' => ['Storage Data Files', $scan['storage_files'], 'storageId', 'data'],
            'TOOLS' => ['Tools', $scan['tools'], 'toolId', 'tool'],
        ];

        foreach ($groups as $key => [$title, $items, $method, $class]) {
            if (empty($items)) continue;
            $lines[] = '  subgraph ' . $key . '["' . $title . '"]';
            foreach ($items as $item) {
                $lines[] = '    ' . self::{$method}($item) . '["' . self::label($item) . '"]:::' . $class;
            }
            $lines[] = '  end';
            $lines[] = '';
        }

        $gapNodes = [];
        $lines[] = '  subgraph GAPS["Gaps & Missing Links — inventory checks"]';
        foreach ($scan['gaps'] as $kind => $items) {
            foreach ($items as $index => $item) {
                if ($kind === 'admin_mutations_without_audit' && is_array($item)) {
                    $label = (($item['method'] ?? '') . ' ' . ($item['path'] ?? '') . ' — missing AuditLogService');
                } elseif (is_array($item)) {
                    $label = (($item['method'] ?? '') . ' ' . ($item['path'] ?? '') . ' missing mapping');
                } elseif ($kind === 'unreferenced_services') {
                    $label = 'no declared or scanned reference: ' . $item;
                } else {
                    $label = ($kind . ': ' . $item);
                }
                $id = 'gap_' . substr(md5($kind . $index . $label), 0, 10);
                $gapNodes[] = [$kind, $item, $id];
                $lines[] = '    ' . $id . '["' . self::label($label) . '"]:::gap';
            }
        }
        if ($gapNodes === []) {
            $lines[] = '    no_gaps["No detected gaps"]:::data';
        }
        $lines[] = '  end';
        $lines[] = '';

        foreach ($scan['routes'] as $route) {
            $routeId = self::routeId($route);
            $controller = (string)($route['controller'] ?? '');
            [$controllerClass] = array_pad(explode('@', $controller), 2, '');
            if ($controllerClass !== '') {
                $lines[] = '  ' . $routeId . ' -- registered handler --> ' . self::controllerId($controllerClass);
            }
            foreach ($route['services'] ?? [] as $service) {
                $lines[] = '  ' . self::controllerId($controllerClass) . ' -. declared service .-> ' . self::serviceId($service);
            }
            if (!empty($route['page'])) {
                $lines[] = '  ' . self::controllerId($controllerClass) . ' -. declared related view .-> ' . self::viewId((string)$route['page']);
            }
        }

        foreach (['admin/list','admin/media'] as $parentView) {
            $lines[] = '  '.self::viewId($parentView).' -. declared include .-> '.self::viewId('admin/list-controls');
        }

        $routeByGetPath = [];
        foreach ($scan['routes'] as $route) {
            if (($route['method'] ?? 'GET') === 'GET') $routeByGetPath[(string)$route['path']] = $route;
        }
        foreach ($scan['navigation'] as $path) {
            if (isset($routeByGetPath[$path])) {
                $lines[] = '  ' . self::navId($path) . ' -- registered destination --> ' . self::routeId($routeByGetPath[$path]);
            }
        }

        foreach ($scan['source_relationships'] as $edge) {
            [$fromKind, $fromName] = explode(':', $edge['from'], 2);
            [$toKind, $toName] = explode(':', $edge['to'], 2);
            $relation = match ($edge['relation']) {
                'extends' => ' -- extends --> ',
                'constructs' => ' -. source new .-> ',
                'static_reference' => ' -. static reference .-> ',
            };
            $lines[] = '  ' . self::nodeId($fromKind, $fromName) . $relation . self::nodeId($toKind, $toName);
        }
        if (in_array('import-product-images', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('import-product-images') . ' -. declared dependency .-> ' . self::serviceId('ImageOptimizerService');
            $lines[] = '  ' . self::toolId('import-product-images') . ' -. declared dependency .-> ' . self::serviceId('DatabaseService');
            $lines[] = '  ' . self::toolId('import-product-images') . ' -. declared collection .-> ' . self::collectionId('products');
        }

        foreach (self::serviceCollections() as $service => $collections) {
            foreach ($collections as $collection) {
                $lines[] = '  ' . self::serviceId($service) . ' -. collection reference .-> ' . self::collectionId($collection);
            }
        }
        foreach ($scan['schema_collections'] as $collection) {
            if (in_array($collection, $scan['storage_files'], true)) {
                $lines[] = '  ' . self::collectionId($collection) . ' -. matching file name .-> ' . self::storageId($collection);
            }
        }
        if (in_array('generate-project-map', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('generate-project-map') . ' --> systematic_map["docs/systematic-map.mmd"]:::data';
        }
        if (in_array('validate-project-map', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('validate-project-map') . ' --> systematic_map';
        }
        if (in_array('smoke-local', $scan['tools'], true)) {
            $lines[] = '  ' . self::toolId('smoke-local') . ' -. declared probe coverage .-> ROUTES_PUBLIC';
            $lines[] = '  ' . self::toolId('smoke-local') . ' -. declared probe coverage .-> ROUTES_ADMIN';
        }

        foreach ($gapNodes as [$kind, $item, $id]) {
            if (is_string($item)) {
                if (str_contains($kind, 'service')) {
                    $lines[] = '  ' . $id . ' -. inspect .-> ' . self::serviceId($item);
                } elseif (str_contains($kind, 'view')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::viewId($item);
                } elseif (str_contains($kind, 'controller')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::controllerId($item);
                } elseif (str_contains($kind, 'schema') || str_contains($kind, 'collection')) {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::collectionId($item);
                }
            } elseif (is_array($item) && !empty($item['controller'])) {
                [$ctrlClass] = array_pad(explode('@', (string)$item['controller']), 2, '');
                if ($ctrlClass !== '') {
                    $lines[] = '  ' . $id . ' -. missing .-> ' . self::controllerId($ctrlClass);
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public static function phpBasenames(string $dir): array {
        if (!is_dir($dir)) return [];
        $files = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)));
        $names = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') $names[] = $file->getBasename('.php');
        }
        sort($names);
        return $names;
    }

    private static function viewNames(string $dir): array {
        if (!is_dir($dir)) return [];
        $files = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)));
        $names = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $relative = str_replace($dir . '/', '', $file->getPathname());
            $names[] = substr($relative, 0, -4);
        }
        sort($names);
        return $names;
    }

    private static function jsonBasenames(string $dir): array {
        if (!is_dir($dir)) return [];
        $names = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $name = basename($file, '.json');
            if (in_array($name, self::RUNTIME_JSON_STORES, true)) continue;
            $names[] = $name;
        }
        sort($names);
        return $names;
    }

    public static function serviceCollections(): array {
        $mapping = [];
        static $collectionPattern = '/(\$this->store\s*->\s*(?:find|read|create|write|upsert|delete)\s*\(\s*)([\'"])([a-z_]+)\2\s*[\),]/';
        static $resourcePattern = '/new\s+ResourceService\s*\(\s*([\'"])([a-z_]+)\1\s*\)/';
        // Static SQL reads are collection dependencies too. Restrict the result to
        // canonical collections so aliases and unrelated tables are not mapped.
        static $sqlPattern = '/\$this->store\s*->\s*query\s*\(\s*"[^"]*?\bFROM\s+`?([a-z_]+)`?/i';
        $schema = require app_path('storage/schema/collections.php');
        $knownCollections = array_keys($schema['collections'] ?? []);

        foreach (self::phpFiles(app_path('app/Services')) as $file) {
            $content = file_get_contents($file);
            $name = basename($file, '.php');
            $collections = [];

            preg_match_all($collectionPattern, $content, $direct, PREG_SET_ORDER);
            foreach ($direct as $m) {
                $collections[$m[3]] = true;
            }
            preg_match_all($sqlPattern, $content, $sqlReads, PREG_SET_ORDER);
            foreach ($sqlReads as $m) {
                $collection = strtolower($m[1]);
                if (in_array($collection, $knownCollections, true)) $collections[$collection] = true;
            }

            if ($collections) {
                $mapping[$name] = array_keys($collections);
            }
        }

        $resourceCollections = [];
        foreach (self::phpFiles(app_path('app/Controllers')) as $file) {
            $content = file_get_contents($file);
            preg_match_all($resourcePattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $resourceCollections[$m[2]] = true;
            }
        }
        if ($resourceCollections) {
            $mapping['ResourceService'] = array_keys($resourceCollections);
        }

        self::applyManualOverrides($mapping);

        return $mapping;
    }

    private static function phpFiles(string $dir): array {
        if (!is_dir($dir)) return [];
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->getExtension() === 'php') $files[] = $f->getPathname();
        }
        sort($files);
        return $files;
    }

    private static function applyManualOverrides(array &$mapping): void {
        $overrides = [
            'AgentContextService' => ['users', 'orders', 'appointments', 'support_tickets'],
            'DatabaseService' => ['users', 'addresses', 'products', 'orders', 'appointments', 'consultation_messages', 'consultation_signals', 'secrets', 'mail_inbox', 'mail_outbox', 'contact_submissions', 'agent_carts', 'checkout_attempts'],
            // Fixed atomic database protocols are not recognized by the generic CRUD scan.
            'RemoteCartService' => ['agent_carts'],
            'RemoteCheckoutService' => ['agent_carts', 'checkout_attempts'],
            'VerifiedOrderService' => ['orders'],
        ];
        foreach ($overrides as $service => $cols) {
            $existing = $mapping[$service] ?? [];
            $mapping[$service] = array_values(array_unique(array_merge($existing, $cols)));
        }
    }

    private static function navigationPaths(): array {
        return [
            '/contact',
            '/account/dashboard',
            '/account/dashboard/orders',
            '/account/dashboard/sessions',
            '/account/dashboard/install',
        ];
    }

    private static function routeDomain(array $route): string {
        $path = (string)($route['path'] ?? '');
        if (str_starts_with($path, '/admin')) return 'ADMIN';
        if (str_starts_with($path, '/support')) return 'SUPPORT';
        if (str_starts_with($path, '/auth') || in_array($path, ['/login', '/logout', '/register', '/forgot-password', '/reset-password'], true)) return 'AUTH';
        if (str_starts_with($path, '/cart') || str_starts_with($path, '/checkout') || str_starts_with($path, '/payment') || str_starts_with($path, '/recharge') || in_array($path, ['/create-order', '/verify-payment'], true)) return 'PAYMENT';
        return 'PUBLIC';
    }

    private static function label(string $value): string {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    private static function nodeId(string $prefix, string $value): string {
        $stable = preg_replace('/[^a-zA-Z0-9]/', '_', $value);
        $stable = preg_replace('/_+/', '_', $stable);
        $stable = trim($stable, '_');
        return $prefix . '_' . strtolower(substr($stable, 0, 48));
    }

    private static function routeId(array $route): string {
        $path = $route['path'] ?? '';
        $id = self::nodeId('route', ($route['method'] ?? '') . ' ' . $path);
        return $path !== '/' && str_ends_with($path, '/') ? $id . '_slash' : $id;
    }
    private static function controllerId(string $name): string { return self::nodeId('controller', $name); }
    private static function serviceId(string $name): string { return self::nodeId('service', $name); }
    private static function viewId(string $name): string { return self::nodeId('view', $name); }
    private static function integrationId(string $name): string { return self::nodeId('integration', $name); }
    private static function collectionId(string $name): string { return self::nodeId('collection', $name); }
    private static function storageId(string $name): string { return self::nodeId('storage', $name); }
    private static function toolId(string $name): string { return self::nodeId('tool', $name); }
    private static function navId(string $name): string { return self::nodeId('nav', $name); }
}
