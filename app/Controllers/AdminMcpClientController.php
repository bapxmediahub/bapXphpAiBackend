<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{AuthService, McpClientService};

final class AdminMcpClientController extends BaseController
{
    private const PAGE = '/admin/mcp-clients';
    protected string $layout = 'admin';
    protected string $seoKey = 'admin';
    private McpClientService $clients;

    public function __construct()
    {
        (new AuthService())->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->validateCsrf();
        $this->clients = new McpClientService();
    }

    public function settings(): void
    {
        $this->show();
    }

    public function issue(): void
    {
        $nonce = $_POST['issue_nonce'] ?? null;
        if (!is_string($nonce) || !isset($_SESSION['mcp_issue_nonce']) || !hash_equals($_SESSION['mcp_issue_nonce'], $nonce)) {
            $this->flash('This form has already been used or expired. Review your clients before issuing a credential.', 'warning');
            $this->redirect(self::PAGE);
        }
        unset($_SESSION['mcp_issue_nonce']);
        try {
            $token = $this->clients->issue(is_string($_POST['client_label'] ?? null) ? $_POST['client_label'] : '');
            $this->show($token);
        } catch (\DomainException $error) {
            $this->flash($error->getMessage(), 'error');
            $this->redirect(self::PAGE);
        }
    }

    public function revoke(): void
    {
        try {
            $this->clients->revoke(is_string($_POST['client_id'] ?? null) ? $_POST['client_id'] : '');
            $this->flash('Client access revoked.', 'success');
        } catch (\DomainException $error) {
            $this->flash($error->getMessage(), 'error');
        }
        $this->redirect(self::PAGE);
    }

    private function show(#[\SensitiveParameter] ?string $token = null): void
    {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        $this->seoOverrides = ['title'=>'Local MCP clients', 'robots'=>'noindex, nofollow'];
        $this->render('admin/mcp-clients', [
            'clients'=>$this->clients->clients(), 'issuedToken'=>$token,
            'issueNonce'=>$_SESSION['mcp_issue_nonce'] ??= bin2hex(random_bytes(16)),
        ]);
    }
}
