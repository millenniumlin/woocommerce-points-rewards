/**
 * WooCommerce Points & Rewards - 統計圖表功能
 * 
 * @package WC_Points_Rewards
 */

(function($) {
    'use strict';

    // 圖表管理器
    var ChartsManager = {
        
        init: function() {
            this.loadChartLibrary();
            this.bindEvents();
            this.initCharts();
        },
        
        loadChartLibrary: function() {
            // 檢查是否已載入 Chart.js
            if (typeof Chart === 'undefined') {
                // 動態載入 Chart.js
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = function() {
                    ChartsManager.initCharts();
                };
                document.head.appendChild(script);
            }
        },
        
        bindEvents: function() {
            // 圖表篩選器變更
            $(document).on('change', '.chart-filter', this.handleFilterChange);
            
            // 圖表類型切換
            $(document).on('click', '.chart-type-toggle', this.handleChartTypeToggle);
            
            // 匯出圖表
            $(document).on('click', '.export-chart', this.handleExportChart);
            
            // 重新載入圖表
            $(document).on('click', '.refresh-chart', this.handleRefreshChart);
        },
        
        initCharts: function() {
            // 點數趨勢圖表
            this.createPointsTrendChart();
            
            // 會員等級分布圖表
            this.createTierDistributionChart();
            
            // 點數活動圖表
            this.createPointsActivityChart();
            
            // 收益統計圖表
            this.createRevenueChart();
        },
        
        createPointsTrendChart: function() {
            var canvas = document.getElementById('points-trend-chart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var data = this.getPointsTrendData();
            
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '獲得點數',
                        data: data.earned,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.3
                    }, {
                        label: '使用點數',
                        data: data.redeemed,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.3
                    }, {
                        label: '到期點數',
                        data: data.expired,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: '點數趨勢分析',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    var percentage = ((context.parsed.y / data.total[context.dataIndex]) * 100).toFixed(1);
                                    return '佔比: ' + percentage + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: '日期'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: '點數'
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // 儲存圖表實例
            this.pointsTrendChart = chart;
        },
        
        createTierDistributionChart: function() {
            var canvas = document.getElementById('tier-distribution-chart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var data = this.getTierDistributionData();
            
            var chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB', 
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: '會員等級分布',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' 人 (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            this.tierDistributionChart = chart;
        },
        
        createPointsActivityChart: function() {
            var canvas = document.getElementById('points-activity-chart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var data = this.getPointsActivityData();
            
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '購物獲得',
                        data: data.shopping,
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    }, {
                        label: '註冊獲得',
                        data: data.registration,
                        backgroundColor: '#17a2b8',
                        borderColor: '#117a8b',
                        borderWidth: 1
                    }, {
                        label: '生日獲得',
                        data: data.birthday,
                        backgroundColor: '#ffc107',
                        borderColor: '#e0a800',
                        borderWidth: 1
                    }, {
                        label: '管理員調整',
                        data: data.admin,
                        backgroundColor: '#6c757d',
                        borderColor: '#5a6268',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: '點數活動分析',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            title: {
                                display: true,
                                text: '時間週期'
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '點數數量'
                            }
                        }
                    }
                }
            });
            
            this.pointsActivityChart = chart;
        },
        
        createRevenueChart: function() {
            var canvas = document.getElementById('revenue-chart');
            if (!canvas) return;
            
            var ctx = canvas.getContext('2d');
            var data = this.getRevenueData();
            
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '總收益',
                        data: data.total,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    }, {
                        label: '點數折扣',
                        data: data.discount,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    }, {
                        label: '點數使用率 (%)',
                        data: data.usage_rate,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: '收益與點數使用分析',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: '日期'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: '金額 (元)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: '使用率 (%)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
            
            this.revenueChart = chart;
        },
        
        // 獲取模擬數據的方法（實際使用時應從後端獲取）
        getPointsTrendData: function() {
            // 這裡應該從 AJAX 獲取真實數據
            return window.pointsTrendData || {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月'],
                earned: [1200, 1900, 3000, 5000, 2300, 3200],
                redeemed: [800, 1200, 2000, 3200, 1500, 2100],
                expired: [50, 80, 120, 200, 100, 150],
                total: [2050, 3180, 5120, 8400, 3900, 5450]
            };
        },
        
        getTierDistributionData: function() {
            return window.tierDistributionData || {
                labels: ['一般會員', '微光會員', '曙光會員', '熾光會員'],
                values: [45, 25, 20, 10]
            };
        },
        
        getPointsActivityData: function() {
            return window.pointsActivityData || {
                labels: ['本週', '上週', '本月', '上月'],
                shopping: [800, 900, 3200, 2800],
                registration: [50, 45, 180, 160],
                birthday: [30, 25, 120, 100],
                admin: [20, 15, 80, 60]
            };
        },
        
        getRevenueData: function() {
            return window.revenueData || {
                labels: ['1月', '2月', '3月', '4月', '5月', '6月'],
                total: [12000, 19000, 30000, 50000, 23000, 32000],
                discount: [800, 1200, 2000, 3200, 1500, 2100],
                usage_rate: [6.7, 6.3, 6.7, 6.4, 6.5, 6.6]
            };
        },
        
        handleFilterChange: function() {
            var $filter = $(this);
            var chartType = $filter.data('chart');
            var filterValue = $filter.val();
            
            // 根據篩選器重新載入對應圖表數據
            ChartsManager.updateChart(chartType, filterValue);
        },
        
        handleChartTypeToggle: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var chartId = $button.data('chart');
            var newType = $button.data('type');
            
            // 切換圖表類型
            ChartsManager.changeChartType(chartId, newType);
            
            // 更新按鈕狀態
            $button.siblings().removeClass('active');
            $button.addClass('active');
        },
        
        handleExportChart: function(e) {
            e.preventDefault();
            
            var chartId = $(this).data('chart');
            var format = $(this).data('format') || 'png';
            
            ChartsManager.exportChart(chartId, format);
        },
        
        handleRefreshChart: function(e) {
            e.preventDefault();
            
            var chartId = $(this).data('chart');
            ChartsManager.refreshChart(chartId);
        },
        
        updateChart: function(chartType, filterValue) {
            // 發送 AJAX 請求獲取新數據
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_get_chart_data',
                    chart_type: chartType,
                    filter_value: filterValue,
                    nonce: wcPointsRewardsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChartsManager.updateChartData(chartType, response.data);
                    }
                }
            });
        },
        
        updateChartData: function(chartType, newData) {
            var chart = this[chartType + 'Chart'];
            if (chart) {
                chart.data = newData;
                chart.update('resize');
            }
        },
        
        changeChartType: function(chartId, newType) {
            var chart = this[chartId + 'Chart'];
            if (chart) {
                chart.config.type = newType;
                chart.update('resize');
            }
        },
        
        exportChart: function(chartId, format) {
            var chart = this[chartId + 'Chart'];
            if (chart) {
                var url = chart.toBase64Image();
                var link = document.createElement('a');
                link.download = chartId + '-chart.' + format;
                link.href = url;
                link.click();
            }
        },
        
        refreshChart: function(chartId) {
            var chart = this[chartId + 'Chart'];
            if (chart) {
                chart.destroy();
                
                // 重新創建圖表
                switch(chartId) {
                    case 'points-trend':
                        this.createPointsTrendChart();
                        break;
                    case 'tier-distribution':
                        this.createTierDistributionChart();
                        break;
                    case 'points-activity':
                        this.createPointsActivityChart();
                        break;
                    case 'revenue':
                        this.createRevenueChart();
                        break;
                }
            }
        },
        
        // 響應式處理
        handleResize: function() {
            Object.keys(this).forEach(function(key) {
                if (key.endsWith('Chart') && this[key]) {
                    this[key].resize();
                }
            }.bind(this));
        }
    };
    
    // 圖表工具函數
    var ChartUtils = {
        
        // 格式化數字
        formatNumber: function(number, decimals) {
            decimals = decimals || 0;
            return number.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        // 生成顏色調色板
        generateColors: function(count) {
            var colors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
            ];
            
            return colors.slice(0, count);
        },
        
        // 計算百分比
        calculatePercentage: function(value, total) {
            return total > 0 ? ((value / total) * 100).toFixed(1) : 0;
        },
        
        // 平滑動畫
        animationConfig: {
            duration: 1500,
            easing: 'easeInOutQuart'
        }
    };
    
    // 當 DOM 準備好時初始化
    $(document).ready(function() {
        ChartsManager.init();
        
        // 視窗大小改變時重新調整圖表
        $(window).on('resize', function() {
            clearTimeout(this.resizeTimeout);
            this.resizeTimeout = setTimeout(function() {
                ChartsManager.handleResize();
            }, 250);
        });
    });
    
    // 匯出到全域
    window.WCPointsRewardsCharts = {
        ChartsManager: ChartsManager,
        ChartUtils: ChartUtils
    };

})(jQuery);