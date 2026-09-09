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
| `shopmonkey auth` | Opnieuw authenticeren |
| `shopmonkey pull` | Huidige thema ophalen naar `./theme` |
| `shopmonkey watch` | Bestanden watchen en pushen naar de shop |
| `shopmonkey update` | De CLI zelf bijwerken naar de laatste versie |
| `shopmonkey --version` | Geïnstalleerde versie tonen |

## Bijwerken

```bash
shopmonkey update
```

Dit draait onder water `composer global update shopmonkeynl/shopmonkey-cli`.

## Debuggen

Deprecation-notices uit dependencies worden standaard verborgen. Zet ze terug aan met:

```bash
SHOPMONKEY_DEBUG=1 shopmonkey pull
```
