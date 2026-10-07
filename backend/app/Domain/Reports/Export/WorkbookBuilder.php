<?php

namespace App\Domain\Reports\Export;

use App\Support\Money\Money;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds branded .xlsx workbooks: a title block, a styled header row, frozen panes,
 * filters, zebra rows, typed number formats, totals rows and print setup on every sheet.
 */
class WorkbookBuilder
{
    // Store palette (ARCHITECTURE D15).
    private const BRAND = '49111C';

    private const INK = '0A0908';

    private const MUTED = '5E503F';

    private const BAND = 'F7F5F3';

    private const RULE = 'DDD6CF';

    private const TOTAL_FILL = 'EFE8E1';

    private const TONES = ['good' => ['E6F4EA', '1E6B34'], 'bad' => ['FBE7E7', '9B1C1C'], 'warn' => ['FFF4DB', '8A5A00']];

    private const HEADER_ROW = 4;

    private Spreadsheet $book;

    private bool $first = true;

    private readonly string $moneyFormat;

    private readonly string $generated;

    private readonly string $timezone;

    public function __construct(private readonly string $title, private readonly string $author, private readonly string $currency)
    {
        $this->book = new Spreadsheet;
        $this->book->getProperties()->setCreator($author)->setLastModifiedBy($author)->setTitle($title)
            ->setCompany((string) config('commerce.store.name'));
        $this->book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->getColor()->setRGB(self::INK);

        $symbol = str_replace('"', '', (string) config("commerce.currency_symbols.{$currency}", "{$currency} "));
        $decimals = Money::exponent($currency);
        $pattern = '#,##0'.($decimals ? '.'.str_repeat('0', $decimals) : '');
        $this->moneyFormat = "\"{$symbol}\"{$pattern};[Red]-\"{$symbol}\"{$pattern}";
        $this->timezone = (string) config('commerce.store.timezone', 'UTC');
        $this->generated = 'Generated '.now($this->timezone)->format('j M Y, g:i A T').' by '.$author;
    }

    /**
     * Adds a data sheet.
     *
     * @param  list<Column>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function table(string $name, string $heading, array $columns, iterable $rows, ?string $note = null): Worksheet
    {
        $sheet = $this->newSheet($name, $heading, count($columns), $note);
        $lastCol = Coordinate::stringFromColumnIndex(count($columns));

        foreach ($columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($letter.self::HEADER_ROW, $column->header);
            $sheet->getColumnDimension($letter)->setWidth($column->width);
        }
        $header = $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW);
        $header->getFont()->setBold(true)->setSize(10.5)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(30);
        foreach ($columns as $i => $column) {
            if (in_array($column->type, [Column::INT, Column::MONEY, Column::PERCENT], true)) {
                $sheet->getStyle(Coordinate::stringFromColumnIndex($i + 1).self::HEADER_ROW)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $r = self::HEADER_ROW;
        foreach ($rows as $row) {
            $r++;
            foreach ($columns as $i => $column) {
                $this->writeCell($sheet, Coordinate::stringFromColumnIndex($i + 1).$r, $column, $row[$column->key] ?? null, $row);
            }
            $sheet->getRowDimension($r)->setRowHeight(20);
        }
        $firstData = self::HEADER_ROW + 1;

        if ($r === self::HEADER_ROW) {
            $r++;
            $sheet->setCellValue("A{$r}", 'No records for this selection.');
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB(self::MUTED);

            return $this->finish($sheet, $lastCol, $r);
        }

        // Typed formats and alignment per column, applied to whole ranges (fast).
        foreach ($columns as $i => $column) {
            $range = Coordinate::stringFromColumnIndex($i + 1)."{$firstData}:".Coordinate::stringFromColumnIndex($i + 1).$r;
            $style = $sheet->getStyle($range);
            match ($column->type) {
                Column::MONEY => $style->getNumberFormat()->setFormatCode($this->moneyFormat),
                Column::INT => $style->getNumberFormat()->setFormatCode('#,##0'),
                Column::PERCENT => $style->getNumberFormat()->setFormatCode('0.0%'),
                Column::DATE => $style->getNumberFormat()->setFormatCode('dd mmm yyyy'),
                Column::DATETIME => $style->getNumberFormat()->setFormatCode('dd mmm yyyy hh:mm'),
                default => null,
            };
            if ($column->type === Column::CODE) {
                $style->getFont()->setName('Consolas')->setSize(10);
            }
            if (in_array($column->type, [Column::BOOL, Column::DATE, Column::DATETIME], true)) {
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
        }

        // Zebra banding and hairline rules.
        for ($row = $firstData; $row <= $r; $row++) {
            if (($row - $firstData) % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BAND);
            }
        }
        $body = $sheet->getStyle("A{$firstData}:{$lastCol}{$r}");
        $body->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::RULE);
        $body->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::RULE);
        $body->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Re-apply cell tones over the banding.
        foreach ($this->pendingTones as [$cell, $tone]) {
            [$bg, $fg] = self::TONES[$tone];
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB($fg);
        }
        $this->pendingTones = [];

        $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastCol}{$r}");

        if (array_filter($columns, fn (Column $c) => $c->total)) {
            $t = $r + 1;
            $sheet->setCellValue("A{$t}", 'Total');
            foreach ($columns as $i => $column) {
                if ($column->total) {
                    $letter = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->setCellValue("{$letter}{$t}", "=SUBTOTAL(9,{$letter}{$firstData}:{$letter}{$r})");
                    $sheet->getStyle("{$letter}{$t}")->getNumberFormat()->setFormatCode($column->type === Column::MONEY ? $this->moneyFormat : '#,##0');
                }
            }
            $totals = $sheet->getStyle("A{$t}:{$lastCol}{$t}");
            $totals->getFont()->setBold(true);
            $totals->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TOTAL_FILL);
            $totals->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRGB(self::BRAND);
            $sheet->getRowDimension($t)->setRowHeight(22);
            $r = $t;
        }

        return $this->finish($sheet, $lastCol, $r);
    }

    /**
     * Adds a two-column "label | value" summary sheet, with an optional contents list
     * linking to the other sheets.
     *
     * @param  list<array{0: string, 1: mixed, 2: string}>  $metrics  [label, value, type]
     * @param  list<array{0: string, 1: string}>  $contents  [sheet name, description]
     */
    public function summary(string $name, string $heading, array $metrics, array $contents = [], ?string $note = null): Worksheet
    {
        $sheet = $this->newSheet($name, $heading, 3, $note);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(60);

        $sheet->fromArray(['Metric', 'Value'], null, 'A'.self::HEADER_ROW);
        $head = $sheet->getStyle('A'.self::HEADER_ROW.':B'.self::HEADER_ROW);
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);
        $sheet->getStyle('B'.self::HEADER_ROW)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(26);

        $r = self::HEADER_ROW;
        foreach ($metrics as [$label, $value, $type]) {
            $r++;
            $sheet->setCellValue("A{$r}", $label);
            $this->writeCell($sheet, "B{$r}", new Column($label, 'v', $type), $value, []);
            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode(match ($type) {
                Column::MONEY => $this->moneyFormat, Column::PERCENT => '0.0%', Column::DATE => 'dd mmm yyyy', default => '#,##0',
            });
            $sheet->getStyle("B{$r}")->getFont()->setBold(true);
            $sheet->getRowDimension($r)->setRowHeight(22);
            if (($r - self::HEADER_ROW) % 2 === 0) {
                $sheet->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BAND);
            }
        }
        $sheet->getStyle('A'.(self::HEADER_ROW + 1).":B{$r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::RULE);
        $sheet->getStyle('A'.(self::HEADER_ROW + 1).":B{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        if ($contents) {
            $r += 2;
            $sheet->setCellValue("A{$r}", 'In this workbook');
            $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::BRAND);
            foreach ($contents as [$tab, $description]) {
                $r++;
                $sheet->setCellValue("A{$r}", $tab);
                $sheet->getCell("A{$r}")->getHyperlink()->setUrl("sheet://'".str_replace("'", "''", $tab)."'!A1");
                $sheet->getStyle("A{$r}")->getFont()->setUnderline(true)->getColor()->setRGB(self::BRAND);
                $sheet->setCellValue("C{$r}", $description);
                $sheet->getStyle("C{$r}")->getFont()->getColor()->setRGB(self::MUTED);
            }
        }

        return $this->finish($sheet, 'C', $r, freeze: false);
    }

    public function download(string $filename): StreamedResponse
    {
        $this->book->setActiveSheetIndex(0);
        $writer = new Xlsx($this->book);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @var list<array{0: string, 1: string}> */
    private array $pendingTones = [];

    private function newSheet(string $name, string $heading, int $columns, ?string $note): Worksheet
    {
        $sheet = $this->first ? $this->book->getActiveSheet() : $this->book->createSheet();
        $this->first = false;
        $sheet->setTitle(mb_substr((string) preg_replace('/[\[\]:*?\/\\\\]/', '-', $name), 0, 31));
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setRGB(self::BRAND);
        $last = Coordinate::stringFromColumnIndex(max(2, $columns));

        $sheet->setCellValue('A1', (string) config('commerce.store.name').' — '.$heading);
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::BRAND);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('A2', $this->generated.($note ? " · {$note}" : ''));
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB(self::MUTED);
        $sheet->getStyle("A3:{$last}3")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::BRAND);
        $sheet->getRowDimension(3)->setRowHeight(6);

        return $sheet;
    }

    private function finish(Worksheet $sheet, string $lastCol, int $lastRow, bool $freeze = true): Worksheet
    {
        if ($freeze) {
            $sheet->freezePane('A'.(self::HEADER_ROW + 1));
        }
        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setPrintArea("A1:{$lastCol}{$lastRow}");
        $setup->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.$this->title.'&R&8Page &P of &N');
        $sheet->setSelectedCell('A'.(self::HEADER_ROW + 1));

        return $sheet;
    }

    /**
     * Timestamps are shown in the store's time zone. Date-only cells are calendar days
     * (report ranges, DATE() buckets) and keep the day they name, with no time part.
     */
    private function excelDate(mixed $value, bool $dateOnly): float
    {
        $at = Carbon::parse($value instanceof DateTimeInterface ? $value : (string) $value);
        $wall = $dateOnly ? $at->format('Y-m-d').' 00:00:00' : $at->setTimezone($this->timezone)->format('Y-m-d H:i:s');

        return (float) ExcelDate::PHPToExcel(Carbon::parse($wall, 'UTC'));
    }

    /** @param array<string, mixed> $row */
    private function writeCell(Worksheet $sheet, string $cell, Column $column, mixed $value, array $row): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $written = match ($column->type) {
            Column::MONEY => round((int) $value / (10 ** Money::exponent($this->currency)), Money::exponent($this->currency)),
            Column::INT => (int) $value,
            Column::PERCENT => (float) $value,
            Column::DATE, Column::DATETIME => $this->excelDate($value, $column->type === Column::DATE),
            Column::BOOL => $value ? 'Yes' : 'No',
            default => (string) $value,
        };
        if (is_string($written)) {
            // Text stays text: stops Excel turning SKUs/phones into numbers or formulas.
            $sheet->setCellValueExplicit($cell, $written, DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($cell, $written);
        }

        if ($column->tone && ($tone = ($column->tone)($row)) && isset(self::TONES[$tone])) {
            $this->pendingTones[] = [$cell, $tone];
        }
    }
}
