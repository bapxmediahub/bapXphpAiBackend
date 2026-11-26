<?php
namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\SecretService;
use App\Services\SiteTelemetryService;

final class RemoteDbController {
    private DatabaseService $store;

    public function __construct() {
        // The HTTP bridge must terminate at hosted MySQL. It must never fall back to
        // its own /remotedb URL when direct MySQL is unavailable.
        $this->store = new DatabaseService(true);
    }

    /**
     * The endpoint authenticates with the MySQL password from config, not a separate
     * invented token. Anyone entitled to query the database already holds it, and it
     * cannot drift out of sync with a second secret.
     *
     * Fails closed: an unset password rejects every request instead of allowing all of
     * them, which is how the endpoint came to accept unauthenticated queries.
     */
    private function requirePassword(array $input): void {
        $config = require app_path('config/database.php');
        $expected = trim((string)($config['pass'] ?? ''));
        if ($expected === '') {
            http_response_code(503);
            echo json_encode(['error' => 'Remote database access is not configured.']);
            exit;
        }
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $given = $input['password'] ?? '';
        if ($given === '' && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $given = trim($m[1]);
        }
        if (!hash_equals($expected, $given)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: invalid or missing database password.']);
            exit;
        }
    }

    public function __invoke() {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $this->requirePassword($input);

        $action = strtolower(trim((string)($input['action'] ?? 'query')));

        if ($action !== 'query') {
            $this->mutate($action, $input);
            return;
        }

        $sql = trim($input['query'] ?? '');
        $params = $input['params'] ?? [];
        if ($sql === '' || !preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only read queries are allowed']);
            return;
        }

        try {
            $result = $this->store->query($sql, $params);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed']);
        }
    }

    private function mutate(string $action, array $input): void {
        $collection = preg_replace('/[^a-z_]/', '', (string)($input['collection'] ?? ''));
        if ($collection === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Collection is required']);
            return;
        }
        try {
            $store = $this->store;
            if (($collection === 'agent_carts' && $action === 'remote_cart_operation')
                ||($collection === 'checkout_attempts' && $action === 'remote_checkout_operation')) {
                if (!is_string($input['operation']??null) || !is_array($input['payload']??null)) throw new \InvalidArgumentException('Cart operation and payload are required.');
                try { $result=$action==='remote_cart_operation'?$store->remoteCartOperation($input['operation'],$input['payload'])
                    :$store->remoteCheckoutOperation($input['operation'],$input['payload']); }
                catch (\App\Services\RemoteCartException $error) {
                    // Preserve safe business failures across the authenticated internal
                    // bridge, including rate-limit status and retry timing.
                    $result=['cart_error'=>['code'=>$error->errorCode,'message'=>$error->getMessage(),
                        'status'=>$error->httpStatus,'retry_after'=>$error->retryAfter]];
                }
                echo json_encode(['success'=>true,'result'=>$result],JSON_THROW_ON_ERROR);return;
            }
            if ($collection === 'settings' && $action === 'delete_authored_page') {
                if (!is_string($input['slug'] ?? null) || !is_string($input['expected'] ?? null)) throw new \InvalidArgumentException('Page and revision required.');
                echo json_encode(['success'=>true, 'deleted'=>$store->deleteAuthoredPage($input['slug'], $input['expected'])]);
                return;
            }
            if ($collection === 'settings' && $action === 'save_authored_page') {
                if (!is_array($input['record'] ?? null) || !is_string($input['expected'] ?? null)) {
                    throw new \InvalidArgumentException('A page record and revision are required.');
                }
                try {
                    $saved = $store->saveAuthoredPage($input['record'], $input['expected']);
                    echo json_encode(['success'=>true, 'saved'=>$saved]);
                } catch (\InvalidArgumentException $error) {
                    if (!isset($input['record']['header_image_url'])) throw $error;
                    echo json_encode(['success'=>true, 'saved'=>false, 'header_unavailable'=>true]);
                }
                return;
            }
            if ($collection === 'contact_submissions' && $action === 'save_campaign_lead') {
                if (!is_string($input['id'] ?? null) || !is_string($input['slug'] ?? null)
                    || !is_string($input['revision'] ?? null) || !is_array($input['answers'] ?? null)) {
                    throw new \InvalidArgumentException('Campaign submission identity and answers are required.');
                }
                $outcome = $store->saveCampaignLead($input['id'], $input['slug'], $input['revision'], $input['answers']);
                echo json_encode(['success'=>true, 'outcome'=>$outcome], JSON_THROW_ON_ERROR);
                return;
            }
            if ($collection === 'campaign_bookings') {
                $id = (string)($input['id'] ?? '');
                if ($action === 'begin_campaign_booking') {
                    $result = $store->beginCampaignBooking($id, (string)($input['slug'] ?? ''),
                        (string)($input['revision'] ?? ''), (string)($input['owner'] ?? ''), $input['mode'] ?? null,
                        $input['freshFrom'] ?? null);
                } elseif ($action === 'read_campaign_booking_family') {
                    $result = $store->readCampaignBookingFamily($id, (string)($input['owner'] ?? ''));
                } elseif ($action === 'change_campaign_booking' && array_key_exists('change', $input)) {
                    $result = $input['change'] === null
                        ? $store->claimCampaignBookingCheck($id)
                        : ['booking'=>$store->updateCampaignBooking($id, $input['change'])];
                } else {
                    throw new \InvalidArgumentException('Unknown booking operation.');
                }
                echo json_encode(['success'=>true, 'result'=>$result], JSON_THROW_ON_ERROR);
                return;
            }
            if ($collection === 'settings' && $action === 'save_header_menu') {
                if (!is_array($input['items'] ?? null) || !is_int($input['revision'] ?? null)) {
                    throw new \InvalidArgumentException('Menu items and revision are required.');
                }
                $saved = $store->saveHeaderMenu($input['items'], $input['revision']);
                echo json_encode(['success'=>true, 'saved'=>$saved]);
                return;
            }
            if ($collection === 'secrets' && $action === 'change_google_reporting') {
                if (!array_key_exists('record', $input) || ($input['record'] !== null && !is_array($input['record']))) {
                    throw new \InvalidArgumentException('An encrypted reporting record or explicit removal is required.');
                }
                $changed = $store->changeGoogleReportingRecord((string)($input['revision'] ?? ''), $input['record']);
                echo json_encode(['success'=>true, 'changed'=>$changed]);
                return;
            }
            if ($collection === 'products' && $action === 'save_catalog_product') {
                $record = $store->saveCatalogProduct((array)($input['record'] ?? []), ($input['match_slug'] ?? false) === true,
                    ($input['require_exact_target'] ?? false) === true);
                echo json_encode(['success'=>true, 'record'=>$record]);
                return;
            }
            if ($collection === 'products' && $action === 'import_products') {
                $result = $store->importProducts(is_string($input['csv'] ?? null) ? $input['csv'] : '', (array)($input['skip'] ?? []));
                echo json_encode(['success'=>true, 'result'=>$result]);
                return;
            }
            if ($collection === 'media_files' && $action === 'update_media_metadata') {
                if (!is_string($input['id'] ?? null) || !is_array($input['fields'] ?? null) || !is_string($input['expected'] ?? null)) {
                    throw new \InvalidArgumentException('Media identity, fields and revision are required.');
                }
                $store->updateMediaMetadata($input['id'], $input['fields'], $input['expected']);
                echo json_encode(['success'=>true,'updated'=>true]);
                return;
            }
            if ($collection === 'users' && $action === 'patch_user') {
                $updated = $store->patchUser((string)($input['id'] ?? ''), $input['fields'] ?? [],
                    $input['credential'] ?? null, $input['expected'] ?? null);
                echo json_encode(['success' => true, 'updated' => $updated]);
                return;
            }
            if ($collection === 'audit_events' && $action === 'count_storefront_activity') {
                $since = $input['since'] ?? null;
                $expected = SiteTelemetryService::recentCutoff();
                // Keep the caller's exact cutoff and allow up to a minute for transit and clock skew.
                if (!is_int($since) || $since < $expected - 60 || $since > $expected + 60) {
                    throw new \InvalidArgumentException('A recent 30-day storefront cutoff is required.');
                }
                try {
                    echo json_encode(['success' => true, 'result' => $store->storefrontActivitySince($since)], JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    http_response_code(503);
                    echo json_encode(['error' => 'Storefront activity is unavailable.']);
                }
                return;
            }
            if ($collection === 'addresses' && $action === 'list_customer_addresses') {
                $result = (new \App\Services\AddressService($store))->forCustomer((string)($input['email'] ?? ''));
                echo json_encode(['success' => true, 'result' => $result]);
                return;
            }
            if ($collection === 'addresses' && in_array($action, ['save_customer_address', 'delete_customer_address'], true)) {
                $service = new \App\Services\AddressService($store);
                $email = (string)($input['email'] ?? '');
                $id = (string)($input['id'] ?? '');
                $result = [];
                if ($action === 'save_customer_address') {
                    $result = $service->save($email, $input['fields'] ?? [], $id);
                } else {
                    $service->delete($email, $id);
                }
                echo json_encode(['success' => true, 'result' => $result]);
                return;
            }
            if ($collection === 'orders' && $action === 'save_verified_order') {
                $record = $input['record'] ?? null;
                if (!is_array($record) || empty($record['id']) || empty($record['payment_id'])) {
                    throw new \InvalidArgumentException('Order and payment identifiers are required.');
                }
                $result = $store->saveVerifiedOrder($record, ($input['issue_invoice'] ?? false) === true);
                echo json_encode(['success' => true, 'result' => $result]);
                return;
            }
            if ($collection === 'orders' && $action === 'resolve_order_tax_review') {
                $result = $store->resolveOrderTaxReview((string)($input['id'] ?? ''), (string)($input['state'] ?? ''), $input['settings'] ?? []);
                echo json_encode(['success' => true, 'result' => $result]);
                return;
            }
            if ($collection === 'orders' && $action === 'record_order_refund') {
                $result = $store->recordOrderRefund(
                    (string)($input['id'] ?? ''), (string)($input['payment_id'] ?? ''),
                    (string)($input['refund_id'] ?? ''), (int)($input['amount_paise'] ?? 0)
                );
                echo json_encode(['success' => true, 'result' => $result]);
                return;
            }
            if ($collection === 'orders' && $action === 'update_order_status') {
                $result = $store->updateOrderStatus(
                    (string)($input['id'] ?? ''), (string)($input['status'] ?? ''),
                    new \DateTimeImmutable((string)($input['now'] ?? 'now')), $input['tracking'] ?? []
                );
                echo json_encode(['success' => true, 'result' => $result]);
                return;
            }
            if ($collection === 'mail_queue' && $action === 'transition_mail_delivery') {
                $result=$store->transitionMailDelivery((string)($input['id']??''),(string)($input['transition']??''),
                    (string)($input['attempt_id']??''),(array)($input['fields']??[]));
                echo json_encode(['success'=>true,'record'=>$result]);return;
            }
            if ($collection === 'support_tickets' && $action === 'transition_support_ticket') {
                $result=$store->updateSupportTicket((string)($input['id']??''),(string)($input['transition']??''),
                    (array)($input['fields']??[]),(array)($input['actor']??[]),(int)($input['expected_version']??-1));
                echo json_encode(['success'=>true,'result'=>$result]);return;
            }
            if ($action === 'upsert') {
                $record = $input['record'] ?? null;
                if (!is_array($record) || empty($record['id'])) throw new \InvalidArgumentException('Record id is required.');
                $saved = $store->upsert($collection, $record);
                http_response_code(200);
                echo json_encode(['success' => true, 'record' => $saved]);
                return;
            }
            if ($action === 'insert_if_absent') {
                $record = $input['record'] ?? null;
                if (!is_array($record) || empty($record['id'])) throw new \InvalidArgumentException('Record id is required.');
                $result = $store->insertIfAbsent($collection, $record);
                http_response_code(200);
                echo json_encode(['success' => true] + $result);
                return;
            }
            if ($action === 'delete') {
                $id = trim((string)($input['id'] ?? ''));
                if ($id === '') throw new \InvalidArgumentException('Record id is required.');
                $store->delete($collection, $id);
                http_response_code(200);
                echo json_encode(['success' => true]);
                return;
            }
            if ($action === 'replace') {
                $records = $input['records'] ?? null;
                if (!is_array($records)) throw new \InvalidArgumentException('Records are required.');
                $store->write($collection, $records);
                http_response_code(200);
                echo json_encode(['success' => true, 'count' => count($records)]);
                return;
            }
            throw new \InvalidArgumentException('Unsupported mutation action.');
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['error' => $action === 'save_campaign_lead'
                ? 'Campaign submission was not confirmed.'
                : ($collection === 'campaign_bookings' ? 'Booking change was not confirmed.'
                    : 'Remote mutation failed: ' . $e->getMessage())]);
        }
    }
}
