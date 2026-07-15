<?php
namespace App\Controllers;
use App\Services\{AuthService,SupportBotService,SupportTicketService};

final class SupportController extends BaseController {
    public function page(): void {
        $this->seoKey = 'support';
        $supportNav = $this->loadSupportNavigation();
        $this->render('public/support', ['supportNav' => $supportNav]);
    }

    public function latestMessage(): void {
        $tickets = (new \App\Services\DatabaseService())->read('support_tickets');
        usort($tickets, fn(array $a, array $b): int => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        $latest = $tickets[0] ?? null;
        if ($latest) {
            $this->jsonResponse(['success' => true, 'message' => ['id' => $latest['id'], 'text' => $latest['message'] . "\n\n" . $latest['reply']]]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => null]);
        }
    }

    public function ask(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = new \App\Services\RateLimiter();
        if (!$limiter->check('support:' . $ip, 10, 60)) {
            $this->jsonResponse(['error' => 'Too many requests. Please try again later.'], 429);
            return;
        }
        $limiter->hit('support:' . $ip);
        try {
            $user = (new AuthService())->user();
            $answer = (new SupportBotService())->answer($_POST['message'] ?? '', $user);
            $this->jsonResponse($answer);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Unable to answer right now. Please try again.'], 400);
        }
    }

    private function loadSupportNavigation(): array {
        $file = app_path('content/support-navigation.yaml');
        if (!is_file($file)) return [];
        $yaml = @file_get_contents($file);
        if ($yaml === false || $yaml === '') return [];
        $sections = [];
        $current = null;
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^\s*-\s*section:\s*(.+)$/', $line, $m)) {
                if ($current) $sections[] = $current;
                $current = ['section' => trim($m[1]), 'links' => []];
            } elseif ($current && preg_match('/^\s+-\s+(\S+):\s*(.+)$/', $line, $m)) {
                $current['links'][] = ['path' => trim($m[1]), 'label' => trim($m[2])];
            }
        }
        if ($current) $sections[] = $current;
        return $sections;
    }
}
