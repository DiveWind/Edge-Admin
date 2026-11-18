<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 后台入口（Admin）路由
|--------------------------------------------------------------------------
|
| 由 AdminServiceProvider 加载：URL 前缀 config('core.apps.admin.prefix')，中间件组 admin，路由名前缀 admin.。
| 本文件只描述结构（分组导入各模块的控制器、apiResources）；授权写在控制器方法的 #[Authorize] 上（设计文档 §10.1）。
|
*/

// 验证骨架用的示例路由，接入第一个模块后删除
Route::get('ping', fn () => response()->vben(['pong' => true]))->name('ping');
