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

            $enrichedRow['official_product_url'] = $officialUrlResult['url'];
            $enrichedRow['enrichment_status'] = $officialUrlResult['url'] !== ''
                ? 'official_url_found'
                : 'official_url_not_found';
            $enrichedRow['enrichment_errors'] = implode('; ', $officialUrlResult['errors']);
            $enrichedRow['fetched_at'] = $now;

            $enrichedRows[] = $enrichedRow;
            $reportRows[] = $this->buildReportRow($enrichedRow, $row);

            $stateRows[(string) $csvRowNumber] = [
                'source_row_number' => $csvRowNumber,
                'source_number' => $sourceNumber,
                'brand_name' => $row['brand_name'] ?? '',
                'perfume_name' => $row['perfume_name'] ?? '',
                'brand_domain' => $brandDomain,
                'official_product_url' => $enrichedRow['official_product_url'],
                'enrichment_status' => $enrichedRow['enrichment_status'],
                'image_status' => $enrichedRow['image_status'],
                'price_status' => $enrichedRow['price_status'],
                'description_status' => $enrichedRow['description_status'],
                'updated_at' => $now,
                'errors' => $officialUrlResult['errors'],
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

        foreach ($this->obviousOfficialProductUrls($row, $brandDomain) as $candidateUrl) {
            $result = $this->validateOfficialProductUrl($candidateUrl, $row, $brandDomain);

            if ($result['url'] !== '') {
                return [
                    'url' => $result['url'],
                    'errors' => [],
                ];
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
        $slugs = array_values(array_unique(array_filter([
            $this->slugify($profile['clean_name']),
            $this->slugify($profile['core_name']),
        ])));
        $urls = [];

        foreach ($locales as $locale) {
            foreach ($slugs as $slug) {
                $urls[] = sprintf('https://www.%s/%s/beauty/products/%s.html', $domain, $locale, $slug);
            }
        }

        return array_values(array_unique($urls));
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
                    'User-Agent' => 'YFAI official enrichment dry-run',
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

        preg_match_all('/\/[a-z]{2}_(?:[a-z]{2}|int)\/beauty\/products\/[^"\'<>\s)]+/i', $content, $relativeMatches);

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

        if ($score === null || $score < 85) {
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

            if (!$matchedConcentration) {
                return null;
            }

            $score += 25;
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

        return str_contains($path, '/beauty/products/') && !str_ends_with($path, '.pdf');
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

        return in_array($status, ['complete', 'completed', 'done', 'success', 'official_url_found', 'official_url_not_found'], true);
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
            'official_product_url' => $enrichedRow['official_product_url'],
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
            'official_product_url',
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
