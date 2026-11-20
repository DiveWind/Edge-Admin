<?php

namespace App\Core\Generator\Editing;

/**
 * 在模块的 README.md 中加入一节（幂等），由 core:make-crud 调用：插在“## 删除本模块”之前（README 模板中的章节），
 * 没有这一章时追加到末尾。已有同样标题（`## <标题>`）的一节时返回 null。
 */
class ModuleReadmeFile
{
    /**
     * 加入后的内容；已有同样标题的一节时返回 null。
     *
     * @param  string  $section  以 `## <标题>` 开头的一节
     */
    public static function addSection(string $markdown, string $section): ?string
    {
        $section = trim($section)."\n";
        $heading = strstr($section, "\n", true) ?: $section;

        if (preg_match('/^'.preg_quote($heading, '/').'$/m', $markdown) === 1) {
            return null;
        }

        if (preg_match('/^## 删除本模块$/m', $markdown, $match, PREG_OFFSET_CAPTURE) === 1) {
            return substr_replace($markdown, $section."\n", $match[0][1], 0);
        }

        return rtrim($markdown)."\n\n".$section;
    }
}
