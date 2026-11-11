<?php
namespace App\Services;

final class AddressService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function forCustomer(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '') return [];
        $addresses = array_values(array_filter($this->store->customerAddresses($email),
            static fn(array $address): bool => strtolower((string)($address['customer_email'] ?? '')) === $email));
        usort($addresses, static fn(array $a, array $b): int => ((int)!empty($b['is_default']) <=> (int)!empty($a['is_default'])) ?: strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
        return $addresses;
    }

    public function owned(string $email, string $id): array {
        $email = strtolower(trim($email));
        $address = $id !== '' ? $this->store->find('addresses', $id) : null;
        if ($email === '' || $address === null || strtolower((string)($address['customer_email'] ?? '')) !== $email) {
            throw new \InvalidArgumentException('Saved address not found.');
        }
        return $address;
    }

    public function delete(string $email, string $id): void {
        $email = strtolower(trim($email));
        $this->store->mutateAddressBook($email, 'delete_customer_address', ['id' => $id], function () use ($email, $id): array {
            $this->deleteOwned($email, $id);
            return [];
        });
    }

    private function deleteOwned(string $email, string $id): void {
        $address = $this->owned($email, $id);
        $this->store->delete('addresses', $id);
        if (!empty($address['is_default'])) {
            $next = $this->forCustomer($email)[0] ?? null;
            if ($next !== null && empty($next['is_default'])) {
                $this->store->upsert('addresses', ['id' => $next['id'], 'is_default' => true]);
            }
        }
    }

    public function save(string $email, array $input, string $id = ''): array {
        $email = strtolower(trim($email));
        $fields = array_intersect_key($input, array_flip(['address_name', 'name', 'phone', 'address', 'city', 'pincode', 'state', 'is_default']));
        return $this->store->mutateAddressBook($email, 'save_customer_address', ['id' => $id, 'fields' => $fields],
            fn(): array => $this->saveOwned($email, $fields, $id));
    }

    private function saveOwned(string $email, array $input, string $id): array {
        $email = strtolower(trim($email));
        $name = trim((string)($input['address_name'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));
        $city = trim((string)($input['city'] ?? ''));
        $pincode = trim((string)($input['pincode'] ?? ''));
        $state = TaxService::normaliseState((string)($input['state'] ?? ''));
        if ($email === '' || $name === '' || $address === '' || $city === '' || $pincode === '' || $state === null) throw new \InvalidArgumentException('A name, address, city, valid state, and PIN code are required to save an address.');
        $current = $id !== '' ? $this->owned($email, $id) : null;
        $existing = $this->forCustomer($email);
        $isDefault = !empty($input['is_default']) || !empty($current['is_default']) || $existing === [];
        if ($isDefault) {
            foreach ($existing as $row) {
                if (($row['id'] ?? '') === $id || empty($row['is_default'])) continue;
                $row['is_default'] = false;
                $row['updated_at'] = date('Y-m-d H:i:s');
                $this->store->upsert('addresses', $row);
            }
        }
        return $this->store->upsert('addresses', [
            'id' => $current['id'] ?? bin2hex(random_bytes(8)), 'customer_email' => $email, 'name' => $name,
            'recipient_name' => trim((string)($input['name'] ?? '')), 'phone' => trim((string)($input['phone'] ?? '')),
            'address' => $address, 'city' => $city, 'pincode' => $pincode, 'state' => $state,
            'is_default' => $isDefault, 'created_at' => $current['created_at'] ?? date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
