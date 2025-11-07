=== Millennium License Manager ===
Contributors: millenniumlim
Tags: license, woocommerce, licensing, software license, api
Requires at least: 6.0
Tested up to: 6.8.3
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

整合的 WordPress 授權系統外掛，包含 WooCommerce 整合功能。

== Description ==

Millennium License Manager 是一個功能完整的授權碼管理系統，專為 WordPress 和 WooCommerce 設計。

= 主要功能 =

* **授權碼生成和管理** - 自動生成唯一的授權碼
* **WooCommerce 整合** - 與 WooCommerce 產品和訂單完全整合
* **REST API** - 完整的 REST API 支援授權驗證和管理
* **授權啟用追蹤** - 追蹤每個授權碼的啟用狀態和位置
* **到期管理** - 靈活的授權到期時間設定
* **郵件通知** - 自動發送授權碼給客戶
* **我的帳戶整合** - 客戶可在帳戶中查看和管理授權碼
* **HPOS 相容** - 支援 WooCommerce 高效能訂單儲存
* **多語言支援** - 內建繁體中文翻譯

= 技術特點 =

* PHP 8.0+ 相容
* WordPress 6.0+ 相容
* WooCommerce 8.0+ 相容
* 支援 WooCommerce HPOS
* 安全的 REST API 認證
* 完整的日誌記錄

== Installation ==

1. 上傳 `millennium-license` 資料夾到 `/wp-content/plugins/` 目錄
2. 在 WordPress 管理後台啟用外掛
3. 前往 **授權管理 > 設定** 進行初始設定
4. 為產品啟用授權功能（在產品編輯頁面的「授權設定」標籤）

== Frequently Asked Questions ==

= 如何為產品啟用授權功能？ =

在產品編輯頁面中，前往「授權設定」標籤，勾選「啟用授權功能」並設定相關選項。

= 如何使用 API？ =

前往 **授權管理 > 設定** 取得 API 密鑰，然後使用 REST API 端點進行授權驗證和管理。

= 授權碼何時會生成？ =

當訂單狀態變更為「已完成」時，系統會自動為啟用授權功能的產品生成授權碼。

= 支援多站點啟用嗎？ =

是的，每個授權碼可以設定最大啟用次數，支援在多個站點或裝置上啟用。

== Screenshots ==

1. 授權碼管理介面
2. 產品授權設定
3. 我的帳戶授權頁面
4. API 設定頁面

== Changelog ==

= 1.0.0 =
* 首次發布
* 授權碼生成和管理功能
* WooCommerce 整合
* REST API 支援
* 郵件通知功能
* 我的帳戶整合
* HPOS 相容性

== Upgrade Notice ==

= 1.0.0 =
首次發布版本

== API Documentation ==

= Authentication =

在請求標頭中加入 `X-API-Key: YOUR_API_KEY` 進行認證。

= Endpoints =

**驗證授權碼**
POST `/wp-json/millennium-license/v1/validate`
參數：license_key, site_url, instance_id

**啟用授權碼**
POST `/wp-json/millennium-license/v1/activate`
參數：license_key, site_url, instance_id

**停用授權碼**
POST `/wp-json/millennium-license/v1/deactivate`
參數：license_key, site_url, instance_id

**檢查授權狀態**
POST `/wp-json/millennium-license/v1/check`
參數：activation_token

詳細 API 文件請參考外掛設定頁面。
