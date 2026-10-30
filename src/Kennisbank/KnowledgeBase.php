<?php

declare(strict_types=1);

namespace App\Kennisbank;

use DateTimeImmutable;

final class KnowledgeBase
{
    /** @var list<KnowledgeArticle> */
    private array $articles;

    public function __construct()
    {
        $publishedAt = new DateTimeImmutable('2026-10-30');

        $this->articles = [
            new KnowledgeArticle(
                'wat-is-ai-zichtbaarheid',
                'Wat is AI-zichtbaarheid?',
                'Wat AI-zichtbaarheid betekent en waarom het steeds belangrijker wordt, in gewone taal.',
                'AI-zichtbaarheid is hoe goed AI-assistenten zoals ChatGPT en Perplexity jouw website kunnen vinden, begrijpen en noemen als antwoord op een vraag. Steeds meer mensen zoeken zo, dus het wordt net zo belangrijk als vindbaar zijn in Google.',
                $publishedAt,
            ),
            new KnowledgeArticle(
                'hoe-kiezen-ai-assistenten-websites',
                'Hoe kiezen AI-assistenten hun bronnen?',
                'Hoe ChatGPT, Perplexity en Google AI websites vinden en kiezen als bron.',
                'AI-assistenten zoeken op het web, lezen pagina\'s en kiezen de bronnen die het duidelijkst en het betrouwbaarst lijken. Ze pakken zelden de hele website, maar de pagina die het antwoord geeft.',
                $publishedAt,
            ),
            new KnowledgeArticle(
                'llms-txt-en-robots-txt',
                'llms.txt en robots.txt uitgelegd',
                'Wat robots.txt en llms.txt zijn, hoe je AI-crawlers toegang geeft en hoe je controleert of het werkt.',
                'robots.txt vertelt zoekmachines en AI-crawlers welke pagina\'s ze mogen bezoeken. llms.txt is een korte gids voor AI met de belangrijkste pagina\'s van je site. Beide zijn eenvoudige tekstbestanden in de hoofdmap van je website.',
                $publishedAt,
            ),
            new KnowledgeArticle(
                'structured-data-uitgelegd',
                'Structured data uitgelegd',
                'Wat structured data (JSON-LD) is, waarom het helpt en welke gegevens je het beste toevoegt.',
                'Structured data is extra informatie in een vaste opmaak, zodat computers in één oogopslag zien wat je pagina is: een bedrijf, een product of een vraag. Bezoekers zien het niet, maar zoekmachines en AI wel.',
                $publishedAt,
            ),
            new KnowledgeArticle(
                'content-schrijven-die-ai-citeert',
                'Zo schrijf je content die AI citeert',
                'Praktische tips om teksten zo te schrijven dat AI-assistenten ze begrijpen en als bron gebruiken.',
                'AI-assistenten nemen het liefst een helder antwoord over dat op één plek staat. Begin met het antwoord, gebruik duidelijke koppen en onderbouw je punten met feiten. Schrijf voor mensen, dan begrijpt de machine het vanzelf.',
                $publishedAt,
            ),
        ];
    }

    /**
     * @return list<KnowledgeArticle>
     */
    public function all(): array
    {
        return $this->articles;
    }

    public function find(string $slug): ?KnowledgeArticle
    {
        return array_find($this->articles, static fn (KnowledgeArticle $article): bool => $article->getSlug() === $slug);
    }
}
