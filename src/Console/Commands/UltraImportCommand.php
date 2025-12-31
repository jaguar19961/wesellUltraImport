<?php

namespace WeSellUltra\Import\Console\Commands;

use Illuminate\Console\Command;
use WeSellUltra\Import\Services\UltraCatalogExporter;

class UltraImportCommand extends Command
{
    protected $signature = 'ultra:import {--all : Request the full dataset instead of only changes} {--output= : Override the output path for the XML feed}';

    protected $description = 'Import Ultra catalog data via SOAP and generate an XML feed.';

    public function __construct(private readonly UltraCatalogExporter $exporter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = (bool)$this->option('all');
        $output = $this->option('output');

        $this->info('Starting Ultra catalog import...');

        try {
            $path = $this->exporter->export($all, $output);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Catalog successfully exported to %s', $path));

        return self::SUCCESS;
    }
}
