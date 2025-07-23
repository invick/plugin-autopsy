<?php

if (!defined('ABSPATH')) {
    exit;
}

class Plugin_Autopsy_Memory_Tracker {
    
    private $memory_snapshots = [];
    private $plugin_memory_usage = [];
    private $initial_memory = 0;
    private $hook_memory = [];
    
    public function __construct() {
        $this->initial_memory = memory_get_usage(true);
        $this->init();
    }
    
    private function init() {
        add_action('plugins_loaded', [$this, 'take_snapshot'], 1);
        add_action('init', [$this, 'take_snapshot']);
        add_action('wp_loaded', [$this, 'take_snapshot']);
        add_action('template_redirect', [$this, 'take_snapshot']);
        add_action('wp_head', [$this, 'take_snapshot']);
        add_action('wp_footer', [$this, 'take_snapshot']);
        add_action('shutdown', [$this, 'analyze_memory_usage'], 1);
        
        $this->hook_into_plugin_actions();
    }
    
    private function hook_into_plugin_actions() {
        $priority = PHP_INT_MAX;
        
        $hooks_to_monitor = [
            'wp_enqueue_scripts',
            'admin_enqueue_scripts',
            'wp_ajax_*',
            'wp_ajax_nopriv_*',
            'rest_api_init',
            'admin_init',
            'admin_menu',
            'widgets_init'
        ];
        
        foreach ($hooks_to_monitor as $hook) {
            add_action($hook, function() use ($hook) {
                $this->track_hook_memory($hook);
            }, $priority);
        }
    }
    
    public function take_snapshot($context = '') {
        if (empty($context)) {
            $context = current_action();
        }
        
        $this->memory_snapshots[$context] = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'time' => microtime(true)
        ];
    }
    
    public function track_hook_memory($hook) {
        $before_memory = memory_get_usage(true);
        
        $this->hook_memory[$hook] = [
            'before' => $before_memory,
            'plugins' => $this->get_active_plugins_for_hook($hook)
        ];
        
        register_shutdown_function(function() use ($hook, $before_memory) {
            $after_memory = memory_get_usage(true);
            $memory_increase = $after_memory - $before_memory;
            
            if (isset($this->hook_memory[$hook])) {
                $this->hook_memory[$hook]['after'] = $after_memory;
                $this->hook_memory[$hook]['increase'] = $memory_increase;
                
                $this->attribute_memory_to_plugins($hook, $memory_increase);
            }
        });
    }
    
    private function get_active_plugins_for_hook($hook) {
        global $wp_filter;
        
        $active_plugins = [];
        
        if (isset($wp_filter[$hook])) {
            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $plugin_info = $this->identify_plugin_from_callback($callback['function']);
                    if ($plugin_info) {
                        $active_plugins[] = $plugin_info;
                    }
                }
            }
        }
        
        return $active_plugins;
    }
    
    private function identify_plugin_from_callback($callback) {
        if (is_string($callback)) {
            $function_info = new ReflectionFunction($callback);
            $filename = $function_info->getFileName();
        } elseif (is_array($callback) && count($callback) === 2) {
            if (is_object($callback[0])) {
                $reflection = new ReflectionClass($callback[0]);
                $filename = $reflection->getFileName();
            } elseif (is_string($callback[0])) {
                try {
                    $reflection = new ReflectionClass($callback[0]);
                    $filename = $reflection->getFileName();
                } catch (ReflectionException $e) {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
        
        if (!$filename) {
            return false;
        }
        
        return $this->identify_plugin_from_file($filename);
    }
    
    private function identify_plugin_from_file($filename) {
        $plugins_dir = wp_normalize_path(WP_PLUGIN_DIR);
        $filename = wp_normalize_path($filename);
        
        if (strpos($filename, $plugins_dir) === 0) {
            $relative_path = str_replace($plugins_dir . '/', '', $filename);
            $plugin_parts = explode('/', $relative_path);
            
            if (!empty($plugin_parts[0])) {
                return [
                    'slug' => $plugin_parts[0],
                    'file' => $filename
                ];
            }
        }
        
        return false;
    }
    
    private function attribute_memory_to_plugins($hook, $memory_increase) {
        if (!isset($this->hook_memory[$hook]['plugins'])) {
            return;
        }
        
        $plugins = $this->hook_memory[$hook]['plugins'];
        $plugin_count = count($plugins);
        
        if ($plugin_count === 0) {
            return;
        }
        
        $memory_per_plugin = $memory_increase / $plugin_count;
        
        foreach ($plugins as $plugin_info) {
            $slug = $plugin_info['slug'];
            
            if (!isset($this->plugin_memory_usage[$slug])) {
                $this->plugin_memory_usage[$slug] = [
                    'total_memory' => 0,
                    'hook_usage' => [],
                    'peak_memory' => 0,
                    'snapshots' => []
                ];
            }
            
            $this->plugin_memory_usage[$slug]['total_memory'] += $memory_per_plugin;
            $this->plugin_memory_usage[$slug]['hook_usage'][$hook] = ($this->plugin_memory_usage[$slug]['hook_usage'][$hook] ?? 0) + $memory_per_plugin;
        }
    }
    
    public function analyze_memory_usage() {
        $this->take_snapshot('shutdown');
        $this->calculate_plugin_memory_impact();
        $this->store_memory_data();
    }
    
    private function calculate_plugin_memory_impact() {
        $total_memory_increase = memory_get_usage(true) - $this->initial_memory;
        
        foreach ($this->plugin_memory_usage as $slug => &$data) {
            $data['percentage_of_total'] = $total_memory_increase > 0 ? ($data['total_memory'] / $total_memory_increase) * 100 : 0;
            $data['formatted_memory'] = $this->format_bytes($data['total_memory']);
            $data['peak_memory'] = memory_get_peak_usage(true);
        }
        
        uasort($this->plugin_memory_usage, function($a, $b) {
            return $b['total_memory'] <=> $a['total_memory'];
        });
    }
    
    private function store_memory_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        $current_url = $this->get_current_url();
        
        $total_memory_data = [
            'initial_memory' => $this->initial_memory,
            'final_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'total_increase' => memory_get_usage(true) - $this->initial_memory,
            'snapshots' => $this->memory_snapshots,
            'hook_memory' => $this->hook_memory
        ];
        
        $wpdb->insert(
            $table_name,
            [
                'plugin_name' => 'system_memory',
                'metric_type' => 'memory_usage',
                'metric_value' => json_encode($total_memory_data),
                'page_url' => $current_url,
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        foreach ($this->plugin_memory_usage as $plugin_slug => $data) {
            $metric_value = json_encode([
                'total_memory' => $data['total_memory'],
                'formatted_memory' => $data['formatted_memory'],
                'percentage_of_total' => $data['percentage_of_total'],
                'hook_usage' => $data['hook_usage'],
                'peak_memory' => $data['peak_memory']
            ]);
            
            $wpdb->insert(
                $table_name,
                [
                    'plugin_name' => $plugin_slug,
                    'metric_type' => 'memory_usage',
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
    
    public function get_plugin_memory_usage() {
        return $this->plugin_memory_usage;
    }
    
    public function get_memory_snapshots() {
        return $this->memory_snapshots;
    }
    
    public function get_summary() {
        $summary = [];
        
        foreach ($this->plugin_memory_usage as $plugin_slug => $data) {
            $summary[$plugin_slug] = [
                'memory_usage' => $data['total_memory'],
                'formatted_memory' => $data['formatted_memory'],
                'percentage' => round($data['percentage_of_total'], 2),
                'top_hooks' => $this->get_top_memory_hooks($data['hook_usage'], 3)
            ];
        }
        
        return $summary;
    }
    
    private function get_top_memory_hooks($hook_usage, $limit = 3) {
        arsort($hook_usage);
        return array_slice($hook_usage, 0, $limit, true);
    }
    
    private function format_bytes($size) {
        if ($size === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($size, 1024);
        
        return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
    }
}