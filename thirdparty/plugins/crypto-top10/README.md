# Crypto Top 10 Plugin

A FlatPress plugin that displays the top 10 cryptocurrencies by market cap with interactive price charts.

**Version:** 2.0.0  
**Requires:** FlatPress 1.5 RC or later, Smarty 5.5+

## Features

- 📊 Displays top 10 cryptocurrencies from CoinGecko API
- 💹 Interactive 7-day price charts using Chart.js
- 🎨 Responsive design optimized for sidebar layout
- 🌍 Multilingual support (15 languages)
- ⚡ No API key required
- 🔒 Secure implementation with proper XSS protection
- 📱 Mobile-friendly
- ✨ Compatible with FlatPress 1.5 RC and Smarty 5.5+

## Version History

### Version 2.0.0
- Updated for FlatPress 1.5 RC compatibility
- Full Smarty 5.5+ support
- Improved asset version handling with fallback for older FlatPress versions
- Enhanced security with proper HTML entity encoding
- Better error handling and timeout management

### Version 1.0.0
- Initial release

## Installation

1. Copy the `crypto-top10` folder to `fp-plugins/`
2. Enable the plugin in FlatPress admin panel or add `'crypto-top10'` to `fp-plugins` array in config
3. Add the widget to your sidebar in the widgets configuration

## Usage

### Adding to Sidebar

Edit your widgets configuration file (or use the admin panel):

```php
'right' => array(
    'crypto-top10',
    // ... other widgets
),
```

### Supported Themes

The plugin is optimized for the leggerov2 theme but will work with any FlatPress theme that supports sidebar widgets.

## Technical Details

### Requirements

- FlatPress 1.5 RC or later
- Smarty 5.5+ (included in FlatPress 1.5 RC)
- jQuery plugin enabled (part of FlatPress standard distribution)
- Internet connection to access CoinGecko API
- Modern web browser with JavaScript enabled

### Files Structure

```
crypto-top10/
├── plugin.crypto-top10.php  # Main plugin file
├── tpls/
│   └── widget.tpl            # Widget template (Smarty 5.5+ compatible)
├── assets/
│   ├── crypto-top10.js       # Main JavaScript
│   ├── crypto-top10.css      # Styles
│   ├── chart.min.js          # Chart.js library
│   └── crypto-top10-mock.js  # Mock data for testing
├── lang/
│   ├── lang.en-us.php        # English translations
│   ├── lang.de-de.php        # German translations
│   └── ...                   # 13 more languages
├── inc/                      # Reserved for includes
└── img/                      # Reserved for images
```

### API

The plugin uses the free CoinGecko API (no authentication required):
- Market data: `https://api.coingecko.com/api/v3/coins/markets`
- Price history: `https://api.coingecko.com/api/v3/coins/{id}/market_chart`

### Localization

The plugin supports 15 languages:
- Czech (cs-cz)
- Danish (da-dk)
- German (de-de)
- Greek (el-gr)
- English (en-us)
- Spanish (es-es)
- Basque (eu-es)
- French (fr-fr)
- Italian (it-it)
- Japanese (ja-jp)
- Dutch (nl-nl)
- Portuguese (pt-br)
- Russian (ru-ru)
- Slovenian (sl-si)
- Turkish (tr-tr)

## Configuration

No additional configuration required. The plugin works out of the box.

### Customization

You can customize the appearance by editing `assets/crypto-top10.css`.

## Testing

For testing in environments where the CoinGecko API is unavailable, the plugin includes mock data support. Set `window.USE_MOCK_DATA = true` and include `crypto-top10-mock.js` before the main plugin script.

## Error Handling

The plugin gracefully handles:
- API connection failures
- Timeout errors (10-second timeout)
- Missing or invalid data
- Network interruptions

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers

## License

GNU General Public License v2 (GPLv2) - Same as FlatPress

## Credits

- Developed for FlatPress
- Uses CoinGecko API for cryptocurrency data
- Uses Chart.js for data visualization
- Uses jQuery for DOM manipulation

## Support

For issues or questions, visit the FlatPress forum or GitHub repository.
