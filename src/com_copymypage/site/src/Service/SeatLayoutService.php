<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\ValueObject\LayoutTableDefinition;
use Joomla\Component\CopyMyPage\Site\ValueObject\SeatDefinition;
use Joomla\Component\CopyMyPage\Site\ValueObject\SeatLayoutDefinition;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Imports and exposes immutable, versioned seating layouts from bundled JSON.
 */
final class SeatLayoutService
{
    public const STATUS_PUBLISHED = 1;

    private const ALLOWED_AREA_TYPES = ['aisle', 'entrance', 'exit', 'obstacle', 'stage'];

    private const ALLOWED_TABLE_SHAPES = ['rectangle', 'round'];

    private const MAX_DEFINITION_BYTES = 524288;

    private const MAX_LOGICAL_SIZE = 100000;

    public const MAX_SEATS = 200;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $definitionsPath
    ) {
    }

    /**
     * Return valid server-bundled definitions without importing them.
     *
     * @return list<array<string, int|string>>
     */
    public function getBundledDefinitions(): array
    {
        if (!is_dir($this->definitionsPath)) {
            return [];
        }

        $definitions = [];
        $iterator    = new \DirectoryIterator($this->definitionsPath);

        foreach ($iterator as $file) {
            if (
                $file->isDot()
                || !$file->isFile()
                || $file->isLink()
                || preg_match('/^[a-z0-9][a-z0-9._-]*\.json$/', $file->getFilename()) !== 1
            ) {
                continue;
            }

            try {
                $definition = $this->loadBundledDefinition($file->getFilename());
            } catch (\Throwable) {
                continue;
            }

            $definitions[] = [
                'alias'     => $definition->alias,
                'file'      => $file->getFilename(),
                'seatCount' => $definition->getSeatCount(),
                'title'     => $definition->title,
                'version'   => $definition->version,
            ];
        }

        usort(
            $definitions,
            static fn(array $left, array $right): int => [
                $left['alias'],
                $left['version'],
                $left['file'],
            ] <=> [
                $right['alias'],
                $right['version'],
                $right['file'],
            ]
        );

        return $definitions;
    }

    /**
     * Return all imported, published layout versions with derived capacities.
     *
     * @return list<array<string, int|string>>
     */
    public function getPublishedLayouts(): array
    {
        $query = $this->createLayoutSummaryQuery()
            ->where($this->db->quoteName('l.status') . ' = ' . self::STATUS_PUBLISHED)
            ->order($this->db->quoteName('l.alias') . ' ASC')
            ->order($this->db->quoteName('l.version') . ' ASC');

        $rows = $this->db->setQuery($query)->loadObjectList();

        return array_map([$this, 'normaliseLayoutSummary'], $rows);
    }

    /**
     * Return one published layout version or null.
     *
     * @return array<string, int|string>|null
     */
    public function getPublishedLayout(int $layoutId): ?array
    {
        if ($layoutId <= 0) {
            return null;
        }

        $query = $this->createLayoutSummaryQuery()
            ->where($this->db->quoteName('l.id') . ' = :layoutId')
            ->where($this->db->quoteName('l.status') . ' = ' . self::STATUS_PUBLISHED)
            ->bind(':layoutId', $layoutId, ParameterType::INTEGER);
        $row = $this->db->setQuery($query)->loadObject();

        return \is_object($row) ? $this->normaliseLayoutSummary($row) : null;
    }

    /**
     * Validate and atomically import one allowlisted bundled JSON definition.
     *
     * @return array<string, bool|int|string>
     */
    public function importBundledDefinition(string $fileName, int $userId): array
    {
        $definition       = $this->loadBundledDefinition($fileName);
        $transactionOpen  = false;
        $userId           = max(0, $userId);

        try {
            $this->db->transactionStart();
            $transactionOpen = true;
            $stored          = $this->findStoredLayout(
                $definition->alias,
                $definition->version,
                true
            );

            if ($stored !== null) {
                $this->assertMatchingStoredDefinition($stored, $definition);
                $this->db->transactionCommit();

                return $this->buildImportResult($stored, false);
            }

            $now = gmdate('Y-m-d H:i:s');
            $row = (object) [
                'alias'          => $definition->alias,
                'version'        => $definition->version,
                'title'          => $definition->title,
                'status'         => self::STATUS_PUBLISHED,
                'logical_width'  => $definition->width,
                'logical_height' => $definition->height,
                'geometry_json'  => json_encode(
                    ['areas' => $definition->areas],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                'definition_hash' => $definition->hash,
                'created'         => $now,
                'created_by'      => $userId,
            ];

            $this->db->insertObject('#__copymypage_seat_layouts', $row);
            $layoutId = (int) $this->db->insertid();

            if ($layoutId <= 0) {
                throw new \RuntimeException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_SAVE'));
            }

            $this->insertLayoutTables($layoutId, $definition->tables);
            $this->insertSeats($layoutId, $definition->tables);

            $stored = $this->findStoredLayout($definition->alias, $definition->version, false);

            if ($stored === null) {
                throw new \RuntimeException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_SAVE'));
            }

            $this->assertMatchingStoredDefinition($stored, $definition);
            $this->db->transactionCommit();

            return $this->buildImportResult($stored, true);
        } catch (\DomainException $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            // A concurrent identical import may win the unique alias/version key.
            $stored = $this->findStoredLayout($definition->alias, $definition->version, false);

            if ($stored !== null && hash_equals((string) $stored->definition_hash, $definition->hash)) {
                $this->assertMatchingStoredDefinition($stored, $definition);

                return $this->buildImportResult($stored, false);
            }

            throw new \RuntimeException(
                Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_SAVE'),
                0,
                $exception
            );
        }
    }

    /**
     * Load and validate one definition under the fixed server directory.
     */
    private function loadBundledDefinition(string $fileName): SeatLayoutDefinition
    {
        $fileName = trim($fileName);

        if (
            $fileName === ''
            || basename($fileName) !== $fileName
            || preg_match('/^[a-z0-9][a-z0-9._-]*\.json$/', $fileName) !== 1
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_FILE'));
        }

        $basePath  = realpath($this->definitionsPath);
        $candidate = $basePath === false ? '' : $basePath . DIRECTORY_SEPARATOR . $fileName;
        $path      = $candidate === '' ? false : realpath($candidate);
        $size      = $path === false ? false : filesize($path);

        if (
            $basePath === false
            || $path === false
            || strcasecmp(dirname($path), $basePath) !== 0
            || !is_file($path)
            || is_link($candidate)
            || $size === false
            || $size > self::MAX_DEFINITION_BYTES
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_FILE'));
        }

        $json = file_get_contents($path);

        if (!\is_string($json) || trim($json) === '') {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_FILE'));
        }

        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \DomainException(
                Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_JSON'),
                0,
                $exception
            );
        }

        if (!\is_array($data) || array_is_list($data)) {
            throw $this->invalidDefinition('root');
        }

        return $this->normaliseDefinition($data);
    }

    /**
     * Strictly validate and normalise the versioned JSON contract.
     *
     * @param   array<string, mixed>  $data
     */
    private function normaliseDefinition(array $data): SeatLayoutDefinition
    {
        $this->assertKeys(
            $data,
            ['alias', 'areas', 'canvas', 'schemaVersion', 'tables', 'title', 'version'],
            ['alias', 'canvas', 'schemaVersion', 'tables', 'title', 'version'],
            'root'
        );

        $schemaVersion = $this->requireInt($data, 'schemaVersion', 1, 1, 'root');
        $alias         = $this->requireString(
            $data,
            'alias',
            64,
            'root',
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/'
        );
        $version = $this->requireInt($data, 'version', 1, PHP_INT_MAX, 'root');
        $title   = $this->requireString($data, 'title', 255, 'root');
        $canvas  = $data['canvas'];

        if (!\is_array($canvas) || array_is_list($canvas)) {
            throw $this->invalidDefinition('canvas');
        }

        $this->assertKeys($canvas, ['height', 'width'], ['height', 'width'], 'canvas');
        $width  = $this->requireInt($canvas, 'width', 1, self::MAX_LOGICAL_SIZE, 'canvas');
        $height = $this->requireInt($canvas, 'height', 1, self::MAX_LOGICAL_SIZE, 'canvas');
        $areas  = $this->normaliseAreas($data['areas'] ?? [], $width, $height);
        $tables = $this->normaliseTables($data['tables'], $width, $height);
        $seatCount = array_sum(
            array_map(
                static fn(LayoutTableDefinition $table): int => \count($table->seats),
                $tables
            )
        );

        if ($seatCount < 1 || $seatCount > self::MAX_SEATS) {
            throw $this->invalidDefinition('tables.seats');
        }

        $provisional = new SeatLayoutDefinition(
            $schemaVersion,
            $alias,
            $version,
            $title,
            $width,
            $height,
            $areas,
            $tables,
            ''
        );
        $canonical = json_encode(
            $provisional->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return new SeatLayoutDefinition(
            $schemaVersion,
            $alias,
            $version,
            $title,
            $width,
            $height,
            $areas,
            $tables,
            hash('sha256', $canonical)
        );
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function normaliseAreas(mixed $areas, int $canvasWidth, int $canvasHeight): array
    {
        if (!\is_array($areas) || !array_is_list($areas)) {
            throw $this->invalidDefinition('areas');
        }

        $result = [];
        $codes  = [];

        foreach ($areas as $index => $area) {
            $path = 'areas.' . $index;

            if (!\is_array($area) || array_is_list($area)) {
                throw $this->invalidDefinition($path);
            }

            $this->assertKeys(
                $area,
                ['code', 'height', 'label', 'rotation', 'shape', 'type', 'width', 'x', 'y'],
                ['code', 'height', 'label', 'shape', 'type', 'width', 'x', 'y'],
                $path
            );
            $code = $this->requireString($area, 'code', 64, $path, '/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
            $type = strtolower($this->requireString($area, 'type', 16, $path));

            if (isset($codes[$code]) || !\in_array($type, self::ALLOWED_AREA_TYPES, true)) {
                throw $this->invalidDefinition($path);
            }

            $codes[$code] = true;
            $shape        = strtolower($this->requireString($area, 'shape', 16, $path));

            if ($shape !== 'rectangle') {
                throw $this->invalidDefinition($path . '.shape');
            }

            $x        = $this->requireInt($area, 'x', 0, $canvasWidth, $path);
            $y        = $this->requireInt($area, 'y', 0, $canvasHeight, $path);
            $areaWidth  = $this->requireInt($area, 'width', 1, $canvasWidth, $path);
            $areaHeight = $this->requireInt($area, 'height', 1, $canvasHeight, $path);

            if ($x + $areaWidth > $canvasWidth || $y + $areaHeight > $canvasHeight) {
                throw $this->invalidDefinition($path);
            }

            $result[] = [
                'code'     => $code,
                'height'   => $areaHeight,
                'label'    => $this->requireString($area, 'label', 255, $path),
                'rotation' => isset($area['rotation'])
                    ? $this->requireInt($area, 'rotation', 0, 359, $path)
                    : 0,
                'shape' => $shape,
                'type'  => $type,
                'width' => $areaWidth,
                'x'     => $x,
                'y'     => $y,
            ];
        }

        usort($result, static fn(array $left, array $right): int => $left['code'] <=> $right['code']);

        return $result;
    }

    /**
     * @return list<LayoutTableDefinition>
     */
    private function normaliseTables(mixed $tables, int $canvasWidth, int $canvasHeight): array
    {
        if (!\is_array($tables) || !array_is_list($tables) || $tables === []) {
            throw $this->invalidDefinition('tables');
        }

        $result          = [];
        $tableCodes      = [];
        $tableNumbers    = [];
        $tableSortOrders = [];
        $seatCodes       = [];
        $seatCoordinates = [];

        foreach ($tables as $index => $table) {
            $path = 'tables.' . $index;

            if (!\is_array($table) || array_is_list($table)) {
                throw $this->invalidDefinition($path);
            }

            $this->assertKeys(
                $table,
                [
                    'code',
                    'height',
                    'label',
                    'number',
                    'rotation',
                    'seats',
                    'shape',
                    'sortOrder',
                    'width',
                    'x',
                    'y',
                ],
                ['code', 'height', 'label', 'number', 'seats', 'shape', 'sortOrder', 'width', 'x', 'y'],
                $path
            );
            $code      = $this->requireString($table, 'code', 64, $path, '/^[A-Z0-9][A-Z0-9_-]*$/');
            $number    = $this->requireString($table, 'number', 32, $path);
            $numberKey = mb_strtolower($number, 'UTF-8');
            $sortOrder = $this->requireInt($table, 'sortOrder', 1, PHP_INT_MAX, $path);

            if (
                isset($tableCodes[$code])
                || isset($tableNumbers[$numberKey])
                || isset($tableSortOrders[$sortOrder])
            ) {
                throw $this->invalidDefinition($path);
            }

            $tableCodes[$code]           = true;
            $tableNumbers[$numberKey]    = true;
            $tableSortOrders[$sortOrder] = true;
            $shape = strtolower($this->requireString($table, 'shape', 16, $path));

            if (!\in_array($shape, self::ALLOWED_TABLE_SHAPES, true)) {
                throw $this->invalidDefinition($path . '.shape');
            }

            $x           = $this->requireInt($table, 'x', 0, $canvasWidth, $path);
            $y           = $this->requireInt($table, 'y', 0, $canvasHeight, $path);
            $tableWidth  = $this->requireInt($table, 'width', 1, $canvasWidth, $path);
            $tableHeight = $this->requireInt($table, 'height', 1, $canvasHeight, $path);

            if ($x + $tableWidth > $canvasWidth || $y + $tableHeight > $canvasHeight) {
                throw $this->invalidDefinition($path);
            }

            $seats = $this->normaliseSeats(
                $table['seats'],
                $code,
                $path,
                $canvasWidth,
                $canvasHeight,
                $seatCodes,
                $seatCoordinates
            );
            $result[] = new LayoutTableDefinition(
                $code,
                $number,
                $this->requireString($table, 'label', 255, $path),
                $shape,
                $x,
                $y,
                $tableWidth,
                $tableHeight,
                isset($table['rotation'])
                    ? $this->requireInt($table, 'rotation', 0, 359, $path)
                    : 0,
                $sortOrder,
                $seats
            );
        }

        usort(
            $result,
            static fn(LayoutTableDefinition $left, LayoutTableDefinition $right): int
                => $left->sortOrder <=> $right->sortOrder
        );

        return $result;
    }

    /**
     * @param   array<string, bool>  $allSeatCodes
     * @param   array<string, bool>  $allSeatCoordinates
     *
     * @return list<SeatDefinition>
     */
    private function normaliseSeats(
        mixed $seats,
        string $tableCode,
        string $tablePath,
        int $canvasWidth,
        int $canvasHeight,
        array &$allSeatCodes,
        array &$allSeatCoordinates
    ): array {
        if (!\is_array($seats) || !array_is_list($seats) || $seats === []) {
            throw $this->invalidDefinition($tablePath . '.seats');
        }

        $result     = [];
        $numbers    = [];
        $sortOrders = [];

        foreach ($seats as $index => $seat) {
            $path = $tablePath . '.seats.' . $index;

            if (!\is_array($seat) || array_is_list($seat)) {
                throw $this->invalidDefinition($path);
            }

            $this->assertKeys(
                $seat,
                ['number', 'sortOrder', 'x', 'y'],
                ['number', 'sortOrder', 'x', 'y'],
                $path
            );
            $number     = $this->requireString($seat, 'number', 32, $path);
            $numberKey  = mb_strtolower($number, 'UTF-8');
            $sortOrder  = $this->requireInt($seat, 'sortOrder', 1, PHP_INT_MAX, $path);
            $x          = $this->requireInt($seat, 'x', 0, $canvasWidth, $path);
            $y          = $this->requireInt($seat, 'y', 0, $canvasHeight, $path);
            $code       = $this->buildSeatCode($tableCode, $number);
            $coordinate = $x . ':' . $y;

            if (
                isset($numbers[$numberKey])
                || isset($sortOrders[$sortOrder])
                || isset($allSeatCodes[$code])
                || isset($allSeatCoordinates[$coordinate])
            ) {
                throw $this->invalidDefinition($path);
            }

            $numbers[$numberKey]              = true;
            $sortOrders[$sortOrder]           = true;
            $allSeatCodes[$code]              = true;
            $allSeatCoordinates[$coordinate]  = true;
            $result[] = new SeatDefinition($code, $number, $x, $y, $sortOrder);
        }

        usort(
            $result,
            static fn(SeatDefinition $left, SeatDefinition $right): int
                => $left->sortOrder <=> $right->sortOrder
        );

        return $result;
    }

    private function buildSeatCode(string $tableCode, string $seatNumber): string
    {
        if (preg_match('/^[0-9]+$/', $seatNumber) === 1) {
            $seatPart = str_pad((string) ((int) $seatNumber), 2, '0', STR_PAD_LEFT);
        } else {
            $seatPart = strtoupper(
                trim((string) preg_replace('/[^A-Z0-9]+/i', '-', $seatNumber), '-')
            );
        }

        if ($seatPart === '') {
            throw $this->invalidDefinition('tables.seats.number');
        }

        return $tableCode . '-S' . $seatPart;
    }

    /**
     * @param   array<string, mixed>  $data
     * @param   list<string>          $allowed
     * @param   list<string>          $required
     */
    private function assertKeys(array $data, array $allowed, array $required, string $path): void
    {
        if (
            array_diff(array_keys($data), $allowed) !== []
            || array_diff($required, array_keys($data)) !== []
        ) {
            throw $this->invalidDefinition($path);
        }
    }

    /**
     * @param   array<string, mixed>  $data
     */
    private function requireInt(array $data, string $key, int $minimum, int $maximum, string $path): int
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value) || $value < $minimum || $value > $maximum) {
            throw $this->invalidDefinition($path . '.' . $key);
        }

        return $value;
    }

    /**
     * @param   array<string, mixed>  $data
     */
    private function requireString(
        array $data,
        string $key,
        int $maximumLength,
        string $path,
        ?string $pattern = null
    ): string {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw $this->invalidDefinition($path . '.' . $key);
        }

        $value = trim($value);

        if (
            $value === ''
            || mb_strlen($value, 'UTF-8') > $maximumLength
            || ($pattern !== null && preg_match($pattern, $value) !== 1)
        ) {
            throw $this->invalidDefinition($path . '.' . $key);
        }

        return $value;
    }

    private function invalidDefinition(string $path): \DomainException
    {
        return new \DomainException(
            Text::sprintf('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_INVALID_FIELD', $path)
        );
    }

    /**
     * @param   list<LayoutTableDefinition>  $tables
     */
    private function insertLayoutTables(int $layoutId, array $tables): void
    {
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__copymypage_layout_tables'))
            ->columns(
                $this->db->quoteName(
                    [
                        'layout_id',
                        'table_code',
                        'table_number',
                        'label',
                        'shape',
                        'x',
                        'y',
                        'width',
                        'height',
                        'rotation',
                        'sort_order',
                    ]
                )
            );

        foreach ($tables as $table) {
            $query->values(
                implode(
                    ',',
                    [
                        $layoutId,
                        $this->db->quote($table->code),
                        $this->db->quote($table->number),
                        $this->db->quote($table->label),
                        $this->db->quote($table->shape),
                        $table->x,
                        $table->y,
                        $table->width,
                        $table->height,
                        $table->rotation,
                        $table->sortOrder,
                    ]
                )
            );
        }

        $this->db->setQuery($query)->execute();
    }

    /**
     * @param   list<LayoutTableDefinition>  $tables
     */
    private function insertSeats(int $layoutId, array $tables): void
    {
        $query = $this->db->getQuery(true)
            ->select(
                [
                    $this->db->quoteName('id'),
                    $this->db->quoteName('table_code'),
                ]
            )
            ->from($this->db->quoteName('#__copymypage_layout_tables'))
            ->where($this->db->quoteName('layout_id') . ' = :layoutId')
            ->bind(':layoutId', $layoutId, ParameterType::INTEGER);
        $tableIds = $this->db->setQuery($query)->loadAssocList('table_code', 'id');

        if (\count($tableIds) !== \count($tables)) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_SAVE'));
        }

        $insert = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__copymypage_seats'))
            ->columns(
                $this->db->quoteName(
                    ['layout_table_id', 'seat_code', 'seat_number', 'x', 'y', 'sort_order']
                )
            );

        foreach ($tables as $table) {
            $tableId = (int) ($tableIds[$table->code] ?? 0);

            if ($tableId <= 0) {
                throw new \RuntimeException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_SAVE'));
            }

            foreach ($table->seats as $seat) {
                $insert->values(
                    implode(
                        ',',
                        [
                            $tableId,
                            $this->db->quote($seat->code),
                            $this->db->quote($seat->number),
                            $seat->x,
                            $seat->y,
                            $seat->sortOrder,
                        ]
                    )
                );
            }
        }

        $this->db->setQuery($insert)->execute();
    }

    private function findStoredLayout(string $alias, int $version, bool $forUpdate): ?object
    {
        if ($forUpdate) {
            $query = $this->db->getQuery(true)
                ->select('l.*')
                ->from($this->db->quoteName('#__copymypage_seat_layouts', 'l'))
                ->where($this->db->quoteName('l.alias') . ' = ' . $this->db->quote($alias))
                ->where($this->db->quoteName('l.version') . ' = ' . $version);
            $row = $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObject();

            if (!\is_object($row)) {
                return null;
            }

            $counts = $this->loadLayoutCounts((int) $row->id);
            $row->table_count = $counts['tableCount'];
            $row->seat_count  = $counts['seatCount'];

            return $row;
        }

        $query = $this->createLayoutSummaryQuery()
            ->where($this->db->quoteName('l.alias') . ' = :alias')
            ->where($this->db->quoteName('l.version') . ' = :version')
            ->bind(':alias', $alias)
            ->bind(':version', $version, ParameterType::INTEGER);
        $row = $this->db->setQuery($query)->loadObject();

        return \is_object($row) ? $row : null;
    }

    /**
     * @return array{seatCount: int, tableCount: int}
     */
    private function loadLayoutCounts(int $layoutId): array
    {
        $query = $this->db->getQuery(true)
            ->select(
                [
                    'COUNT(DISTINCT ' . $this->db->quoteName('t.id') . ') AS '
                        . $this->db->quoteName('table_count'),
                    'COUNT(' . $this->db->quoteName('s.id') . ') AS '
                        . $this->db->quoteName('seat_count'),
                ]
            )
            ->from($this->db->quoteName('#__copymypage_layout_tables', 't'))
            ->leftJoin(
                $this->db->quoteName('#__copymypage_seats', 's')
                    . ' ON ' . $this->db->quoteName('s.layout_table_id')
                    . ' = ' . $this->db->quoteName('t.id')
            )
            ->where($this->db->quoteName('t.layout_id') . ' = :layoutId')
            ->bind(':layoutId', $layoutId, ParameterType::INTEGER);
        $row = $this->db->setQuery($query)->loadObject();

        return [
            'seatCount'  => (int) ($row->seat_count ?? 0),
            'tableCount' => (int) ($row->table_count ?? 0),
        ];
    }

    private function createLayoutSummaryQuery(): \Joomla\Database\DatabaseQuery
    {
        return $this->db->getQuery(true)
            ->select(
                [
                    $this->db->quoteName('l.id'),
                    $this->db->quoteName('l.alias'),
                    $this->db->quoteName('l.version'),
                    $this->db->quoteName('l.title'),
                    $this->db->quoteName('l.status'),
                    $this->db->quoteName('l.logical_width'),
                    $this->db->quoteName('l.logical_height'),
                    $this->db->quoteName('l.definition_hash'),
                    'COUNT(DISTINCT ' . $this->db->quoteName('t.id') . ') AS '
                        . $this->db->quoteName('table_count'),
                    'COUNT(' . $this->db->quoteName('s.id') . ') AS '
                        . $this->db->quoteName('seat_count'),
                ]
            )
            ->from($this->db->quoteName('#__copymypage_seat_layouts', 'l'))
            ->leftJoin(
                $this->db->quoteName('#__copymypage_layout_tables', 't')
                    . ' ON ' . $this->db->quoteName('t.layout_id')
                    . ' = ' . $this->db->quoteName('l.id')
            )
            ->leftJoin(
                $this->db->quoteName('#__copymypage_seats', 's')
                    . ' ON ' . $this->db->quoteName('s.layout_table_id')
                    . ' = ' . $this->db->quoteName('t.id')
            )
            ->group(
                $this->db->quoteName(
                    [
                        'l.id',
                        'l.alias',
                        'l.version',
                        'l.title',
                        'l.status',
                        'l.logical_width',
                        'l.logical_height',
                        'l.definition_hash',
                    ]
                )
            );
    }

    private function assertMatchingStoredDefinition(object $stored, SeatLayoutDefinition $definition): void
    {
        if (!hash_equals((string) $stored->definition_hash, $definition->hash)) {
            throw new \DomainException(
                Text::sprintf(
                    'COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_VERSION_CONFLICT',
                    $definition->alias,
                    $definition->version
                )
            );
        }

        if (
            (int) $stored->table_count !== \count($definition->tables)
            || (int) $stored->seat_count !== $definition->getSeatCount()
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_CORRUPT'));
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function normaliseLayoutSummary(object $row): array
    {
        return [
            'alias'       => (string) $row->alias,
            'height'      => (int) $row->logical_height,
            'id'          => (int) $row->id,
            'seatCount'   => (int) $row->seat_count,
            'status'      => (int) $row->status,
            'tableCount'  => (int) $row->table_count,
            'title'       => (string) $row->title,
            'version'     => (int) $row->version,
            'width'       => (int) $row->logical_width,
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function buildImportResult(object $row, bool $imported): array
    {
        return [
            'alias'      => (string) $row->alias,
            'id'         => (int) $row->id,
            'imported'   => $imported,
            'seatCount'  => (int) $row->seat_count,
            'tableCount' => (int) $row->table_count,
            'title'      => (string) $row->title,
            'version'    => (int) $row->version,
        ];
    }
}
