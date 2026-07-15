<?php
namespace App\Controllers;

final class TtsController extends BaseController {
    public function tokenize(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $text = trim((string)($input['text'] ?? ''));
        if ($text === '') {
            $this->jsonResponse(['success' => false, 'error' => 'Empty text string'], 400);
            return;
        }
        $clean = strtolower(preg_replace('/[^a-z0-9 ]/', '', $text));
        $tokens = [];
        for ($i = 0; $i < strlen($clean); $i++) {
            $tokens[] = ord($clean[$i]);
        }
        $this->jsonResponse(['success' => true, 'tokens' => $tokens]);
    }
}
