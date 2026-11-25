<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\{AuthoredPageService, CampaignDownloadService, CampaignPaymentService, RateLimiter};

final class CampaignBookingController
{
    public function __construct(private CampaignPaymentService $payments = new CampaignPaymentService()) {}

    public function start(string $slug): void
    {
        $this->privateResponse();
        $csrf = $_POST['_csrf'] ?? null;
        $expected = $_SESSION['csrf_token'] ?? '';
        $owner = CampaignPaymentService::browserOwner($_SESSION);
        $revision = $_POST['revision'] ?? null;
        if (!AuthoredPageService::validSlug($slug)) {
            $this->show($slug, null, 404);
            return;
        }
        if (!is_string($csrf) || $expected === '' || !hash_equals($expected, $csrf) || $owner === null) {
            $this->show($slug, null, 419, 'This booking form expired. Reopen the campaign page and review the amount.');
            return;
        }
        if (!AuthoredPageService::validRevision($revision)) {
            $this->show($slug, null, 409, 'This campaign changed. Review the current booking before paying.');
            return;
        }
        session_write_close();
        try {
            $booking = $this->payments->start($slug, $revision, $owner,
                static fn() => RateLimiter::consumeCampaignBooking((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
        } catch (\Throwable) {
            $this->show($slug, null, 503, 'Booking status could not be confirmed. Reopen the campaign to check this booking.');
            return;
        }
        if (isset($booking['id'])) {
            header('Location: '.self::path($slug, $booking['id']), true, 303);
            return;
        }
        [$status, $message] = match ($booking['outcome']) {
            'stale' => [409, 'This campaign changed. Review the current booking before paying.'],
            'limited' => [429, 'Too many booking requests. Please try later.'],
            default => [503, 'Payment is temporarily unavailable. Please try later.'],
        };
        $this->show($slug, null, $status, $message);
    }

    public function status(string $slug, string $id): void
    {
        $this->privateResponse();
        $owner = CampaignPaymentService::browserOwner($_SESSION);
        session_write_close();
        $booking = $this->payments->owned($id, $owner);
        if ($booking === null || $booking['slug'] !== $slug) {
            $this->show($slug, null, 404);
            return;
        }
        $this->show($slug, $this->payments->reconcile($booking));
    }

    public function download(string $slug, string $id, string $file): void
    {
        $this->privateResponse();
        $owner = CampaignPaymentService::browserOwner($_SESSION);
        session_write_close();
        $booking = $this->payments->owned($id, $owner);
        $descriptor = $booking !== null && $booking['slug'] === $slug ? $this->payments->paidFile($booking, $file) : null;
        if ($descriptor === null || !(new CampaignDownloadService())->stream($slug, $descriptor)) {
            $this->show($slug, null, 404, 'This download is unavailable.');
        }
    }

    private static function path(string $slug, string $id): string
    {
        return '/pages/'.rawurlencode($slug).'/booking/'.rawurlencode($id);
    }

    private function privateResponse(): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    }

    private function show(string $slug, ?array $booking, int $status = 200, string $error = 'This booking is unavailable.'): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $bookingPath = $booking === null ? '' : self::path($slug, $booking['id']);
        $pagePath = AuthoredPageService::validSlug($slug) ? '/pages/'.rawurlencode($slug) : '/';
        require app_path('views/public/campaign-booking.php');
    }
}
