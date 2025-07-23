<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'plugin_autopsy_data';

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
$time_range = isset($_GET['time_range']) ? sanitize_text_field($_GET['time_range']) : '24h';

$time_conditions = [
    '1h' => "timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    '24h' => "timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
    '7d' => "timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30d' => "timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
];

$time_condition = isset($time_conditions[$time_range]) ? $time_conditions[$time_range] : $time_conditions['24h'];

?>

<div class="wrap">
    <h1><?php _e('Plugin Autopsy - Performance Profiler', 'plugin-autopsy'); ?></h1>
    
    <div class="plugin-autopsy-header">
        <div class="time-range-selector">
            <label for="time-range"><?php _e('Time Range:', 'plugin-autopsy'); ?></label>
            <select id="time-range" name="time_range">
                <option value="1h" <?php selected($time_range, '1h'); ?>><?php _e('Last Hour', 'plugin-autopsy'); ?></option>
                <option value="24h" <?php selected($time_range, '24h'); ?>><?php _e('Last 24 Hours', 'plugin-autopsy'); ?></option>
                <option value="7d" <?php selected($time_range, '7d'); ?>><?php _e('Last 7 Days', 'plugin-autopsy'); ?></option>
                <option value="30d" <?php selected($time_range, '30d'); ?>><?php _e('Last 30 Days', 'plugin-autopsy'); ?></option>
            </select>
        </div>
    </div>

    <nav class="nav-tab-wrapper">
        <a href="?page=plugin-autopsy&tab=overview&time_range=<?php echo esc_attr($time_range); ?>" 
           class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Overview', 'plugin-autopsy'); ?>
        </a>
        <a href="?page=plugin-autopsy&tab=database&time_range=<?php echo esc_attr($time_range); ?>" 
           class="nav-tab <?php echo $active_tab === 'database' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Database Queries', 'plugin-autopsy'); ?>
        </a>
        <a href="?page=plugin-autopsy&tab=assets&time_range=<?php echo esc_attr($time_range); ?>" 
           class="nav-tab <?php echo $active_tab === 'assets' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Asset Loading', 'plugin-autopsy'); ?>
        </a>
        <a href="?page=plugin-autopsy&tab=memory&time_range=<?php echo esc_attr($time_range); ?>" 
           class="nav-tab <?php echo $active_tab === 'memory' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Memory Usage', 'plugin-autopsy'); ?>
        </a>
        <a href="?page=plugin-autopsy&tab=recommendations&time_range=<?php echo esc_attr($time_range); ?>" 
           class="nav-tab <?php echo $active_tab === 'recommendations' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Recommendations', 'plugin-autopsy'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php
        switch ($active_tab) {
            case 'overview':
                include_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-tabs/overview.php';
                break;
            case 'database':
                include_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-tabs/database.php';
                break;
            case 'assets':
                include_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-tabs/assets.php';
                break;
            case 'memory':
                include_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-tabs/memory.php';
                break;
            case 'recommendations':
                include_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-tabs/recommendations.php';
                break;
            default:
                include_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-tabs/overview.php';
        }
        ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#time-range').on('change', function() {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('time_range', $(this).val());
        window.location.href = currentUrl.toString();
    });
    
    $('.plugin-toggle').on('click', function() {
        const pluginSlug = $(this).data('plugin');
        const $details = $('#plugin-details-' + pluginSlug);
        
        if ($details.is(':visible')) {
            $details.slideUp();
            $(this).text('Show Details');
        } else {
            $details.slideDown();
            $(this).text('Hide Details');
        }
    });
});
</script>