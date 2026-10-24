# AIzichtbaar

**Maak je website citeerbaar in ChatGPT, Perplexity en Google AI — zonder technische kennis.**

Low-entry platform voor Nederlandse ondernemers. Geef je website door, de rest doet de AI.

## Naam & Domein
- **Naam:** AIzichtbaar
- **Aanbevolen domein:** aizichtbaar.nl (controleer beschikbaarheid bij SIDN)
- Kleuren geïnspireerd op backlinks.nl (diep groen #1E6B3A)

## Features
- **Gratis AI Zichtbaarheid Scan** – Snelle basischeck met score en 3 punten
- **Uitgebreide Scan €49** – Diepgaande analyse + concrete actiepunten + content-suggesties (Mollie-ready)
- Gebruikerslogin-structuur (Symfony Security)
- Responsive design met Tailwind CSS
- Klaar voor Mollie-betalingen

## Tech Stack
- **Backend:** Symfony 8.1 (PHP 8.4+)
- **Database:** Doctrine ORM
- **Frontend:** Twig + Tailwind CSS
- **Betalingen:** Mollie (`mollie/mollie-api-php`)

## Snelle start

```bash
composer install
cp .env .env.local
# Zet MOLLIE_API_KEY=test_... in .env.local
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
symfony server:start
# of: php -S localhost:8000 -t public
```

Bezoek http://localhost:8000 → /scan

## Mollie
1. Maak een account aan op mollie.com
2. Kopieer je test API key
3. Zet `MOLLIE_API_KEY=test_xxx` in `.env.local`
4. De betaalflow is klaar in `ScanController` + `MollieService`

## Wat de klant ziet
- Gratis scan: snelle score
- Betaalde scan (€49): uitgebreide uitleg waarom het belangrijk is + volledige analyse

---

*Klaar om geld te verdienen met AI-zichtbaarheid.*
