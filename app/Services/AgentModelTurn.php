<?php
declare(strict_types=1);

namespace App\Services;

/** Server-owned inputs for one native agent inference request. */
final readonly class AgentModelTurn
{
    /** @param list<array{question:string, answer:string}> $history Bounded, actor-owned dialogue. */
    public function __construct(
        public string $instructions,
        private array $history,
        private string $question,
        private array $references
    ) {}

    /** @return list<array{role:string, content:string}> */
    public function dialogue(): array
    {
        $messages = [];
        foreach ($this->history as $turn) {
            if (trim($turn['question']) === '' || trim($turn['answer']) === '') continue;
            $messages[] = ['role' => 'user', 'content' => $turn['question']];
            $messages[] = ['role' => 'assistant', 'content' => $turn['answer']];
        }
        $messages[] = ['role' => 'user', 'content' => json_encode(
            ['question' => $this->question, 'reference_data' => $this->references],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )];
        return $messages;
    }
}
