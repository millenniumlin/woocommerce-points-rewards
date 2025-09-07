# 生日點數通知信件內容檢測 (Birthday Points Notification Email Content Detection)

## 郵件模板詳細內容

### 生日點數通知郵件

#### 基本資訊
- **功能檔案**: `includes/class-notifications.php`
- **方法名稱**: `send_birthday_points_notification($user_id, $points)`
- **觸發條件**: 生日月份發放點數時
- **設定控制**: `wc_points_rewards_enable_birthday_notification`

#### 郵件主旨 (Subject)
```
生日快樂！您獲得了生日贈送點數
```

#### 郵件內容 (Message Body)
```html
親愛的 {用戶顯示名稱}，

🎉 生日快樂！🎂

在這個特別的日子裡，我們為您準備了 {點數數量} 點作為生日禮物！
{條件性：點數將於 {到期日期} 到期，請記得使用。}

立即使用點數購物：{商店頁面連結}
查看我的帳戶：{會員中心連結}
```

#### 動態變數說明

| 變數 | 來源 | 範例值 | 說明 |
|------|------|--------|------|
| `{用戶顯示名稱}` | `$user->display_name` | "王小明" | 用戶在 WordPress 中設定的顯示名稱 |
| `{點數數量}` | `wc_points_rewards_number_format($points)` | "200" | 經過格式化的點數數量，使用系統設定的格式 |
| `{到期日期}` | `date('Y-m-d', strtotime("+{$expiry_months} months"))` | "2024-12-31" | 依據設定的點數有效期計算的到期日 |
| `{商店頁面連結}` | `wc_get_page_permalink('shop')` | "https://example.com/shop/" | WooCommerce 商店頁面連結 |
| `{會員中心連結}` | `wc_get_page_permalink('myaccount')` | "https://example.com/my-account/" | WooCommerce 會員中心連結 |

#### 條件性內容

**點數有效期顯示邏輯**:
```php
$expiry_months = get_option('wc_points_rewards_points_expiry_months', 12);
if ($expiry_months > 0) {
    $expiry_date = date('Y-m-d', strtotime("+{$expiry_months} months"));
    $expiry_text = sprintf(__('<br>點數將於 %s 到期，請記得使用。', 'wc-points-rewards'), $expiry_date);
} else {
    $expiry_text = ''; // 永不過期則不顯示
}
```

#### 郵件格式設定

**Headers**:
```php
$headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
);
```

**樣式包裝**:
- 使用 WooCommerce 郵件包裝器 (`$mailer->wrap_message()`)
- 包含網站品牌樣式
- 響應式設計，支援行動裝置

## 郵件發送邏輯

### 啟用條件檢查
```php
// 1. 檢查生日通知是否啟用
$enable_birthday_notification = get_option('wc_points_rewards_enable_birthday_notification', 'yes');
if ($enable_birthday_notification !== 'yes') {
    return; // 不發送
}

// 2. 檢查用戶是否存在
$user = get_user_by('id', $user_id);
if (!$user) {
    return; // 用戶不存在，不發送
}
```

### 郵件發送流程
1. **收集用戶資料**: 獲取用戶物件和郵件地址
2. **組合主旨**: 使用翻譯函數支援多語言
3. **組合內容**: 替換動態變數，加入個人化資訊
4. **套用樣式**: 使用 WooCommerce 郵件模板
5. **發送郵件**: 透過 WordPress 郵件系統
6. **觸發 Hook**: 允許第三方外掛擴充功能

### Hook 事件
```php
do_action('wc_points_rewards_birthday_points_notification_sent', $user_id, $points);
```

## 與其他通知郵件的比較

### 相同點
- 使用相同的 `send_email()` 統一方法
- 支援 WooCommerce 郵件包裝器
- HTML 格式，UTF-8 編碼
- 包含購物和帳戶連結
- 個人化稱呼用戶

### 差異點
| 項目 | 生日通知 | 註冊通知 | 點數到期通知 |
|------|----------|----------|--------------|
| **觸發時機** | 生日月份 | 用戶註冊 | 點數即將到期 |
| **情感色彩** | 慶祝、溫馨 | 歡迎、引導 | 提醒、急迫 |
| **emoji 使用** | 🎉🎂 | 無 | 無 |
| **過期提醒** | 條件性顯示 | 無 | 主要內容 |
| **設定控制** | 獨立開關 | 無專用開關 | 獨立開關 |

## 多語言支援

### 翻譯字串 (使用 `__()` 函數)
```php
__('生日快樂！您獲得了生日贈送點數', 'wc-points-rewards')
__('親愛的 %s，<br><br>🎉 生日快樂！🎂<br><br>在這個特別的日子裡，我們為您準備了 %s 點作為生日禮物！%s<br><br>立即使用點數購物：%s<br>查看我的帳戶：%s', 'wc-points-rewards')
__('<br>點數將於 %s 到期，請記得使用。', 'wc-points-rewards')
```

### 語言檔案位置
- 文本域: `wc-points-rewards`
- 語言檔案目錄: `/languages/`
- 支援格式: `.po`, `.mo` 檔案

## 測試情境

### 正常發送情境
1. **用戶條件**: 有效的 WordPress 用戶
2. **生日設定**: 當月生日且 `birthday_set = '1'`
3. **系統設定**: 生日點數啟用、生日通知啟用
4. **點數條件**: 成功發放生日點數

### 不發送情境
1. **通知停用**: `enable_birthday_notification = 'no'`
2. **用戶無效**: 用戶不存在或已刪除
3. **郵件系統**: WordPress 郵件功能停用
4. **重複發送**: 當年當月已發送過（防重複機制）

### 錯誤處理
- **用戶不存在**: 靜默跳過，不拋出錯誤
- **郵件發送失敗**: WordPress 郵件系統處理
- **模板錯誤**: 使用預設文字，確保基本功能

## 客製化建議

### 管理員可調整項目
1. **啟用/停用**: 通知設定頁面控制
2. **主旨客製**: 透過翻譯檔案或 Hook
3. **內容調整**: 透過翻譯檔案
4. **樣式修改**: WooCommerce 郵件模板系統

### 開發者擴充點
1. **Hook 監聽**: `wc_points_rewards_birthday_points_notification_sent`
2. **過濾器**: 可加入自定義過濾器修改內容
3. **模板覆寫**: 透過主題覆寫郵件模板
4. **多媒體**: 加入圖片或其他媒體元素

## 效能考量

### 郵件發送效能
- **批次處理**: 每日 cron 批次檢查生日用戶
- **非同步發送**: 可考慮整合郵件佇列系統
- **錯誤重試**: WordPress 郵件系統內建重試機制

### 資源使用
- **記憶體**: 每封郵件約 1-2KB 記憶體
- **資料庫**: 發送記錄存於 transient 快取
- **網路**: SMTP 發送或本地 sendmail