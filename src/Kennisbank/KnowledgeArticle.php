<?php

declare(strict_types=1);

namespace App\Kennisbank;

use DateTimeImmutable;

final readonly class KnowledgeArticle
{
    public function __construct(
        private string $slug,
        private string $title,
        private string $description,
        private string $summary,
        private DateTimeImmutable $publishedAt,
    ) {
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getPublishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
