# Shopmonkey CLI

CLI voor het pullen, watchen en pushen van Shopmonkey-thema's.

## Installatie

```bash
composer global require shopmonkeynl/shopmonkey-cli
```

Zorg dat `~/.composer/vendor/bin` in je `PATH` staat.

## Commando's

| Commando | Omschrijving |
|---|---|
| `shopmonkey init` | Authenticeren en de projectsetup (gulp, upload-script, prettier) opnieuw uitvoeren. Gebeurt automatisch bij de eerste `pull` of `watch`, handmatig is dus niet nodig |
| `shopmonkey auth` | Sessie controleren en (opnieuw) inloggen zolang de gegevens niet kloppen |
| `shopmonkey pull` | Huidige thema ophalen naar `./theme` (`--force` slaat de controle op lokale wijzigingen over) |
| `shopmonkey watch` | Sessie controleren, daarna bestanden watchen en pushen naar de shop |
| `shopmonkey doctor` | Controleert versie, PHP, node/gulp, sessie, shop en bijna-te-grote bestanden |
| `shopmonkey update` | De CLI zelf bijwerken naar de laatste versie |
| `shopmonkey --version` | Geïnstalleerde versie tonen |

## Bijwerken

```bash
shopmonkey update
```

Dit draait onder water `composer global update shopmonkeynl/shopmonkey-cli`.

## Inloggen

`auth` en `watch` controleren eerst of je sessie nog geldig is. Zo niet, dan
start dezelfde login-flow als `init`: gegevens van je klembord (JSON van de
bookmarklet), anders JSON plakken, anders elk veld los. `watch` start de
watcher pas als de sessie klopt.

## Veiligheid

- **Verkeerde shop:** de CLI onthoudt van welke shop en welk thema een map het laatst is gesynct. Wijst `config.json` ergens anders heen, dan vraagt `pull`/`watch` eerst om bevestiging (standaard nee). Hetzelfde geldt als je via het klembord naar een andere shop wisselt. Bovenaan elk commando staat welke shop en welk thema gebruikt wordt.
- **Lokale wijzigingen:** `pull` laat zien welke lokale bestanden overschreven of verwijderd zouden worden (bijv. werk dat nog niet is geüpload) en vraagt bevestiging. Met `--force` sla je dat over.
- **64 KB-limiet:** Lightspeed kapt templates bij een pull af op 65535 bytes. Daarom:
  - `pull` schrijft een afgekapt bestand niet weg (je lokale versie blijft staan) en meldt het als fout.
  - `watch` uploadt geen template van 64 KB of groter en geeft een foutmelding. Bewust toch uploaden kan met `SHOPMONKEY_ALLOW_LARGE=1`.
  - Vanaf 80% van de limiet krijg je een waarschuwing, zodat je op tijd kunt splitsen in snippets.
- **Upload-fouten:** `watch` toont de echte melding van de shop, en meldt duidelijk als je sessie verlopen is (voorheen kon dat als "Success" verschijnen).

## Debuggen

Deprecation-notices uit dependencies worden standaard verborgen. Zet ze terug aan met:

```bash
SHOPMONKEY_DEBUG=1 shopmonkey pull
```
