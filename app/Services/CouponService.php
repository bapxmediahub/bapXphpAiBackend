<?php
namespace App\Services;
final class CouponService { public function __construct(private DatabaseService $store = new DatabaseService()){} public function all(): array{return $this->store->read('coupons');} }
