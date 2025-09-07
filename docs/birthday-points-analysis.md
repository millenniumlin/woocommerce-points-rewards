# 生日贈送點數系統分析 (Birthday Points System Analysis)

## 當前實現狀況 (Current Implementation Status)

### 1. 設定條件 (Configuration Settings)

#### 管理員設定頁面 (Admin Settings)
- **位置**: `admin/views/settings.php` 和 `admin/class-settings.php`
- **啟用設定**: `enable_birthday_points` (預設: 'yes')
- **點數數量**: `birthday_points` (預設: 200 點)
- **設定描述**: "在用戶生日月份贈送點數" (Award points in user's birthday month)

#### 預設設定值 (Default Settings)
- 來源: `install.php`
- 生日點數功能: 預設啟用 (`enable_birthday_points: 'yes'`)
- 生日贈送點數: 預設 200 點 (`birthday_points: 200`)
- 點數有效期: 12個月 (`points_expiry_months: 12`)

### 2. 邏輯實現 (Logic Implementation)

#### 生日點數發放邏輯 (`includes/class-points-calculator.php`)

**方法**: `award_birthday_points($user_id)`
- 檢查 `enable_birthday_points` 設定
- 檢查 `birthday_points` 數量 > 0
- 防止重複發放：檢查當年當月是否已發放
- 記錄描述：'生日贈送點數'
- 設定過期時間：根據 `points_expiry_months`

**方法**: `check_birthday_points()` (每日執行)
- 查詢當月生日的用戶
- 條件：
  - `user_meta.birthday` 存在且不為空
  - `user_meta.birthday_set = '1'` (已確認設定)
  - 生日月份 = 當前月份
- 對每個符合條件的用戶呼叫 `award_birthday_points()`

**方法**: `check_immediate_birthday_bonus($user_id)`
- 用戶設定生日時立即檢查
- 如果設定的生日月份是當前月份，立即發放點數

#### 定時任務 (Scheduled Tasks)
- **Hook**: `wc_points_rewards_daily_birthday_check`
- **頻率**: 每日 (daily)
- **註冊位置**: `woocommerce-points-rewards.php`

### 3. 資料庫檢查邏輯 (Database Check Logic)

#### 防重複發放檢查
```sql
SELECT COUNT(*) 
FROM wp_wc_points_rewards_points 
WHERE user_id = %d 
AND type = 'earned' 
AND description = '生日贈送點數' 
AND YEAR(created_at) = %d 
AND MONTH(created_at) = %d
```

#### 生日用戶查詢
```sql
SELECT DISTINCT u1.user_id 
FROM wp_usermeta u1
INNER JOIN wp_usermeta u2 ON u1.user_id = u2.user_id
WHERE u1.meta_key = 'birthday' 
AND u1.meta_value != ''
AND u1.meta_value IS NOT NULL
AND u2.meta_key = 'birthday_set'
AND u2.meta_value = '1'
AND MONTH(STR_TO_DATE(u1.meta_value, '%Y-%m-%d')) = %d
```

## 通知信件檢測 (Notification Email Detection)

### 現有通知系統 (`includes/class-notifications.php`)

#### 已實現的通知類型
1. **點數到期通知** (`send_points_expiry_notification`)
   - 主旨：'點數即將到期提醒'
   - 內容：點數數量、到期日期、購物連結
   - 觸發條件：點數到期前 N 天（可設定）
   - 郵件內容模板：
     ```
     親愛的 {用戶名稱}，

     您有 {點數數量} 點即將於 {到期日期} 到期。
     請盡快使用，避免點數失效。

     立即購物：{商店連結}
     ```
   
2. **會員等級到期通知** (`send_tier_expiry_notification`)
   - 主旨：'{等級名稱} 等級即將到期'
   - 內容：等級名稱、到期日期、購物連結
   - 觸發條件：等級到期前 N 天
   - 郵件內容模板：
     ```
     親愛的 {用戶名稱}，

     您的 {等級名稱} 等級將於 {到期日期} 到期。
     請繼續購物以維持會員等級資格。

     立即購物：{商店連結}
     ```
   
3. **歡迎註冊通知** (`send_welcome_points_notification`)
   - 主旨：'歡迎加入！您獲得了註冊贈送點數'
   - 內容：歡迎訊息、註冊點數、購物連結
   - 觸發條件：新用戶註冊並獲得註冊點數
   - 郵件內容模板：
     ```
     親愛的 {用戶名稱}，

     歡迎加入我們！
     作為新會員，您獲得了 {點數數量} 點作為歡迎禮。

     立即開始購物：{商店連結}
     ```
   
4. **一般點數獲得通知** (`send_points_earned_notification`)
   - 主旨：'您獲得了新的點數！'
   - 內容：點數數量、訂單資訊（如適用）
   - 觸發條件：購物或其他活動獲得點數
   - 郵件內容模板：
     ```
     親愛的 {用戶名稱}，

     感謝您的購買！您的訂單 #{訂單號} 已獲得 {點數數量} 點。
     （或：您獲得了 {點數數量} 點！）

     查看我的帳戶：{會員中心連結}
     ```

### **新增功能**: 生日點數通知

#### 實現詳情
- **方法名稱**: `send_birthday_points_notification($user_id, $points)`
- **主旨**: "生日快樂！您獲得了生日贈送點數"
- **觸發條件**: 
  - 用戶生日月份
  - 生日點數功能已啟用
  - 生日通知功能已啟用
- **郵件內容模板**:
  ```
  親愛的 {用戶名稱}，

  🎉 生日快樂！🎂

  在這個特別的日子裡，我們為您準備了 {點數數量} 點作為生日禮物！
  {如有過期時間：點數將於 {到期日期} 到期，請記得使用。}

  立即使用點數購物：{商店連結}
  查看我的帳戶：{會員中心連結}
  ```

#### 設定控制
- **啟用開關**: `enable_birthday_notification` (預設: 'yes')
- **管理位置**: 管理後台 > 點數獎勵設定 > 通知設定
- **描述**: "發放生日點數時自動發送通知郵件"

#### 技術整合
- **觸發位置**: `includes/class-points-calculator.php` 的 `award_birthday_points()` 方法
- **Hook 支援**: `do_action('wc_points_rewards_birthday_points_notification_sent', $user_id, $points)`
- **第三方擴充**: 允許其他外掛監聽生日通知事件

### 郵件系統技術細節

#### 郵件發送機制 (`send_email()` 方法)
- **模板包裝**: 使用 WooCommerce 郵件包裝器美化郵件樣式
- **編碼**: UTF-8 支援中文內容
- **發送者**: 使用網站名稱和管理員信箱
- **內容類型**: HTML 格式
- **備援機制**: 若 WooCommerce 不可用，使用 WordPress 原生 `wp_mail()`

#### 防重複發送機制
- **Transient 快取**: 24 小時內不重複發送相同通知
- **快取鍵格式**: `{通知類型}_{用戶ID}_{日期}`
- **例子**: `points_expiry_123_2024-01-15`

#### 個人化元素
- **用戶名稱**: `$user->display_name`
- **點數格式化**: `wc_points_rewards_number_format()` 統一格式
- **日期格式**: 'Y-m-d' 格式
- **連結生成**: 
  - 商店: `wc_get_page_permalink('shop')`
  - 會員中心: `wc_get_page_permalink('myaccount')`

### **缺失功能**: 生日點數通知

#### 目前狀況
- ❌ **沒有生日點數專用的通知信件**
- ❌ 生日點數發放時不會自動發送通知
- ❌ 管理員設定中沒有生日通知開關

#### 已新增的功能 ✅
- ✅ **生日點數通知方法** (`send_birthday_points_notification`)
- ✅ **生日通知設定選項** (在管理員設定頁面)
- ✅ **整合到生日點數發放流程** (在 `award_birthday_points` 中觸發)
- ✅ **Hook 支援** (允許第三方擴充)
- ✅ **防重複發送機制** (繼承現有通知系統邏輯)

## 建議改進 (Recommended Improvements)

### 1. 新增生日點數通知信件
- **主旨**: "生日快樂！您獲得了生日贈送點數"
- **內容**: 
  - 個人化生日祝福
  - 贈送的點數數量
  - 點數有效期限
  - 購物連結
  - 查看帳戶連結

### 2. 管理員設定選項
- 新增：啟用生日點數通知 (`enable_birthday_notification`)
- 整合到現有通知設定區塊

### 3. Hook 整合
- 在 `award_birthday_points` 成功發放後觸發通知
- 使用 `do_action` 允許第三方擴充

## 技術實現細節 (Technical Implementation Details)

### 使用的 WordPress/WooCommerce 功能
- **用戶資料**: `get_user_meta()` 取得生日資訊
- **定時任務**: `wp_schedule_event()` 設定每日檢查
- **郵件發送**: WooCommerce 郵件包裝器
- **設定管理**: WordPress Options API
- **資料庫**: 自定義資料表 `wp_wc_points_rewards_points`

### 安全性考量
- SQL 查詢使用 `$wpdb->prepare()` 防止 SQL 注入
- 用戶輸入使用 `sanitize_text_field()` 和 `esc_attr()` 清理
- 表單使用 `wp_nonce` 驗證
- 防重複發放機制避免誤發

### 效能考量
- 每日僅執行一次生日檢查
- 使用索引查詢減少資料庫負擔
- Transient 快取避免重複通知