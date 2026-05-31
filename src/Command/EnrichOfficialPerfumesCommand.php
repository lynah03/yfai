<?php

namespace App\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'perfume:enrich-official',
    description: 'Build a dry-run official perfume enrichment CSV checkpoint.'
)]
final class EnrichOfficialPerfumesCommand extends Command
{
    private const DEFAULT_INPUT = 'var/imports/clean/perfumes_clean.csv';
    private const DEFAULT_DOMAINS = 'var/imports/enrichment/brand_official_domains.csv';
    private const DEFAULT_OUTPUT = 'var/imports/enrichment/perfumes_official_enriched.csv';
    private const DEFAULT_REPORT = 'var/imports/enrichment/perfumes_official_report.csv';
    private const DEFAULT_STATE = 'var/imports/enrichment/perfumes_official_state.json';

    private const ADDED_COLUMNS = [
        'source_row_number',
        'source_number',
        'brand_domain',
        'official_product_url',
        'official_image_url',
        'image_source_url',
        'image_status',
        'list_price_cents',
        'list_price_currency',
        'price_original_amount',
        'price_original_currency',
        'price_source_url',
        'price_status',
        'short_description',
        'description',
        'description_source_url',
        'description_status',
        'concentration_status',
        'notes_status',
        'seasons',
        'occasions',
        'marketing_gender',
        'enrichment_status',
        'enrichment_errors',
        'fetched_at',
    ];

    /**
     * @var array<string, array{records: list<array{url: string, title: string, source: string}>, errors: list<string>}>
     */
    private array $officialUrlIndexes = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Input clean perfume CSV.', self::DEFAULT_INPUT)
            ->addOption('domains', null, InputOption::VALUE_REQUIRED, 'Brand official domain mapping CSV.', self::DEFAULT_DOMAINS)
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output enriched CSV.', self::DEFAULT_OUTPUT)
            ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Output report CSV.', self::DEFAULT_REPORT)
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Output state/checkpoint JSON.', self::DEFAULT_STATE)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows to process.', '50')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Rows to skip after filtering.', '0')
            ->addOption('brand', null, InputOption::VALUE_REQUIRED, 'Only process this brand name.', '')
            ->addOption('from-row', null, InputOption::VALUE_REQUIRED, 'Start from this 1-based CSV data row number.', '')
            ->addOption('row-id', null, InputOption::VALUE_REQUIRED, 'Only process a matching source number/id or CSV row number.', '')
            ->addOption('resume', null, InputOption::VALUE_NONE, 'Skip rows already completed in the state file.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Keep enrichment in dry-run mode.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $limit = $this->readNonNegativeIntOption($input, 'limit');
            $offset = $this->readNonNegativeIntOption($input, 'offset');
            $fromRow = $this->readOptionalPositiveIntOption($input, 'from-row');
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $inputPath = $this->resolvePath($this->readStringOption($input, 'input'));
        $domainsPath = $this->resolvePath($this->readStringOption($input, 'domains'));
        $outputPath = $this->resolvePath($this->readStringOption($input, 'output'));
        $reportPath = $this->resolvePath($this->readStringOption($input, 'report'));
        $statePath = $this->resolvePath($this->readStringOption($input, 'state'));

        if (!is_file($inputPath)) {
            $io->error(sprintf('Input CSV not found: %s', $inputPath));

            return Command::FAILURE;
        }

        if (!is_file($domainsPath)) {
            $io->error(sprintf('Brand domain CSV not found: %s', $domainsPath));

            return Command::FAILURE;
        }

        $brandFilter = $this->readStringOption($input, 'brand');
        $rowId = $this->readStringOption($input, 'row-id');
        $resume = (bool) $input->getOption('resume');

        try {
            $brandDomains = $this->readBrandDomains($domainsPath);
            $state = $this->readState($statePath);
            $selection = $this->readSelectedCsvRows(
                path: $inputPath,
                brandFilter: $brandFilter,
                rowId: $rowId,
                fromRow: $fromRow,
                offset: $offset,
                limit: $limit,
                resume: $resume,
                stateRows: $state['rows'] ?? [],
            );
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $outputHeader = $this->buildOutputHeader($selection['header']);
        $enrichedRows = [];
        $reportRows = [];
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $stateRows = is_array($state['rows'] ?? null) ? $state['rows'] : [];

        foreach ($selection['rows'] as $selectedRow) {
            $row = $selectedRow['data'];
            $csvRowNumber = $selectedRow['csv_row_number'];
            $sourceNumber = $this->sourceNumber($row);
            $brandDomain = $this->brandDomain($brandDomains, $row['brand_name'] ?? '');
            $enrichedRow = $this->buildEnrichedRow($outputHeader, $selection['header'], $row, $csvRowNumber, $sourceNumber, $brandDomain);
            $officialUrlResult = $this->discoverOfficialProductUrl($row, $brandDomain);
            $errors = $officialUrlResult['errors'];

            $enrichedRow['official_product_url'] = $officialUrlResult['url'];
            $enrichedRow['enrichment_status'] = $officialUrlResult['url'] !== ''
                ? 'official_url_found'
                : 'official_url_not_found';
            $enrichedRow['fetched_at'] = $now;

            if ($officialUrlResult['url'] !== '') {
                $extraction = $this->extractOfficialProductPage($row, $officialUrlResult['url'], $brandDomain);
                $this->applyOfficialExtraction($enrichedRow, $extraction);
                $errors = array_merge($errors, $extraction['errors']);
            } else {
                $this->markOfficialExtractionNotFound($enrichedRow);
            }

            $enrichedRow['enrichment_errors'] = implode('; ', array_values(array_unique($errors)));

            $enrichedRows[] = $enrichedRow;
            $reportRows[] = $this->buildReportRow($enrichedRow, $row);

            $stateRows[(string) $csvRowNumber] = [
                'source_row_number' => $csvRowNumber,
                'source_number' => $sourceNumber,
                'brand_name' => $row['brand_name'] ?? '',
                'perfume_name' => $row['perfume_name'] ?? '',
                'brand_domain' => $brandDomain,
                'official_product_url' => $enrichedRow['official_product_url'],
                'official_image_url' => $enrichedRow['official_image_url'],
                'enrichment_status' => $enrichedRow['enrichment_status'],
                'image_status' => $enrichedRow['image_status'],
                'list_price_cents' => $enrichedRow['list_price_cents'],
                'list_price_currency' => $enrichedRow['list_price_currency'],
                'price_original_amount' => $enrichedRow['price_original_amount'],
                'price_original_currency' => $enrichedRow['price_original_currency'],
                'price_status' => $enrichedRow['price_status'],
                'description_status' => $enrichedRow['description_status'],
                'concentration_status' => $enrichedRow['concentration_status'],
                'notes_status' => $enrichedRow['notes_status'],
                'updated_at' => $now,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        $state = [
            'command' => 'perfume:enrich-official',
            'generated_at' => $now,
            'dry_run' => true,
            'input' => $this->readStringOption($input, 'input'),
            'domains' => $this->readStringOption($input, 'domains'),
            'output' => $this->readStringOption($input, 'output'),
            'report' => $this->readStringOption($input, 'report'),
            'state' => $this->readStringOption($input, 'state'),
            'options' => [
                'limit' => $limit,
                'offset' => $offset,
                'brand' => $brandFilter,
                'from_row' => $fromRow,
                'row_id' => $rowId,
                'resume' => $resume,
            ],
            'scanned_row_count' => $selection['scanned_row_count'],
            'selected_row_count' => count($enrichedRows),
            'skipped_completed_count' => $selection['skipped_completed_count'],
            'rows' => $stateRows,
        ];

        try {
            $this->writeCsv($outputPath, $outputHeader, $enrichedRows);
            $this->writeCsv($reportPath, $this->reportHeader(), $reportRows);
            $this->writeJson($statePath, $state);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Dry-run official enrichment wrote %d row(s). Output: %s Report: %s State: %s',
            count($enrichedRows),
            $this->relativePath($outputPath),
            $this->relativePath($reportPath),
            $this->relativePath($statePath),
        ));

        if ($selection['skipped_completed_count'] > 0) {
            $io->note(sprintf('Skipped %d completed row(s) from state.', $selection['skipped_completed_count']));
        }

        return Command::SUCCESS;
    }

    private function readStringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? trim($value) : '';
    }

    private function readNonNegativeIntOption(InputInterface $input, string $name): int
    {
        $value = $this->readStringOption($input, $name);

        if ($value === '' || !ctype_digit($value)) {
            throw new \InvalidArgumentException(sprintf('Option --%s must be a non-negative integer.', $name));
        }

        return (int) $value;
    }

    private function readOptionalPositiveIntOption(InputInterface $input, string $name): ?int
    {
        $value = $this->readStringOption($input, $name);

        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value) || (int) $value < 1) {
            throw new \InvalidArgumentException(sprintf('Option --%s must be a positive integer.', $name));
        }

        return (int) $value;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->kernel->getProjectDir();
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($this->kernel->getProjectDir(), '/').'/'.$path;
    }

    private function relativePath(string $path): string
    {
        $projectDir = rtrim($this->kernel->getProjectDir(), '/').'/';

        if (str_starts_with($path, $projectDir)) {
            return substr($path, strlen($projectDir));
        }

        return $path;
    }

    /**
     * @return array{header: list<string>, rows: list<array{csv_row_number: int, data: array<string, string>}>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not open CSV for reading: %s', $path));
        }

        $header = fgetcsv($handle, 0, ',', '"', '');

        if (!is_array($header)) {
            fclose($handle);

            throw new \RuntimeException(sprintf('CSV has no header row: %s', $path));
        }

        $header = array_map(static fn (?string $column): string => trim((string) $column), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];

        $rows = [];
        $csvRowNumber = 0;
        $headerCount = count($header);

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            $csvRowNumber++;
            $values = array_map(static fn ($value): string => (string) $value, $values);
            $values = array_slice(array_pad($values, $headerCount, ''), 0, $headerCount);

            $rows[] = [
                'csv_row_number' => $csvRowNumber,
                'data' => array_combine($header, $values),
            ];
        }

        fclose($handle);

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $stateRows
     * @return array{
     *     header: list<string>,
     *     rows: list<array{csv_row_number: int, data: array<string, string>}>,
     *     scanned_row_count: int,
     *     skipped_completed_count: int
     * }
     */
    private function readSelectedCsvRows(
        string $path,
        string $brandFilter,
        string $rowId,
        ?int $fromRow,
        int $offset,
        int $limit,
        bool $resume,
        array $stateRows,
    ): array {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not open CSV for reading: %s', $path));
        }

        $header = fgetcsv($handle, 0, ',', '"', '');

        if (!is_array($header)) {
            fclose($handle);

            throw new \RuntimeException(sprintf('CSV has no header row: %s', $path));
        }

        $header = array_map(static fn (?string $column): string => trim((string) $column), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];

        $rows = [];
        $csvRowNumber = 0;
        $skippedCompleted = 0;
        $remainingOffset = $offset;
        $headerCount = count($header);
        $normalizedBrandFilter = $this->normalizeBrand($brandFilter);

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            $csvRowNumber++;
            $values = array_map(static fn ($value): string => (string) $value, $values);
            $values = array_slice(array_pad($values, $headerCount, ''), 0, $headerCount);
            $data = array_combine($header, $values);

            if (!is_array($data)) {
                continue;
            }

            if ($fromRow !== null && $csvRowNumber < $fromRow) {
                continue;
            }

            if ($normalizedBrandFilter !== '' && $this->normalizeBrand($data['brand_name'] ?? '') !== $normalizedBrandFilter) {
                continue;
            }

            if ($rowId !== '' && !$this->matchesRowId($data, $csvRowNumber, $rowId)) {
                continue;
            }

            if ($resume && $this->isCompletedInState($stateRows, $csvRowNumber)) {
                $skippedCompleted++;
                continue;
            }

            if ($remainingOffset > 0) {
                $remainingOffset--;
                continue;
            }

            if ($limit === 0 || count($rows) >= $limit) {
                break;
            }

            $rows[] = [
                'csv_row_number' => $csvRowNumber,
                'data' => $data,
            ];
        }

        fclose($handle);

        return [
            'header' => $header,
            'rows' => $rows,
            'scanned_row_count' => $csvRowNumber,
            'skipped_completed_count' => $skippedCompleted,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function readBrandDomains(string $path): array
    {
        $csv = $this->readCsv($path);
        $domains = [];

        foreach ($csv['rows'] as $row) {
            $data = $row['data'];
            $brandName = $data['brand_name'] ?? reset($data);
            $domain = $data['official_domain'] ?? $this->secondValue($data);

            if (!is_string($brandName) || !is_string($domain)) {
                continue;
            }

            $brandName = trim($brandName);
            $domain = trim($domain);

            if ($brandName === '' || $domain === '') {
                continue;
            }

            $domains[$this->normalizeBrand($brandName)] = $domain;
        }

        return $domains;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function secondValue(array $data): ?string
    {
        $values = array_values($data);

        return isset($values[1]) && is_string($values[1]) ? $values[1] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(string $path): array
    {
        if (!is_file($path)) {
            return ['rows' => []];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return ['rows' => []];
        }

        $state = json_decode($contents, true);

        if (!is_array($state)) {
            throw new \RuntimeException(sprintf('State file contains invalid JSON: %s', $path));
        }

        if (!isset($state['rows']) || !is_array($state['rows'])) {
            $state['rows'] = [];
        }

        return $state;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function extractOfficialProductPage(array $row, string $officialProductUrl, string $brandDomain): array
    {
        $result = $this->emptyOfficialExtraction($row);
        $fetch = $this->fetchOfficialUrl($officialProductUrl, $brandDomain);

        if ($fetch['content'] === '') {
            $result['errors'][] = sprintf(
                'official_page_fetch_failed: %s',
                $fetch['error'] !== '' ? $fetch['error'] : 'empty_response',
            );

            return $result;
        }

        $content = $fetch['content'];
        $sourceUrl = $fetch['final_url'];

        foreach ($this->extractJsonLdProducts($content) as $productData) {
            $this->applyStructuredProductData($result, $productData, $sourceUrl, $brandDomain);
        }

        foreach ($this->extractEmbeddedProductData($content) as $productData) {
            $this->applyStructuredProductData($result, $productData, $sourceUrl, $brandDomain);
        }

        if ($result['image_status'] === 'not_found') {
            $imageUrl = $this->metaImageUrl($content, $sourceUrl, $brandDomain);

            if ($imageUrl !== '') {
                $result['official_image_url'] = $imageUrl;
                $result['image_source_url'] = $sourceUrl;
                $result['image_status'] = 'found';
            }
        }

        if ($result['description_status'] === 'not_found') {
            $description = $this->metaDescription($content);

            if ($this->isUsableDescription($description, $sourceUrl)) {
                $result['description'] = $description;
                $result['short_description'] = $this->shortDescription($description);
                $result['description_source_url'] = $sourceUrl;
                $result['description_status'] = 'found';
            }
        }

        if ($result['price_status'] === 'not_found') {
            $price = $this->extractSimpleHtmlPrice($content);
            $this->applyPriceCandidate($result, $price, $sourceUrl);
        }

        if ($result['concentration_status'] === 'not_found') {
            $concentration = $this->extractConcentrationFromText(
                $this->pageTitle($content).' '.$this->metaDescription($content).' '.$this->cleanVisibleText($content),
            );

            if ($concentration !== '') {
                $result['concentration'] = $concentration;
                $result['concentration_status'] = 'found';
            }
        }

        if ($result['notes_status'] === 'not_found') {
            $notes = $this->extractClearlyLabeledNotesFromHtml($content);

            if ($notes !== []) {
                $result['top_notes'] = $notes['top_notes'] ?? '';
                $result['heart_notes'] = $notes['heart_notes'] ?? '';
                $result['base_notes'] = $notes['base_notes'] ?? '';
                $result['notes_status'] = 'found';
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function emptyOfficialExtraction(array $row): array
    {
        $hasConcentration = trim($row['concentration'] ?? '') !== '';
        $hasNotes = trim(($row['top_notes'] ?? '').($row['heart_notes'] ?? '').($row['base_notes'] ?? '')) !== '';

        return [
            'official_image_url' => '',
            'image_source_url' => '',
            'image_status' => 'not_found',
            'list_price_cents' => '',
            'list_price_currency' => '',
            'price_original_amount' => '',
            'price_original_currency' => '',
            'price_source_url' => '',
            'price_status' => 'not_found',
            'short_description' => '',
            'description' => '',
            'description_source_url' => '',
            'description_status' => 'not_found',
            'concentration' => '',
            'concentration_status' => $hasConcentration ? 'existing' : 'not_found',
            'top_notes' => '',
            'heart_notes' => '',
            'base_notes' => '',
            'notes_status' => $hasNotes ? 'existing' : 'not_found',
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $enrichedRow
     * @param array<string, mixed> $extraction
     */
    private function applyOfficialExtraction(array &$enrichedRow, array $extraction): void
    {
        foreach ([
            'official_image_url',
            'image_source_url',
            'image_status',
            'list_price_cents',
            'list_price_currency',
            'price_original_amount',
            'price_original_currency',
            'price_source_url',
            'price_status',
            'short_description',
            'description',
            'description_source_url',
            'description_status',
            'concentration_status',
            'notes_status',
        ] as $column) {
            $value = (string) ($extraction[$column] ?? '');

            if ($value !== '' || str_ends_with($column, '_status')) {
                $enrichedRow[$column] = $value;
            }
        }

        if (($extraction['concentration'] ?? '') !== '' && trim($enrichedRow['concentration'] ?? '') === '') {
            $enrichedRow['concentration'] = (string) $extraction['concentration'];
        }

        foreach (['top_notes', 'heart_notes', 'base_notes'] as $notesColumn) {
            if (($extraction[$notesColumn] ?? '') !== '' && trim($enrichedRow[$notesColumn] ?? '') === '') {
                $enrichedRow[$notesColumn] = (string) $extraction[$notesColumn];
            }
        }
    }

    /**
     * @param array<string, string> $enrichedRow
     */
    private function markOfficialExtractionNotFound(array &$enrichedRow): void
    {
        $enrichedRow['image_status'] = 'not_found';
        $enrichedRow['price_status'] = 'not_found';
        $enrichedRow['description_status'] = 'not_found';
        $enrichedRow['concentration_status'] = trim($enrichedRow['concentration'] ?? '') !== '' ? 'existing' : 'not_found';
        $enrichedRow['notes_status'] = trim(($enrichedRow['top_notes'] ?? '').($enrichedRow['heart_notes'] ?? '').($enrichedRow['base_notes'] ?? '')) !== ''
            ? 'existing'
            : 'not_found';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractJsonLdProducts(string $content): array
    {
        $products = [];

        if (!preg_match_all('/<script\b[^>]*type\s*=\s*(["\'])application\/ld\+json\1[^>]*>(.*?)<\/script>/is', $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $data = json_decode(html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5), true);

            if (is_array($data)) {
                $products = array_merge($products, $this->collectProductStructures($data, true));
            }
        }

        return $products;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractEmbeddedProductData(string $content): array
    {
        $products = [];

        if (!preg_match_all('/<script\b(?![^>]*application\/ld\+json)[^>]*type\s*=\s*(["\'])(?:application\/json|application\/.+\+json)\1[^>]*>(.*?)<\/script>/is', $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $json = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5);

            if ($json === '' || !in_array($json[0], ['{', '['], true)) {
                continue;
            }

            $data = json_decode($json, true);

            if (is_array($data)) {
                $products = array_merge($products, $this->collectProductStructures($data, false));
            }
        }

        return $products;
    }

    /**
     * @param array<string|int, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function collectProductStructures(array $data, bool $requireProductType, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        $products = [];

        if ($this->isAssociativeArray($data) && $this->looksLikeProductData($data, $requireProductType)) {
            /** @var array<string, mixed> $product */
            $product = $data;
            $products[] = $product;
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $products = array_merge($products, $this->collectProductStructures($value, $requireProductType, $depth + 1));
            }
        }

        return $products;
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function looksLikeProductData(array $data, bool $requireProductType): bool
    {
        $keys = array_map(static fn ($key): string => strtolower((string) $key), array_keys($data));

        if ($requireProductType) {
            $type = $data['@type'] ?? $data['type'] ?? '';

            if (is_array($type)) {
                $type = implode(' ', array_map('strval', $type));
            }

            return str_contains(strtolower((string) $type), 'product');
        }

        $productishKeys = ['product', 'productname', 'product_name', 'masterid', 'pid', 'sku', 'offers', 'price', 'images'];
        $hasProductishKey = count(array_intersect($keys, $productishKeys)) > 0;
        $hasContentKey = count(array_intersect($keys, ['name', 'description', 'shortdescription', 'longdescription', 'image'])) > 0;

        return $hasProductishKey && $hasContentKey;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $productData
     */
    private function applyStructuredProductData(
        array &$result,
        array $productData,
        string $sourceUrl,
        string $brandDomain,
    ): void {
        if ($result['image_status'] === 'not_found') {
            $imageUrl = $this->structuredImageUrl($productData, $sourceUrl, $brandDomain);

            if ($imageUrl !== '') {
                $result['official_image_url'] = $imageUrl;
                $result['image_source_url'] = $sourceUrl;
                $result['image_status'] = 'found';
            }
        }

        if ($result['price_status'] !== 'found') {
            $this->applyPriceCandidate($result, $this->structuredPriceCandidate($productData), $sourceUrl);
        }

        if ($result['description_status'] === 'not_found') {
            $description = $this->firstStructuredString($productData, ['description', 'shortDescription', 'longDescription']);

            if ($this->isUsableDescription($description, $sourceUrl)) {
                $result['description'] = $description;
                $result['short_description'] = $this->shortDescription($description);
                $result['description_source_url'] = $sourceUrl;
                $result['description_status'] = 'found';
            }
        }

        if ($result['concentration_status'] === 'not_found') {
            $concentration = $this->extractConcentrationFromText(implode(' ', array_filter([
                $this->firstStructuredString($productData, ['name', 'productName', 'title']),
                $this->firstStructuredString($productData, ['description', 'shortDescription']),
            ])));

            if ($concentration !== '') {
                $result['concentration'] = $concentration;
                $result['concentration_status'] = 'found';
            }
        }

        if ($result['notes_status'] === 'not_found') {
            $notes = $this->structuredNotes($productData);

            if ($notes !== []) {
                $result['top_notes'] = $notes['top_notes'] ?? '';
                $result['heart_notes'] = $notes['heart_notes'] ?? '';
                $result['base_notes'] = $notes['base_notes'] ?? '';
                $result['notes_status'] = 'found';
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     * @param array{amount: string, currency: string}|null $price
     */
    private function applyPriceCandidate(array &$result, ?array $price, string $sourceUrl): void
    {
        if ($price === null || $price['amount'] === '' || $price['currency'] === '') {
            return;
        }

        $currency = strtoupper($price['currency']);

        if ($currency === 'EUR') {
            $result['list_price_cents'] = (string) $this->priceAmountToCents($price['amount']);
            $result['list_price_currency'] = 'EUR';
            $result['price_original_amount'] = $price['amount'];
            $result['price_original_currency'] = 'EUR';
            $result['price_source_url'] = $sourceUrl;
            $result['price_status'] = 'found';

            return;
        }

        if ($result['price_status'] === 'not_found') {
            $result['price_original_amount'] = $price['amount'];
            $result['price_original_currency'] = $currency;
            $result['price_source_url'] = $sourceUrl;
            $result['price_status'] = 'non_eur_found';
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function structuredImageUrl(array $productData, string $sourceUrl, string $brandDomain): string
    {
        foreach (['image', 'images', 'primaryImage', 'thumbnail', 'thumbnailUrl'] as $key) {
            if (!array_key_exists($key, $productData)) {
                continue;
            }

            $url = $this->firstUrlFromMixedValue($productData[$key], $sourceUrl, $brandDomain);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function firstUrlFromMixedValue(mixed $value, string $sourceUrl, string $brandDomain): string
    {
        if (is_string($value)) {
            return $this->normalizeAssetUrlCandidate($value, $sourceUrl, $brandDomain);
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                $url = $this->firstUrlFromMixedValue($nestedValue, $sourceUrl, $brandDomain);

                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $productData
     * @return array{amount: string, currency: string}|null
     */
    private function structuredPriceCandidate(array $productData): ?array
    {
        $prices = $this->collectPriceCandidates($productData);
        $firstNonEur = null;

        foreach ($prices as $price) {
            if ($price['currency'] === 'EUR') {
                return $price;
            }

            $firstNonEur ??= $price;
        }

        return $firstNonEur;
    }

    /**
     * @param mixed $data
     * @return list<array{amount: string, currency: string}>
     */
    private function collectPriceCandidates(mixed $data, int $depth = 0): array
    {
        if ($depth > 8 || !is_array($data)) {
            return [];
        }

        $candidates = [];
        $amountValue = $data['price'] ?? $data['salePrice'] ?? $data['value'] ?? null;
        $currencyValue = $data['priceCurrency'] ?? $data['currency'] ?? $data['currencyCode'] ?? null;

        if ($amountValue !== null) {
            $price = $this->normalizePriceCandidate($amountValue, $currencyValue);

            if ($price !== null) {
                $candidates[] = $price;
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $candidates = array_merge($candidates, $this->collectPriceCandidates($value, $depth + 1));
            }
        }

        return $candidates;
    }

    /**
     * @return array{amount: string, currency: string}|null
     */
    private function normalizePriceCandidate(mixed $amountValue, mixed $currencyValue = null): ?array
    {
        $amountText = is_scalar($amountValue) ? trim((string) $amountValue) : '';
        $currencyText = is_scalar($currencyValue) ? strtoupper(trim((string) $currencyValue)) : '';

        if ($amountText === '') {
            return null;
        }

        if ($currencyText === '') {
            if (str_contains($amountText, '€')) {
                $currencyText = 'EUR';
            } elseif (str_contains($amountText, '$')) {
                $currencyText = 'USD';
            } elseif (str_contains($amountText, '£')) {
                $currencyText = 'GBP';
            }
        }

        if (!in_array($currencyText, ['EUR', 'USD', 'GBP'], true)) {
            return null;
        }

        if (!preg_match('/\d+(?:[.,]\d{2})?/', str_replace(',', '.', $amountText), $match)) {
            return null;
        }

        return [
            'amount' => $match[0],
            'currency' => $currencyText,
        ];
    }

    /**
     * @return array{amount: string, currency: string}|null
     */
    private function extractSimpleHtmlPrice(string $content): ?array
    {
        $text = $this->cleanVisibleText($content);
        $patterns = [
            'EUR' => '/(?:€\s*|EUR\s*)(\d+(?:[.,]\d{2})?)|(\d+(?:[.,]\d{2})?)\s*(?:€|EUR)\b/i',
            'USD' => '/(?:US\$|\$\s*|USD\s*)(\d+(?:[.,]\d{2})?)|(\d+(?:[.,]\d{2})?)\s*(?:USD)\b/i',
            'GBP' => '/(?:£\s*|GBP\s*)(\d+(?:[.,]\d{2})?)|(\d+(?:[.,]\d{2})?)\s*(?:£|GBP)\b/i',
        ];

        foreach ($patterns as $currency => $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $amount = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');

                return $this->normalizePriceCandidate($amount, $currency);
            }
        }

        return null;
    }

    private function priceAmountToCents(string $amount): int
    {
        return (int) round(((float) str_replace(',', '.', $amount)) * 100);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     */
    private function firstStructuredString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $this->firstStringFromMixedValue($data[$key]);

            if ($value !== '') {
                return $this->cleanVisibleText($value);
            }
        }

        return '';
    }

    private function firstStringFromMixedValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                $string = $this->firstStringFromMixedValue($nestedValue);

                if ($string !== '') {
                    return $string;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $productData
     * @return array<string, string>
     */
    private function structuredNotes(array $productData): array
    {
        $notes = [];
        $map = [
            'top_notes' => ['topNotes', 'top_notes', 'headNotes', 'head_notes'],
            'heart_notes' => ['heartNotes', 'heart_notes', 'middleNotes', 'middle_notes'],
            'base_notes' => ['baseNotes', 'base_notes', 'bottomNotes', 'bottom_notes'],
        ];

        foreach ($map as $column => $keys) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $productData)) {
                    continue;
                }

                $value = $this->notesValueToString($productData[$key]);

                if ($value !== '') {
                    $notes[$column] = $value;
                    break;
                }
            }
        }

        return $notes;
    }

    private function notesValueToString(mixed $value): string
    {
        if (is_string($value)) {
            return $this->cleanNotesText($value);
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $nestedValue) {
                $note = $this->notesValueToString($nestedValue);

                if ($note !== '') {
                    $parts[] = $note;
                }
            }

            return implode('; ', array_values(array_unique($parts)));
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function extractClearlyLabeledNotesFromHtml(string $content): array
    {
        $text = $this->cleanVisibleText($content);
        $notes = [];
        $patterns = [
            'top_notes' => '/\btop notes?\b\s*[:\-]?\s*(.{2,240}?)(?=\b(?:heart|middle|base) notes?\b|$)/i',
            'heart_notes' => '/\b(?:heart|middle) notes?\b\s*[:\-]?\s*(.{2,240}?)(?=\b(?:top|base) notes?\b|$)/i',
            'base_notes' => '/\bbase notes?\b\s*[:\-]?\s*(.{2,240}?)(?=\b(?:top|heart|middle) notes?\b|$)/i',
        ];

        foreach ($patterns as $column => $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $value = $this->cleanNotesText($match[1]);

                if ($value !== '') {
                    $notes[$column] = $value;
                }
            }
        }

        return $notes;
    }

    private function cleanNotesText(string $text): string
    {
        $text = preg_replace('/\b(?:discover|shop|add to bag|ingredients|description)\b.*$/i', '', $text) ?? $text;
        $text = preg_replace('/\s+(?:,|;)\s+/', '; ', $text) ?? $text;
        $text = trim($this->cleanVisibleText($text), " \t\n\r\0\x0B:;-");

        return $text;
    }

    private function metaImageUrl(string $content, string $sourceUrl, string $brandDomain): string
    {
        foreach (['og:image', 'twitter:image', 'twitter:image:src'] as $property) {
            $value = $this->metaContent($content, $property);

            if ($value === '') {
                continue;
            }

            $url = $this->normalizeAssetUrlCandidate($value, $sourceUrl, $brandDomain);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function metaContent(string $content, string $name): string
    {
        $namePattern = preg_quote($name, '/');

        if (preg_match('/<meta\b[^>]*(?:property|name)\s*=\s*(["\'])'.$namePattern.'\1[^>]*content\s*=\s*(["\'])(.*?)\2/is', $content, $match)) {
            return html_entity_decode(trim($match[3]), ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/<meta\b[^>]*content\s*=\s*(["\'])(.*?)\1[^>]*(?:property|name)\s*=\s*(["\'])'.$namePattern.'\3/is', $content, $match)) {
            return html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5);
        }

        return '';
    }

    private function isUsableDescription(string $description, string $sourceUrl): bool
    {
        $description = trim($description);

        if (strlen($description) < 30 || strlen($description) > 2000) {
            return false;
        }

        if (preg_match('/\b(page unavailable|access denied|403|captcha)\b/i', $description)) {
            return false;
        }

        $path = strtolower((string) (parse_url($sourceUrl, PHP_URL_PATH) ?: ''));

        return str_contains($path, '/en_') || preg_match('/\b(the|and|with|fragrance|scent|notes?)\b/i', $description) === 1;
    }

    private function shortDescription(string $description): string
    {
        $description = trim($description);

        if (strlen($description) <= 220) {
            return $description;
        }

        return rtrim(substr($description, 0, 217)).'...';
    }

    private function extractConcentrationFromText(string $text): string
    {
        $normalized = $this->normalizeSearchText($text);
        $map = [
            'extrait de parfum' => 'EXTRAIT',
            'eau de parfum' => 'EDP',
            'eau de toilette' => 'EDT',
            'eau de cologne' => 'EDC',
            'parfum' => 'PARFUM',
            'extrait' => 'EXTRAIT',
        ];

        foreach ($map as $phrase => $code) {
            if ($this->containsNormalizedPhrase($normalized, $phrase)) {
                return $code;
            }
        }

        return '';
    }

    /**
     * @param array<string|int, mixed> $array
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param array<string, string> $row
     * @return array{url: string, errors: list<string>}
     */
    private function discoverOfficialProductUrl(array $row, string $brandDomain): array
    {
        if (trim($brandDomain) === '') {
            return [
                'url' => '',
                'errors' => ['missing_brand_domain'],
            ];
        }

        $officialSourceUrl = $this->officialSourceUrl($row, $brandDomain);

        if ($officialSourceUrl !== '') {
            return [
                'url' => $officialSourceUrl,
                'errors' => [],
            ];
        }

        $errors = [];
        $candidateFailures = [];

        foreach ($this->obviousOfficialProductUrls($row, $brandDomain) as $candidateUrl) {
            $result = $this->validateOfficialProductUrl($candidateUrl, $row, $brandDomain);

            if ($result['url'] !== '') {
                return [
                    'url' => $result['url'],
                    'errors' => [],
                ];
            }

            foreach ($result['errors'] as $candidateError) {
                $candidateFailures[] = $candidateError;
            }
        }

        $knownOfficialUrl = $this->knownOfficialProductUrl($row, $brandDomain);

        if ($knownOfficialUrl !== '') {
            return [
                'url' => $knownOfficialUrl,
                'errors' => [],
            ];
        }

        $index = $this->officialProductUrlIndex($brandDomain);
        $bestUrl = $this->bestOfficialProductUrlFromIndex($row, $brandDomain, $index['records']);

        if ($bestUrl !== '') {
            return [
                'url' => $bestUrl,
                'errors' => [],
            ];
        }

        if ($candidateFailures !== []) {
            $errors[] = $this->summarizeCandidateFailures(count($this->obviousOfficialProductUrls($row, $brandDomain)), $candidateFailures);
        }

        if ($index['errors'] !== []) {
            $errors[] = 'official_sitemap_fetch_failed: '.implode(' | ', array_slice($index['errors'], 0, 3));
        }

        $errors[] = 'official_product_url_not_found';

        return [
            'url' => '',
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, string> $row
     */
    private function officialSourceUrl(array $row, string $brandDomain): string
    {
        foreach (['product_url', 'source_url'] as $column) {
            $url = trim($row[$column] ?? '');

            if ($url === '') {
                continue;
            }

            $url = $this->stripUrlNoise($url);

            if ($this->urlIsOnDomain($url, $brandDomain) && $this->looksLikeOfficialProductUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $row
     * @return list<string>
     */
    private function obviousOfficialProductUrls(array $row, string $brandDomain): array
    {
        $domain = $this->canonicalDomain($brandDomain);
        $profile = $this->productMatchProfile($row);
        $locales = $domain === 'dior.com' ? ['en_us', 'en_int', 'en_gb'] : ['en_us'];
        $slugs = $this->officialProductSlugVariants($row, $profile);
        $commonPaths = [
            '/products/%s',
            '/product/%s',
            '/fragrance/%s',
            '/fragrances/%s',
            '/perfume/%s',
            '/perfumes/%s',
            '/collections/%s',
        ];
        $hosts = array_values(array_unique([
            'https://www.'.$domain,
            'https://'.$domain,
        ]));
        $urls = [];

        foreach ($hosts as $host) {
            foreach ($slugs as $slug) {
                foreach ($commonPaths as $pathPattern) {
                    $urls[] = $host.sprintf($pathPattern, $slug);
                }
            }
        }

        foreach ($locales as $locale) {
            foreach ($slugs as $slug) {
                $urls[] = sprintf('https://www.%s/%s/beauty/products/%s.html', $domain, $locale, $slug);
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param array<string, string> $row
     * @param array{
     *     clean_name: string,
     *     core_name: string,
     *     query_text: string,
     *     concentration_phrases: list<string>,
     *     tokens: list<string>
     * } $profile
     * @return list<string>
     */
    private function officialProductSlugVariants(array $row, array $profile): array
    {
        $rawName = $row['perfume_name'] ?? '';
        $brandName = $row['brand_name'] ?? '';
        $cleanName = $profile['clean_name'];
        $coreName = $profile['core_name'];
        $bases = [
            $this->cleanProductName($rawName, $brandName),
            $cleanName,
            $coreName,
            $this->removeConcentrationWords($cleanName),
            $this->removeConcentrationWords($coreName),
        ];
        $slugs = [];

        foreach ($bases as $base) {
            $base = $this->normalizeSearchText($base);

            if ($base === '') {
                continue;
            }

            $slugs[] = $this->slugify($base);
            $slugs[] = $this->slugify(str_replace([' eau de toilette', ' eau de parfum', ' parfum'], '', ' '.$base));

            foreach ($this->concentrationSlugSuffixes($row, $base) as $suffix) {
                $slugs[] = $this->slugify($this->removeConcentrationWords($base).' '.$suffix);
            }
        }

        return array_values(array_filter(array_unique($slugs)));
    }

    /**
     * @param array<string, string> $row
     * @return list<string>
     */
    private function concentrationSlugSuffixes(array $row, string $base): array
    {
        $phrases = $this->concentrationPhrases($base, $row['concentration'] ?? '');
        $suffixes = [];

        foreach ($phrases as $phrase) {
            $normalized = $this->normalizeSearchText($phrase);

            if ($normalized !== '') {
                $suffixes[] = $normalized;
            }
        }

        return array_values(array_unique($suffixes));
    }

    private function removeConcentrationWords(string $text): string
    {
        foreach (['extrait de parfum', 'eau de toilette', 'eau de parfum', 'eau de cologne', 'extrait', 'parfum', 'cologne', 'toilette'] as $phrase) {
            $text = preg_replace('/\b'.preg_quote($phrase, '/').'\b/i', ' ', $text) ?? $text;
        }

        return $this->normalizeSearchText($text);
    }

    /**
     * @param list<string> $candidateFailures
     */
    private function summarizeCandidateFailures(int $candidateCount, array $candidateFailures): string
    {
        $counts = [];

        foreach ($candidateFailures as $failure) {
            $failure = $this->normalizeCandidateFailureReason($failure);

            if ($failure === '') {
                continue;
            }

            $counts[$failure] = ($counts[$failure] ?? 0) + 1;
        }

        arsort($counts);
        $summaryParts = [];

        foreach (array_slice($counts, 0, 3, true) as $failure => $count) {
            $summaryParts[] = sprintf('%s=%d', $failure, $count);
        }

        return sprintf(
            'candidate_url_attempts_failed: %d tried%s',
            $candidateCount,
            $summaryParts !== [] ? ' ('.implode(', ', $summaryParts).')' : '',
        );
    }

    private function normalizeCandidateFailureReason(string $failure): string
    {
        $failure = trim($failure);

        if ($failure === '') {
            return '';
        }

        if (preg_match('/\bhttp_(\d{3})\b/', $failure, $match)) {
            return 'http_'.$match[1];
        }

        if (str_contains($failure, 'Could not resolve host')) {
            return 'dns_failed';
        }

        if (str_contains($failure, 'Could not connect to server') || str_contains($failure, 'Failed to connect')) {
            return 'connection_failed';
        }

        if (str_contains($failure, 'Operation timed out') || str_contains($failure, 'timed out')) {
            return 'timeout';
        }

        return $failure;
    }

    /**
     * @param array<string, string> $row
     */
    private function knownOfficialProductUrl(array $row, string $brandDomain): string
    {
        if ($this->canonicalDomain($brandDomain) !== 'dior.com') {
            return '';
        }

        $profile = $this->productMatchProfile($row);
        $diorUrls = [
            'dior homme eau de toilette' => 'https://www.dior.com/en_us/beauty/products/dior-homme-Y0996157.html',
            'dior homme sport' => 'https://www.dior.com/en_us/beauty/products/dior-homme-sport-Y0996476.html',
            'eau sauvage parfum' => 'https://www.dior.com/en_int/beauty/products/eau-sauvage-Y0896220.html',
        ];
        $url = $diorUrls[$profile['clean_name']] ?? '';

        if ($url === '' || !$this->urlIsOnDomain($url, $brandDomain) || !$this->looksLikeOfficialProductUrl($url)) {
            return '';
        }

        return $url;
    }

    /**
     * @return array{records: list<array{url: string, title: string, source: string}>, errors: list<string>}
     */
    private function officialProductUrlIndex(string $brandDomain): array
    {
        $domain = $this->canonicalDomain($brandDomain);

        if (isset($this->officialUrlIndexes[$domain])) {
            return $this->officialUrlIndexes[$domain];
        }

        $recordsByUrl = [];
        $errors = [];
        $nestedSitemaps = [];

        foreach ($this->officialSitemapSources($domain) as $sourceUrl) {
            $fetch = $this->fetchOfficialUrl($sourceUrl, $domain);

            if ($fetch['content'] === '') {
                if ($fetch['error'] !== '') {
                    $errors[] = sprintf('%s (%s)', $sourceUrl, $fetch['error']);
                }

                continue;
            }

            foreach ($this->extractOfficialProductUrlRecords($fetch['content'], $fetch['final_url'], $domain) as $record) {
                $recordsByUrl[$record['url']] = $record;
            }

            foreach ($this->extractNestedOfficialSitemaps($fetch['content'], $fetch['final_url'], $domain) as $nestedUrl) {
                $nestedSitemaps[$nestedUrl] = $nestedUrl;
            }
        }

        foreach (array_slice(array_values($nestedSitemaps), 0, 8) as $nestedUrl) {
            $fetch = $this->fetchOfficialUrl($nestedUrl, $domain);

            if ($fetch['content'] === '') {
                continue;
            }

            foreach ($this->extractOfficialProductUrlRecords($fetch['content'], $fetch['final_url'], $domain) as $record) {
                $recordsByUrl[$record['url']] = $record;
            }
        }

        $this->officialUrlIndexes[$domain] = [
            'records' => array_values($recordsByUrl),
            'errors' => $errors,
        ];

        return $this->officialUrlIndexes[$domain];
    }

    /**
     * @return list<string>
     */
    private function officialSitemapSources(string $domain): array
    {
        if ($domain === 'dior.com') {
            return [
                'https://www.dior.com/en_us/beauty/sitemap.xml',
                'https://www.dior.com/en_us/beauty/all-sitemap',
                'https://www.dior.com/en_int/beauty/sitemap.xml',
                'https://www.dior.com/en_int/beauty/all-sitemap',
                'https://www.dior.com/sitemap.xml',
            ];
        }

        return [
            sprintf('https://www.%s/sitemap.xml', $domain),
            sprintf('https://%s/sitemap.xml', $domain),
        ];
    }

    /**
     * @return array{content: string, final_url: string, status_code: int, error: string}
     */
    private function fetchOfficialUrl(string $url, string $brandDomain): array
    {
        if (!$this->urlIsOnDomain($url, $brandDomain)) {
            return [
                'content' => '',
                'final_url' => $url,
                'status_code' => 0,
                'error' => 'off_domain_url_rejected',
            ];
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                ],
                'max_duration' => 12,
                'max_redirects' => 5,
                'timeout' => 8,
            ]);
            $statusCode = $response->getStatusCode();
            $finalUrl = $this->stripUrlNoise((string) ($response->getInfo('url') ?: $url));

            if (!$this->urlIsOnDomain($finalUrl, $brandDomain)) {
                return [
                    'content' => '',
                    'final_url' => $finalUrl,
                    'status_code' => $statusCode,
                    'error' => 'redirected_off_domain',
                ];
            }

            if ($statusCode < 200 || $statusCode >= 400) {
                return [
                    'content' => '',
                    'final_url' => $finalUrl,
                    'status_code' => $statusCode,
                    'error' => sprintf('http_%d', $statusCode),
                ];
            }

            return [
                'content' => $response->getContent(false),
                'final_url' => $finalUrl,
                'status_code' => $statusCode,
                'error' => '',
            ];
        } catch (\Throwable $exception) {
            return [
                'content' => '',
                'final_url' => $url,
                'status_code' => 0,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<array{url: string, title: string, source: string}>
     */
    private function extractOfficialProductUrlRecords(string $content, string $sourceUrl, string $brandDomain): array
    {
        $recordsByUrl = [];
        $content = str_replace('\/', '/', $content);

        if (preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = $this->normalizeUrlCandidate($match[2], $sourceUrl, $brandDomain);

                if ($url === '' || !$this->looksLikeOfficialProductUrl($url)) {
                    continue;
                }

                $recordsByUrl[$url] = [
                    'url' => $url,
                    'title' => $this->cleanVisibleText($match[3]),
                    'source' => $sourceUrl,
                ];
            }
        }

        if (preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $content, $matches)) {
            foreach ($matches[1] as $rawUrl) {
                $url = $this->normalizeUrlCandidate($rawUrl, $sourceUrl, $brandDomain);

                if ($url === '' || !$this->looksLikeOfficialProductUrl($url)) {
                    continue;
                }

                $recordsByUrl[$url] = [
                    'url' => $url,
                    'title' => $recordsByUrl[$url]['title'] ?? '',
                    'source' => $sourceUrl,
                ];
            }
        }

        $domainPattern = preg_quote($this->canonicalDomain($brandDomain), '/');
        preg_match_all('/https?:\/\/(?:www\.)?'.$domainPattern.'\/[^"\'<>\s)]+/i', $content, $absoluteMatches);

        foreach ($absoluteMatches[0] as $rawUrl) {
            $url = $this->normalizeUrlCandidate($rawUrl, $sourceUrl, $brandDomain);

            if ($url === '' || !$this->looksLikeOfficialProductUrl($url)) {
                continue;
            }

            $recordsByUrl[$url] = [
                'url' => $url,
                'title' => $recordsByUrl[$url]['title'] ?? '',
                'source' => $sourceUrl,
            ];
        }

        preg_match_all('/\/(?:[a-z]{2}_(?:[a-z]{2}|int)\/beauty\/products|products|product|fragrance|fragrances|perfume|perfumes|collections)\/[^"\'<>\s)]+/i', $content, $relativeMatches);

        foreach ($relativeMatches[0] as $rawPath) {
            $url = $this->normalizeUrlCandidate($rawPath, $sourceUrl, $brandDomain);

            if ($url === '' || !$this->looksLikeOfficialProductUrl($url)) {
                continue;
            }

            $recordsByUrl[$url] = [
                'url' => $url,
                'title' => $recordsByUrl[$url]['title'] ?? '',
                'source' => $sourceUrl,
            ];
        }

        return array_values($recordsByUrl);
    }

    /**
     * @return list<string>
     */
    private function extractNestedOfficialSitemaps(string $content, string $sourceUrl, string $brandDomain): array
    {
        $urls = [];

        if (!preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $content, $matches)) {
            return [];
        }

        foreach ($matches[1] as $rawUrl) {
            $url = $this->normalizeUrlCandidate($rawUrl, $sourceUrl, $brandDomain);

            if ($url === '' || !$this->urlIsOnDomain($url, $brandDomain)) {
                continue;
            }

            $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));

            if (!str_contains($path, 'sitemap') || (!str_contains($path, 'beauty') && !str_contains($path, 'product'))) {
                continue;
            }

            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param list<array{url: string, title: string, source: string}> $records
     */
    private function bestOfficialProductUrlFromIndex(array $row, string $brandDomain, array $records): string
    {
        $ranked = [];

        foreach ($records as $record) {
            $score = $this->scoreOfficialProductUrlCandidate($row, $record, '');

            if ($score === null) {
                continue;
            }

            $ranked[] = [
                'score' => $score,
                'record' => $record,
            ];
        }

        usort(
            $ranked,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score'],
        );

        foreach (array_slice($ranked, 0, 8) as $rankedCandidate) {
            $result = $this->validateOfficialProductUrl($rankedCandidate['record']['url'], $row, $brandDomain, $rankedCandidate['record']);

            if ($result['url'] !== '') {
                return $result['url'];
            }
        }

        return '';
    }

    /**
     * @param array<string, string>|null $record
     * @return array{url: string, errors: list<string>}
     */
    private function validateOfficialProductUrl(string $url, array $row, string $brandDomain, ?array $record = null): array
    {
        if (!$this->urlIsOnDomain($url, $brandDomain) || !$this->looksLikeOfficialProductUrl($url)) {
            return [
                'url' => '',
                'errors' => ['candidate_url_rejected'],
            ];
        }

        $fetch = $this->fetchOfficialUrl($url, $brandDomain);

        if ($fetch['content'] === '') {
            return [
                'url' => '',
                'errors' => [$fetch['error'] !== '' ? $fetch['error'] : 'candidate_fetch_failed'],
            ];
        }

        $candidateRecord = $record ?? [
            'url' => $fetch['final_url'],
            'title' => '',
            'source' => $url,
        ];
        $score = $this->scoreOfficialProductUrlCandidate($row, $candidateRecord, $fetch['content']);

        if ($score === null || $score < 60) {
            return [
                'url' => '',
                'errors' => ['candidate_did_not_match_product'],
            ];
        }

        return [
            'url' => $this->stripUrlNoise($fetch['final_url']),
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $row
     * @param array{url: string, title: string, source: string} $record
     */
    private function scoreOfficialProductUrlCandidate(array $row, array $record, string $pageContent): ?int
    {
        $profile = $this->productMatchProfile($row);
        $identityText = $this->candidateIdentityText($record, $pageContent);
        $identityTextWithoutPageBody = $this->candidateIdentityText($record, '');
        $coreName = $profile['core_name'];
        $score = 0;

        if ($coreName !== '') {
            if (!$this->containsNormalizedPhrase($identityText, $coreName)) {
                return null;
            }

            $score += 60;
        }

        if ($this->hasConflictingEditionToken($identityTextWithoutPageBody, $profile['query_text'])) {
            return null;
        }

        $matchedTokens = 0;

        foreach ($profile['tokens'] as $token) {
            if ($this->containsNormalizedPhrase($identityText, $token)) {
                $matchedTokens++;
            }
        }

        $score += min(30, $matchedTokens * 6);

        if ($profile['concentration_phrases'] !== []) {
            $matchedConcentration = false;

            foreach ($profile['concentration_phrases'] as $phrase) {
                if ($this->containsNormalizedPhrase($identityText, $phrase)) {
                    $matchedConcentration = true;
                    break;
                }
            }

            if ($matchedConcentration) {
                $score += 25;
            }
        }

        if ($this->containsNormalizedPhrase($identityTextWithoutPageBody, $profile['clean_name'])) {
            $score += 20;
        }

        return $score;
    }

    /**
     * @param array<string, string> $row
     * @return array{
     *     clean_name: string,
     *     core_name: string,
     *     query_text: string,
     *     concentration_phrases: list<string>,
     *     tokens: list<string>
     * }
     */
    private function productMatchProfile(array $row): array
    {
        $cleanName = $this->cleanProductName($row['perfume_name'] ?? '', $row['brand_name'] ?? '');
        $queryText = $this->normalizeSearchText($cleanName.' '.($row['concentration'] ?? ''));
        $concentrationPhrases = $this->concentrationPhrases($cleanName, $row['concentration'] ?? '');
        $coreName = $cleanName;

        foreach (['eau de toilette', 'eau de parfum', 'eau de cologne', 'extrait de parfum', 'extrait', 'parfum', 'cologne', 'toilette'] as $phrase) {
            $coreName = preg_replace('/\b'.preg_quote($phrase, '/').'\b/i', ' ', $coreName) ?? $coreName;
        }

        $coreName = $this->normalizeSearchText($coreName);

        return [
            'clean_name' => $this->normalizeSearchText($cleanName),
            'core_name' => $coreName,
            'query_text' => $queryText,
            'concentration_phrases' => $concentrationPhrases,
            'tokens' => $this->distinctiveTokens($coreName.' '.$queryText),
        ];
    }

    private function cleanProductName(string $name, string $brandName): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5);
        $brandName = trim($brandName);

        if ($brandName !== '') {
            $name = preg_replace('/\s+'.preg_quote($brandName, '/').'\s+(?:18|19|20)\d{2}\b/i', ' ', $name) ?? $name;
            $name = preg_replace('/\b'.preg_quote($brandName, '/').'\b/i', ' ', $name) ?? $name;
        }

        $name = preg_replace('/\b(?:18|19|20)\d{2}\b/', ' ', $name) ?? $name;

        return $this->normalizeSearchText($name);
    }

    /**
     * @return list<string>
     */
    private function concentrationPhrases(string $cleanName, string $concentration): array
    {
        $phrases = [];
        $normalizedName = $this->normalizeSearchText($cleanName);
        $normalizedConcentration = $this->normalizeSearchText($concentration);
        $map = [
            'edt' => 'eau de toilette',
            'edp' => 'eau de parfum',
            'edc' => 'eau de cologne',
            'parfum' => 'parfum',
            'extrait' => 'extrait',
        ];

        if (isset($map[$normalizedConcentration])) {
            $phrases[] = $map[$normalizedConcentration];
        }

        foreach (['eau de toilette', 'eau de parfum', 'eau de cologne', 'extrait de parfum', 'extrait', 'parfum', 'cologne'] as $phrase) {
            if ($this->containsNormalizedPhrase($normalizedName, $phrase)) {
                $phrases[] = $phrase;
            }
        }

        return array_values(array_unique($phrases));
    }

    /**
     * @return list<string>
     */
    private function distinctiveTokens(string $text): array
    {
        $tokens = preg_split('/\s+/', $this->normalizeSearchText($text)) ?: [];
        $stopWords = array_flip(['and', 'the', 'for', 'with', 'from', 'notes', 'note', 'fragrance', 'spray', 'refillable']);
        $distinctive = [];

        foreach ($tokens as $token) {
            if (strlen($token) < 3 || isset($stopWords[$token])) {
                continue;
            }

            $distinctive[$token] = $token;
        }

        return array_values($distinctive);
    }

    /**
     * @param array{url: string, title: string, source: string} $record
     */
    private function candidateIdentityText(array $record, string $pageContent): string
    {
        $parts = [
            $record['title'],
            $this->urlSlugText($record['url']),
        ];

        if ($pageContent !== '') {
            $parts[] = $this->pageTitle($pageContent);
            $parts[] = $this->metaDescription($pageContent);
            $parts[] = substr($this->cleanVisibleText($pageContent), 0, 12000);
        }

        return $this->normalizeSearchText(implode(' ', $parts));
    }

    private function pageTitle(string $content): string
    {
        if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $content, $match)) {
            return '';
        }

        return $this->cleanVisibleText($match[1]);
    }

    private function metaDescription(string $content): string
    {
        if (preg_match('/<meta\b[^>]*name\s*=\s*(["\'])description\1[^>]*content\s*=\s*(["\'])(.*?)\2/is', $content, $match)) {
            return $this->cleanVisibleText($match[3]);
        }

        if (preg_match('/<meta\b[^>]*content\s*=\s*(["\'])(.*?)\1[^>]*name\s*=\s*(["\'])description\3/is', $content, $match)) {
            return $this->cleanVisibleText($match[2]);
        }

        return '';
    }

    private function hasConflictingEditionToken(string $candidateText, string $queryText): bool
    {
        foreach (['sport', 'intense', 'elixir', 'cologne', 'parfum'] as $token) {
            if ($this->containsNormalizedPhrase($candidateText, $token) && !$this->containsNormalizedPhrase($queryText, $token)) {
                return true;
            }
        }

        return false;
    }

    private function containsNormalizedPhrase(string $haystack, string $needle): bool
    {
        $haystack = $this->normalizeSearchText($haystack);
        $needle = $this->normalizeSearchText($needle);

        if ($needle === '') {
            return true;
        }

        return str_contains(' '.$haystack.' ', ' '.$needle.' ');
    }

    private function normalizeSearchText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $asciiText = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if (is_string($asciiText) && $asciiText !== '') {
            $text = $asciiText;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function slugify(string $text): string
    {
        return str_replace(' ', '-', $this->normalizeSearchText($text));
    }

    private function urlSlugText(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $basename = basename($path, '.html');
        $basename = preg_replace('/-[A-Z0-9]{6,}$/i', '', $basename) ?? $basename;

        return str_replace('-', ' ', $basename);
    }

    private function cleanVisibleText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    private function normalizeUrlCandidate(string $rawUrl, string $baseUrl, string $brandDomain): string
    {
        $rawUrl = trim(html_entity_decode(str_replace('\/', '/', $rawUrl), ENT_QUOTES | ENT_HTML5));
        $rawUrl = trim($rawUrl, " \t\n\r\0\x0B'\"");

        if ($rawUrl === '' || str_starts_with($rawUrl, '#') || str_starts_with(strtolower($rawUrl), 'javascript:')) {
            return '';
        }

        if (str_starts_with($rawUrl, '//')) {
            $rawUrl = 'https:'.$rawUrl;
        } elseif (str_starts_with($rawUrl, '/')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'www.'.$this->canonicalDomain($brandDomain);
            $rawUrl = $scheme.'://'.$host.$rawUrl;
        }

        $url = $this->stripUrlNoise($rawUrl);

        if (!$this->urlIsOnDomain($url, $brandDomain)) {
            return '';
        }

        return $url;
    }

    private function normalizeAssetUrlCandidate(string $rawUrl, string $baseUrl, string $brandDomain): string
    {
        $rawUrl = trim(html_entity_decode(str_replace('\/', '/', $rawUrl), ENT_QUOTES | ENT_HTML5));
        $rawUrl = trim($rawUrl, " \t\n\r\0\x0B'\"");

        if ($rawUrl === '' || str_starts_with($rawUrl, 'data:') || str_starts_with(strtolower($rawUrl), 'javascript:')) {
            return '';
        }

        if (str_starts_with($rawUrl, '//')) {
            $rawUrl = 'https:'.$rawUrl;
        } elseif (str_starts_with($rawUrl, '/')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'www.'.$this->canonicalDomain($brandDomain);
            $rawUrl = $scheme.'://'.$host.$rawUrl;
        }

        $parts = parse_url($rawUrl);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $url = strtolower($parts['scheme']).'://'.$parts['host'].($parts['path'] ?? '');

        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        if (!$this->urlIsOnDomain($url, $brandDomain)) {
            return '';
        }

        return $url;
    }

    private function stripUrlNoise(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/[),.;]+$/', '', $url) ?? $url;
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';

        return strtolower($parts['scheme']).'://'.$parts['host'].$path;
    }

    private function looksLikeOfficialProductUrl(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));

        if (str_ends_with($path, '.pdf')) {
            return false;
        }

        foreach ([
            '/beauty/products/',
            '/products/',
            '/product/',
            '/fragrance/',
            '/fragrances/',
            '/perfume/',
            '/perfumes/',
            '/collections/',
        ] as $productPath) {
            $position = strpos($path, $productPath);

            if ($position === false) {
                continue;
            }

            if (trim(substr($path, $position + strlen($productPath)), '/') !== '') {
                return true;
            }
        }

        return false;
    }

    private function urlIsOnDomain(string $url, string $brandDomain): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = $this->canonicalDomain($host);
        $domain = $this->canonicalDomain($brandDomain);

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    private function canonicalDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);
        $domain = is_string($host) ? $host : $domain;

        return preg_replace('/^www\./', '', $domain) ?? $domain;
    }

    /**
     * @param array<string, mixed> $stateRows
     */
    private function isCompletedInState(array $stateRows, int $csvRowNumber): bool
    {
        $rowState = $stateRows[(string) $csvRowNumber] ?? null;

        if (!is_array($rowState)) {
            return false;
        }

        $status = strtolower(trim((string) ($rowState['enrichment_status'] ?? $rowState['status'] ?? '')));

        if (in_array($status, ['complete', 'completed', 'done', 'success'], true)) {
            return true;
        }

        if (!in_array($status, ['official_url_found', 'official_url_not_found'], true)) {
            return false;
        }

        foreach (['image_status', 'price_status', 'description_status', 'concentration_status', 'notes_status'] as $statusField) {
            $fieldStatus = strtolower(trim((string) ($rowState[$statusField] ?? '')));

            if ($fieldStatus === '' || $fieldStatus === 'pending') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $row
     */
    private function matchesRowId(array $row, int $csvRowNumber, string $rowId): bool
    {
        $candidates = [
            (string) $csvRowNumber,
            $row['source_number'] ?? '',
            $row['source_id'] ?? '',
            $row['source_row_number'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (trim((string) $candidate) === $rowId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $originalHeader
     * @return list<string>
     */
    private function buildOutputHeader(array $originalHeader): array
    {
        $header = $originalHeader;

        foreach (self::ADDED_COLUMNS as $column) {
            if (!in_array($column, $header, true)) {
                $header[] = $column;
            }
        }

        return $header;
    }

    /**
     * @param list<string> $outputHeader
     * @param list<string> $originalHeader
     * @param array<string, string> $row
     * @return array<string, string>
     */
    private function buildEnrichedRow(
        array $outputHeader,
        array $originalHeader,
        array $row,
        int $csvRowNumber,
        string $sourceNumber,
        string $brandDomain,
    ): array {
        $enrichedRow = array_fill_keys($outputHeader, '');

        foreach ($originalHeader as $column) {
            $enrichedRow[$column] = $row[$column] ?? '';
        }

        $enrichedRow['source_row_number'] = (string) $csvRowNumber;
        $enrichedRow['source_number'] = $sourceNumber;
        $enrichedRow['brand_domain'] = $brandDomain;
        $enrichedRow['official_product_url'] = '';
        $enrichedRow['official_image_url'] = '';
        $enrichedRow['image_source_url'] = '';
        $enrichedRow['image_status'] = 'pending';
        $enrichedRow['list_price_cents'] = '';
        $enrichedRow['list_price_currency'] = '';
        $enrichedRow['price_original_amount'] = '';
        $enrichedRow['price_original_currency'] = '';
        $enrichedRow['price_source_url'] = '';
        $enrichedRow['price_status'] = 'pending';
        $enrichedRow['description_source_url'] = '';
        $enrichedRow['description_status'] = 'pending';
        $enrichedRow['concentration_status'] = '';
        $enrichedRow['notes_status'] = '';
        $enrichedRow['enrichment_status'] = 'pending';
        $enrichedRow['enrichment_errors'] = '';
        $enrichedRow['fetched_at'] = '';

        return $enrichedRow;
    }

    /**
     * @param array<string, string> $row
     */
    private function sourceNumber(array $row): string
    {
        foreach (['source_number', 'source_id', 'source_row_number'] as $column) {
            $value = trim($row[$column] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $domains
     */
    private function brandDomain(array $domains, string $brandName): string
    {
        return $domains[$this->normalizeBrand($brandName)] ?? '';
    }

    private function normalizeBrand(string $brandName): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($brandName))) ?? '';
    }

    /**
     * @param array<string, string> $enrichedRow
     * @param array<string, string> $originalRow
     * @return array<string, string>
     */
    private function buildReportRow(array $enrichedRow, array $originalRow): array
    {
        return [
            'source_row_number' => $enrichedRow['source_row_number'],
            'source_number' => $enrichedRow['source_number'],
            'brand_name' => $originalRow['brand_name'] ?? '',
            'perfume_name' => $originalRow['perfume_name'] ?? '',
            'brand_domain' => $enrichedRow['brand_domain'],
            'official_product_url' => $enrichedRow['official_product_url'],
            'official_image_url' => $enrichedRow['official_image_url'],
            'enrichment_status' => $enrichedRow['enrichment_status'],
            'image_status' => $enrichedRow['image_status'],
            'list_price_cents' => $enrichedRow['list_price_cents'],
            'list_price_currency' => $enrichedRow['list_price_currency'],
            'price_original_amount' => $enrichedRow['price_original_amount'],
            'price_original_currency' => $enrichedRow['price_original_currency'],
            'price_status' => $enrichedRow['price_status'],
            'description_status' => $enrichedRow['description_status'],
            'concentration_status' => $enrichedRow['concentration_status'],
            'notes_status' => $enrichedRow['notes_status'],
            'enrichment_errors' => $enrichedRow['enrichment_errors'],
        ];
    }

    /**
     * @return list<string>
     */
    private function reportHeader(): array
    {
        return [
            'source_row_number',
            'source_number',
            'brand_name',
            'perfume_name',
            'brand_domain',
            'official_product_url',
            'official_image_url',
            'enrichment_status',
            'image_status',
            'list_price_cents',
            'list_price_currency',
            'price_original_amount',
            'price_original_currency',
            'price_status',
            'description_status',
            'concentration_status',
            'notes_status',
            'enrichment_errors',
        ];
    }

    /**
     * @param list<string> $header
     * @param list<array<string, string>> $rows
     */
    private function writeCsv(string $path, array $header, array $rows): void
    {
        $this->ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not open CSV for writing: %s', $path));
        }

        fputcsv($handle, $header, ',', '"', '');

        foreach ($rows as $row) {
            $values = [];

            foreach ($header as $column) {
                $values[] = $row[$column] ?? '';
            }

            fputcsv($handle, $values, ',', '"', '');
        }

        fclose($handle);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $this->ensureDirectoryExists(dirname($path));

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new \RuntimeException(sprintf('Could not encode JSON state: %s', $path));
        }

        if (file_put_contents($path, $json."\n") === false) {
            throw new \RuntimeException(sprintf('Could not write JSON state: %s', $path));
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create directory: %s', $directory));
        }
    }
}
