<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'plugin_autopsy_data';

$asset_data = $wpdb->get_results($wpdb->prepare("
    SELECT plugin_name, metric_value 
    FROM {$table_name} 
    WHERE metric_type = 'asset_loading' AND {$time_condition}
    ORDER BY timestamp DESC
"));

$plugin_instance = plugin_autopsy();
$plugin_assets = [];
foreach ($asset_data as $row) {
    $metric_value = json_decode($row->metric_value, true);
    $plugin_name = $plugin_instance->get_plugin_name_from_slug($row->plugin_name);
    
    if (!isset($plugin_assets[$plugin_name])) {
        $plugin_assets[$plugin_name] = [
            'js_files' => 0,
            'css_files' => 0,
            'total_size' => 0,
            'js_size' => 0,
            'css_size' => 0
        ];
    }
    
    $plugin_assets[$plugin_name]['js_files'] += $metric_value['js_files'] ?? 0;
    $plugin_assets[$plugin_name]['css_files'] += $metric_value['css_files'] ?? 0;
    $plugin_assets[$plugin_name]['total_size'] += $metric_value['total_size'] ?? 0;
    $plugin_assets[$plugin_name]['js_size'] += $metric_value['total_js_size'] ?? 0;
    $plugin_assets[$plugin_name]['css_size'] += $metric_value['total_css_size'] ?? 0;
}

function format_bytes($size) {
    if ($size === 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    
    return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
}

uasort($plugin_assets, function($a, $b) {
    return $b['total_size'] <=> $a['total_size'];
});

?>

<div class="plugin-autopsy-assets">
    <div class="asset-stats">
        <div class="stat-card">
            <h3><?php _e('Total JS Files', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo array_sum(array_column($plugin_assets, 'js_files')); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total CSS Files', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo array_sum(array_column($plugin_assets, 'css_files')); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Total Asset Size', 'plugin-autopsy'); ?></h3>
            <div class="stat-number"><?php echo format_bytes(array_sum(array_column($plugin_assets, 'total_size'))); ?></div>
        </div>
        
        <div class="stat-card">
            <h3><?php _e('Largest Plugin', 'plugin-autopsy'); ?></h3>
            <div class="stat-number">
                <?php 
                if (!empty($plugin_assets)) {
                    $largest = array_keys($plugin_assets)[0];
                    echo format_bytes($plugin_assets[$largest]['total_size']);
                } else {
                    echo '0 B';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="asset-table">
        <h3><?php _e('Asset Loading by Plugin', 'plugin-autopsy'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'plugin-autopsy'); ?></th>
                    <th><?php _e('JS Files', 'plugin-autopsy'); ?></th>
                    <th><?php _e('CSS Files', 'plugin-autopsy'); ?></th>
                    <th><?php _e('JS Size', 'plugin-autopsy'); ?></th>
                    <th><?php _e('CSS Size', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Total Size', 'plugin-autopsy'); ?></th>
                    <th><?php _e('Impact Level', 'plugin-autopsy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugin_assets as $plugin_name => $data): 
                    $total_files = $data['js_files'] + $data['css_files'];
                    $impact_level = 'low';
                    
                    if ($data['total_size'] > 1000000 || $total_files > 10) { // 1MB or 10+ files
                        $impact_level = 'high';
                    } elseif ($data['total_size'] > 500000 || $total_files > 5) { // 500KB or 5+ files
                        $impact_level = 'medium';
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html($plugin_name); ?></strong></td>
                    <td><?php echo esc_html($data['js_files']); ?></td>
                    <td><?php echo esc_html($data['css_files']); ?></td>
                    <td><?php echo esc_html(format_bytes($data['js_size'])); ?></td>
                    <td><?php echo esc_html(format_bytes($data['css_size'])); ?></td>
                    <td><strong><?php echo esc_html(format_bytes($data['total_size'])); ?></strong></td>
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

    <div class="asset-chart-container">
        <h3><?php _e('Asset Size Distribution', 'plugin-autopsy'); ?></h3>
        <canvas id="asset-size-chart" width="400" height="200"></canvas>
    </div>

    <div class="asset-recommendations">
        <h3><?php _e('Asset Optimization Recommendations', 'plugin-autopsy'); ?></h3>
        
        <?php 
        $large_asset_plugins = array_filter($plugin_assets, function($data) { 
            return $data['total_size'] > 500000; // 500KB
        });
        
        $many_files_plugins = array_filter($plugin_assets, function($data) { 
            return ($data['js_files'] + $data['css_files']) > 5; 
        });
        ?>
        
        <?php if (!empty($large_asset_plugins)): ?>
            <div class="recommendation-item">
                <div class="recommendation-title"><?php _e('Large Asset Files Detected', 'plugin-autopsy'); ?></div>
                <div class="recommendation-description">
                    <?php _e('The following plugins are loading large asset files:', 'plugin-autopsy'); ?>
                    <ul>
                        <?php foreach ($large_asset_plugins as $plugin => $data): ?>
                            <li><strong><?php echo esc_html($plugin); ?></strong> - <?php echo esc_html(format_bytes($data['total_size'])); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <strong><?php _e('Recommendations:', 'plugin-autopsy'); ?></strong>
                    <ul>
                        <li><?php _e('Minify and compress CSS/JS files', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Use a CDN for faster asset delivery', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Load assets only on pages where needed', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Consider combining multiple small files', 'plugin-autopsy'); ?></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($many_files_plugins)): ?>
            <div class="recommendation-item">
                <div class="recommendation-title"><?php _e('Many Asset Files', 'plugin-autopsy'); ?></div>
                <div class="recommendation-description">
                    <?php _e('The following plugins are loading many separate files:', 'plugin-autopsy'); ?>
                    <ul>
                        <?php foreach ($many_files_plugins as $plugin => $data): ?>
                            <li><strong><?php echo esc_html($plugin); ?></strong> - <?php echo esc_html($data['js_files'] + $data['css_files']); ?> files</li>
                        <?php endforeach; ?>
                    </ul>
                    <strong><?php _e('Recommendations:', 'plugin-autopsy'); ?></strong>
                    <ul>
                        <li><?php _e('Combine multiple files into fewer bundles', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Use file concatenation techniques', 'plugin-autopsy'); ?></li>
                        <li><?php _e('Implement lazy loading for non-critical assets', 'plugin-autopsy'); ?></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($large_asset_plugins) && empty($many_files_plugins)): ?>
            <div class="alert alert-info">
                <p><?php _e('Great! Your plugins are loading assets efficiently. No major performance issues detected.', 'plugin-autopsy'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.assetChartData = {
    labels: [<?php echo implode(',', array_map(function($plugin) { return '"' . esc_js($plugin) . '"'; }, array_keys(array_slice($plugin_assets, 0, 10, true)))); ?>],
    data: [<?php echo implode(',', array_map(function($data) { return round($data['total_size'] / 1024, 2); }, array_slice($plugin_assets, 0, 10, true))); ?>]
};
</script>