<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'plugin_autopsy_data';

$database_data = $wpdb->get_results($wpdb->prepare("
    SELECT plugin_name, metric_value 
    FROM {$table_name} 
    WHERE metric_type = 'database_queries' AND {$time_condition}
    ORDER BY timestamp DESC
"));

$plugin_instance = plugin_autopsy();
$plugin_queries = [];
foreach ($database_data as $row) {
    $metric_value = json_decode($row->metric_value, true);
    $plugin_name = $plugin_instance->get_plugin_name_from_slug($row->plugin_name);
    
    if (!isset($plugin_queries[$plugin_name])) {
        $plugin_queries[$plugin_name] = [
            'total_queries' => 0,
            'total_time' => 0,
            'slow_queries' => 0,
            'average_time' => 0
        ];
    }
    
    $plugin_queries[$plugin_name]['total_queries'] += $metric_value['query_count'] ?? 0;
    $plugin_queries[$plugin_name]['total_time'] += $metric_value['total_time'] ?? 0;
    $plugin_queries[$plugin_name]['slow_queries'] += $metric_value['slow_query_count'] ?? 0;
}

foreach ($plugin_queries as $plugin => &$data) {
    $data['average_time'] = $data['total_queries'] > 0 ? $data['total_time'] / $data['total_queries'] : 0;
    $data['total_time'] = round($data['total_time'] * 1000, 2);
    $data['average_time'] = round($data['average_time'] * 1000, 2);
}

uasort($plugin_queries, function($a, $b) {
    return $b['total_time'] <=> $a['total_time'];
});

?>

<div class="plugin-autopsy-database">
    <div class="database-stats">
        <div class="stat-card">
            <h3><?php _e('Total Queries', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo array_sum(array_column($plugin_queries, 'total_queries')); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total Query Time', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo round(array_sum(array_column($plugin_queries, 'total_time')), 1); ?>ms</div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Slow Queries', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo array_sum(array_column($plugin_queries, 'slow_queries')); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Average Query Time', 'plugin-autopsy'); ?></h3>
            <div class="stat-number">
                <?php 
                $total_queries = array_sum(array_column($plugin_queries, 'total_queries'));
                $total_time = array_sum(array_column($plugin_queries, 'total_time'));
                echo $total_queries > 0 ? round($total_time / $total_queries, 2) : 0; 
                ?>ms
            </div>
        </div>
    </div>

    <div class="database-table">
        <h3><?php _e('Database Queries by Plugin', 'plugin-autopsy'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Total Queries', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Total Time (ms)', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Average Time (ms)', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Slow Queries', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Impact Level', 'plugin-autopsy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugin_queries as $plugin_name => $data): 
                    $impact_level = 'low';
                    if ($data['total_queries'] > 50 || $data['slow_queries'] > 0) {
                        $impact_level = 'high';
                    } elseif ($data['total_queries'] > 20 || $data['average_time'] > 10) {
                        $impact_level = 'medium';
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html($plugin_name); ?></strong></td>
                    <td><?php echo esc_html($data['total_queries']); ?></td>
                    <td><?php echo esc_html($data['total_time']); ?></td>
                    <td><?php echo esc_html($data['average_time']); ?></td>
                    <td>
                        <?php if ($data['slow_queries'] > 0): ?>
                            <span class="slow-queries-count"><?php echo esc_html($data['slow_queries']); ?></span>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="impact-rating impact-<?php echo esc_attr($impact_level); ?>">
                            <?php echo esc_html(ucfirst($impact_level)); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!defined('SAVEQUERIES') || !SAVEQUERIES): ?>
        <div class="alert alert-warning">
            <p>
                <strong><?php _e('Notice:', 'plugin-autopsy'); ?></strong>
                <?php _e('To get more detailed database query tracking, please add', 'plugin-autopsy'); ?>
                <code>define('SAVEQUERIES', true);</code>
                <?php _e('to your wp-config.php file.', 'plugin-autopsy'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="database-recommendations">
        <h3><?php _e('Database Optimization Recommendations', 'plugin-autopsy'); ?></h3>
        
        <?php 
        $high_query_plugins = array_filter($plugin_queries, function($data) { 
            return $data['total_queries'] > 50; 
        });
        
        $slow_query_plugins = array_filter($plugin_queries, function($data) { 
            return $data['slow_queries'] > 0; 
        });
        ?>
        
        <?php if (!empty($high_query_plugins)): ?>
            <div class="recommendation-item">
                <div class="recommendation-title"><?php _e('High Query Count Detected', 'plugin-autopsy'); ?></div>
                <div class="recommendation-description">
                    <?php _e('The following plugins are making an excessive number of database queries:', 'plugin-autopsy'); ?>
                    <ul>
                        <?php foreach ($high_query_plugins as $plugin => $data): ?>
                            <li><strong><?php echo esc_html($plugin); ?></strong> - <?php echo esc_html($data['total_queries']); ?> queries</li>
                        <?php endforeach; ?>
                    </ul>
                    <?php _e('Consider implementing caching or looking for more efficient alternatives.', 'plugin-autopsy'); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($slow_query_plugins)): ?>
            <div class="recommendation-item">
                <div class="recommendation-title"><?php _e('Slow Queries Found', 'plugin-autopsy'); ?></div>
                <div class="recommendation-description">
                    <?php _e('The following plugins have slow database queries (>50ms):', 'plugin-autopsy'); ?>
                    <ul>
                        <?php foreach ($slow_query_plugins as $plugin => $data): ?>
                            <li><strong><?php echo esc_html($plugin); ?></strong> - <?php echo esc_html($data['slow_queries']); ?> slow queries</li>
                        <?php endforeach; ?>
                    </ul>
                    <?php _e('These queries should be optimized or indexes should be added to improve performance.', 'plugin-autopsy'); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($high_query_plugins) && empty($slow_query_plugins)): ?>
            <div class="alert alert-info">
                <p><?php _e('Great! No database performance issues detected. Your plugins are handling database operations efficiently.', 'plugin-autopsy'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>