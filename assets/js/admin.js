jQuery(document).ready(function($) {
    
    $('#refresh-data').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text(pluginAutopsy.strings.loading).prop('disabled', true);
        
        $.ajax({
            url: pluginAutopsy.ajaxUrl,
            type: 'POST',
            data: {
                action: 'plugin_autopsy_refresh_data',
                nonce: pluginAutopsy.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || pluginAutopsy.strings.error);
                }
            },
            error: function() {
                alert(pluginAutopsy.strings.error);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    $('#export-report').on('click', function() {
        const currentUrl = new URL(window.location);
        const exportUrl = currentUrl.toString() + '&export=csv';
        window.open(exportUrl, '_blank');
    });
    
    $('#clear-data').on('click', function() {
        if (!confirm('Are you sure you want to clear old performance data? This action cannot be undone.')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text(pluginAutopsy.strings.loading).prop('disabled', true);
        
        $.ajax({
            url: pluginAutopsy.ajaxUrl,
            type: 'POST',
            data: {
                action: 'plugin_autopsy_clear_data',
                nonce: pluginAutopsy.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Old data has been cleared successfully.');
                    location.reload();
                } else {
                    alert(response.data || pluginAutopsy.strings.error);
                }
            },
            error: function() {
                alert(pluginAutopsy.strings.error);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    $('.plugin-row').on('click', function() {
        const $row = $(this);
        const $details = $row.next('.plugin-details');
        
        if ($details.length && $details.hasClass('plugin-details')) {
            $details.slideToggle();
            $row.find('.toggle-icon').text($details.is(':visible') ? '−' : '+');
        }
    });
    
    $('.sort-column').on('click', function(e) {
        e.preventDefault();
        
        const $this = $(this);
        const column = $this.data('column');
        const direction = $this.hasClass('asc') ? 'desc' : 'asc';
        
        $('.sort-column').removeClass('asc desc');
        $this.addClass(direction);
        
        sortTable(column, direction);
    });
    
    function sortTable(column, direction) {
        const $table = $('.wp-list-table tbody');
        const $rows = $table.find('tr').get();
        
        $rows.sort(function(a, b) {
            const aVal = getCellValue(a, column);
            const bVal = getCellValue(b, column);
            
            if ($.isNumeric(aVal) && $.isNumeric(bVal)) {
                return direction === 'asc' ? aVal - bVal : bVal - aVal;
            }
            
            return direction === 'asc' ? 
                aVal.localeCompare(bVal) : 
                bVal.localeCompare(aVal);
        });
        
        $.each($rows, function(index, row) {
            $table.append(row);
        });
    }
    
    function getCellValue(row, column) {
        const $cell = $(row).find('td').eq(getColumnIndex(column));
        const text = $cell.text().trim();
        
        const numMatch = text.match(/[\d.,]+/);
        return numMatch ? parseFloat(numMatch[0].replace(',', '')) : text;
    }
    
    function getColumnIndex(column) {
        const headers = $('.wp-list-table th');
        let index = 0;
        
        headers.each(function(i) {
            if ($(this).text().toLowerCase().includes(column.toLowerCase())) {
                index = i;
                return false;
            }
        });
        
        return index;
    }
    
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    }
    
    function initializeCharts() {
        const $memoryChart = $('#memory-usage-chart');
        if ($memoryChart.length) {
            createMemoryChart($memoryChart[0]);
        }
        
        const $queryChart = $('#query-performance-chart');
        if ($queryChart.length) {
            createQueryChart($queryChart[0]);
        }
        
        const $assetChart = $('#asset-loading-chart');
        if ($assetChart.length) {
            createAssetChart($assetChart[0]);
        }
    }
    
    function createMemoryChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: window.memoryChartData?.labels || [],
                datasets: [{
                    data: window.memoryChartData?.data || [],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Memory Usage by Plugin'
                    }
                }
            }
        });
    }
    
    function createQueryChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: window.queryChartData?.labels || [],
                datasets: [{
                    label: 'Query Count',
                    data: window.queryChartData?.queryData || [],
                    backgroundColor: '#36A2EB',
                    yAxisID: 'y'
                }, {
                    label: 'Query Time (ms)',
                    data: window.queryChartData?.timeData || [],
                    backgroundColor: '#FF6384',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Database Query Performance'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Query Count'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Time (ms)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }
    
    function createAssetChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: window.assetChartData?.labels || [],
                datasets: [{
                    label: 'Asset Size (KB)',
                    data: window.assetChartData?.data || [],
                    backgroundColor: '#FFCE56'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Asset Loading by Plugin'
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Size (KB)'
                        }
                    }
                }
            }
        });
    }
    
    $('.recommendation-dismiss').on('click', function() {
        const $recommendation = $(this).closest('.recommendation-item');
        $recommendation.fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    $('.view-slow-queries').on('click', function(e) {
        e.preventDefault();
        
        const pluginSlug = $(this).data('plugin');
        const $modal = $('#slow-queries-modal');
        
        if (!$modal.length) {
            $('body').append('<div id="slow-queries-modal" class="modal"><div class="modal-content"><span class="close">&times;</span><div class="modal-body"></div></div></div>');
        }
        
        $.ajax({
            url: pluginAutopsy.ajaxUrl,
            type: 'POST',
            data: {
                action: 'plugin_autopsy_get_slow_queries',
                plugin: pluginSlug,
                nonce: pluginAutopsy.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#slow-queries-modal .modal-body').html(response.data);
                    $('#slow-queries-modal').show();
                }
            }
        });
    });
    
    $(document).on('click', '.close, .modal', function(e) {
        if (e.target === this) {
            $('.modal').hide();
        }
    });
    
    function updateProgressBars() {
        $('.progress-bar .progress-fill').each(function() {
            const $this = $(this);
            const percentage = $this.data('percentage') || 0;
            $this.css('width', percentage + '%');
        });
    }
    
    updateProgressBars();
    
    setInterval(function() {
        if ($('#auto-refresh').is(':checked')) {
            location.reload();
        }
    }, 30000);
    
});