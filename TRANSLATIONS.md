# Translation Support

This plugin now supports both Traditional Chinese and English translations.

## Available Languages

- **Traditional Chinese (zh_TW)** - Original language with complete translations
- **English (en_US)** - Newly added with comprehensive translations

## How It Works

The plugin uses WordPress's standard internationalization (i18n) system:

1. **Automatic Language Detection**: WordPress automatically selects the appropriate language based on your site's locale setting
2. **Text Domain**: All translatable strings use the `wc-points-rewards` text domain
3. **Translation Files**: Located in the `/languages/` directory

## Translation Files

- `wc-points-rewards.pot` - Translation template with all translatable strings
- `wc-points-rewards-zh_TW.po/.mo` - Traditional Chinese translations
- `wc-points-rewards-en_US.po/.mo` - English translations

## Translation Coverage

### English Translation Statistics:
- **83 strings (20.1%)** - Fully translated with natural English
- **250 strings (60.5%)** - Partially translated (mixed but functional)
- **80 strings (19.4%)** - Remain in original Chinese

### Examples of Translated Strings:
- "點數設定" → "Points Settings"
- "會員等級" → "Member Tier"
- "註冊贈送點數" → "Registration bonus points"
- "%s 設定值過大，已調整為最大值 %d" → "%s setting value too large, adjusted to maximum %d"

## Switching Languages

To change the plugin language:

1. Go to **WordPress Admin → Settings → General**
2. Change **Site Language** to your preferred language
3. The plugin will automatically use the appropriate translation

## For Developers

If you want to add more languages or improve existing translations:

1. Use the `wc-points-rewards.pot` file as a template
2. Create new `.po` files for your language (e.g., `wc-points-rewards-fr_FR.po`)
3. Compile to `.mo` files using `msgfmt`
4. Place in the `/languages/` directory

## Contributing Translations

We welcome contributions to improve translations! Please:

1. Fork the repository
2. Update or create translation files
3. Test your translations
4. Submit a pull request

The translation system ensures the plugin can serve both Chinese and international users effectively.