<?php

namespace App\Core\System\Services;

use App\Core\System\Enums\ExportFormat;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\{Cell, Row};
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * 导出文件的流式写入（spatie/simple-excel，设计文档 §6.10）：逐行写入本机文件，不在内存中保留已写出的行。
 *
 * - XLSX：字符串一律写为字符串单元格——openspout 默认把以 `=` 开头的字符串写成公式（公式注入）；数字、布尔照常；
 * - CSV：以 `=`、`+`、`-`、`@`、制表符、回车开头的字符串前加 `'`（OWASP 对 CSV 注入的处理），带 UTF-8 BOM（Excel 直接打开中文不乱码）；
 * - null 与空字符串为空单元格。
 *
 * 无状态：每次 write() 新建写入器、写完关闭。
 */
class ExportWriter
{
    /**
     * CSV 中会被电子表格当作公式起始的字符。
     *
     * @var list<string>
     */
    public const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * 写入表头与各行，返回写出的数据行数（不含表头）。
     *
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|bool|null>>  $rows
     * @return int<0, max>
     */
    public function write(string $path, ExportFormat $format, array $header, iterable $rows): int
    {
        $writer = SimpleExcelWriter::create($path, $format->value, shouldAddBom: true);
        $count = 0;

        try {
            $writer->addRow($this->row($header, $format));

            foreach ($rows as $values) {
                $writer->addRow($this->row($values, $format));
                $count++;
            }
        } finally {
            $writer->close();
        }

        return $count;
    }

    /**
     * @param  list<string|int|float|bool|null>  $values
     */
    private function row(array $values, ExportFormat $format): Row
    {
        return new Row(array_map(fn (string|int|float|bool|null $value): Cell => $this->cell($value, $format), $values));
    }

    private function cell(string|int|float|bool|null $value, ExportFormat $format): Cell
    {
        if (! is_string($value) || $value === '') {
            return Cell::fromValue($value);
        }

        if ($format === ExportFormat::Csv && in_array($value[0], self::FORMULA_PREFIXES, true)) {
            $value = "'".$value;
        }

        return new StringCell($value);
    }
}
