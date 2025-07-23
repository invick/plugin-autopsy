# Plugin Autopsy - WordPress Plugin Bloat & Performance Profiler

A comprehensive WordPress plugin that provides forensic-level analysis of plugin performance, helping you identify and resolve performance bottlenecks in your WordPress site.

## Features

### 🔍 **Forensic-Level Analysis**
- **Database Query Tracking**: Monitor queries per page load, execution time, and slow queries
- **Asset Loading Analysis**: Track JS/CSS files loaded by each plugin with file sizes
- **Memory Usage Profiling**: Real-time memory consumption monitoring per plugin
- **Performance Impact Scoring**: Comprehensive scoring system to identify problematic plugins

### 📊 **Visual Dashboard**
- **Overview Tab**: Quick stats and top resource-heavy plugins
- **Database Tab**: Detailed query analysis and optimization suggestions
- **Assets Tab**: File loading breakdown with size analysis
- **Memory Tab**: Memory usage patterns and allocation tracking
- **Recommendations Tab**: AI-powered optimization suggestions

### 🎯 **Key Benefits**
- **Plugin-Level Granularity**: Unlike generic monitoring tools, get specific insights for each plugin
- **Performance Comparison**: Visual comparison between plugins to make informed decisions
- **Actionable Recommendations**: Specific steps to optimize or replace problematic plugins
- **Historical Data**: Track performance trends over time

## Installation

1. Download or clone this repository to your WordPress plugins directory:
   ```
   /wp-content/plugins/plugin-autopsy/
   ```

2. Activate the plugin through the 'Plugins' menu in WordPress

3. For database query tracking, add this line to your `wp-config.php` file:
   ```php
   define('SAVEQUERIES', true);
   ```

4. Navigate to **Tools > Plugin Autopsy** in your WordPress admin

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## How It Works

### Database Query Tracking
The plugin hooks into WordPress's database layer to track:
- Number of queries per plugin
- Query execution time
- Slow queries (>50ms)
- Query origins through backtrace analysis

### Asset Loading Monitoring
Monitors both frontend and admin asset loading:
- Enqueued scripts and styles
- File sizes and dependencies
- Loading context (frontend/admin)
- Direct inline assets

### Memory Usage Analysis
Tracks memory consumption at various WordPress hooks:
- Plugin-specific memory allocation
- Hook-based memory tracking
- Peak memory usage monitoring
- Memory increase attribution

### Performance Scoring
Uses a weighted algorithm considering:
- Database query impact (30%)
- Memory usage (35%)
- Asset loading (20%)
- Query execution time (15%)

## Dashboard Overview

### Overview Tab
- Total plugins analyzed
- Aggregate performance statistics
- Top resource-heavy plugins table
- Quick action buttons

### Database Tab
- Query count and timing per plugin
- Slow query identification
- Database optimization suggestions
- Query performance trends

### Assets Tab
- CSS/JS file breakdown by plugin
- File size analysis
- Loading optimization recommendations
- Asset loading patterns

### Memory Tab
- Memory usage per plugin
- Hook-based memory allocation
- Memory trend analysis
- Memory leak detection

### Recommendations Tab
- Performance improvement suggestions
- Plugin comparison matrix
- Optimization action steps
- Potential performance savings

## API Usage

### Getting Plugin Data Programmatically

```php
// Get the main plugin instance
$autopsy = plugin_autopsy();

// Get current profiler data
$data = $autopsy->get_profiler_data();

// Add custom profiler data
$autopsy->add_profiler_data('my-plugin', 'custom_metric', $custom_data);
```

### Database Schema

The plugin creates a table `wp_plugin_autopsy_data` with the following structure:
- `id`: Primary key
- `plugin_name`: Plugin slug or identifier
- `metric_type`: Type of metric (database_queries, asset_loading, memory_usage)
- `metric_value`: JSON-encoded metric data
- `page_url`: URL where the metric was recorded
- `timestamp`: When the metric was recorded

## Configuration

### Time Ranges
Choose from different analysis periods:
- Last Hour
- Last 24 Hours
- Last 7 Days
- Last 30 Days

### Data Retention
The plugin automatically cleans up old data to prevent database bloat. You can manually clear data using the "Clear Old Data" button.

## Performance Recommendations

The plugin provides intelligent recommendations based on:

### High Database Usage
- Plugins making >50 queries per page
- Slow queries (>50ms execution time)
- Suggestions for caching and optimization

### High Memory Usage
- Plugins using >20% of total memory
- Memory leak detection
- Alternative plugin suggestions

### Large Asset Files
- Plugins loading >500KB of assets
- Minification and compression suggestions
- CDN recommendations

## Troubleshooting

### Database Queries Not Showing
1. Ensure `SAVEQUERIES` is defined as `true` in `wp-config.php`
2. Check that the plugin is active and properly installed
3. Visit some pages to generate data

### Memory Tracking Issues
1. Ensure your server has sufficient memory allocated to PHP
2. Check for conflicting plugins that might interfere with memory tracking
3. Some hosting providers restrict memory tracking functions

### No Data Appearing
1. Wait a few minutes after activation for data to accumulate
2. Visit both frontend and admin pages to generate metrics
3. Check the selected time range in the dashboard

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the LICENSE file for details.

## Support

For support, please open an issue on the GitHub repository or contact the plugin author.

## Changelog

### Version 1.0.0
- Initial release
- Database query tracking
- Asset loading analysis
- Memory usage monitoring
- Performance recommendations dashboard
- Visual comparison features