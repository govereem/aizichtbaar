<?php

declare(strict_types=1);

namespace App\Scan;

final readonly class CheckResult
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public string $detail,
        public int $weight = 1,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'weight' => $this->weight,
        ];
    }
}
