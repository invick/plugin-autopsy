<?php
/**
 * Plugin Name: Plugin Autopsy
 * Plugin URI: https://github.com/yourusername/plugin-autopsy
 * Description: Plugin Bloat & Performance Profiler - Provides forensic-level analysis of what each plugin is doing under the hood including DB queries, file loads, memory usage, and performance impact.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plugin-autopsy
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PLUGIN_AUTOPSY_VERSION', '1.0.0');
define('PLUGIN_AUTOPSY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PLUGIN_AUTOPSY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Privacy and security configuration options
// Set these in wp-config.php to control what data is logged

// Control query logging (default: true)
if (!defined('PLUGIN_AUTOPSY_LOG_QUERIES')) {
    define('PLUGIN_AUTOPSY_LOG_QUERIES', true);
}

// Control file path logging (default: true)
if (!defined('PLUGIN_AUTOPSY_LOG_PATHS')) {
    define('PLUGIN_AUTOPSY_LOG_PATHS', true);
}

// Control memory tracking (default: true)
if (!defined('PLUGIN_AUTOPSY_LOG_MEMORY')) {
    define('PLUGIN_AUTOPSY_LOG_MEMORY', true);
}

// Control asset tracking (default: true)
if (!defined('PLUGIN_AUTOPSY_LOG_ASSETS')) {
    define('PLUGIN_AUTOPSY_LOG_ASSETS', true);
}

// Maximum query length to store (default: 1000 characters)
if (!defined('PLUGIN_AUTOPSY_MAX_QUERY_LENGTH')) {
    define('PLUGIN_AUTOPSY_MAX_QUERY_LENGTH', 1000);
}

// Data retention period in days (default: 30 days)
if (!defined('PLUGIN_AUTOPSY_DATA_RETENTION_DAYS')) {
    define('PLUGIN_AUTOPSY_DATA_RETENTION_DAYS', 30);
}

class PluginAutopsy {
    
    private static $instance = null;
    private $profiler_data = [];
    private $query_tracker;
    private $asset_tracker;
    private $memory_tracker;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init_trackers']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_plugin_autopsy_refresh_data', [$this, 'ajax_refresh_data']);
        add_action('wp_ajax_plugin_autopsy_clear_data', [$this, 'ajax_clear_data']);
        add_action('wp_ajax_plugin_autopsy_get_slow_queries', [$this, 'ajax_get_slow_queries']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('plugin-autopsy', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function init_trackers() {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            require_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/class-query-tracker.php';
            require_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/class-asset-tracker.php';
            require_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/class-memory-tracker.php';
            
            $this->query_tracker = new Plugin_Autopsy_Query_Tracker();
            $this->asset_tracker = new Plugin_Autopsy_Asset_Tracker();
            $this->memory_tracker = new Plugin_Autopsy_Memory_Tracker();
        }
    }
    
    public function add_admin_menu() {
        add_management_page(
            __('Plugin Autopsy', 'plugin-autopsy'),
            __('Plugin Autopsy', 'plugin-autopsy'),
            'manage_options',
            'plugin-autopsy',
            [$this, 'admin_page']
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('tools_page_plugin-autopsy' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'plugin-autopsy-admin',
            PLUGIN_AUTOPSY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            PLUGIN_AUTOPSY_VERSION,
            true
        );
        
        wp_enqueue_style(
            'plugin-autopsy-admin',
            PLUGIN_AUTOPSY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PLUGIN_AUTOPSY_VERSION
        );
        
        wp_localize_script('plugin-autopsy-admin', 'pluginAutopsy', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('plugin_autopsy_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'plugin-autopsy'),
                'error' => __('Error occurred while fetching data', 'plugin-autopsy'),
            ]
        ]);
    }
    
    public function admin_page() {
        require_once PLUGIN_AUTOPSY_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    public function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            plugin_name varchar(255) NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value longtext NOT NULL,
            page_url varchar(500) DEFAULT '',
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plugin_name (plugin_name),
            KEY metric_type (metric_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        add_option('plugin_autopsy_version', PLUGIN_AUTOPSY_VERSION);
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('plugin_autopsy_cleanup');
    }
    
    public function get_profiler_data() {
        return $this->profiler_data;
    }
    
    public function add_profiler_data($plugin, $type, $data) {
        if (!isset($this->profiler_data[$plugin])) {
            $this->profiler_data[$plugin] = [];
        }
        
        if (!isset($this->profiler_data[$plugin][$type])) {
            $this->profiler_data[$plugin][$type] = [];
        }
        
        $this->profiler_data[$plugin][$type][] = $data;
    }
    
    public function get_overview_data($time_condition) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        
        $query_data = $wpdb->get_results($wpdb->prepare("
            SELECT plugin_name, metric_type, metric_value 
            FROM {$table_name} 
            WHERE {$time_condition}
            ORDER BY timestamp DESC
        "));
        
        $plugin_stats = [];
        $total_queries = 0;
        $total_assets = 0;
        $peak_memory = 0;
        
        foreach ($query_data as $row) {
            $plugin = $row->plugin_name;
            $metric_type = $row->metric_type;
            $metric_value = json_decode($row->metric_value, true);
            
            if (!isset($plugin_stats[$plugin])) {
                $plugin_stats[$plugin] = [
                    'name' => $this->get_plugin_name_from_slug($plugin),
                    'query_count' => 0,
                    'query_time' => 0,
                    'memory_usage' => 0,
                    'memory_percent' => 0,
                    'asset_count' => 0,
                    'asset_size' => 0,
                    'impact_score' => 0
                ];
            }
            
            switch ($metric_type) {
                case 'database_queries':
                    $plugin_stats[$plugin]['query_count'] += $metric_value['query_count'] ?? 0;
                    $plugin_stats[$plugin]['query_time'] += $metric_value['total_time'] ?? 0;
                    $total_queries += $metric_value['query_count'] ?? 0;
                    break;
                    
                case 'asset_loading':
                    $plugin_stats[$plugin]['asset_count'] += ($metric_value['js_files'] ?? 0) + ($metric_value['css_files'] ?? 0);
                    $plugin_stats[$plugin]['asset_size'] += $metric_value['total_size'] ?? 0;
                    $total_assets += ($metric_value['js_files'] ?? 0) + ($metric_value['css_files'] ?? 0);
                    break;
                    
                case 'memory_usage':
                    if ($plugin !== 'system_memory') {
                        $plugin_stats[$plugin]['memory_usage'] += $metric_value['total_memory'] ?? 0;
                        $plugin_stats[$plugin]['memory_percent'] += $metric_value['percentage_of_total'] ?? 0;
                    } else {
                        $peak_memory = max($peak_memory, $metric_value['peak_memory'] ?? 0);
                    }
                    break;
            }
        }
        
        foreach ($plugin_stats as $plugin => &$stats) {
            $stats['query_time'] = round($stats['query_time'] * 1000, 2);
            $stats['memory_usage'] = $this->format_bytes($stats['memory_usage']);
            $stats['memory_percent'] = round($stats['memory_percent'], 1);
            $stats['asset_size'] = $this->format_bytes($stats['asset_size']);
            $stats['description'] = $this->get_plugin_description($plugin);
            
            $impact_score = 0;
            $impact_score += min(($stats['query_count'] / 10) * 20, 30);
            $impact_score += min(($stats['memory_percent'] / 10) * 25, 35);
            $impact_score += min(($stats['asset_count'] / 5) * 15, 20);
            $impact_score += min(($stats['query_time'] / 100) * 15, 15);
            
            $stats['impact_score'] = round($impact_score);
            $stats['impact_level'] = $impact_score > 70 ? 'high' : ($impact_score > 40 ? 'medium' : 'low');
        }
        
        uasort($plugin_stats, function($a, $b) {
            return $b['impact_score'] <=> $a['impact_score'];
        });
        
        return [
            'total_plugins' => count($plugin_stats),
            'total_queries' => $total_queries,
            'total_assets' => $total_assets,
            'peak_memory' => $this->format_bytes($peak_memory),
            'top_plugins' => array_slice($plugin_stats, 0, 10, true)
        ];
    }
    
    public function get_plugin_name_from_slug($slug) {
        if ($slug === 'wordpress-core') {
            return 'WordPress Core';
        }
        
        if ($slug === 'active-theme') {
            return 'Active Theme';
        }
        
        if ($slug === 'system_memory') {
            return 'System Memory';
        }
        
        $plugin_file = $slug . '/' . $slug . '.php';
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            $plugins = get_plugins();
            foreach ($plugins as $file => $data) {
                if (strpos($file, $slug . '/') === 0) {
                    return $data['Name'];
                }
            }
        } else {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
            return $plugin_data['Name'];
        }
        
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
    
    private function get_plugin_description($slug) {
        if ($slug === 'wordpress-core') {
            return 'WordPress core functionality';
        }
        
        if ($slug === 'active-theme') {
            return 'Current active theme';
        }
        
        $plugin_file = $slug . '/' . $slug . '.php';
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            $plugins = get_plugins();
            foreach ($plugins as $file => $data) {
                if (strpos($file, $slug . '/') === 0) {
                    return $data['Description'];
                }
            }
        } else {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
            return $plugin_data['Description'];
        }
        
        return 'Plugin information not available';
    }
    
    private function format_bytes($size) {
        if ($size === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($size, 1024);
        
        return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
    }
    
    public function generate_recommendations($time_condition) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        
        $recommendations = [];
        
        $query_data = $wpdb->get_results($wpdb->prepare("
            SELECT plugin_name, metric_type, metric_value 
            FROM {$table_name} 
            WHERE {$time_condition}
            ORDER BY timestamp DESC
        "));
        
        $plugin_metrics = [];
        
        foreach ($query_data as $row) {
            $plugin = $row->plugin_name;
            $metric_type = $row->metric_type;
            $metric_value = json_decode($row->metric_value, true);
            
            if (!isset($plugin_metrics[$plugin])) {
                $plugin_metrics[$plugin] = [];
            }
            
            $plugin_metrics[$plugin][$metric_type] = $metric_value;
        }
        
        foreach ($plugin_metrics as $plugin_slug => $metrics) {
            $plugin_name = $this->get_plugin_name_from_slug($plugin_slug);
            
            if (isset($metrics['database_queries'])) {
                $query_data = $metrics['database_queries'];
                
                if (($query_data['query_count'] ?? 0) > 50) {
                    $recommendations[] = [
                        'title' => sprintf(__('High Database Query Count - %s', 'plugin-autopsy'), $plugin_name),
                        'description' => sprintf(__('The plugin %s is making %d database queries per page load, which is significantly high and may slow down your site.', 'plugin-autopsy'), $plugin_name, $query_data['query_count']),
                        'priority' => ($query_data['query_count'] > 100) ? 'high' : 'medium',
                        'affected_plugins' => [$plugin_name],
                        'action_steps' => [
                            __('Consider implementing caching for this plugin', 'plugin-autopsy'),
                            __('Look for alternative plugins with better performance', 'plugin-autopsy'),
                            __('Contact the plugin developer about optimization', 'plugin-autopsy')
                        ],
                        'potential_savings' => sprintf(__('Reducing queries could improve page load time by %dms', 'plugin-autopsy'), ($query_data['total_time'] ?? 0) * 1000)
                    ];
                }
                
                if (($query_data['slow_query_count'] ?? 0) > 0) {
                    $recommendations[] = [
                        'title' => sprintf(__('Slow Database Queries Detected - %s', 'plugin-autopsy'), $plugin_name),
                        'description' => sprintf(__('The plugin %s has %d slow queries (>50ms each) that are impacting performance.', 'plugin-autopsy'), $plugin_name, $query_data['slow_query_count']),
                        'priority' => 'high',
                        'affected_plugins' => [$plugin_name],
                        'action_steps' => [
                            __('Review and optimize slow queries', 'plugin-autopsy'),
                            __('Add database indexes if needed', 'plugin-autopsy'),
                            __('Consider query optimization or caching', 'plugin-autopsy')
                        ],
                        'potential_savings' => __('Optimizing slow queries can significantly improve response times', 'plugin-autopsy')
                    ];
                }
            }
            
            if (isset($metrics['memory_usage'])) {
                $memory_data = $metrics['memory_usage'];
                
                if (($memory_data['percentage_of_total'] ?? 0) > 20) {
                    $recommendations[] = [
                        'title' => sprintf(__('High Memory Usage - %s', 'plugin-autopsy'), $plugin_name),
                        'description' => sprintf(__('The plugin %s is using %s of memory (%.1f%% of total), which is quite high.', 'plugin-autopsy'), $plugin_name, $memory_data['formatted_memory'], $memory_data['percentage_of_total']),
                        'priority' => ($memory_data['percentage_of_total'] > 30) ? 'high' : 'medium',
                        'affected_plugins' => [$plugin_name],
                        'action_steps' => [
                            __('Check for memory leaks in the plugin', 'plugin-autopsy'),
                            __('Consider alternatives with lower memory footprint', 'plugin-autopsy'),
                            __('Increase server memory if this plugin is essential', 'plugin-autopsy')
                        ],
                        'potential_savings' => sprintf(__('Reducing memory usage by %s', 'plugin-autopsy'), $memory_data['formatted_memory'])
                    ];
                }
            }
            
            if (isset($metrics['asset_loading'])) {
                $asset_data = $metrics['asset_loading'];
                $total_size = ($asset_data['total_js_size'] ?? 0) + ($asset_data['total_css_size'] ?? 0);
                
                if ($total_size > 500000) { // 500KB
                    $recommendations[] = [
                        'title' => sprintf(__('Large Asset Files - %s', 'plugin-autopsy'), $plugin_name),
                        'description' => sprintf(__('The plugin %s is loading %s of CSS/JS files, which may slow down page loading.', 'plugin-autopsy'), $plugin_name, $this->format_bytes($total_size)),
                        'priority' => ($total_size > 1000000) ? 'high' : 'medium',
                        'affected_plugins' => [$plugin_name],
                        'action_steps' => [
                            __('Minify and compress CSS/JS files', 'plugin-autopsy'),
                            __('Load assets only on pages where needed', 'plugin-autopsy'),
                            __('Use a CDN for faster asset delivery', 'plugin-autopsy'),
                            __('Consider combining multiple small files', 'plugin-autopsy')
                        ],
                        'potential_savings' => sprintf(__('File optimization could reduce load time by reducing %s of assets', 'plugin-autopsy'), $this->format_bytes($total_size))
                    ];
                }
            }
        }
        
        if (count($plugin_metrics) > 20) {
            $recommendations[] = [
                'title' => __('Too Many Active Plugins', 'plugin-autopsy'),
                'description' => sprintf(__('You have %d active plugins. Having too many plugins can slow down your site even if each plugin is optimized.', 'plugin-autopsy'), count($plugin_metrics)),
                'priority' => 'medium',
                'affected_plugins' => [],
                'action_steps' => [
                    __('Review and deactivate unused plugins', 'plugin-autopsy'),
                    __('Look for plugins that provide multiple features', 'plugin-autopsy'),
                    __('Consider custom development for simple features', 'plugin-autopsy')
                ],
                'potential_savings' => __('Reducing plugin count can improve overall site performance', 'plugin-autopsy')
            ];
        }
        
        return $recommendations;
    }
    
    public function get_plugin_comparison_data($time_condition) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        
        $query_data = $wpdb->get_results($wpdb->prepare("
            SELECT plugin_name, metric_type, metric_value 
            FROM {$table_name} 
            WHERE {$time_condition}
            ORDER BY timestamp DESC
        "));
        
        $plugin_data = [];
        
        foreach ($query_data as $row) {
            $plugin = $row->plugin_name;
            $metric_type = $row->metric_type;
            $metric_value = json_decode($row->metric_value, true);
            
            if (!isset($plugin_data[$plugin])) {
                $plugin_data[$plugin] = [
                    'name' => $this->get_plugin_name_from_slug($plugin),
                    'version' => $this->get_plugin_version($plugin),
                    'db_queries' => 0,
                    'db_time' => 0,
                    'memory_usage' => 0,
                    'memory_percent' => 0,
                    'asset_count' => 0,
                    'asset_size' => 0
                ];
            }
            
            switch ($metric_type) {
                case 'database_queries':
                    $plugin_data[$plugin]['db_queries'] = $metric_value['query_count'] ?? 0;
                    $plugin_data[$plugin]['db_time'] = round(($metric_value['total_time'] ?? 0) * 1000, 2);
                    break;
                    
                case 'memory_usage':
                    $plugin_data[$plugin]['memory_usage'] = $this->format_bytes($metric_value['total_memory'] ?? 0);
                    $plugin_data[$plugin]['memory_percent'] = round($metric_value['percentage_of_total'] ?? 0, 1);
                    break;
                    
                case 'asset_loading':
                    $plugin_data[$plugin]['asset_count'] = ($metric_value['js_files'] ?? 0) + ($metric_value['css_files'] ?? 0);
                    $plugin_data[$plugin]['asset_size'] = $this->format_bytes($metric_value['total_size'] ?? 0);
                    break;
            }
        }
        
        foreach ($plugin_data as $plugin => &$data) {
            $score = $this->calculate_performance_score($data);
            $data['performance_score'] = $score;
            $data['score_level'] = $score > 80 ? 'good' : ($score > 60 ? 'medium' : 'poor');
            
            $data['db_impact_level'] = $this->get_impact_level($data['db_queries'], [5, 15]);
            $data['memory_impact_level'] = $this->get_impact_level($data['memory_percent'], [5, 15]);
            $data['asset_impact_level'] = $this->get_impact_level($data['asset_count'], [3, 8]);
            
            $data['recommendation'] = $this->generate_plugin_recommendation($data);
        }
        
        uasort($plugin_data, function($a, $b) {
            return $a['performance_score'] <=> $b['performance_score'];
        });
        
        return $plugin_data;
    }
    
    public function get_comparison_chart_data($time_condition) {
        $plugin_data = $this->get_plugin_comparison_data($time_condition);
        
        $labels = [];
        $scores = [];
        
        foreach (array_slice($plugin_data, 0, 10, true) as $plugin => $data) {
            $labels[] = $data['name'];
            $scores[] = $data['performance_score'];
        }
        
        return [
            'labels' => $labels,
            'data' => $scores
        ];
    }
    
    private function calculate_performance_score($data) {
        $score = 100;
        
        $score -= min(($data['db_queries'] / 5) * 10, 30);
        $score -= min(($data['memory_percent'] / 5) * 15, 25);
        $score -= min(($data['asset_count'] / 3) * 10, 20);
        $score -= min(($data['db_time'] / 50) * 15, 25);
        
        return max(0, round($score));
    }
    
    private function get_impact_level($value, $thresholds) {
        if ($value <= $thresholds[0]) return 'low';
        if ($value <= $thresholds[1]) return 'medium';
        return 'high';
    }
    
    private function generate_plugin_recommendation($data) {
        if ($data['performance_score'] > 80) {
            return __('Good performance', 'plugin-autopsy');
        } elseif ($data['performance_score'] > 60) {
            return __('Consider optimization', 'plugin-autopsy');
        } else {
            return __('Needs attention - consider alternatives', 'plugin-autopsy');
        }
    }
    
    private function get_plugin_version($slug) {
        if (in_array($slug, ['wordpress-core', 'active-theme', 'system_memory'])) {
            return '';
        }
        
        $plugin_file = $slug . '/' . $slug . '.php';
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            $plugins = get_plugins();
            foreach ($plugins as $file => $data) {
                if (strpos($file, $slug . '/') === 0) {
                    return $data['Version'];
                }
            }
        } else {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
            return $plugin_data['Version'];
        }
        
        return '';
    }
    
    /**
     * AJAX handler for refreshing performance data
     */
    public function ajax_refresh_data() {
        // Verify nonce
        if (!check_ajax_referer('plugin_autopsy_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'plugin-autopsy')]);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'plugin-autopsy')]);
            return;
        }
        
        try {
            // Clear any existing output buffers to force fresh data collection
            if (ob_get_level()) {
                ob_clean();
            }
            
            // Trigger data collection by visiting current page
            wp_send_json_success(['message' => __('Data refresh initiated. Please reload the page to see updated results.', 'plugin-autopsy')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error refreshing data: ', 'plugin-autopsy') . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX handler for clearing old performance data
     */
    public function ajax_clear_data() {
        // Verify nonce
        if (!check_ajax_referer('plugin_autopsy_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'plugin-autopsy')]);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'plugin-autopsy')]);
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        
        try {
            // Delete data older than configured retention period
            $retention_days = PLUGIN_AUTOPSY_DATA_RETENTION_DAYS;
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$table_name} 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", $retention_days));
            
            if ($deleted === false) {
                wp_send_json_error(['message' => __('Database error occurred while clearing data', 'plugin-autopsy')]);
                return;
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Successfully cleared %d old records', 'plugin-autopsy'), $deleted),
                'deleted_count' => $deleted
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error clearing data: ', 'plugin-autopsy') . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX handler for getting slow queries for a specific plugin
     */
    public function ajax_get_slow_queries() {
        // Verify nonce
        if (!check_ajax_referer('plugin_autopsy_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'plugin-autopsy')]);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'plugin-autopsy')]);
            return;
        }
        
        // Sanitize plugin parameter
        $plugin_slug = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        
        if (empty($plugin_slug)) {
            wp_send_json_error(['message' => __('Plugin parameter is required', 'plugin-autopsy')]);
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        
        try {
            $slow_queries_data = $wpdb->get_results($wpdb->prepare("
                SELECT metric_value, timestamp 
                FROM {$table_name} 
                WHERE plugin_name = %s 
                AND metric_type = 'database_queries'
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY timestamp DESC 
                LIMIT 10
            ", $plugin_slug));
            
            $slow_queries_html = '<h3>' . sprintf(__('Slow Queries for %s', 'plugin-autopsy'), esc_html($this->get_plugin_name_from_slug($plugin_slug))) . '</h3>';
            
            if (empty($slow_queries_data)) {
                $slow_queries_html .= '<p>' . __('No slow queries found in the last 24 hours.', 'plugin-autopsy') . '</p>';
            } else {
                $slow_queries_html .= '<div class="slow-queries-list">';
                
                foreach ($slow_queries_data as $data) {
                    $metric_value = json_decode($data->metric_value, true);
                    $slow_queries = $metric_value['slow_queries'] ?? [];
                    
                    if (!empty($slow_queries)) {
                        foreach ($slow_queries as $query) {
                            $sanitized_query = $this->sanitize_query_for_display($query['query'] ?? '');
                            $query_time = number_format(($query['time'] ?? 0) * 1000, 2);
                            
                            $slow_queries_html .= '<div class="slow-query-item">';
                            $slow_queries_html .= '<div class="query-time"><strong>' . $query_time . 'ms</strong></div>';
                            $slow_queries_html .= '<div class="query-text"><code>' . esc_html($sanitized_query) . '</code></div>';
                            if (!empty($query['file'])) {
                                $relative_file = $this->get_relative_file_path($query['file']);
                                $slow_queries_html .= '<div class="query-file">File: ' . esc_html($relative_file) . ':' . intval($query['line'] ?? 0) . '</div>';
                            }
                            $slow_queries_html .= '</div>';
                        }
                    }
                }
                
                $slow_queries_html .= '</div>';
            }
            
            wp_send_json_success($slow_queries_html);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error retrieving slow queries: ', 'plugin-autopsy') . $e->getMessage()]);
        }
    }
    
    /**
     * Sanitize SQL queries for safe display by removing sensitive data
     */
    private function sanitize_query_for_display($query) {
        if (empty($query)) {
            return '';
        }
        
        // Patterns to remove sensitive data
        $sensitive_patterns = [
            '/password\s*=\s*[\'"][^\'"]*[\'"]/i' => 'password = [REDACTED]',
            '/pwd\s*=\s*[\'"][^\'"]*[\'"]/i' => 'pwd = [REDACTED]',
            '/token\s*=\s*[\'"][^\'"]*[\'"]/i' => 'token = [REDACTED]',
            '/api_key\s*=\s*[\'"][^\'"]*[\'"]/i' => 'api_key = [REDACTED]',
            '/secret\s*=\s*[\'"][^\'"]*[\'"]/i' => 'secret = [REDACTED]',
            '/auth\s*=\s*[\'"][^\'"]*[\'"]/i' => 'auth = [REDACTED]',
            '/session\s*=\s*[\'"][^\'"]*[\'"]/i' => 'session = [REDACTED]',
        ];
        
        foreach ($sensitive_patterns as $pattern => $replacement) {
            $query = preg_replace($pattern, $replacement, $query);
        }
        
        // Limit query length for display
        if (strlen($query) > 500) {
            $query = substr($query, 0, 500) . '... [TRUNCATED]';
        }
        
        return $query;
    }
    
    /**
     * Convert absolute file paths to relative paths for security
     */
    private function get_relative_file_path($file_path) {
        if (empty($file_path)) {
            return '';
        }
        
        // Remove sensitive server paths
        $replacements = [
            wp_normalize_path(ABSPATH) => '',
            wp_normalize_path(WP_CONTENT_DIR) => '/wp-content',
            wp_normalize_path(WP_PLUGIN_DIR) => '/wp-content/plugins',
            wp_normalize_path(get_theme_root()) => '/wp-content/themes',
        ];
        
        $relative_path = $file_path;
        foreach ($replacements as $absolute => $relative) {
            if (strpos($file_path, $absolute) === 0) {
                $relative_path = $relative . str_replace($absolute, '', $file_path);
                break;
            }
        }
        
        return $relative_path;
    }
}

function plugin_autopsy() {
    return PluginAutopsy::get_instance();
}

plugin_autopsy();