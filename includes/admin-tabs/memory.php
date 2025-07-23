<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'plugin_autopsy_data';

$memory_data = $wpdb->get_results($wpdb->prepare("
    SELECT plugin_name, metric_value 
    FROM {$table_name} 
    WHERE metric_type = 'memory_usage' AND {$time_condition}
    ORDER BY timestamp DESC
"));

$plugin_instance = plugin_autopsy();
$plugin_memory = [];
$system_memory = [];

foreach ($memory_data as $row) {
    $metric_value = json_decode($row->metric_value, true);
    
    if ($row->plugin_name === 'system_memory') {
        $system_memory = $metric_value;
        continue;
    }
    
    $plugin_name = $plugin_instance->get_plugin_name_from_slug($row->plugin_name);
    
    if (!isset($plugin_memory[$plugin_name])) {
        $plugin_memory[$plugin_name] = [
            'total_memory' => 0,
            'percentage' => 0,
            'formatted_memory' => '0 B'
        ];
    }
    
    $plugin_memory[$plugin_name]['total_memory'] += $metric_value['total_memory'] ?? 0;
    $plugin_memory[$plugin_name]['percentage'] += $metric_value['percentage_of_total'] ?? 0;
}

function format_bytes($size) {
    if ($size === 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    
    return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
}

foreach ($plugin_memory as $plugin => &$data) {
    $data['formatted_memory'] = format_bytes($data['total_memory']);
    $data['percentage'] = round($data['percentage'], 2);
}

uasort($plugin_memory, function($a, $b) {
    return $b['total_memory'] <=> $a['total_memory'];
});

?>

<div class="plugin-autopsy-memory">
    <div class="memory-stats">
        <div class="stat-card">
            <h3><?php _e('Peak Memory Usage', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo format_bytes($system_memory['peak_memory'] ?? 0); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Initial Memory', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo format_bytes($system_memory['initial_memory'] ?? 0); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Memory Increase', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo format_bytes($system_memory['total_increase'] ?? 0); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Plugins Tracked', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo count($plugin_memory); ?></div>
        </div>
    </div>

    <div class="memory-chart-container">
        <h3><?php _e('Memory Usage Distribution', 'plugin-autopsy'); ?></h3>
        <canvas id="memory-usage-chart" width="400" height="200"></canvas>
    </div>

    <div class="memory-table">
        <h3><?php _e('Memory Usage by Plugin', 'plugin-autopsy'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Memory Usage', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Percentage of Total', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Impact Level', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Status', 'plugin-autopsy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugin_memory as $plugin_name => $data): 
                    $impact_level = 'low';
                    $status = 'good';
                    
                    if ($data['percentage'] > 20) {
                        $impact_level = 'high';
                        $status = 'needs attention';
                    } elseif ($data['percentage'] > 10) {
                        $impact_level = 'medium';
                        $status = 'monitor';
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html($plugin_name); ?></strong></td>
                    <td><?php echo esc_html($data['formatted_memory']); ?></td>
                    <td>
                        <div class="percentage-bar">
                            <div class="percentage-fill" style="width: <?php echo min($data['percentage'], 100); ?>%;"></div>
                            <span class="percentage-text"><?php echo esc_html($data['percentage']); ?>%</span>
                        </div>
                    </td>
                    <td>
                        <div class="impact-rating impact-<?php echo esc_attr($impact_level); ?>">
                            <?php echo esc_html(ucfirst($impact_level)); ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-<?php echo esc_attr(str_replace(' ', '-', $status)); ?>">
                            <?php echo esc_html(ucfirst($status)); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($system_memory['snapshots'])): ?>
        <div class="memory-timeline">
            <h3><?php _e('Memory Usage Timeline', 'plugin-autopsy'); ?></h3>
            <div class="timeline-chart">
                <?php foreach ($system_memory['snapshots'] as $hook => $snapshot): ?>
                    <div class="timeline-item">
                        <div class="timeline-label"><?php echo esc_html($hook); ?></div>
                        <div class="timeline-value"><?php echo format_bytes($snapshot['memory_usage']); ?></div>
                        <div class="timeline-bar">
                            <div class="timeline-fill" style="width: <?php echo min(($snapshot['memory_usage'] / ($system_memory['peak_memory'] ?? 1)) * 100, 100); ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="memory-recommendations">
        <h3><?php _e('Memory Optimization Recommendations', 'plugin-autopsy'); ?></h3>
        
        <?php 
        $high_memory_plugins = array_filter($plugin_memory, function($data) { 
            return $data['percentage'] > 15; 
        });
        
        $total_plugin_percentage = array_sum(array_column($plugin_memory, 'percentage'));
        ?>
        
        <?php if (!empty($high_memory_plugins)): ?>
            <div class="recommendation-item">
                <div class="recommendation-title"><?php _e('High Memory Usage Detected', 'plugin-autopsy'); ?></div>
                <div class="recommendation-description">
                    <?php _e('The following plugins are using significant amounts of memory:', 'plugin-autopsy'); ?>
                    <ul>
                        <?php foreach ($high_memory_plugins as $plugin => $data): ?>
                            <li><strong><?php echo esc_html($plugin); ?></strong> - <?php echo esc_html($data['formatted_memory']); ?> (<?php echo esc_html($data['percentage']); ?>%)</li>
                        <?php endforeach; ?>
                    </ul>
                    <strong><?php _e('Recommendations:', 'plugin-autopsy'); ?></strong>
                    <ul>
                        <li><?php _e('Check for memory leaks in these plugins', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Consider alternatives with lower memory footprint', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Increase server memory if these plugins are essential', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Contact plugin authors about optimization opportunities', 'plugin-autopsy'); ?></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($total_plugin_percentage > 80): ?>
            <div class="recommendation-item">
                <div class="recommendation-title"><?php _e('Overall High Memory Usage', 'plugin-autopsy'); ?></div>
                <div class="recommendation-description">
                    <?php printf(__('Your plugins are collectively using %s%% of total memory, which is quite high.', 'plugin-autopsy'), number_format($total_plugin_percentage, 1)); ?>
                    <br><br>
                    <strong><?php _e('Recommendations:', 'plugin-autopsy'); ?></strong>
                    <ul>
                        <li><?php _e('Consider deactivating unnecessary plugins', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Look for plugins that provide multiple features to reduce plugin count', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Upgrade your server memory allocation', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Implement object caching to reduce memory pressure', 'plugin-autopsy'); ?></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($high_memory_plugins) && $total_plugin_percentage < 60): ?>
            <div class="alert alert-info">
                <p><?php _e('Excellent! Your plugins are using memory efficiently. No major memory issues detected.', 'plugin-autopsy'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.percentage-bar {
    position: relative;
    width: 100%;
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

.percentage-fill {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #FFC107, #F44336);
    transition: width 0.3s ease;
}

.percentage-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: bold;
    color: #333;
}

.timeline-chart {
    max-width: 600px;
}

.timeline-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    gap: 15px;
}

.timeline-label {
    min-width: 120px;
    font-size: 12px;
    color: #666;
}

.timeline-value {
    min-width: 80px;
    font-weight: bold;
    font-size: 12px;
}

.timeline-bar {
    flex: 1;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
}

.timeline-fill {
    height: 100%;
    background: #0073aa;
    transition: width 0.3s ease;
}

.status-good { color: #4CAF50; }
.status-monitor { color: #FFC107; }
.status-needs-attention { color: #F44336; }
</style>

<script>
window.memoryChartData = {
    labels: [<?php echo implode(',', array_map(function($plugin) { return '"' . esc_js($plugin) . '"'; }, array_keys(array_slice($plugin_memory, 0, 8, true)))); ?>],
    data: [<?php echo implode(',', array_column(array_slice($plugin_memory, 0, 8, true), 'percentage')); ?>]
};
</script>