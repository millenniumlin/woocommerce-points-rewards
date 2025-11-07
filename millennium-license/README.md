# Millennium License Manager

整合的 WordPress 授權系統外掛，包含 WooCommerce 整合功能。

## 功能特色

### 🔑 授權碼管理
- 自動生成唯一授權碼
- 可自訂授權碼格式（例如：XXXX-XXXX-XXXX-XXXX）
- 授權碼狀態管理（啟用、停用、過期）
- 完整的授權碼生命週期管理

### 🛒 WooCommerce 整合
- 產品授權設定
- 訂單完成後自動生成授權碼
- 支援多個授權碼產品
- 訂單郵件包含授權碼
- 我的帳戶頁面授權碼管理

### 🔌 REST API
- 完整的 REST API 支援
- 授權碼驗證端點
- 授權碼啟用/停用端點
- 安全的 API 認證機制
- 詳細的 API 日誌記錄

### 📊 管理功能
- 直觀的管理介面
- 授權碼列表和搜尋
- 批次操作支援
- 詳細的啟用記錄
- 完整的日誌追蹤

### 📧 通知系統
- 自動郵件通知
- 可自訂郵件模板
- HTML 郵件支援

## 系統需求

- WordPress 6.0 或更高版本
- WooCommerce 8.0 或更高版本
- PHP 8.0 或更高版本
- MySQL 5.7+ 或 MariaDB 10.2+

## 安裝說明

1. 將 `millennium-license` 資料夾上傳到 `/wp-content/plugins/` 目錄
2. 在 WordPress 管理後台啟用外掛
3. 前往 **授權管理 > 設定** 進行基本設定
4. 為產品啟用授權功能

## 快速開始

### 為產品啟用授權功能

1. 前往 **產品 > 所有產品**
2. 編輯或新增產品
3. 在產品資料中找到「授權設定」標籤
4. 勾選「啟用授權功能」
5. 設定：
   - 最大啟用次數
   - 有效期限
   - 授權碼數量
6. 儲存產品

### 手動建立授權碼

1. 前往 **授權管理 > 新增授權碼**
2. 選擇產品（選填）
3. 輸入用戶 ID（選填）
4. 設定最大啟用次數
5. 設定到期時間（選填）
6. 點擊「建立授權碼」

## API 使用說明

### 認證方式

在請求標頭中加入 API 密鑰：

```
X-API-Key: YOUR_API_KEY
```

API 密鑰可在 **授權管理 > 設定** 頁面取得。

### API 端點

#### 1. 驗證授權碼

```http
POST /wp-json/millennium-license/v1/validate
Content-Type: application/json

{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://example.com",
  "instance_id": "unique-instance-id"
}
```

**回應範例：**

```json
{
  "success": true,
  "valid": true,
  "message": "授權碼有效",
  "data": {
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "status": "active",
    "expires_at": "2025-12-31 23:59:59",
    "max_activations": 5,
    "activation_count": 2
  }
}
```

#### 2. 啟用授權碼

```http
POST /wp-json/millennium-license/v1/activate
Content-Type: application/json

{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://example.com",
  "instance_id": "unique-instance-id"
}
```

**回應範例：**

```json
{
  "success": true,
  "activated": true,
  "message": "授權碼已啟用",
  "activation_token": "abc123...",
  "data": {
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "expires_at": "2025-12-31 23:59:59"
  }
}
```

#### 3. 停用授權碼

```http
POST /wp-json/millennium-license/v1/deactivate
Content-Type: application/json

{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://example.com",
  "instance_id": "unique-instance-id"
}
```

#### 4. 檢查授權狀態

```http
POST /wp-json/millennium-license/v1/check
Content-Type: application/json

{
  "activation_token": "abc123..."
}
```

## 資料庫結構

### millennium_licenses

授權碼主表

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint(20) | 主鍵 |
| license_key | varchar(255) | 授權碼 |
| product_id | bigint(20) | 產品 ID |
| order_id | bigint(20) | 訂單 ID |
| user_id | bigint(20) | 用戶 ID |
| status | varchar(20) | 狀態 |
| max_activations | int(11) | 最大啟用次數 |
| activation_count | int(11) | 已啟用次數 |
| expires_at | datetime | 到期時間 |
| created_at | datetime | 建立時間 |
| updated_at | datetime | 更新時間 |

### millennium_license_activations

授權啟用記錄表

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint(20) | 主鍵 |
| license_id | bigint(20) | 授權碼 ID |
| activation_token | varchar(255) | 啟用令牌 |
| site_url | varchar(255) | 站點 URL |
| instance_id | varchar(255) | 實例 ID |
| activated_at | datetime | 啟用時間 |
| last_checked | datetime | 最後檢查時間 |
| status | varchar(20) | 狀態 |
| metadata | longtext | 額外資料 |

### millennium_license_logs

授權操作日誌表

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | bigint(20) | 主鍵 |
| license_id | bigint(20) | 授權碼 ID |
| action | varchar(50) | 操作類型 |
| ip_address | varchar(45) | IP 位址 |
| user_agent | text | User Agent |
| metadata | longtext | 額外資料 |
| created_at | datetime | 建立時間 |

## 開發指南

### Hooks 和 Filters

#### Actions

```php
// 授權碼建立後
do_action('millennium_license_created', $license_id, $license);

// 授權碼啟用後
do_action('millennium_license_activated', $license_id, $activation_token);

// 授權碼停用後
do_action('millennium_license_deactivated', $license_id);

// 訂單生成授權碼後
do_action('millennium_license_order_licenses_generated', $order_id, $license_keys);
```

#### Filters

```php
// 修改授權碼格式
add_filter('millennium_license_key_format', function($format) {
    return 'XXXX-XXXX-XXXX-XXXX-XXXX';
});

// 修改預設到期天數
add_filter('millennium_license_default_expiry_days', function($days) {
    return 730; // 2 years
});

// 自訂 API 認證
add_filter('millennium_license_api_authenticate', function($authenticated) {
    // Your custom authentication logic
    return $authenticated;
});
```

### 使用 PHP 程式碼操作授權碼

```php
// 獲取授權管理器實例
$manager = Millennium_License_Manager_Core::instance();

// 建立授權碼
$license = $manager->create_license(array(
    'product_id' => 123,
    'user_id' => 1,
    'max_activations' => 5,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
));

// 取得授權碼
$license = $manager->get_license_by_key('XXXX-XXXX-XXXX-XXXX');

// 更新授權碼
$manager->update_license($license->id, array(
    'status' => 'inactive'
));

// 刪除授權碼
$manager->delete_license($license->id);

// 驗證授權碼
$result = Millennium_License_Key::validate('XXXX-XXXX-XXXX-XXXX');
if ($result['valid']) {
    // 授權碼有效
}

// 啟用授權碼
$result = Millennium_License_Key::activate(
    'XXXX-XXXX-XXXX-XXXX',
    'https://example.com',
    'unique-instance-id'
);
```

## 相容性

- ✅ PHP 8.0, 8.1, 8.2, 8.3
- ✅ WordPress 6.0 - 6.8.3
- ✅ WooCommerce 8.0 - 10.3.4
- ✅ WooCommerce HPOS（高效能訂單儲存）
- ✅ WordPress Multisite

## 安全性

- 授權碼使用安全的隨機字元生成
- API 支援多種認證方式
- 完整的日誌記錄追蹤
- 防止 SQL 注入攻擊
- XSS 防護
- CSRF 保護

## 效能最佳化

- 資料庫索引優化
- 快取機制支援
- 批次操作支援
- 最小化資料庫查詢

## 授權條款

本外掛採用 GPLv2 或更高版本授權。

## 貢獻

歡迎提交 Issue 和 Pull Request！

## 支援

如有任何問題或需要協助，請透過以下方式聯繫：

- GitHub Issues: [https://github.com/millenniumlin/millennium-license/issues](https://github.com/millenniumlin/millennium-license/issues)
- Email: support@example.com

## 更新日誌

### 1.0.0 (2024-01-01)
- 首次發布
- 完整的授權碼管理系統
- WooCommerce 整合
- REST API 支援
- HPOS 相容性
