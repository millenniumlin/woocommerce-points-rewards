# 點數兌換問題修復說明

## 問題描述
當用戶在購物車或結帳頁面輸入點數並點擊"使用點數"按鈕時，出現"發生錯誤，請稍後再試"的錯誤訊息，點數無法正常折抵購物車金額。

## 已修復的問題

### 1. 預設設定不一致
- **問題**: `max_discount_percent` 在不同地方有不同的預設值（50% vs 100%）
- **修復**: 統一預設值為 100%，允許完全折抵

### 2. 錯誤訊息不夠明確
- **問題**: 只顯示通用錯誤訊息，無法知道具體原因
- **修復**: 提供詳細的錯誤訊息，說明具體的限制條件

### 3. 缺乏管理員覆蓋機制
- **問題**: 即使是管理員也無法跳過某些限制
- **修復**: 新增管理員覆蓋功能，允許管理員強制使用點數

### 4. 錯誤處理不完整
- **問題**: 某些錯誤沒有被妥善處理
- **修復**: 新增完整的 try-catch 錯誤處理機制

## 修改的檔案

1. **includes/class-points-calculator.php**
   - 修正 `can_use_points()` 方法的預設值
   - 新增 `can_force_use_points()` 方法

2. **frontend/class-checkout.php**
   - 改善 AJAX 錯誤處理
   - 新增詳細的錯誤訊息
   - 新增管理員覆蓋邏輯

3. **assets/js/frontend.js**
   - 改善前端錯誤處理
   - 新增詳細的錯誤日誌記錄

4. **includes/functions.php**
   - 新增多個輔助函數
   - 新增調試功能

## 緊急修復方法

如果問題仍然存在，您可以使用以下方法之一：

### 方法 1: 使用主題 functions.php 強制啟用
將 `examples/theme-functions-override.php` 中的代碼複製到您的主題的 `functions.php` 文件中。

### 方法 2: 啟用調試模式
在 WordPress 的 `wp-config.php` 中啟用調試：
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 方法 3: 檢查伺服器日誌
查看 WordPress 錯誤日誌檔案（通常在 `/wp-content/debug.log`）以獲取詳細的錯誤信息。

## 測試建議

1. **清除快取**: 清除所有快取（外掛快取、瀏覽器快取等）
2. **測試不同角色**: 使用一般用戶和管理員帳號進行測試
3. **檢查設定**: 確認 WooCommerce Points & Rewards 的設定正確
4. **瀏覽器控制台**: 檢查瀏覽器開發者工具的控制台是否有 JavaScript 錯誤

## 進階設定

### 啟用管理員覆蓋功能
在 WordPress 後台的點數設定中，您可能需要手動啟用管理員覆蓋功能。如果設定介面沒有此選項，可以直接在資料庫中設定：

```sql
UPDATE wp_options 
SET option_value = REPLACE(option_value, '"allow_admin_override";s:2:"no"', '"allow_admin_override";s:3:"yes"') 
WHERE option_name = 'wc_points_rewards_settings';
```

### 調整點數限制
您也可以調整其他相關設定：

```sql
-- 移除最低購物車金額限制
UPDATE wp_options SET option_value = '0' WHERE option_name = 'wc_points_rewards_min_cart_total';

-- 允許 100% 折抵
UPDATE wp_options SET option_value = '100' WHERE option_name = 'wc_points_rewards_max_discount_percent';
```

## 聯絡支援

如果問題仍然存在，請提供以下信息：

1. WordPress 版本
2. WooCommerce 版本
3. PHP 版本
4. 使用的主題和外掛
5. 瀏覽器控制台錯誤訊息
6. WordPress 錯誤日誌

這將幫助進一步診斷問題。