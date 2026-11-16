<?php

namespace App\Enums;

/**
 * 草稿 / 已发布，公告与文章共用。
 */
enum PublishStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
