# Invoice Spell Checking Guide

## Overview
Spell checking has been enabled on all invoice line item fields (item names and descriptions). This feature uses your browser's built-in spell checker, which works offline and can be customized with your own product names and technical terms.

## How to Use

### Automatic Spell Checking
- As you type in item name or description fields, misspelled words will be automatically underlined in red
- The spell checker uses your browser's default language dictionary

### Viewing Suggestions
1. Right-click (or Ctrl+click on Mac) on any underlined word
2. A context menu will appear with suggested corrections
3. Click a suggestion to replace the misspelled word

### Adding Custom Terms
For product names, technical terms, or industry-specific vocabulary:

1. Right-click on the word you want to add
2. Select **"Add to Dictionary"** (or similar option depending on browser)
3. The term is now saved and won't be flagged as misspelled again

**Benefits:**
- Terms are saved permanently in your browser
- Works offline (no internet required)
- If browser sync is enabled, your custom dictionary syncs across devices
- Unique to each browser/user, so different team members can have different dictionaries

## Browser-Specific Notes

### Google Chrome / Microsoft Edge
- Right-click → "Add to dictionary"
- Manage dictionary: Settings → Languages → Spell check → Custom spelling dictionary

### Mozilla Firefox  
- Right-click → "Add to Dictionary"
- Manage dictionary: Settings → General → Language → Check your spelling

### Safari (Mac)
- Right-click → "Learn Spelling"
- Managed through macOS system dictionary

## Tips for Invoice Data Entry

1. **Build your dictionary gradually** - Add common product names as you encounter them
2. **Use consistent spelling** - Pick one spelling variation and stick with it
3. **Abbreviations** - Add frequently used abbreviations to your dictionary
4. **Model numbers** - Add product codes and model numbers you use often

## Technical Details

- **Offline**: Works without internet connection
- **Privacy**: All spell checking happens in your browser (no data sent to external servers)
- **Performance**: Instant feedback as you type
- **Compatibility**: Works in all modern browsers (Chrome, Firefox, Safari, Edge)

## Common Product Terms to Add

Examples of terms you might want to add to your dictionary:
- Product brand names (e.g., "InvoicePlane", company-specific products)
- Technical specifications (e.g., "MHz", "Mbps", custom units)
- Industry jargon (e.g., "SKU", "FOB", specific to your business)
- Customer-specific terms (e.g., project codes, location names)

## Troubleshooting

**Spell check not working?**
1. Ensure your browser is updated to the latest version
2. Check that spell check is enabled in browser settings
3. Try refreshing the page (Ctrl+F5 or Cmd+Shift+R)

**Wrong language being used?**
1. Check your browser's language settings
2. Set the correct language as primary in browser preferences

**Want to remove a term?**
1. Access browser settings → Language → Custom dictionary
2. Remove the term from the list

## Future Enhancements

If you need more advanced features such as:
- Grammar checking
- Multiple language support with easy switching
- Centralized company dictionary shared across all users
- API integration with external spell check services

Please contact your system administrator to discuss implementing Typo.js or LanguageTool integration.

