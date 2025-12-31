<?php

namespace WeSellUltra\Import\Tests;

use PHPUnit\Framework\TestCase;
use WeSellUltra\Import\Services\UltraCatalogExporter;
use WeSellUltra\Import\Services\UltraClient;

class FakeUltraClient extends UltraClient
{
    public function __construct(private array $fixtures)
    {
    }

    public function fetchData(string $service, bool $all, ?string $additionalParameters, bool $compress, int $maxAttempts, int $sleepSeconds): ?string
    {
        return $this->fixtures[$service] ?? null;
    }
}

class UltraCatalogExporterTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = sys_get_temp_dir() . '/ultra_catalog_test.xml';
        @unlink($this->outputPath);
    }

    public function test_it_generates_offer_per_characteristic_with_properties_and_images(): void
    {
        $fixtures = [
            'NOMENCLATURETYPELIST' => file_get_contents(__DIR__ . '/fixtures/categories.xml'),
            'BRAND' => file_get_contents(__DIR__ . '/fixtures/brands.xml'),
            'NOMENCLATURE' => file_get_contents(__DIR__ . '/fixtures/nomenclature.xml'),
            'PRICELIST' => file_get_contents(__DIR__ . '/fixtures/prices.xml'),
            'BALANCE' => file_get_contents(__DIR__ . '/fixtures/balances.xml'),
        ];

        $exporter = new UltraCatalogExporter(
            new FakeUltraClient($fixtures),
            $this->outputPath,
            'https://example.com/product/{code}',
            [
                'max_attempts' => 1,
                'sleep_seconds' => 0,
            ]
        );

        $exportPath = $exporter->export();

        $this->assertFileExists($exportPath);

        $xml = simplexml_load_file($exportPath);
        $offers = $xml->xpath('/yml_catalog/shop/offers/offer');
        $this->assertCount(1, $offers);

        $offer = $offers[0];
        $this->assertSame('SKU1-c1', (string)$offer['id']);
        $this->assertSame('p1', (string)$offer['productId']);
        $this->assertSame('10.50', (string)$offer['price']);
        $this->assertSame('7', (string)$offer['quantity']);
        $this->assertSame('3', (string)$offer->categoryId);
        $this->assertSame('Prod1', (string)$offer->name);
        $this->assertSame('Prod1 Full', (string)$offer->productName);
        $this->assertSame('Size M', (string)$offer->characteristicName);

        $pictures = $offer->xpath('picture');
        $this->assertContains('https://example.com/p1.png', array_map('strval', $pictures));
        $this->assertContains('https://example.com/c1.png', array_map('strval', $pictures));

        $specifications = $offer->xpath('specification');
        $specValues = array_map(fn ($node) => (string)$node, $specifications);
        $this->assertContains('Red', $specValues);
        $this->assertContains('10', $specValues);
    }
}
