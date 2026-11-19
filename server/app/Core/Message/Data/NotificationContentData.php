<?php

namespace App\Core\Message\Data;

use App\Core\Message\Enums\NotificationLevel;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Uri\Rfc3986\Uri;

/**
 * 通知中心里一条通知的内容（设计文档 §6.9）：InboxNotification::toInbox() 的返回值，存入 notifications.data，结构对所有模块统一。
 *
 * - title：必填，最长 255：前端的 i18n key 或纯文本（后端不翻译）；
 * - content：可空，最长 1000：i18n key 或纯文本（不是 HTML）；
 * - level：NotificationLevel，默认 info；
 * - link：可空：前端路由（以 `/` 开头，不能是 `//`）或 http / https 网址，最长 2048；
 * - params：title、content 的翻译参数，`{ 名称: 标量或 null }`（类型由 PHPStan 检查）；
 * - subject：可空：通知所关于的记录，存为 `{ type: 多态别名, id }`，只供后端按记录查找、清理通知（Notification::about()），不输出。
 *
 * 构造时校验，不符合时抛出 InvalidArgumentException（开发期的错误：发送即失败，不会写入结构不对的通知）。
 */
final readonly class NotificationContentData
{
    public const int TITLE_MAX_LENGTH = 255;

    public const int CONTENT_MAX_LENGTH = 1000;

    public const int LINK_MAX_LENGTH = 2048;

    /**
     * @param  array<string, scalar|null>  $params
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $title,
        public ?string $content = null,
        public NotificationLevel $level = NotificationLevel::Info,
        public ?string $link = null,
        public array $params = [],
        public ?Model $subject = null,
    ) {
        if (trim($title) === '' || mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw new InvalidArgumentException('The notification title must be a non-empty string of at most '.self::TITLE_MAX_LENGTH.' characters.');
        }

        if ($content !== null && mb_strlen($content) > self::CONTENT_MAX_LENGTH) {
            throw new InvalidArgumentException('The notification content must be at most '.self::CONTENT_MAX_LENGTH.' characters.');
        }

        if ($link !== null && ! self::isValidLink($link)) {
            throw new InvalidArgumentException("The notification link [{$link}] must be a frontend route starting with / or an http(s) URL.");
        }

        if ($subject instanceof Model && ! $subject->exists) {
            throw new InvalidArgumentException('The notification subject must be a saved model.');
        }
    }

    /**
     * 链接是否合法：前端路由（`/` 开头，不能是协议相对的 `//`），或带主机名的 http / https 网址。
     */
    public static function isValidLink(string $link): bool
    {
        if ($link === '' || strlen($link) > self::LINK_MAX_LENGTH) {
            return false;
        }

        if (str_starts_with($link, '/')) {
            return ! str_starts_with($link, '//') && ! str_contains($link, '\\');
        }

        $uri = Uri::parse($link);

        return $uri instanceof Uri && in_array(strtolower((string) $uri->getScheme()), ['http', 'https'], true) && ($uri->getHost() ?? '') !== '';
    }

    /**
     * 存入 notifications.data 的内容（database 通道）。
     *
     * @return array{title: string, content: ?string, level: string, link: ?string, params: array<string, scalar|null>, subject: array{type: string, id: int|string}|null}
     */
    public function toDatabase(): array
    {
        return [...$this->toPayload(), 'subject' => $this->subjectReference()];
    }

    /**
     * 对外的内容（接口输出与广播推送的字段，不含 subject）。
     *
     * @return array{title: string, content: ?string, level: string, link: ?string, params: array<string, scalar|null>}
     */
    public function toPayload(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'level' => $this->level->value,
            'link' => $this->link,
            'params' => $this->params,
        ];
    }

    /**
     * subject 的引用：多态别名（enforceMorphMap 下总是别名，§4.5）与主键。
     *
     * @return array{type: string, id: int|string}|null
     */
    private function subjectReference(): ?array
    {
        if (! $this->subject instanceof Model) {
            return null;
        }

        $key = $this->subject->getKey();

        return ['type' => $this->subject->getMorphClass(), 'id' => is_int($key) ? $key : (string) (is_scalar($key) ? $key : '')];
    }
}
