<?php

namespace App\Core\System\Contracts;

/**
 * 标记接口：绑定在实现它的记录上的附件，访问权**只**跟随这条记录（设计文档 §6.8、§6.10）。
 *
 * - AttachmentPolicy::view() 的第 3 条（附件管理员在数据范围内也可访问）不适用；
 * - 附件管理的列表（GET /system/attachments）不列出它们。
 *
 * 用于批量数据、个人敏感文件这类附件管理员也不应看到的文件：导出的文件（Export）、业务模块的工资条等。
 */
interface PrivateAttachable {}
