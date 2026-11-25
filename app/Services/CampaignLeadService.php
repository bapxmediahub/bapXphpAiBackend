<?php
namespace App\Services;

final class CampaignLeadService
{
    public const SLOT_IDS = ['detail_1', 'detail_2', 'detail_3'];
    public const OPTIONAL_TYPES = ['text'=>'Short text', 'textarea'=>'Long text', 'tel'=>'Phone number'];

    /** Native editor controls normalize to a fixed, revisioned definition. */
    public static function fromEditor(mixed $enabled, mixed $slots): ?array
    {
        if ($enabled === null) return null;
        if ($enabled !== '1' || !is_array($slots) || array_diff(array_keys($slots), self::SLOT_IDS)) {
            throw new \InvalidArgumentException('Review the campaign form fields.');
        }
        $fields = [];
        foreach (self::SLOT_IDS as $id) {
            $slot = $slots[$id] ?? [];
            if (!is_array($slot) || array_diff(array_keys($slot), ['type','label','required'])) {
                throw new \InvalidArgumentException('Review the campaign form fields.');
            }
            if (($slot['type'] ?? '') === '') continue;
            $required = $slot['required'] ?? null;
            if ($required !== null && $required !== '1') {
                throw new \InvalidArgumentException('Review the required field options.');
            }
            $fields[] = ['id'=>$id, 'type'=>$slot['type'], 'label'=>$slot['label'] ?? null, 'required'=>$required === '1'];
        }
        return self::definition($fields);
    }

    public static function definition(mixed $fields): array
    {
        if (!is_array($fields) || !array_is_list($fields) || count($fields) > count(self::SLOT_IDS)) {
            throw new \InvalidArgumentException('Choose up to three optional campaign fields.');
        }
        $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field) || array_diff(array_keys($field), ['id','type','label','required'])) {
                throw new \InvalidArgumentException('Review the campaign form fields.');
            }
            $id = $field['id'] ?? null;
            $type = $field['type'] ?? null;
            $label = $field['label'] ?? null;
            $required = $field['required'] ?? null;
            if (!in_array($id, self::SLOT_IDS, true) || isset($normalized[$id])
                || !is_string($type) || !isset(self::OPTIONAL_TYPES[$type]) || !is_bool($required)) {
                throw new \InvalidArgumentException('Choose a supported campaign field type for each slot.');
            }
            if (!is_string($label) || !mb_check_encoding($label, 'UTF-8')
                || trim($label) === '' || mb_strlen(trim($label)) > 72 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
                throw new \InvalidArgumentException('Each included campaign field needs a single-line label of up to 72 characters.');
            }
            $normalized[$id] = ['id'=>$id, 'type'=>$type, 'label'=>trim($label), 'required'=>$required];
        }
        ksort($normalized);
        return array_values($normalized);
    }

    public static function pageDefinition(array $page): ?array
    {
        if (!isset($page['campaign_form'])) return null;
        try {
            return self::definition($page['campaign_form']);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function publicFields(array $fields): array
    {
        $result = [
            ['id'=>'name', 'type'=>'text', 'label'=>'Name', 'required'=>true, 'limit'=>120],
            ['id'=>'email', 'type'=>'email', 'label'=>'Email', 'required'=>true, 'limit'=>254],
        ];
        $limits = ['text'=>240, 'textarea'=>1000, 'tel'=>40];
        foreach ($fields as $field) $result[] = $field + ['limit'=>$limits[$field['type']]];
        return $result;
    }

    public static function validate(array $fields, mixed $answers): array
    {
        $schema = self::publicFields($fields);
        $values = $errors = [];
        if (!is_array($answers)) return ['values'=>$values, 'errors'=>['form'=>'Enter the required fields and try again.']];
        if (array_diff(array_keys($answers), array_column($schema, 'id'))) {
            $errors['form'] = 'The enquiry contains an unrecognized field. Review the form and try again.';
        }
        foreach ($schema as $field) {
            $id = $field['id'];
            $value = $answers[$id] ?? '';
            $control = $field['type'] === 'textarea' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
            if (!is_string($value) || strlen($value) > $field['limit'] * 4 || !mb_check_encoding($value, 'UTF-8')
                || mb_strlen($value) > $field['limit'] || preg_match($control, $value)) {
                $errors[$id] = 'Enter valid text within the displayed limit.';
                continue;
            }
            $values[$id] = $value = trim($value);
            if ($value === '') {
                if ($field['required']) $errors[$id] = 'Complete this field.';
                continue;
            }
            if ($field['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$id] = 'Enter a valid email address.';
            }
            if ($field['type'] === 'tel' && (!preg_match('/^\+?[0-9][0-9 ().-]*$/D', $value)
                || !preg_match('/^[0-9]{7,15}$/D', (string)preg_replace('/[^0-9]/', '', $value)))) {
                $errors[$id] = 'Enter a valid phone number.';
            }
        }
        return compact('values','errors');
    }

    /** A private session key binds the receipt to the published definition for thirty minutes. */
    public static function issueToken(string $slug, string $revision, string $key, ?int $now = null): string
    {
        $stamp = (string)($now ?? time());
        $nonce = $stamp . '.' . bin2hex(random_bytes(16));
        return $nonce . '.' . self::signature($slug, $revision, $nonce, $key);
    }

    public static function submissionId(string $slug, string $revision, mixed $token, string $key, ?int $now = null): ?string
    {
        $now ??= time();
        if (!is_string($token) || !preg_match('/^([0-9]{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', $token, $parts)
            || $key === '' || (int)$parts[1] > $now || (int)$parts[1] < $now - 1800
            || !hash_equals(self::signature($slug, $revision, $parts[1].'.'.$parts[2], $key), $parts[3])) {
            return null;
        }
        return 'lead_' . substr(hash('sha256', $token), 0, 24);
    }

    private static function signature(string $slug, string $revision, string $nonce, string $key): string
    {
        return hash_hmac('sha256', 'campaign-enquiry|' . $slug . '|' . $revision . '|' . $nonce, $key);
    }

    public static function contactRecord(string $id, string $slug, string $revision, array $fields, array $values): array
    {
        $sections = [];
        $phone = '';
        foreach ($fields as $field) {
            $value = $values[$field['id']] ?? '';
            if ($value === '') continue;
            if ($field['type'] === 'tel' && $phone === '') $phone = $value;
            $sections[] = $field['label'] . "\n" . $value;
        }
        return [
            'id'=>$id, 'name'=>$values['name'], 'email'=>$values['email'], 'phone'=>$phone,
            'subject'=>'Campaign enquiry',
            'message'=>'Campaign enquiry received.' . ($sections ? "\n\n" . implode("\n\n", $sections) : ''),
            'campaign_slug'=>$slug, 'campaign_revision'=>$revision,
            // The envelope columns written by DatabaseService are DATETIME.
            // https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html
            'status'=>'new', 'created_at'=>date('c'),
        ];
    }
}
