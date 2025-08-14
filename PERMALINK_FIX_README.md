# WooCommerce Points & Rewards - Permalink Structure Fix

## Issue Description

Previously, the WooCommerce Points & Rewards plugin had compatibility issues with different WordPress permalink structures:

### Working (Default Permalink Structure)
When WordPress permalink structure was set to "Default", the three account menu items worked correctly:
- 我的點數 (My Points): `https://example.com/?page_id=28&points-rewards`
- 點數記錄 (Points History): `https://example.com/?page_id=28&points-history`
- 會員等級 (Member Tier): `https://example.com/?page_id=28&member-tier`

### Not Working (Pretty Permalink Structures)
When permalink structure was changed to "Post name" or custom structures, the URLs became:
- 我的點數: `https://example.com/my-account/points-rewards/`
- 點數記錄: `https://example.com/my-account/points-history/`
- 會員等級: `https://example.com/my-account/member-tier/`

But these URLs would not display any content (404 or blank page).

### Temporary Workaround
Adding a "?" before the endpoint name would make them work:
- 我的點數: `https://example.com/my-account/?points-rewards/`
- 點數記錄: `https://example.com/my-account/?points-history/`
- 會員等級: `https://example.com/my-account/?member-tier/`

## Solution Implemented

### Root Cause Analysis
The issue was caused by:
1. Improper URL generation logic that didn't account for different permalink structures
2. Incomplete integration with WooCommerce's endpoint system
3. Missing rewrite rules for pretty permalinks

### Files Modified

#### 1. `includes/functions.php`
**Function**: `wc_points_rewards_get_account_endpoint_url()`

**Changes**:
- Enhanced compatibility with all permalink structures
- Added proper fallback to WooCommerce built-in functions
- Improved error handling and default scenarios

```php
function wc_points_rewards_get_account_endpoint_url($endpoint) {
    // Priority 1: Use WooCommerce built-in if available and endpoint is registered
    if (function_exists('wc_get_account_endpoint_url')) {
        if (WC()->query && isset(WC()->query->query_vars[$endpoint])) {
            return wc_get_account_endpoint_url($endpoint);
        }
    }
    
    // Priority 2: Use custom class method
    if (class_exists('WC_Points_Rewards_Account')) {
        return WC_Points_Rewards_Account::get_account_endpoint_url($endpoint);
    }
    
    // Priority 3: Manual URL construction
    // ... (rest of fallback logic)
}
```

#### 2. `frontend/class-account.php`
**Class**: `WC_Points_Rewards_Account`

**Key Changes**:
- Improved WooCommerce integration in `setup_woocommerce_integration()`
- Enhanced endpoint registration in `add_endpoints()`
- Better URL generation in `get_account_endpoint_url()`
- Added account page detection for our endpoints

**New Methods**:
- `is_account_page_check()`: Ensures our endpoint pages are recognized as account pages
- Enhanced `add_wc_query_vars()`: Properly registers endpoints with WooCommerce

#### 3. `install.php`
**Function**: `setup_rewrite_rules()`

**Changes**:
- Better integration with WooCommerce query system
- Improved rewrite rules setup
- More reliable flush_rewrite_rules handling

### Technical Implementation Details

#### URL Generation Logic Flow
1. **Check WooCommerce Integration**: First try to use WooCommerce's built-in `wc_get_account_endpoint_url()` if the endpoint is properly registered
2. **Use Custom Class Method**: Fall back to our custom implementation in `WC_Points_Rewards_Account`
3. **Manual Construction**: As last resort, manually construct URLs based on permalink structure

#### Endpoint Registration Process
1. **WordPress Level**: Register endpoints using `add_rewrite_endpoint()`
2. **WooCommerce Level**: Add endpoints to `WC()->query->query_vars`
3. **Integration**: Ensure endpoints are included in WooCommerce's query vars filter

#### Permalink Structure Handling
- **Default Structure** (`/?p=123`): Use query parameters (e.g., `&points-rewards`)
- **Pretty Structures** (`/%postname%/`): Use path segments (e.g., `/points-rewards/`)
- **Automatic Detection**: Code automatically detects current permalink structure

## Testing Verification

### Test Scenarios Covered
1. ✅ Default permalink structure with query parameters
2. ✅ Post name permalink structure with pretty URLs
3. ✅ Custom permalink structures
4. ✅ Mixed scenarios and edge cases

### URLs Generated After Fix

#### Default Permalinks
- My Points: `https://example.com/?page_id=28&points-rewards`
- Points History: `https://example.com/?page_id=28&points-history`  
- Member Tier: `https://example.com/?page_id=28&member-tier`

#### Pretty Permalinks
- My Points: `https://example.com/my-account/points-rewards/`
- Points History: `https://example.com/my-account/points-history/`
- Member Tier: `https://example.com/my-account/member-tier/`

## Installation Instructions

1. **Backup**: Always backup your website before applying changes
2. **Deploy**: Upload the modified files to your WordPress installation
3. **Flush Rewrite Rules**: Go to WordPress Admin → Settings → Permalinks and click "Save Changes"
4. **Test**: Verify that all three menu items work in "My Account" page

## Compatibility

- ✅ WordPress 6.0+
- ✅ WooCommerce 8.0+
- ✅ PHP 8.0+
- ✅ All permalink structures
- ✅ Multisite installations

## Future Maintenance

The fix is designed to be:
- **Forward Compatible**: Works with future WordPress/WooCommerce updates
- **Backward Compatible**: Maintains existing functionality
- **Self-Healing**: Includes fallback mechanisms for edge cases

## Support

If you encounter any issues after applying this fix:
1. Clear any caching plugins
2. Go to Settings → Permalinks and click "Save Changes"
3. Deactivate and reactivate the plugin if necessary
4. Check WordPress error logs for any PHP errors

---

**Version**: 1.0.2+fix
**Branch**: `permalink-structure-fix`
**Date**: December 2024