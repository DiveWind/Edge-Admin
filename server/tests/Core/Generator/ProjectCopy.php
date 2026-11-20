<?php

namespace Tests\Core\Generator;

use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 端到端检查用的项目副本（tests/Core/Generator/EndToEndTest.php）：系统临时目录下一份完整的 server/，在其中执行生成命令与全部检查，
 * 不触碰真实的工作区；delete() 删除整个副本。
 *
 * - 复制项目文件，跳过 .git、日志、缓存、本地的 .env 与 SQLite 库（副本中的命令显式给出环境变量）；
 * - vendor 用硬链接（不占空间，复制很快；跨文件系统时退回复制）：副本中的命令只读 vendor，不会经由硬链接改动真实的依赖。
 *   vendor 中的 .git 目录（以源码方式安装的包）跳过。
 */
final readonly class ProjectCopy
{
    /**
     * 不复制的文件与目录（相对项目根目录）。
     *
     * @var list<string>
     */
    private const array SKIPPED = [
        '.env',
        '.phpunit.cache',
        '.phpunit.result.cache',
        'bootstrap/cache/config.php',
        'database/database.sqlite',
        'node_modules',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        'storage/logs',
    ];

    /**
     * 不复制的文件名前缀（路由缓存 bootstrap/cache/routes-*.php）。
     *
     * @var list<string>
     */
    private const array SKIPPED_PREFIXES = ['bootstrap/cache/routes'];

    private function __construct(public string $path, public string $source) {}

    public static function create(string $source): self
    {
        $copy = new self(sys_get_temp_dir().'/core-generator-e2e-'.Str::lower(Str::random(16)), rtrim($source, '/'));
        $files = new Filesystem;

        $copy->mirror($copy->source, $copy->path, link: false);

        foreach (['storage/framework/cache', 'storage/framework/sessions', 'storage/framework/testing', 'storage/framework/views', 'storage/logs'] as $directory) {
            $files->ensureDirectoryExists($copy->path($directory));
        }

        return $copy;
    }

    public function path(string $file = ''): string
    {
        return $this->path.($file === '' ? '' : '/'.$file);
    }

    /**
     * 在副本中执行命令。
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    public function run(array $command, array $env = [], int $timeout = 600): ProcessResult
    {
        return Process::path($this->path)->timeout($timeout)->env($env)->run($command);
    }

    /**
     * 副本中某个目录下的文件（相对路径）。
     *
     * @return list<string>
     */
    public function files(string $directory): array
    {
        if (! is_dir($this->path($directory))) {
            return [];
        }

        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path($directory), FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = Str::after($file->getPathname(), $this->path.'/');
            }
        }

        sort($files);

        return $files;
    }

    public function delete(): void
    {
        new Filesystem()->deleteDirectory($this->path);
    }

    /**
     * 复制目录：vendor 中的文件用硬链接，其余复制。
     */
    private function mirror(string $from, string $to, bool $link): void
    {
        new Filesystem()->ensureDirectoryExists($to);

        foreach (new FilesystemIterator($from, FilesystemIterator::SKIP_DOTS) as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $relative = Str::after($item->getPathname(), $this->source.'/');
            $target = $to.'/'.$item->getFilename();

            if ($item->getFilename() === '.git' || in_array($relative, self::SKIPPED, true) || array_any(self::SKIPPED_PREFIXES, fn (string $prefix): bool => str_starts_with($relative, $prefix))) {
                continue;
            }

            if ($item->isDir() && ! $item->isLink()) {
                $this->mirror($item->getPathname(), $target, $link || $relative === 'vendor');

                continue;
            }

            if ($item->isLink()) {
                symlink((string) readlink($item->getPathname()), $target);
            } elseif (! $link || ! @link($item->getPathname(), $target)) {
                copy($item->getPathname(), $target);
            }
        }
    }
}
