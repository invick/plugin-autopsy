# Plugin Autopsy - Installation Guide

## Quick Installation Steps

1. **Upload the plugin files** to your WordPress `/wp-content/plugins/plugin-autopsy/` directory

2. **Activate the plugin** through the 'Plugins' menu in WordPress Admin

3. **Enable detailed query tracking** by adding this line to your `wp-config.php` file:
   ```php
   define('SAVEQUERIES', true);
   ```

4. **Access the dashboard** by navigating to **Tools > Plugin Autopsy** in your WordPress admin

## First Time Setup

### After activation, the plugin will:
- Create a database table `wp_plugin_autopsy_data` to store performance metrics
- Start tracking plugin performance automatically
- Begin collecting data on database queries, asset loading, and memory usage

### To see data:
1. Visit some pages on your site (both frontend and admin)
2. Wait a few minutes for data to accumulate
3. Go to **Tools > Plugin Autopsy** to view the analysis

## Fixing Common Issues

### "No data available" message:
- Ensure you've visited some pages after activation
- Check that the plugin is properly activated
- Verify the database table was created successfully

### Database queries not showing:
- Add `define('SAVEQUERIES', true);` to wp-config.php
- Make sure this line is before the `/* That's all, stop editing! */` comment

### Permission errors:
- Ensure your WordPress user has 'manage_options' capability
- Check file permissions are correct (typically 644 for files, 755 for directories)

## What Data Is Collected

The plugin tracks:
- **Database Queries**: Count, execution time, slow queries per plugin
- **Asset Loading**: JS/CSS files, file sizes, loading context per plugin  
- **Memory Usage**: Memory consumption, peak usage, allocation per plugin
- **Performance Metrics**: Impact scores and optimization recommendations

## Data Privacy

- All data is stored locally in your WordPress database
- No data is sent to external servers
- Data can be cleared using the "Clear Old Data" button in the dashboard

## Troubleshooting

### Plugin conflicts:
If you experience conflicts with other plugins:
1. Deactivate Plugin Autopsy temporarily
2. Test your site functionality
3. Report any issues for investigation

### Performance impact:
The plugin is designed to have minimal performance impact, but if you notice slowdowns:
1. The tracking overhead is typically <1% of page load time
2. Data collection can be temporarily disabled by deactivating the plugin
3. Historical data remains available even when deactivated

## Support

For support or bug reports:
1. Check the plugin files are uploaded correctly
2. Verify WordPress and PHP version requirements are met
3. Test with a default WordPress theme to rule out theme conflicts

## Next Steps

Once installed and collecting data:
1. Check the **Overview** tab for a quick health check
2. Review the **Recommendations** tab for optimization suggestions
3. Use the time range selector to analyze different periods
4. Export reports for further analysis if needed