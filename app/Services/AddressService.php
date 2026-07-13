<?php
namespace App\Services;

final class AddressService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function forCustomer(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '') return [];
        return array_values(array_filter($this->store->read('addresses'), static fn(array $address): bool => strtolower((string)($address['customer_email'] ?? '')) === $email));
    }

    public function save(string $email, array $input): array {
        $email = strtolower(trim($email));
        $name = trim((string)($input['address_name'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));
        $city = trim((string)($input['city'] ?? ''));
        $pincode = trim((string)($input['pincode'] ?? ''));
        if ($email === '' || $name === '' || $address === '' || $city === '' || $pincode === '') throw new \InvalidArgumentException('A name, address, city, and PIN code are required to save an address.');
        return $this->store->upsert('addresses', [
            'id' => bin2hex(random_bytes(8)), 'customer_email' => $email, 'name' => $name,
            'recipient_name' => trim((string)($input['name'] ?? '')), 'phone' => trim((string)($input['phone'] ?? '')),
            'address' => $address, 'city' => $city, 'pincode' => $pincode,
            'created_at' => date('c'), 'updated_at' => date('c'),
        ]);
    }
}
