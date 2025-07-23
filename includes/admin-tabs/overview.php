<?php

if (!defined('ABSPATH')) {
    exit;
}

$plugin_instance = plugin_autopsy();
$overview_data = $plugin_instance->get_overview_data($time_condition);

?>

<div class="plugin-autopsy-overview">
    <div class="overview-stats">
        <div class="stat-card">
            <h3><?php _e('Total Plugins Analyzed', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo esc_html($overview_data['total_plugins']); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total DB Queries', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo esc_html($overview_data['total_queries']); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total Assets Loaded', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo esc_html($overview_data['total_assets']); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Peak Memory Usage', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo esc_html($overview_data['peak_memory']); ?></div>
        </div>
    </div>

    <div class="overview-charts">
        <div class="chart-container">
            <h3><?php _e('Top Resource-Heavy Plugins', 'plugin-autopsy'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Plugin', 'plugin-autopsy'); ?></th>
                        <th><?php _e('DB Queries', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Memory Usage', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Assets', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Impact Score', 'plugin-autopsy'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overview_data['top_plugins'] as $plugin_data): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($plugin_data['name']); ?></strong>
                            <div class="plugin-description"><?php echo esc_html($plugin_data['description']); ?></div>
                        </td>
                        <td>
                            <span class="query-count"><?php echo esc_html($plugin_data['query_count']); ?></span>
                            <div class="query-time"><?php echo esc_html($plugin_data['query_time']); ?>ms</div>
                        </td>
                        <td>
                            <span class="memory-usage"><?php echo esc_html($plugin_data['memory_usage']); ?></span>
                            <div class="memory-percent"><?php echo esc_html($plugin_data['memory_percent']); ?>%</div>
                        </td>
                        <td>
                            <span class="asset-count"><?php echo esc_html($plugin_data['asset_count']); ?> files</span>
                            <div class="asset-size"><?php echo esc_html($plugin_data['asset_size']); ?></div>
                        </td>
                        <td>
                            <div class="impact-score impact-<?php echo esc_attr($plugin_data['impact_level']); ?>">
                                <?php echo esc_html($plugin_data['impact_score']); ?>/100
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="quick-actions">
        <h3><?php _e('Quick Actions', 'plugin-autopsy'); ?></h3>
        <div class="action-buttons">
            <button class="button button-primary" id="refresh-data">
                <?php _e('Refresh Analysis', 'plugin-autopsy'); ?>
            </button>
            <button class="button" id="export-report">
                <?php _e('Export Report', 'plugin-autopsy'); ?>
            </button>
            <button class="button" id="clear-data">
                <?php _e('Clear Old Data', 'plugin-autopsy'); ?>
            </button>
        </div>
    </div>
</div>

