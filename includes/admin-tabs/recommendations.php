<?php

if (!defined('ABSPATH')) {
    exit;
}

$plugin_instance = plugin_autopsy();
$recommendations = $plugin_instance->generate_recommendations($time_condition);

?>

<div class="plugin-autopsy-recommendations">
    <div class="recommendations-header">
        <h3><?php _e('Performance Recommendations', 'plugin-autopsy'); ?></h3>
        <p><?php _e('Based on your plugin usage analysis, here are our recommendations to improve your site performance:', 'plugin-autopsy'); ?></p>
    </div>

    <?php if (empty($recommendations)): ?>
        <div class="alert alert-info">
            <p><?php _e('Great! No critical performance issues were detected. Your plugins are performing well.', 'plugin-autopsy'); ?></p>
        </div>
    <?php else: ?>
        
        <?php foreach ($recommendations as $recommendation): ?>
            <div class="recommendation-item">
                <div class="recommendation-header">
                    <span class="recommendation-title"><?php echo esc_html($recommendation['title']); ?></span>
                    <span class="recommendation-priority priority-<?php echo esc_attr($recommendation['priority']); ?>">
                        <?php echo esc_html(ucfirst($recommendation['priority'])); ?> Priority
                    </span>
                </div>
                
                <div class="recommendation-description">
                    <?php echo wp_kses_post($recommendation['description']); ?>
                </div>
                
                <?php if (!empty($recommendation['affected_plugins'])): ?>
                    <div class="affected-plugins">
                        <strong><?php _e('Affected Plugins:', 'plugin-autopsy'); ?></strong>
                        <ul>
                            <?php foreach ($recommendation['affected_plugins'] as $plugin): ?>
                                <li><?php echo esc_html($plugin); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recommendation['action_steps'])): ?>
                    <div class="action-steps">
                        <strong><?php _e('Recommended Actions:', 'plugin-autopsy'); ?></strong>
                        <ol>
                            <?php foreach ($recommendation['action_steps'] as $step): ?>
                                <li><?php echo wp_kses_post($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recommendation['potential_savings'])): ?>
                    <div class="potential-savings">
                        <strong><?php _e('Potential Performance Improvement:', 'plugin-autopsy'); ?></strong>
                        <?php echo wp_kses_post($recommendation['potential_savings']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>

    <div class="comparison-section">
        <h3><?php _e('Plugin Performance Comparison', 'plugin-autopsy'); ?></h3>
        
        <div class="comparison-charts">
            <div class="chart-container">
                <h4><?php _e('Resource Usage Comparison', 'plugin-autopsy'); ?></h4>
                <canvas id="performance-comparison-chart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <div class="comparison-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Plugin', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Performance Score', 'plugin-autopsy'); ?></th>
                        <th><?php _e('DB Impact', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Memory Impact', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Asset Impact', 'plugin-autopsy'); ?></th>
                        <th><?php _e('Recommendation', 'plugin-autopsy'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plugin_instance->get_plugin_comparison_data($time_condition) as $plugin_data): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($plugin_data['name']); ?></strong>
                                <div class="plugin-version"><?php echo esc_html($plugin_data['version']); ?></div>
                            </td>
                            <td>
                                <div class="performance-score score-<?php echo esc_attr($plugin_data['score_level']); ?>">
                                    <?php echo esc_html($plugin_data['performance_score']); ?>/100
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" data-percentage="<?php echo esc_attr($plugin_data['performance_score']); ?>"></div>
                                </div>
                            </td>
                            <td>
                                <div class="impact-rating impact-<?php echo esc_attr($plugin_data['db_impact_level']); ?>">
                                    <?php echo esc_html(ucfirst($plugin_data['db_impact_level'])); ?>
                                </div>
                                <div class="impact-details">
                                    <?php echo esc_html($plugin_data['db_queries']); ?> queries
                                    <br><?php echo esc_html($plugin_data['db_time']); ?>ms
                                </div>
                            </td>
                            <td>
                                <div class="impact-rating impact-<?php echo esc_attr($plugin_data['memory_impact_level']); ?>">
                                    <?php echo esc_html(ucfirst($plugin_data['memory_impact_level'])); ?>
                                </div>
                                <div class="impact-details">
                                    <?php echo esc_html($plugin_data['memory_usage']); ?>
                                    <br><?php echo esc_html($plugin_data['memory_percent']); ?>%
                                </div>
                            </td>
                            <td>
                                <div class="impact-rating impact-<?php echo esc_attr($plugin_data['asset_impact_level']); ?>">
                                    <?php echo esc_html(ucfirst($plugin_data['asset_impact_level'])); ?>
                                </div>
                                <div class="impact-details">
                                    <?php echo esc_html($plugin_data['asset_count']); ?> files
                                    <br><?php echo esc_html($plugin_data['asset_size']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="plugin-recommendation">
                                    <?php echo wp_kses_post($plugin_data['recommendation']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.comparisonChartData = <?php echo json_encode($plugin_instance->get_comparison_chart_data($time_condition)); ?>;
</script>

