<?php

if (!defined('ABSPATH')) {
    exit;
}

class Plugin_Autopsy_Query_Tracker {
    
    private $queries = [];
    private $query_start_times = [];
    private $plugin_queries = [];
    
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            add_filter('query', [$this, 'track_query'], 10, 1);
            add_action('shutdown', [$this, 'analyze_queries'], 1);
        } else {
            add_action('admin_notices', [$this, 'savequeries_notice']);
        }
    }
    
    public function savequeries_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p>';
            echo __('Plugin Autopsy: To track database queries, please add <code>define("SAVEQUERIES", true);</code> to your wp-config.php file.', 'plugin-autopsy');
            echo '</p></div>';
        }
    }
    
    public function track_query($query) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $plugin_info = $this->identify_plugin_from_backtrace($backtrace);
        
        if ($plugin_info) {
            $query_id = uniqid();
            $this->query_start_times[$query_id] = microtime(true);
            
            $this->queries[$query_id] = [
                'query' => $query,
                'plugin' => $plugin_info,
                'backtrace' => $backtrace,
                'start_time' => $this->query_start_times[$query_id]
            ];
        }
        
        return $query;
    }
    
    private function identify_plugin_from_backtrace($backtrace) {
        $wp_content_dir = wp_normalize_path(WP_CONTENT_DIR);
        $plugins_dir = wp_normalize_path(WP_PLUGIN_DIR);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                $file = wp_normalize_path($trace['file']);
                
                if (strpos($file, $plugins_dir) === 0) {
                    $relative_path = str_replace($plugins_dir . '/', '', $file);
                    $plugin_parts = explode('/', $relative_path);
                    
                    if (!empty($plugin_parts[0])) {
                        return [
                            'slug' => $plugin_parts[0],
                            'file' => $file,
                            'line' => isset($trace['line']) ? $trace['line'] : 0,
                            'function' => isset($trace['function']) ? $trace['function'] : ''
                        ];
                    }
                }
            }
        }
        
        return false;
    }
    
    public function analyze_queries() {
        global $wpdb;
        
        if (!empty($wpdb->queries)) {
            foreach ($wpdb->queries as $query_data) {
                $query = $query_data[0];
                $time = $query_data[1];
                $stack = isset($query_data[2]) ? $query_data[2] : '';
                
                $plugin_info = $this->identify_plugin_from_stack($stack);
                
                if ($plugin_info) {
                    if (!isset($this->plugin_queries[$plugin_info['slug']])) {
                        $this->plugin_queries[$plugin_info['slug']] = [
                            'queries' => [],
                            'total_time' => 0,
                            'query_count' => 0,
                            'slow_queries' => []
                        ];
                    }
                    
                    $this->plugin_queries[$plugin_info['slug']]['queries'][] = [
                        'query' => $query,
                        'time' => $time,
                        'file' => $plugin_info['file'],
                        'line' => $plugin_info['line']
                    ];
                    
                    $this->plugin_queries[$plugin_info['slug']]['total_time'] += $time;
                    $this->plugin_queries[$plugin_info['slug']]['query_count']++;
                    
                    if ($time > 0.05) {
                        $this->plugin_queries[$plugin_info['slug']]['slow_queries'][] = [
                            'query' => $query,
                            'time' => $time,
                            'file' => $plugin_info['file'],
                            'line' => $plugin_info['line']
                        ];
                    }
                }
            }
        }
        
        $this->store_query_data();
    }
    
    private function identify_plugin_from_stack($stack) {
        if (empty($stack)) {
            return false;
        }
        
        $plugins_dir = wp_normalize_path(WP_PLUGIN_DIR);
        $lines = explode("\n", $stack);
        
        foreach ($lines as $line) {
            if (preg_match('/([^(]+)\((\d+)\)/', $line, $matches)) {
                $file = wp_normalize_path(trim($matches[1]));
                $line_number = intval($matches[2]);
                
                if (strpos($file, $plugins_dir) === 0) {
                    $relative_path = str_replace($plugins_dir . '/', '', $file);
                    $plugin_parts = explode('/', $relative_path);
                    
                    if (!empty($plugin_parts[0])) {
                        return [
                            'slug' => $plugin_parts[0],
                            'file' => $file,
                            'line' => $line_number
                        ];
                    }
                }
            }
        }
        
        return false;
    }
    
    private function store_query_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        $current_url = $this->get_current_url();
        
        foreach ($this->plugin_queries as $plugin_slug => $data) {
            // Sanitize queries before storage
            $sanitized_queries = $this->sanitize_queries_for_storage(array_slice($data['queries'], 0, 10));
            $sanitized_slow_queries = $this->sanitize_queries_for_storage($data['slow_queries']);
            
            $metric_value = json_encode([
                'query_count' => $data['query_count'],
                'total_time' => $data['total_time'],
                'average_time' => $data['query_count'] > 0 ? $data['total_time'] / $data['query_count'] : 0,
                'slow_query_count' => count($data['slow_queries']),
                'queries' => $sanitized_queries,
                'slow_queries' => $sanitized_slow_queries
            ]);
            
            $wpdb->insert(
                $table_name,
                [
                    'plugin_name' => $plugin_slug,
                    'metric_type' => 'database_queries',
                    'metric_value' => $metric_value,
                    'page_url' => $current_url,
                    'timestamp' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
    }
    
    private function get_current_url() {
        if (is_admin()) {
            global $pagenow;
            return admin_url($pagenow . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
        }
        
        return home_url(add_query_arg(null, null));
    }
    
    public function get_plugin_queries() {
        return $this->plugin_queries;
    }
    
    public function get_summary() {
        $summary = [];
        
        foreach ($this->plugin_queries as $plugin_slug => $data) {
            $summary[$plugin_slug] = [
                'query_count' => $data['query_count'],
                'total_time' => round($data['total_time'], 4),
                'average_time' => round($data['query_count'] > 0 ? $data['total_time'] / $data['query_count'] : 0, 4),
                'slow_queries' => count($data['slow_queries'])
            ];
        }
        
        uasort($summary, function($a, $b) {
            return $b['total_time'] <=> $a['total_time'];
        });
        
        return $summary;
    }
    
    /**
     * Sanitize queries array for safe storage
     */
    private function sanitize_queries_for_storage($queries) {
        if (empty($queries) || !is_array($queries)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($queries as $query_data) {
            if (!is_array($query_data)) {
                continue;
            }
            
            $sanitized_query = $query_data;
            
            // Sanitize the query text if present
            if (isset($query_data['query'])) {
                $sanitized_query['query'] = $this->sanitize_query_text($query_data['query']);
            }
            
            // Convert absolute file paths to relative paths
            if (isset($query_data['file'])) {
                $sanitized_query['file'] = $this->get_relative_file_path($query_data['file']);
            }
            
            $sanitized[] = $sanitized_query;
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize SQL query text by removing sensitive data
     */
    private function sanitize_query_text($query) {
        if (empty($query)) {
            return '';
        }
        
        // Check if query logging is disabled
        if (defined('PLUGIN_AUTOPSY_LOG_QUERIES') && !PLUGIN_AUTOPSY_LOG_QUERIES) {
            return '[Query logging disabled]';
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
            '/nonce\s*=\s*[\'"][^\'"]*[\'"]/i' => 'nonce = [REDACTED]',
            // Remove email addresses from queries
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[EMAIL]',
            // Remove potential IP addresses
            '/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/' => '[IP]',
        ];
        
        foreach ($sensitive_patterns as $pattern => $replacement) {
            $query = preg_replace($pattern, $replacement, $query);
        }
        
        // Limit query length for storage efficiency
        $max_length = PLUGIN_AUTOPSY_MAX_QUERY_LENGTH;
        if (strlen($query) > $max_length) {
            $query = substr($query, 0, $max_length) . '... [TRUNCATED]';
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
        
        // Check if file path logging is disabled
        if (defined('PLUGIN_AUTOPSY_LOG_PATHS') && !PLUGIN_AUTOPSY_LOG_PATHS) {
            return '[Path logging disabled]';
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
            if (strpos(wp_normalize_path($file_path), $absolute) === 0) {
                $relative_path = $relative . str_replace($absolute, '', wp_normalize_path($file_path));
                break;
            }
        }
        
        return $relative_path;
    }
}