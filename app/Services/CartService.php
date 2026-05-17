<?php
namespace App\Services;
final class CartService { public function items(): array{return $_SESSION['cart'] ?? [];} }
