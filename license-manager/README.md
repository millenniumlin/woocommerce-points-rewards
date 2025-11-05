# ML License Manager

授權碼管理系統，用於管理和驗證產品授權。

## 功能特色

### 🔑 授權碼管理
- 自動生成唯一授權碼
- 支援多種授權碼格式（英數字、十六進位）
- 可設定授權碼有效期限
- 啟用次數限制管理
- 授權碼狀態管理（啟用、未啟用、過期、撤銷）

### 📊 完整的管理介面
- 儀表板總覽統計
- 授權碼列表與管理
- 啟用記錄查詢
- 詳細的設定選項

### 🌐 REST API 支援
- 授權碼驗證 API
- 授權碼啟用 API
- 授權碼停用 API
- 授權碼狀態檢查 API

### 📝 完整的日誌記錄
- 授權碼活動日誌
- 啟用/停用記錄
- IP 地址追蹤

## 系統需求

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.2+

## 安裝說明

1. 下載外掛檔案
2. 上傳到 `/wp-content/plugins/license-manager/` 目錄
3. 在 WordPress 管理後台啟用外掛
4. 前往 **授權管理** 選單開始使用

## 資料庫結構

外掛啟用時會自動創建以下資料表：

### ml_license_keys（授權碼表）
儲存授權碼的基本資訊
- 授權碼
- 產品 ID
- 訂單 ID
- 用戶 ID
- 狀態
- 啟用次數限制
- 已啟用次數
- 過期時間

### ml_license_activations（啟用記錄表）
儲存授權碼的啟用記錄
- 授權碼 ID
- 啟用令牌
- 實例名稱
- 實例識別碼
- IP 地址
- 狀態
- 啟用/停用時間

### ml_license_logs（活動日誌表）
儲存授權碼的所有活動記錄
- 授權碼 ID
- 動作類型
- 描述
- IP 地址
- User Agent

### ml_license_meta（元數據表）
儲存授權碼的額外元數據
- 授權碼 ID
- 鍵
- 值

## 快速開始

### 1. 生成授權碼

前往 **授權管理 > 新增授權碼**，填寫以下資訊：

- **用戶**：選擇要分配此授權碼的用戶（可選）
- **產品ID**：關聯的產品ID（可選）
- **啟用次數限制**：此授權碼可以啟用的次數
- **過期時間**：授權碼的有效期限（留空表示永久有效）

點擊「生成授權碼」按鈕，系統會自動生成一個唯一的授權碼。

### 2. 管理授權碼

前往 **授權管理 > 授權碼** 查看所有授權碼列表，您可以：

- 查看授權碼狀態
- 查看啟用次數
- 刪除授權碼

### 3. 查看啟用記錄

前往 **授權管理 > 啟用記錄** 查看所有授權碼的啟用記錄，包括：

- 授權碼
- 實例名稱和ID
- IP 地址
- 啟用時間
- 最後檢查時間

### 4. 設定系統

前往 **授權管理 > 設定** 配置系統設定：

#### 基本設定
- 啟用授權系統
- 預設啟用次數限制
- 預設有效天數

#### 授權碼生成設定
- 授權碼長度（16-64 字符）
- 授權碼格式（英數字或十六進位）

#### API 設定
- 啟用 REST API
- 要求 API 驗證

## REST API 使用

### 驗證授權碼

```bash
POST /wp-json/ml-license/v1/validate
Content-Type: application/json

{
  "license_key": "YOUR_LICENSE_KEY"
}
```

**回應範例：**
```json
{
  "success": true,
  "message": "授權碼有效",
  "data": {
    "license_key": "YOUR_LICENSE_KEY",
    "status": "active",
    "expires_at": "2025-11-05 12:00:00",
    "activation_count": 1,
    "activation_limit": 5
  }
}
```

### 啟用授權碼

```bash
POST /wp-json/ml-license/v1/activate
Content-Type: application/json

{
  "license_key": "YOUR_LICENSE_KEY",
  "instance_name": "My Website",
  "instance_id": "unique-instance-id"
}
```

**回應範例：**
```json
{
  "success": true,
  "message": "授權碼啟用成功",
  "data": {
    "activation_token": "ACTIVATION_TOKEN",
    "license_key": "YOUR_LICENSE_KEY",
    "expires_at": "2025-11-05 12:00:00"
  }
}
```

### 停用授權碼

```bash
POST /wp-json/ml-license/v1/deactivate
Content-Type: application/json

{
  "activation_token": "ACTIVATION_TOKEN"
}
```

**回應範例：**
```json
{
  "success": true,
  "message": "授權碼停用成功"
}
```

### 檢查授權碼狀態

```bash
POST /wp-json/ml-license/v1/check
Content-Type: application/json

{
  "activation_token": "ACTIVATION_TOKEN"
}
```

**回應範例：**
```json
{
  "success": true,
  "data": {
    "is_valid": true,
    "license_key": "YOUR_LICENSE_KEY",
    "status": "active",
    "license_status": "active",
    "expires_at": "2025-11-05 12:00:00",
    "last_checked_at": "2024-11-05 11:22:13"
  }
}
```

## 程式開發指南

### 生成授權碼

```php
$license_manager = ML_License_Key::instance();

$license_key = $license_manager->generate_license_key(array(
    'product_id' => 123,
    'user_id' => 1,
    'activation_limit' => 5,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
));

if (is_wp_error($license_key)) {
    // 處理錯誤
    echo $license_key->get_error_message();
} else {
    // 成功生成
    echo "授權碼：" . $license_key;
}
```

### 驗證授權碼

```php
$license_manager = ML_License_Key::instance();

$result = $license_manager->validate_license('YOUR_LICENSE_KEY');

if (is_wp_error($result)) {
    // 驗證失敗
    echo $result->get_error_message();
} else {
    // 驗證成功
    echo "授權碼有效";
}
```

### 獲取授權碼資訊

```php
$license_manager = ML_License_Key::instance();

$license = $license_manager->get_license('YOUR_LICENSE_KEY');

if ($license) {
    echo "狀態：" . $license->status;
    echo "啟用次數：" . $license->activation_count . "/" . $license->activation_limit;
    echo "過期時間：" . $license->expires_at;
}
```

### 管理授權碼元數據

```php
$license_manager = ML_License_Key::instance();

// 設定元數據
$license_manager->update_license_meta($license_id, 'custom_field', 'custom_value');

// 獲取元數據
$value = $license_manager->get_license_meta($license_id, 'custom_field');
```

## 授權碼狀態說明

- **active**：啟用中，授權碼可以正常使用
- **inactive**：未啟用，授權碼尚未被啟用
- **expired**：已過期，授權碼已超過有效期限
- **revoked**：已撤銷，授權碼已被管理員撤銷

## 安全性

- 所有授權碼都是隨機生成的唯一字符串
- 啟用令牌使用 64 位隨機密碼
- 記錄所有 IP 地址和 User Agent 以便追蹤
- 支援 API 身份驗證（可在設定中啟用）

## 常見問題

### 如何重設授權碼的啟用次數？

目前需要直接在資料庫中修改 `activation_count` 欄位。未來版本會在管理介面中添加此功能。

### 授權碼可以綁定到特定域名嗎？

目前版本記錄了實例名稱和 ID，可以用於追蹤，但不強制綁定。未來版本會添加域名綁定功能。

### 如何批量生成授權碼？

目前只能通過管理介面逐個生成。可以通過程式碼使用 `ML_License_Key::instance()->generate_license_key()` 批量生成。

## 更新日誌

### 1.0.0
- 初始版本發布
- 基礎授權碼管理功能
- 資料庫表格創建
- 管理介面
- REST API 架構
- 授權碼驗證系統
- 啟用記錄追蹤

## 授權

GPL v2 or later

## 作者

millenniumlim

## 支援

如有問題或建議，請在 GitHub 上提交 Issue。
