<?php
namespace App\Services;
final class CouponService { public function __construct(private JsonStoreService $store = new JsonStoreService()){} public function all(): array{return $this->store->read('coupons');} }
