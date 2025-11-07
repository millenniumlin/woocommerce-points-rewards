# Millennium License Manager - 安裝與使用指南

## 🚀 安裝步驟

### 方法 1：通過 WordPress 管理後台

1. 將整個 `millennium-license` 資料夾壓縮為 ZIP 檔案
2. 登入 WordPress 管理後台
3. 前往 **外掛 > 安裝外掛 > 上傳外掛**
4. 選擇 ZIP 檔案並上傳
5. 點擊「立即啟用」

### 方法 2：手動安裝

1. 將 `millennium-license` 資料夾上傳到 `/wp-content/plugins/` 目錄
2. 登入 WordPress 管理後台
3. 前往 **外掛 > 已安裝外掛**
4. 找到「Millennium License Manager」並點擊「啟用」

## ⚙️ 初始設定

### 1. 基本設定

啟用外掛後，前往 **授權管理 > 設定**：

1. **授權碼格式**
   - 預設：`XXXX-XXXX-XXXX-XXXX`
   - 可自訂，使用 `X` 代表隨機字元
   - 範例：`YYYY-XXXX-XXXX` 或 `XXXXXXXX`

2. **預設有效期限**
   - 設定新授權碼的預設有效天數
   - 留空或設為 0 表示永久有效

3. **預設最大啟用次數**
   - 設定每個授權碼可啟用的次數
   - 建議值：1-5

4. **啟用 API**
   - 勾選以啟用 REST API 功能
   - 取得 API 密鑰以供外部應用使用

5. **郵件通知**
   - 勾選以在訂單完成時自動發送授權碼給客戶

### 2. 產品設定

為產品啟用授權功能：

1. 前往 **產品 > 所有產品**
2. 編輯或新增產品
3. 在產品資料中找到「**授權設定**」標籤
4. 勾選「**啟用授權功能**」
5. 設定以下選項：
   - **最大啟用次數**：此產品的授權碼可啟用幾次
   - **有效期限（天）**：授權碼的有效期限
   - **授權碼數量**：每次購買生成幾個授權碼
6. 儲存產品

## 📝 使用方式

### 手動建立授權碼

1. 前往 **授權管理 > 新增授權碼**
2. 填寫表單：
   - **產品**：選擇關聯的產品（選填）
   - **用戶**：輸入用戶 ID（選填）
   - **最大啟用次數**：設定可啟用次數
   - **到期時間**：選擇到期時間（選填）
3. 點擊「**建立授權碼**」
4. 系統會自動生成唯一的授權碼

### 管理授權碼

前往 **授權管理 > 所有授權碼**：

- **查看**：檢視授權碼詳情
- **啟用/停用**：變更授權碼狀態
- **刪除**：永久刪除授權碼
- **批次操作**：選擇多個授權碼進行批次操作

### 授權碼自動生成流程

1. 客戶在網站上購買啟用授權功能的產品
2. 客戶完成付款
3. 訂單狀態變更為「已完成」
4. 系統自動生成授權碼
5. 授權碼會顯示在：
   - 訂單詳情頁面
   - 訂單完成郵件
   - 客戶的「我的帳戶 > 授權碼」頁面

## 🔌 API 使用

### 取得 API 密鑰

1. 前往 **授權管理 > 設定**
2. 在「API 設定」區塊找到 API 密鑰
3. 點擊複製

### API 端點

所有 API 請求都需要在標頭中包含 API 密鑰：

```
X-API-Key: YOUR_API_KEY
```

#### 1. 驗證授權碼

```bash
curl -X POST https://your-site.com/wp-json/millennium-license/v1/validate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "site_url": "https://client-site.com",
    "instance_id": "unique-id"
  }'
```

#### 2. 啟用授權碼

```bash
curl -X POST https://your-site.com/wp-json/millennium-license/v1/activate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "site_url": "https://client-site.com",
    "instance_id": "unique-id"
  }'
```

#### 3. 停用授權碼

```bash
curl -X POST https://your-site.com/wp-json/millennium-license/v1/deactivate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "site_url": "https://client-site.com",
    "instance_id": "unique-id"
  }'
```

#### 4. 檢查授權狀態

```bash
curl -X POST https://your-site.com/wp-json/millennium-license/v1/check \
  -H "Content-Type: application/json" \
  -d '{
    "activation_token": "token-from-activation"
  }'
```

## 👥 客戶使用方式

### 查看授權碼

客戶可以在以下位置查看他們的授權碼：

1. **訂單詳情頁面**
   - 前往「我的帳戶 > 訂單」
   - 點擊訂單查看詳情
   - 在「您的授權碼」區塊中查看

2. **授權碼專頁**
   - 前往「我的帳戶 > 授權碼」
   - 查看所有授權碼及其狀態

3. **訂單郵件**
   - 查收訂單完成郵件
   - 郵件中包含所有授權碼

### 使用授權碼

1. 複製授權碼
2. 在應用程式或軟體中輸入授權碼
3. 按照應用程式的指示完成啟用

## 🔧 進階功能

### 使用 PHP 代碼操作

```php
// 獲取授權管理器
$manager = Millennium_License_Manager_Core::instance();

// 建立授權碼
$license = $manager->create_license([
    'product_id' => 123,
    'user_id' => 1,
    'max_activations' => 3,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
]);

// 驗證授權碼
$result = Millennium_License_Key::validate('XXXX-XXXX-XXXX-XXXX');

// 啟用授權碼
$result = Millennium_License_Key::activate(
    'XXXX-XXXX-XXXX-XXXX',
    'https://example.com',
    'instance-123'
);
```

### 自訂 Hooks

```php
// 授權碼建立後
add_action('millennium_license_created', function($license_id, $license) {
    // 自訂邏輯
}, 10, 2);

// 修改授權碼格式
add_filter('millennium_license_key_format', function($format) {
    return 'YYYY-XXXX-XXXX-XXXX';
});
```

## 🐛 故障排除

### 授權碼未自動生成

1. 確認產品已啟用授權功能
2. 檢查訂單狀態是否為「已完成」
3. 查看 WordPress 錯誤日誌

### API 無法使用

1. 確認在設定中已啟用 API
2. 檢查 API 密鑰是否正確
3. 確認網站的固定連結設定正確

### 郵件未發送

1. 確認已啟用郵件通知
2. 檢查 WordPress 郵件設定
3. 考慮安裝 SMTP 外掛改善郵件發送

## 📊 資料庫維護

### 清理過期授權碼

```php
// 自訂排程任務清理過期授權碼
add_action('wp', function() {
    if (!wp_next_scheduled('cleanup_expired_licenses')) {
        wp_schedule_event(time(), 'daily', 'cleanup_expired_licenses');
    }
});

add_action('cleanup_expired_licenses', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'millennium_licenses';
    $wpdb->query(
        "UPDATE $table 
         SET status = 'expired' 
         WHERE expires_at < NOW() 
         AND status = 'active'"
    );
});
```

## 🔒 安全性建議

1. **保護 API 密鑰**
   - 不要在公開的程式碼中暴露 API 密鑰
   - 定期更換 API 密鑰

2. **SSL 加密**
   - 在生產環境使用 HTTPS
   - API 通訊使用 SSL 加密

3. **權限管理**
   - 只給需要的用戶授予「管理選項」權限
   - 定期審查管理員帳戶

## 📞 支援

如有問題或需要協助：

- **文檔**：查看 README.md 和 readme.txt
- **GitHub Issues**：報告 bug 或功能請求
- **Email**：聯繫開發者

## 🔄 更新外掛

1. 下載新版本的外掛
2. 停用現有版本
3. 刪除舊檔案
4. 上傳新版本
5. 重新啟用外掛

**注意**：更新前請備份資料庫！

## ✅ 檢查清單

安裝後確認：

- [ ] 外掛已成功啟用
- [ ] 在設定頁面配置基本選項
- [ ] 為測試產品啟用授權功能
- [ ] 建立測試訂單驗證授權碼生成
- [ ] 測試 API 端點（如需要）
- [ ] 檢查客戶帳戶中的授權碼頁面

完成這些步驟後，您的授權系統就可以正常運作了！
