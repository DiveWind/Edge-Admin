# Message

内核模块，目录 `app/Core/Message`。结构与规则见设计文档 §4.3、§5；接口与规则见 §6.9。

## 职责

- 通知中心：每个用户自己的站内通知（Laravel 数据库通知，官方 `notifications` 表），各模块的通知统一出现在这里；
  通知类的基类 `Notifications/InboxNotification`（固定的内容结构、对外的类型名、停用用户不接收）；已读通知的定期清理；
- 公告：草稿 → 已发布 → 已撤回，目标为全部用户或指定角色，富文本正文（保存时净化）与附件；
  发布后由队列分批（`Bus::batch`）写入接收人的通知中心，撤回时删除已发出的通知；“我收到的公告”。

不负责：接口的路由与输出格式（`app/Http/Admin/Message`）；实时推送（Reverb 是可选包，默认不安装，见下文）；邮件等其他通道的模板。

## 对外提供

| 类 | 用途 |
|---|---|
| `Notifications/InboxNotification` | 通知中心的通知的基类（扩展点）：子类实现 `type()`（对外的类型名）与 `toInbox()`（内容）；`databaseType()` / `broadcastType()` 返回类型名，`toDatabase()` / `toArray()` 由内容生成（都是 `final`）；`via()` 默认 `['database']`；`shouldSend()`（`final`）对停用的用户返回 false |
| `Data/NotificationContentData` | 一条通知的内容：`title`、`content`、`level`、`link`、`params`、`subject`；构造时校验（见下文） |
| `Enums/NotificationLevel` | 通知与公告的级别：`info`、`success`、`warning`、`error`（`HasColor`，`/options/enums` 的 `notification_level`） |
| `Models/Notification` | 通知（继承框架的 `DatabaseNotification`，同一张表，多态别名 `notification`）：`ownedBy($user)` / `isOwnedBy($user)`、`about($record)`（按 `data->subject` 查找关于某条记录的通知）、`isRead()`；`MassPrunable`（已读且超过 `core.message.notifications.read_ttl` 天） |
| `Policies/NotificationPolicy` | 只有本人能查看、标记已读、删除（不需要权限码，管理员也不能操作他人的通知） |
| `Actions/MarkNotificationAsRead`、`MarkAllNotificationsAsRead`、`DeleteNotification` | 标记已读（幂等）、本人全部已读、删除 |
| `Actions/DeleteUserNotifications`、`Listeners/DeleteNotificationsOfDeletedUser` | 用户被删除时删除他的通知（监听 `User` 的 `deleted` 模型事件） |
| `Models/Announcement` | 公告（多态别名 `announcement`）：`content` 写入时净化（`App\Support\Eloquent\Casts\SanitizedHtml`）；`HasDataScope`（`created_by`）、`HasAttachments`（用途 `attachments`，`files()`）；`recipients()`（接收人）、`receivedBy($user)` / `isReceivedBy($user)`（是否发给了某人）、`excerpt()`（正文的纯文本摘要） |
| `Enums/AnnouncementStatus`、`Enums/AnnouncementTarget` | 状态 `draft` / `published` / `revoked`（`HasColor`）；目标 `all` / `roles` |
| `Actions/CreateAnnouncement`、`UpdateAnnouncement`、`DeleteAnnouncement` | 新增草稿、修改草稿、删除草稿或已撤回的公告；目标角色按 `target` 整体替换，附件经 `SyncAttachments` 绑定 |
| `Actions/PublishAnnouncement` | 发布：锁行复核草稿 → `published` → 派发 `Jobs/SendAnnouncement`（事务提交后入队） |
| `Jobs/SendAnnouncement`、`Jobs/SendAnnouncementChunk` | 分批发送：按 `core.message.announcements.batch_size` 个接收人一批派发 `Bus::batch`（`announcement:<id>`，`allowFailures()`）；每批锁住公告行复核仍已发布、重新筛选用户后 `sendNow()`，整批一个事务 |
| `Notifications/AnnouncementPublished` | 公告的通知（类型 `message.announcement`，`subject` 为公告，`link` 为 `core.message.announcements.link`） |
| `Actions/RevokeAnnouncement` | 撤回：锁行复核已发布 → `revoked`，同一事务中删除已发出的通知；撤回是终态 |
| `Policies/AnnouncementPolicy` | 公告管理（`message.announcement.*`、状态规则、数据范围）与 `read`（我收到的公告：已发布且目标包含操作者） |

- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；接口放在各受信任入口的同名目录：`app/Http/Admin/Message`。
- 受限的入口只通过 `Contracts` 与 `Data` 访问（`php artisan core:make-contract`）。

## 模块怎么接入通知中心（扩展点，设计文档 §5.5）

1. 定义通知类，继承 `InboxNotification`（`php artisan make:notification App/Modules/Content/Notifications/ArticleApproved`，再改为继承它）：

   ```php
   namespace App\Modules\Content\Notifications;

   use App\Core\Message\Data\NotificationContentData;
   use App\Core\Message\Enums\NotificationLevel;
   use App\Core\Message\Notifications\InboxNotification;
   use App\Modules\Content\Models\Article;

   class ArticleApproved extends InboxNotification
   {
       public function __construct(private readonly Article $article) {}

       public static function type(): string
       {
           return 'content.article_approved';          // 对外的类型名：小写点分、以模块名开头、全局唯一
       }

       public function toInbox(object $notifiable): NotificationContentData
       {
           return new NotificationContentData(
               title: 'content.notification.articleApproved',   // 前端的 i18n key 或纯文本
               content: null,                                   // 可选：i18n key 或纯文本（不是 HTML）
               level: NotificationLevel::Success,
               link: "/content/article/{$this->article->id}",   // 前端路由（/ 开头）或 http(s) 网址
               params: ['title' => $this->article->title],      // 翻译参数
               subject: $this->article,                         // 可选：按记录清理时用 Notification::about($article)
           );
       }
   }
   ```

2. 发送：`$user->notify(new ArticleApproved($article));`，或 `Notification::send($users, ...)`。接收人很多时让通知类实现 `ShouldQueue`（基类已使用 `Queueable`），或像公告一样自己分批。
3. 需要时清理：记录被删除时 `Notification::query()->about($article)->delete()`（在模块自己的 Action 中）。
4. 前端按 `type` 区分图标与筛选、翻译类型名；`title`、`content` 用 `$t(key, params)` 显示，点击时跳转 `link`。

`tests/Arch` 强制：`app/` 中 `Illuminate\Notifications\Notification` 的子类都继承 `InboxNotification`，`type()` 符合 `InboxNotification::TYPE_PATTERN` 且全局唯一。

**内容的规则**（`NotificationContentData` 构造时校验，不符合时抛出 `InvalidArgumentException`）：`title` 必填、最长 255；`content` 最长 1000；
`link` 为 `/` 开头的前端路由（不能是 `//`）或带主机名的 `http` / `https` 网址，最长 2048；`params` 为 `{ 名称: 标量或 null }`；`subject` 为已保存的模型。

**实时推送**：项目装了 Reverb（设计文档 §12，可选）后，在通知类的 `via()` 中加上 `'broadcast'` 即可，载荷就是 `toArray()`（字段同接口输出，外加 `id`、`type`）；
频道由 `User::receivesBroadcastNotificationsOn()` 决定（框架默认用类名，启用时改为 `user.{id}`，并在 `routes/channels.php` 中只授权本人）。

## 依赖 Core 的能力

- System：`User`（接收人；停用的不接收）、`Role`（公告的目标角色）；菜单与权限节点（`MenuRegistry`，`resources/menus.php`）；
  数据权限（`HasDataScope`，公告按 `created_by`）；附件（`HasAttachments`、`OwnedAttachment`、`SyncAttachments`）；枚举选项（`EnumRegistry`）。
- Support：富文本净化 `Eloquent/Casts/SanitizedHtml`（`symfony/html-sanitizer`，规则在 `AppServiceProvider` 中绑定）。
- 配置（部署级，`config/core.php` 的 `message`）：`notifications.read_ttl`（已读通知保留天数）、`announcements.batch_size`（每批人数）、`announcements.link`（公告通知的前端链接）。
- 调度：`routes/console.php` 每天 `model:prune --model=Notification`。队列：默认队列（公告的分批发送），批处理使用框架的 `job_batches` 表。

## 生成命令

```bash
php artisan core:make-model <名称> --module=Message --core       # 模型、迁移、工厂、Policy，登记多态别名
php artisan core:make-action <名称> --module=Message --core      # 写操作
php artisan core:make-query <名称> --module=Message --core       # 复杂查询、统计
php artisan core:make-enum <名称> --module=Message --core        # 枚举（实现 HasLabel）
php artisan core:make-contract <名称> --module=Message --core    # 向受限入口开放的契约
php artisan core:make-controller <名称> --module=Message --core --model=<模型>   # 后台接口
```
