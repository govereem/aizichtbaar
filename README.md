# AIzichtbaar

**Maak je website citeerbaar in ChatGPT, Perplexity en Google AI — zonder technische kennis.**

Low-entry platform voor Nederlandse ondernemers. Geef je website door, de rest doet de AI.

## Naam & Domein
- **Naam:** AIzichtbaar
- **Aanbevolen domein:** aizichtbaar.nl (controleer beschikbaarheid bij SIDN)
- Kleuren geïnspireerd op backlinks.nl (diep groen #1E6B3A / #267A48)

## Logo
Modern icoon: link + AI spark in diep groen. Zie `public/images/logo.svg`.

## Features (MVP)
- Gebruikerslogin & registratie (Symfony Security)
- Dashboard: voeg alleen je website-URL toe
- Automatische basis-audit (robots.txt, entity, content-structuur)
- Generatie van citation-ready advertorials / content
- Simpele status & rapportage
- Responsive, clean design met Tailwind CSS

## Tech Stack
- **Backend:** Symfony 8.1 (PHP 8.4+)
- **Database:** Doctrine ORM
- **Frontend:** Twig + Tailwind CSS
- **Auth:** Symfony Security

## Snelle start

```bash
composer install
cp .env .env.local
# Zet DATABASE_URL (bijv. sqlite)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
symfony server:start
# of: php -S localhost:8000 -t public
```

Bezoek http://localhost:8000

## Filosofie
De klant hoeft alleen zijn site door te geven. Alles verder (analyse, content, plaatsing-ideeën, AI-optimalisatie) gebeurt automatisch of semi-automatisch. Bijna te goed om waar te zijn.

---

*Gebouwd voor de Nederlandse markt, klaar om uit te breiden met echte AI-API’s en Mollie-betalingen.*
