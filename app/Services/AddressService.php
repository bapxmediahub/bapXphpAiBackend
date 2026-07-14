<?php
namespace App\Services;

final class AddressService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function forCustomer(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '') return [];
        $addresses = array_values(array_filter($this->store->read('addresses'), static fn(array $address): bool => strtolower((string)($address['customer_email'] ?? '')) === $email));
        usort($addresses, static fn(array $a, array $b): int => ((int)!empty($b['is_default']) <=> (int)!empty($a['is_default'])) ?: strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
        return $addresses;
    }

    public function save(string $email, array $input): array {
        $email = strtolower(trim($email));
        $name = trim((string)($input['address_name'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));
        $city = trim((string)($input['city'] ?? ''));
        $pincode = trim((string)($input['pincode'] ?? ''));
        if ($email === '' || $name === '' || $address === '' || $city === '' || $pincode === '') throw new \InvalidArgumentException('A name, address, city, and PIN code are required to save an address.');
        $existing = $this->forCustomer($email);
        $isDefault = !empty($input['is_default']) || $existing === [];
        if ($isDefault) {
            foreach ($existing as $row) {
                if (empty($row['is_default'])) continue;
                $row['is_default'] = false;
                $row['updated_at'] = date('c');
                $this->store->upsert('addresses', $row);
            }
        }
        return $this->store->upsert('addresses', [
            'id' => bin2hex(random_bytes(8)), 'customer_email' => $email, 'name' => $name,
            'recipient_name' => trim((string)($input['name'] ?? '')), 'phone' => trim((string)($input['phone'] ?? '')),
            'address' => $address, 'city' => $city, 'pincode' => $pincode,
            'is_default' => $isDefault, 'created_at' => date('c'), 'updated_at' => date('c'),
        ]);
    }
}
