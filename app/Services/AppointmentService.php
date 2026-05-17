<?php
namespace App\Services;
final class AppointmentService { public function __construct(private JsonStoreService $store = new JsonStoreService()){} public function all(): array{return $this->store->read('appointments');} }
