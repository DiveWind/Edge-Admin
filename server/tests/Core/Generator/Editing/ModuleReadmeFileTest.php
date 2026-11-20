<?php

use App\Core\Generator\Editing\ModuleReadmeFile;

/*
|--------------------------------------------------------------------------
| ModuleReadmeFile：在模块 README 中加入一个模型的一节（core:make-crud）
|--------------------------------------------------------------------------
*/

it('inserts the section before the chapter on deleting the module, once', function (): void {
    $readme = "# Blog\n\n## 职责\n\nTODO\n\n## 删除本模块\n\n1. …\n";
    $updated = (string) ModuleReadmeFile::addSection($readme, "## Post\n\n内容\n");

    expect($updated)->toBe("# Blog\n\n## 职责\n\nTODO\n\n## Post\n\n内容\n\n## 删除本模块\n\n1. …\n")
        ->and(ModuleReadmeFile::addSection($updated, "## Post\n\n其他\n"))->toBeNull();
});

it('appends the section when there is no chapter on deleting the module', function (): void {
    expect(ModuleReadmeFile::addSection("# Blog\n", "## Post\n\n内容"))->toBe("# Blog\n\n## Post\n\n内容\n");
});
