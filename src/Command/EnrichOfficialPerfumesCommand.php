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

    public function __construct(
        private readonly KernelInterface $kernel,
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

            $enrichedRows[] = $enrichedRow;
            $reportRows[] = $this->buildReportRow($enrichedRow, $row);

            $stateRows[(string) $csvRowNumber] = [
                'source_row_number' => $csvRowNumber,
                'source_number' => $sourceNumber,
                'brand_name' => $row['brand_name'] ?? '',
                'perfume_name' => $row['perfume_name'] ?? '',
                'brand_domain' => $brandDomain,
                'enrichment_status' => $enrichedRow['enrichment_status'],
                'image_status' => $enrichedRow['image_status'],
                'price_status' => $enrichedRow['price_status'],
                'description_status' => $enrichedRow['description_status'],
                'updated_at' => $now,
                'errors' => [],
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
     * @param array<string, mixed> $stateRows
     */
    private function isCompletedInState(array $stateRows, int $csvRowNumber): bool
    {
        $rowState = $stateRows[(string) $csvRowNumber] ?? null;

        if (!is_array($rowState)) {
            return false;
        }

        $status = strtolower(trim((string) ($rowState['enrichment_status'] ?? $rowState['status'] ?? '')));

        return in_array($status, ['complete', 'completed', 'done', 'success'], true);
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
            'enrichment_status' => $enrichedRow['enrichment_status'],
            'image_status' => $enrichedRow['image_status'],
            'price_status' => $enrichedRow['price_status'],
            'description_status' => $enrichedRow['description_status'],
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
            'enrichment_status',
            'image_status',
            'price_status',
            'description_status',
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
