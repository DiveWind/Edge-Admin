<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\ModuleProviderFile;
use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-contract：模块向受限入口开放的能力（设计文档 §5.2、§5.8）：
 *
 * - Contracts/<名称>：接口，参数与返回值只用标量和本模块的 Data，不暴露模型；
 * - Data/<名称>Data：只读数据对象（调用 core:make-data）；
 * - Services/<名称>Service：接口的实现，读取本模块的模型并转换为 Data，写入调用本模块的 Action；
 * - 在模块的 ServiceProvider 的 `$bindings` 中绑定接口与实现。
 */
#[Signature('core:make-contract
    {name : 契约（接口）名，如 DeviceConfig}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成向受限入口开放的契约：接口、只读数据对象、实现，并在模块 Provider 中绑定')]
class ContractMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Contract';

    protected function stubName(): string
    {
        return 'contract';
    }

    protected function subNamespace(): string
    {
        return 'Contracts';
    }

    protected function example(): string
    {
        return 'DeviceConfig';
    }

    /**
     * 生成 Data 与实现，并登记绑定。已存在的文件（未加 --force 时）沿用。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        $name = class_basename($class);
        $service = $this->implementation($name);
        $status = $this->generateUnlessExists(
            'core:make-data',
            ['name' => $name, '--module' => $this->moduleName(), '--core' => (bool) $this->option('core')],
            $this->modulePath("Data/{$name}Data.php"),
        );

        if (! $this->option('force') && $this->files->exists($this->getPath($service))) {
            $this->components->twoColumnDetail("[{$this->relativePath($this->getPath($service))}]", '<fg=yellow;options=bold>已存在，跳过</>');
        } elseif (! $this->writeClass('contract.service', $service, $this->replacements($class), 'Service')) {
            $status = self::FAILURE;
        }

        $this->register($this->moduleProvider()['path'], "契约绑定 {$name}", fn (string $code): ?string => ModuleProviderFile::registerBinding($code, $class, $service));

        return $status;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        $name = class_basename($class);

        return [
            ...parent::replacements($class),
            'contract' => $name,
            'namespacedContract' => $this->moduleNamespace().'\\Contracts\\'.$name,
            'data' => $name.'Data',
            'namespacedData' => $this->moduleNamespace().'\\Data\\'.$name.'Data',
            'service' => class_basename($this->implementation($name)),
        ];
    }

    /**
     * 契约的实现类：Services/<名称>Service。
     */
    private function implementation(string $contract): string
    {
        return $this->moduleNamespace().'\\Services\\'.$contract.'Service';
    }
}
