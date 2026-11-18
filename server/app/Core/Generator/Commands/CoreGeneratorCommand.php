<?php

namespace App\Core\Generator\Commands;

use App\Core\Generator\Editing\{PhpImports, UnexpectedFormatException};
use Closure;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * core:make-* 生成命令的基类，在官方 GeneratorCommand 之上统一：
 *
 * - 模板：项目的 `stubs/core.<名称>.stub` 优先，否则用本模块 `stubs/<名称>.stub`（`php artisan stub:publish` 会一并发布）；
 * - 名称：按 StudlyCase 规范化并补上类型后缀（如 Controller），不允许子目录；
 * - 导入：按 Pint 的格式分组、排序（生成物直接通过 `composer lint`）；
 * - 退出码：失败（已存在、输入错误、无法登记）时为 1（官方 make 命令为 0）；handle() 的返回值与官方一致（失败时为 false）；
 * - 修改共享文件（config/core.php 等）幂等，格式不符合预期时报错并打印需手工粘贴的内容。
 */
abstract class CoreGeneratorCommand extends GeneratorCommand
{
    /**
     * 本次执行是否失败（每次执行开始时重置），决定退出码。
     */
    protected bool $failed = false;

    /**
     * 模板名称，如 `action` 对应 `stubs/core.action.stub`。
     */
    abstract protected function stubName(): string;

    /**
     * 生成文件之后的处理（调用其他生成命令、登记到共享文件），返回退出码。
     */
    protected function afterGenerating(string $class, string $path): int
    {
        return self::SUCCESS;
    }

    /**
     * 输入错误时返回错误信息。
     */
    protected function inputError(): ?string
    {
        return $this->nameError($this->rawName());
    }

    /**
     * 模板中除 namespace / class 外的替换内容，键为 `{{ xxx }}` 中的名称。
     *
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [];
    }

    /**
     * 类名后缀（Controller、Request 等）：名称不以它结尾时自动补上。
     */
    protected function suffix(): string
    {
        return '';
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $this->failed = false;

        if (($error = $this->inputError()) !== null) {
            $this->components->error(self::punctuate($error));

            return $this->markFailed();
        }

        if (parent::handle() === false) {
            return $this->markFailed();
        }

        $class = $this->qualifyClass($this->getNameInput());

        return $this->afterGenerating($class, $this->getPath($class)) === self::SUCCESS ? null : $this->markFailed();
    }

    /**
     * 执行失败时退出码为 1（官方 make 命令的 handle() 返回 false，退出码仍为 0）。
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = parent::execute($input, $output);

        return $this->failed ? self::FAILURE : $status;
    }

    /**
     * 标记本次执行失败。
     */
    protected function markFailed(): false
    {
        $this->failed = true;

        return false;
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStub($this->stubName());
    }

    /**
     * 模板路径：项目的 `stubs/core.<名称>.stub` 优先。
     */
    protected function resolveStub(string $name): string
    {
        $custom = $this->laravel->basePath("stubs/core.{$name}.stub");

        return file_exists($custom) ? $custom : self::defaultStub($name);
    }

    /**
     * 命令自带的默认模板。
     */
    public static function defaultStub(string $name): string
    {
        return dirname(__DIR__)."/stubs/{$name}.stub";
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        return $this->replace(parent::buildClass($name), $this->replacements($name));
    }

    /**
     * 按 Pint 的格式整理导入（替代官方的简单排序）。
     *
     * @param  string  $stub
     */
    protected function sortImports($stub): string
    {
        return PhpImports::normalize($stub);
    }

    /**
     * 规范化后的类名：StudlyCase，并补上后缀。
     */
    protected function getNameInput(): string
    {
        $name = Str::studly($this->rawName());

        return $this->suffix() === '' || str_ends_with($name, $this->suffix()) ? $name : $name.$this->suffix();
    }

    /**
     * 用户输入的名称（去掉空白与 .php 后缀）。
     */
    protected function rawName(): string
    {
        return Str::of($this->argument('name'))->trim()->chopEnd('.php')->toString();
    }

    /**
     * 名称是否合法：单段、StudlyCase 后是合法的 PHP 类名、不是保留字。
     */
    protected function nameError(string $name, string $label = '名称'): ?string
    {
        if (str_contains($name, '/') || str_contains($name, '\\')) {
            return "{$label}“{$name}”不能包含子目录或命名空间：目录由 --module、--app 等选项决定。";
        }

        $studly = Str::studly($name);

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly) !== 1) {
            return "{$label}“{$name}”不合法：应由字母和数字组成且以字母开头，如 Article。";
        }

        if ($this->isReservedName($studly)) {
            return "{$label}“{$name}”是 PHP 的保留字。";
        }

        return null;
    }

    /**
     * 字符串选项（未提供或为空时返回 null）。
     */
    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * 按模板生成非类文件（路由文件、测试、README 等），遵循 --force；返回是否已生成。
     *
     * @param  array<string, string>  $replacements
     */
    protected function writeFromStub(string $stub, string $path, array $replacements, string $type): bool
    {
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->error("{$type} [{$this->relativePath($path)}] already exists.");

            return false;
        }

        $this->makeDirectory($path);
        $this->files->put($path, $this->replace($this->files->get($this->resolveStub($stub)), $replacements));
        $this->components->info(sprintf('%s [%s] created successfully.', $type, $this->relativePath($path)));

        return true;
    }

    /**
     * 按另一个模板生成一个类（如契约的实现），遵循 --force；返回是否已生成。
     *
     * @param  array<string, string>  $replacements
     */
    protected function writeClass(string $stub, string $class, array $replacements, string $type): bool
    {
        $path = $this->getPath($class);

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->error("{$type} [{$this->relativePath($path)}] already exists.");

            return false;
        }

        $code = $this->files->get($this->resolveStub($stub));

        $this->replaceNamespace($code, $class);

        $this->makeDirectory($path);
        $this->files->put($path, $this->sortImports($this->replace($this->replaceClass($code, $class), $replacements)));
        $this->components->info(sprintf('%s [%s] created successfully.', $type, $this->relativePath($path)));

        return true;
    }

    /**
     * 修改共享文件（幂等）。$edit 返回新内容，已登记时返回 null；格式不符合预期时报错并打印需手工粘贴的内容。
     *
     * @param  Closure(string): ?string  $edit
     */
    protected function register(string $path, string $what, Closure $edit): void
    {
        $file = $this->relativePath($path);

        if (! $this->files->exists($path)) {
            try {
                $edit('');
                $snippet = '';
            } catch (UnexpectedFormatException $e) {
                $snippet = $e->snippet;
            }

            $this->failRegistration("找不到 [{$file}]，无法登记 {$what}".($snippet === '' ? '' : '。请手工添加'), $snippet);

            return;
        }

        try {
            $updated = $edit($this->files->get($path));
        } catch (UnexpectedFormatException $e) {
            $this->failRegistration("无法在 [{$file}] 中登记 {$what}：{$e->getMessage()}。请手工添加", $e->snippet);

            return;
        }

        if ($updated === null) {
            $this->components->twoColumnDetail("[{$file}] {$what}", '<fg=yellow;options=bold>已登记过，跳过</>');

            return;
        }

        $this->files->put($path, $updated);
        $this->components->twoColumnDetail("[{$file}] {$what}", '<fg=green;options=bold>已登记</>');
    }

    /**
     * 登记失败：报错并打印需手工粘贴的内容，命令最终返回失败。
     */
    protected function failRegistration(string $message, string $snippet): void
    {
        $this->failed = true;
        $this->components->error(self::punctuate($message.($snippet === '' ? '。' : '：')));

        if ($snippet !== '') {
            $this->line($snippet);
            $this->newLine();
        }
    }

    /**
     * 调用另一个生成命令，返回其退出码。
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function generate(string $command, array $arguments): int
    {
        return $this->call($command, [...$arguments, '--force' => (bool) $this->option('force')]);
    }

    /**
     * 调用另一个生成命令；$path 已存在且未加 --force 时跳过（编排多个文件时，已有的文件不视为错误）。
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function generateUnlessExists(string $command, array $arguments, string $path): int
    {
        if (! $this->option('force') && $path !== '' && $this->files->exists($path)) {
            $this->components->twoColumnDetail("[{$this->relativePath($path)}]", '<fg=yellow;options=bold>已存在，跳过</>');

            return self::SUCCESS;
        }

        return $this->generate($command, $arguments);
    }

    /**
     * config/core.php 中登记的入口：键 => 是否受信任。直接读取文件，不受配置缓存与本进程 CORE_APPS 的影响。
     *
     * @return array<string, bool>
     */
    protected function registeredApps(): array
    {
        $path = config_path('core.php');
        $config = is_file($path) ? $this->files->getRequire($path) : [];
        $apps = is_array($config) && is_array($config['apps'] ?? null) ? $config['apps'] : [];
        $trusted = [];

        foreach ($apps as $key => $app) {
            $trusted[(string) $key] = is_array($app) && ($app['trusted'] ?? false) === true;
        }

        return $trusted;
    }

    /**
     * 相对项目根目录的路径（用于提示）。
     */
    protected function relativePath(string $path): string
    {
        return Str::after($path, rtrim($this->laravel->basePath(), '/').'/');
    }

    /**
     * 控制台组件会给不以英文标点结尾的消息补上 `.`：中文的句号去掉（由组件补上），冒号换成英文冒号。
     */
    protected static function punctuate(string $message): string
    {
        return match (true) {
            str_ends_with($message, '。') => mb_substr($message, 0, -1),
            str_ends_with($message, '：') => mb_substr($message, 0, -1).':',
            default => $message,
        };
    }

    /**
     * 替换模板中的 `{{ xxx }}`。
     *
     * @param  array<string, string>  $replacements
     */
    protected function replace(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace(['{{ '.$key.' }}', '{{'.$key.'}}'], $value, $stub);
        }

        return $stub;
    }
}
