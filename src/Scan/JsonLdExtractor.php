<?php

declare(strict_types=1);

namespace App\Scan;

use Symfony\Component\DomCrawler\Crawler;

final class JsonLdExtractor
{
    /**
     * Returns every JSON-LD object in the HTML, with @graph entries flattened into the list.
     *
     * @return list<array<string, mixed>>
     */
    public function extract(?string $html): array
    {
        if (null === $html) {
            return [];
        }

        $blocks = (new Crawler($html))
            ->filter('script[type="application/ld+json"]')
            ->each(fn (Crawler $node): string => $node->text());

        $objects = [];
        foreach ($blocks as $block) {
            $data = json_decode($block, true);
            if (is_array($data)) {
                $this->collect($data, $objects);
            }
        }

        return $objects;
    }

    /**
     * @param list<array<string, mixed>> $objects
     *
     * @return list<string>
     */
    public function types(array $objects): array
    {
        $types = [];
        foreach ($objects as $object) {
            foreach ((array) ($object['@type'] ?? []) as $type) {
                if (is_string($type)) {
                    $types[] = $type;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param list<array<string, mixed>> $objects
     *
     * @return array<string, mixed>|null
     */
    public function findByType(array $objects, string ...$wanted): ?array
    {
        foreach ($objects as $object) {
            if (array_intersect($wanted, (array) ($object['@type'] ?? []))) {
                return $object;
            }
        }

        return null;
    }

    /**
     * @param array<mixed>                $data
     * @param list<array<string, mixed>> $objects
     */
    private function collect(array $data, array &$objects): void
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                if (is_array($item)) {
                    $this->collect($item, $objects);
                }
            }

            return;
        }

        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $this->collect($item, $objects);
                }
            }

            return;
        }

        $objects[] = $data;
    }
}
