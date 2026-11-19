<?php

namespace App\Core\System\Enums;

use App\Core\System\Contracts\HasLabel;

/**
 * 导出文件的格式（设计文档 §6.10）：值即文件的扩展名。默认 xlsx。
 *
 * 实现 HasLabel：显示名称由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum ExportFormat: string implements HasLabel
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';

    /**
     * 文件的 MIME。
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv',
        };
    }

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Xlsx => __('Excel workbook (.xlsx)'),
            self::Csv => __('CSV (.csv)'),
        };
    }
}
