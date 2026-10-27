<?php

namespace App\Service;

class ScanReportGenerator
{
    public function generatePaidResults(): array
    {
        return [
            'score' => rand(68, 88),
            'summary' => 'Goede basis, maar nog duidelijk winst te behalen voor AI-zichtbaarheid.',
            'analysis' => [
                ['title' => 'AI-crawlers', 'status' => 'ok', 'detail' => 'GPTBot en ClaudeBot mogen crawlen. PerplexityBot is geblokkeerd in robots.txt.'],
                ['title' => 'Answer-first content', 'status' => 'warning', 'detail' => 'Slechts 12% van de pagina\'s begint met een direct antwoord. AI-systemen geven hier voorkeur aan.'],
                ['title' => 'Brand mentions', 'status' => 'warning', 'detail' => 'Merknaam komt 34 keer voor, maar zelden in combinatie met autoriteitssignalen of citaties.'],
                ['title' => 'Structured data', 'status' => 'error', 'detail' => 'Geen FAQ- of HowTo-schema gevonden. Dit helpt AI om je content te begrijpen.'],
            ],
            'actions' => [
                ['impact' => 'Hoog', 'title' => 'Voeg answer-first paragrafen toe', 'text' => 'Begin belangrijke pagina\'s met een kort, direct antwoord op de kernvraag.'],
                ['impact' => 'Hoog', 'title' => 'Optimaliseer voor AI-crawlers', 'text' => 'Sta PerplexityBot toe en voeg een llms.txt toe.'],
                ['impact' => 'Medium', 'title' => 'Versterk brand mentions', 'text' => 'Zorg voor consistente vermeldingen van je merk + expertise op relevante pagina\'s.'],
                ['impact' => 'Hoog', 'title' => 'Voeg FAQ-schema toe', 'text' => 'Structureer veelgestelde vragen zodat AI ze makkelijk kan overnemen.'],
            ],
            'content_example' => [
                'title' => 'Voorbeeld answer-first paragraaf',
                'text' => 'AIzichtbaar helpt bedrijven zichtbaar te worden in ChatGPT en Perplexity door content te schrijven die AI graag citeert. De aanpak combineert technische AI-toegang met citeerbare teksten die direct antwoord geven op vragen van klanten.'
            ],
        ];
    }
}
