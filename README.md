# Ultra Import

Pachet Laravel care se conectează la serviciul SOAP Ultra pentru a prelua catalogul de produse și a genera un feed XML (YML) gata de import pentru marketplace-uri.

## Instalare

### Cum îl adaugi într-o aplicație Laravel existentă

1. În `composer.json` din proiect, declară repo-ul git (dacă pachetul nu este pe Packagist):

   ```json
   {
     "repositories": [
       {"type": "vcs", "url": "https://github.com/ORG/wesellUltraImport.git"}
     ]
   }
   ```

2. Instalează pachetul (folosește versiunea/tag-ul potrivit proiectului tău):

   ```bash
   composer require wesell/ultra-import:dev-main
   ```

3. Dacă auto-discovery este dezactivat, adaugă manual provider-ul în `config/app.php`:

   ```php
   'providers' => [
       // ...
       WeSellUltra\Import\Providers\UltraImportServiceProvider::class,
   ],
   ```

4. Publică fișierul de configurare și personalizează valorile:

   ```bash
   php artisan vendor:publish --tag=config --provider="WeSellUltra\\Import\\Providers\\UltraImportServiceProvider"
   ```

5. Setează în `.env` variabilele principale (vezi secțiunea de configurare) și verifică permisiunile pentru calea de ieșire a feed-ului.

6. Rulează importul manual sau configurează un cron/queued job pentru comanda Artisan `ultra:import`.

## Configurare

Variabile principale din `config/ultra_import.php`:

- `ULTRA_WSDL` – endpoint-ul WSDL (`https://portal.it-ultra.com/b2b/ru/ws/b2b.1cws?wsdl`).
- `ULTRA_OUTPUT_PATH` – calea unde se salvează feed-ul XML (implicit `storage/app/ultra/catalog.xml`).
- `ULTRA_PRODUCT_URL` – șablon URL pentru produse; `{code}` va fi înlocuit cu codul produsului.
- `ULTRA_POLL_ATTEMPTS` și `ULTRA_POLL_SLEEP` – controlează numărul de încercări și pauza la interogarea `isReady`.

## Utilizare

Rulează importul complet:

```bash
php artisan ultra:import --all
```

Rulează doar modificările și suprascrie calea de ieșire:

```bash
php artisan ultra:import --output=/tmp/feed.xml
```

Comanda declanșează secvența `requestData -> isReady -> getDataByID` pentru serviciile `NOMENCLATURETYPELIST`, `BRAND`, `NOMENCLATURE`, `PRICELIST` și `BALANCE`, apoi compune structurile `categories` și `offers` în formatul cerut (atribute `price`, `quantity`, imaginile produsului, brand, parametrii și specificațiile). În caz de răspuns diferit de `OK`, procesul se oprește cu eroare.

Feed-ul rezultat respectă structura:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<yml_catalog date="2025-12-31 09:33">
  <shop>
    <categories>
      <category id="3" parentId="0">
        <name>Jucarii</name>
      </category>
    </categories>
    <offers>
      <offer id="..." productId="..." quantity="..." price="...">
        <!-- url, price, categoryId, picture*, name, xmlId, productName, brand, vendor, param, specification* -->
      </offer>
    </offers>
  </shop>
</yml_catalog>
```
