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
| `shopmonkey init` | Authenticeren en dependencies (gulp/prettier/watcher) installeren |
| `shopmonkey auth` | Sessie controleren en (opnieuw) inloggen zolang de gegevens niet kloppen |
| `shopmonkey pull` | Huidige thema ophalen naar `./theme` |
| `shopmonkey watch` | Sessie controleren, daarna bestanden watchen en pushen naar de shop |
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

## Debuggen

Deprecation-notices uit dependencies worden standaard verborgen. Zet ze terug aan met:

```bash
SHOPMONKEY_DEBUG=1 shopmonkey pull
```
