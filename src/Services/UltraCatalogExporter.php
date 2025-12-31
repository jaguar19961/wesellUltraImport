<?php

namespace WeSellUltra\Import\Services;

use DateTimeImmutable;
use DOMDocument;
use SimpleXMLElement;

class UltraCatalogExporter
{
    public function __construct(
        private readonly UltraClient $client,
        private readonly string $defaultOutputPath,
        private readonly string $productUrlTemplate,
        private readonly array $pollConfig
    ) {
    }

    public function export(bool $all = true, ?string $outputPath = null): string
    {
        $outputPath ??= $this->defaultOutputPath;
        $poll = $this->pollConfig;

        $categoriesXml = $this->client->fetchData('NOMENCLATURETYPELIST', $all, null, false, $poll['max_attempts'], $poll['sleep_seconds']);
        $brandsXml = $this->client->fetchData('BRAND', $all, null, false, $poll['max_attempts'], $poll['sleep_seconds']);
        $nomenclatureXml = $this->client->fetchData('NOMENCLATURE', $all, null, false, $poll['max_attempts'], $poll['sleep_seconds']);
        $pricesXml = $this->client->fetchData('PRICELIST', $all, null, false, $poll['max_attempts'], $poll['sleep_seconds']);
        $balanceXml = $this->client->fetchData('BALANCE', $all, null, false, $poll['max_attempts'], $poll['sleep_seconds']);

        $categories = $this->parseCategories($categoriesXml);
        $brands = $this->parseBrands($brandsXml);
        $products = $this->parseProducts($nomenclatureXml);
        $prices = $this->parsePrices($pricesXml);
        $balances = $this->parseBalances($balanceXml);

        $document = $this->buildXml($categories, $brands, $products, $prices, $balances);
        $document->save($outputPath);

        return $outputPath;
    }

    private function parseCategories(?string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        $data = new SimpleXMLElement($xml);
        $nodes = $data->xpath('//nomenclatureType') ?: [];

        return array_map(function (SimpleXMLElement $node) {
            return [
                'id' => (string)($node->UUID ?? ''),
                'parent' => (string)($node->parent ?? ''),
                'name' => (string)($node->name ?? ''),
                'order' => (int)($node->orderBy ?? 0),
            ];
        }, $nodes);
    }

    private function parseBrands(?string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        $data = new SimpleXMLElement($xml);
        $nodes = $data->xpath('//brand') ?: [];
        $brands = [];

        foreach ($nodes as $node) {
            $brands[(string)($node->UUID ?? '')] = [
                'name' => (string)($node->name ?? ''),
                'image' => (string)($node->image->pathGlobal ?? $node->image->path ?? ''),
            ];
        }

        return $brands;
    }

    private function parseProducts(?string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        $data = new SimpleXMLElement($xml);
        $nodes = $data->xpath('//nomenclature') ?: [];

        return array_map(function (SimpleXMLElement $node) {
            $images = [];
            foreach ($node->imageList->image ?? [] as $image) {
                $images[] = (string)($image->pathGlobal ?? $image->path ?? '');
            }

            return [
                'uuid' => (string)($node->UUID ?? ''),
                'code' => (string)($node->code ?? ''),
                'name' => (string)($node->name ?? ''),
                'full_name' => (string)($node->fullName ?? ''),
                'description' => (string)($node->description ?? ''),
                'article' => (string)($node->article ?? ''),
                'brand' => (string)($node->brand ?? ''),
                'category' => (string)($node->nomenclatureType ?? ''),
                'images' => $images,
                'characteristics' => $this->parseCharacteristics($node->characteristicList ?? null),
                'properties' => $this->parseProperties($node->propertyList ?? null),
            ];
        }, $nodes);
    }

    private function parseCharacteristics(?SimpleXMLElement $characteristics): array
    {
        if ($characteristics === null) {
            return [];
        }

        $items = [];
        foreach ($characteristics->characteristic ?? [] as $characteristic) {
            $images = [];
            foreach ($characteristic->imageList->image ?? [] as $image) {
                $images[] = (string)($image->pathGlobal ?? $image->path ?? '');
            }

            $items[(string)($characteristic->UUID ?? '')] = [
                'name' => (string)($characteristic->name ?? ''),
                'images' => $images,
                'properties' => $this->parseProperties($characteristic->propertyList ?? null),
            ];
        }

        return $items;
    }

    private function parseProperties(?SimpleXMLElement $propertyList): array
    {
        if ($propertyList === null) {
            return [];
        }

        $properties = [];
        foreach ($propertyList->propertyValue ?? [] as $property) {
            $properties[] = [
                'name' => (string)($property->property->name ?? ''),
                'value' => (string)($property->value->simpleValue ?? $property->value->name ?? ''),
                'type' => (string)($property->value->type ?? ''),
            ];
        }

        return $properties;
    }

    private function parsePrices(?string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        $data = new SimpleXMLElement($xml);
        $prices = [];

        foreach ($data->xpath('//price') ?: [] as $price) {
            $nomenclatureId = (string)($price->UUID ?? '');
            $characteristicId = (string)($price->Characteristic ?? 'default');
            $prices[$nomenclatureId][$characteristicId] = [
                'value' => (float)($price->Price ?? 0),
                'currency' => (string)($price->PriceType->valute ?? ''),
            ];
        }

        return $prices;
    }

    private function parseBalances(?string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        $data = new SimpleXMLElement($xml);
        $balances = [];

        foreach ($data->xpath('//balance') ?: [] as $balance) {
            $nomenclatureId = (string)($balance->UUID ?? '');
            $characteristicId = (string)($balance->Characteristic ?? 'default');
            $balances[$nomenclatureId][$characteristicId] = (int)($balance->quantity ?? 0);
        }

        return $balances;
    }

    private function buildXml(array $categories, array $brands, array $products, array $prices, array $balances): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $catalog = $doc->createElement('yml_catalog');
        $catalog->setAttribute('date', (new DateTimeImmutable('now'))->format('Y-m-d H:i'));
        $doc->appendChild($catalog);

        $shop = $doc->createElement('shop');
        $catalog->appendChild($shop);

        $categoriesNode = $doc->createElement('categories');
        foreach ($categories as $category) {
            $categoryNode = $doc->createElement('category');
            $categoryNode->setAttribute('id', $category['id']);
            if (!empty($category['parent'])) {
                $categoryNode->setAttribute('parentId', $category['parent']);
            }
            $categoryNode->appendChild($doc->createElement('name', htmlspecialchars($category['name'])));
            $categoriesNode->appendChild($categoryNode);
        }
        $shop->appendChild($categoriesNode);

        $offersNode = $doc->createElement('offers');
        foreach ($products as $product) {
            $characteristics = $product['characteristics'] ?: ['default' => null];
            foreach ($characteristics as $characteristicId => $characteristic) {
                $offerNode = $doc->createElement('offer');
                $offerId = $product['code'] ?: $product['uuid'];
                if ($characteristicId !== 'default') {
                    $offerId .= '-' . $characteristicId;
                    $offerNode->setAttribute('characteristicId', $characteristicId);
                }

                $offerNode->setAttribute('id', $offerId);
                $offerNode->setAttribute('productId', $product['uuid']);

                $price = $prices[$product['uuid']][$characteristicId] ?? $prices[$product['uuid']]['default'] ?? null;
                $balance = $balances[$product['uuid']][$characteristicId] ?? $balances[$product['uuid']]['default'] ?? 0;

                $offerNode->setAttribute('quantity', (string)$balance);
                if ($price !== null) {
                    $offerNode->setAttribute('price', number_format($price['value'], 2, '.', ''));
                }

                $url = str_replace('{code}', $product['code'], $this->productUrlTemplate);
                $offerNode->appendChild($doc->createElement('url', $url));

                if ($price !== null) {
                    $offerNode->appendChild($doc->createElement('price', number_format($price['value'], 2, '.', '')));
                }

                $offerNode->appendChild($doc->createElement('categoryId', $product['category']));
                $images = array_merge($product['images'], $characteristic['images'] ?? []);
                foreach ($images as $image) {
                    if (!empty($image)) {
                        $offerNode->appendChild($doc->createElement('picture', $image));
                    }
                }

                $offerNode->appendChild($doc->createElement('name', $product['name']));
                $offerNode->appendChild($doc->createElement('xmlId', $product['uuid']));
                $offerNode->appendChild($doc->createElement('productName', $product['full_name'] ?: $product['name']));

                if (!empty($product['brand']) && isset($brands[$product['brand']])) {
                    $brandNode = $doc->createElement('brand');
                    $brandNode->appendChild($doc->createElement('name', $brands[$product['brand']]['name']));
                    if (!empty($brands[$product['brand']]['image'])) {
                        $brandNode->appendChild($doc->createElement('picture', $brands[$product['brand']]['image']));
                    }
                    $offerNode->appendChild($brandNode);
                }

                $offerNode->appendChild($doc->createElement('vendor', 'Ultra'));

                if (!empty($product['article'])) {
                    $param = $doc->createElement('param', $product['article']);
                    $param->setAttribute('name', 'Артикул');
                    $param->setAttribute('code', 'article');
                    $offerNode->appendChild($param);
                }

                $properties = array_merge($product['properties'], $characteristic['properties'] ?? []);
                foreach ($properties as $property) {
                    $spec = $doc->createElement('specification', $property['value']);
                    $spec->setAttribute('name', $property['name']);
                    $spec->setAttribute('type', $property['type']);
                    $offerNode->appendChild($spec);
                }

                if ($characteristicId !== 'default' && $characteristic) {
                    $offerNode->appendChild($doc->createElement('characteristicName', $characteristic['name']));
                }

                $offersNode->appendChild($offerNode);
            }
        }

        $shop->appendChild($offersNode);

        return $doc;
    }
}
