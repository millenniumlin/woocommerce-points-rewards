/**
 * WooCommerce Points & Rewards - 管理後台 JavaScript
 * 
 * @package WC_Points_Rewards
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // 會員等級管理
    var TierManagement = {
        
        init: function() {
            this.bindEvents();
            this.initSortable();
        },
        
        bindEvents: function() {
            // 保存等級
            $(document).on('click', '.save-tier-btn', this.handleSaveTier);
            
            // 刪除等級
            $(document).on('click', '.delete-tier-btn', this.handleDeleteTier);
            
            // 編輯等級
            $(document).on('click', '.edit-tier-btn', this.handleEditTier);
            
            // 添加新等級
            $(document).on('click', '.add-tier-btn', this.handleAddTier);
            
            // 取消編輯
            $(document).on('click', '.cancel-edit-btn', this.handleCancelEdit);
            
            // 表單驗證
            $(document).on('input', '.tier-form input', this.validateForm);
        },
        
        initSortable: function() {
            if ($('.tiers-sortable').length > 0) {
                $('.tiers-sortable').sortable({
                    handle: '.tier-handle',
                    axis: 'y',
                    update: function(event, ui) {
                        TierManagement.updateTierOrder();
                    }
                });
            }
        },
        
        handleSaveTier: function(e) {
            e.preventDefault();
            
            var $form = $(this).closest('.tier-form');
            var $button = $(this);
            
            // 驗證表單
            if (!TierManagement.validateTierForm($form)) {
                return;
            }
            
            var formData = {
                action: 'wc_points_rewards_save_tier',
                nonce: wcPointsRewardsAdmin.nonce,
                tier_id: $form.find('[name="tier_id"]').val(),
                tier_name: $form.find('[name="tier_name"]').val(),
                min_amount: $form.find('[name="min_amount"]').val(),
                bonus_percentage: $form.find('[name="bonus_percentage"]').val(),
                tier_order: $form.find('[name="tier_order"]').val()
            };
            
            $button.prop('disabled', true).text(wcPointsRewardsAdmin.processing);
            
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        TierManagement.showNotice(response.data, 'success');
                        // 重新載入頁面或更新列表
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        TierManagement.showNotice(response.data, 'error');
                    }
                },
                error: function() {
                    TierManagement.showNotice(wcPointsRewardsAdmin.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('保存');
                }
            });
        },
        
        handleDeleteTier: function(e) {
            e.preventDefault();
            
            if (!confirm(wcPointsRewardsAdmin.confirmDelete)) {
                return;
            }
            
            var tierId = $(this).data('tier-id');
            var $button = $(this);
            
            $button.prop('disabled', true).text(wcPointsRewardsAdmin.processing);
            
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_delete_tier',
                    nonce: wcPointsRewardsAdmin.nonce,
                    tier_id: tierId
                },
                success: function(response) {
                    if (response.success) {
                        TierManagement.showNotice(response.data, 'success');
                        $button.closest('.tier-row').fadeOut(500, function() {
                            $(this).remove();
                        });
                    } else {
                        TierManagement.showNotice(response.data, 'error');
                        $button.prop('disabled', false).text('刪除');
                    }
                },
                error: function() {
                    TierManagement.showNotice(wcPointsRewardsAdmin.error, 'error');
                    $button.prop('disabled', false).text('刪除');
                }
            });
        },
        
        handleEditTier: function(e) {
            e.preventDefault();
            
            var $row = $(this).closest('.tier-row');
            $row.find('.tier-display').hide();
            $row.find('.tier-edit').show();
        },
        
        handleAddTier: function(e) {
            e.preventDefault();
            
            var $template = $('.tier-row-template').clone();
            $template.removeClass('tier-row-template').addClass('tier-row');
            $template.find('.tier-edit').show();
            $('.tiers-list').append($template);
        },
        
        handleCancelEdit: function(e) {
            e.preventDefault();
            
            var $row = $(this).closest('.tier-row');
            
            if ($row.find('[name="tier_id"]').val() === '') {
                // 新增的等級，直接移除
                $row.remove();
            } else {
                // 編輯現有等級，恢復顯示
                $row.find('.tier-display').show();
                $row.find('.tier-edit').hide();
                $row.find('.tier-form')[0].reset();
            }
        },
        
        updateTierOrder: function() {
            $('.tier-row').each(function(index) {
                $(this).find('[name="tier_order"]').val(index + 1);
            });
            
            // 自動保存排序
            var orders = [];
            $('.tier-row').each(function() {
                var tierId = $(this).find('[name="tier_id"]').val();
                if (tierId) {
                    orders.push({
                        id: tierId,
                        order: $(this).find('[name="tier_order"]').val()
                    });
                }
            });
            
            if (orders.length > 0) {
                $.ajax({
                    url: wcPointsRewardsAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_points_rewards_update_tier_order',
                        nonce: wcPointsRewardsAdmin.nonce,
                        orders: orders
                    }
                });
            }
        },
        
        validateTierForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // 驗證等級名稱
            var tierName = $form.find('[name="tier_name"]').val().trim();
            if (!tierName) {
                errors.push('等級名稱不能為空');
                isValid = false;
            }
            
            // 驗證最低消費金額
            var minAmount = parseFloat($form.find('[name="min_amount"]').val());
            if (isNaN(minAmount) || minAmount < 0) {
                errors.push('最低消費金額必須為有效數字');
                isValid = false;
            }
            
            // 驗證回饋百分比
            var bonusPercentage = parseFloat($form.find('[name="bonus_percentage"]').val());
            if (isNaN(bonusPercentage) || bonusPercentage < 0 || bonusPercentage > 100) {
                errors.push('回饋百分比必須在0-100之間');
                isValid = false;
            }
            
            if (!isValid) {
                TierManagement.showNotice(errors.join('<br>'), 'error');
            }
            
            return isValid;
        },
        
        validateForm: function() {
            var $input = $(this);
            var $form = $input.closest('.tier-form');
            
            // 即時驗證
            $input.removeClass('error');
            
            if ($input.attr('name') === 'tier_name') {
                if (!$input.val().trim()) {
                    $input.addClass('error');
                }
            } else if ($input.attr('name') === 'min_amount') {
                var value = parseFloat($input.val());
                if (isNaN(value) || value < 0) {
                    $input.addClass('error');
                }
            } else if ($input.attr('name') === 'bonus_percentage') {
                var value = parseFloat($input.val());
                if (isNaN(value) || value < 0 || value > 100) {
                    $input.addClass('error');
                }
            }
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">')
                .html('<p>' + message + '</p>')
                .hide();
            
            $('.wrap h1').after($notice);
            $notice.fadeIn();
            
            // 自動隱藏成功訊息
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        }
    };
    
    // 點數管理
    var PointsManagement = {
        
        init: function() {
            this.bindEvents();
            this.initFilters();
        },
        
        bindEvents: function() {
            // 手動添加點數
            $(document).on('click', '.add-points-btn', this.handleAddPoints);
            
            // 手動扣除點數
            $(document).on('click', '.deduct-points-btn', this.handleDeductPoints);
            
            // 批量操作
            $(document).on('change', '.bulk-action-select', this.handleBulkAction);
            
            // 搜尋用戶
            $(document).on('input', '.user-search', this.debounce(this.handleUserSearch, 300));
            
            // 篩選變更
            $(document).on('change', '.points-filters select', this.handleFilterChange);
        },
        
        initFilters: function() {
            // 初始化日期篩選器
            if ($('.date-filter').length > 0) {
                $('.date-filter').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },
        
        handleAddPoints: function(e) {
            e.preventDefault();
            
            var userId = $(this).data('user-id');
            PointsManagement.showPointsModal(userId, 'add');
        },
        
        handleDeductPoints: function(e) {
            e.preventDefault();
            
            var userId = $(this).data('user-id');
            PointsManagement.showPointsModal(userId, 'deduct');
        },
        
        showPointsModal: function(userId, action) {
            var title = action === 'add' ? '添加點數' : '扣除點數';
            var buttonText = action === 'add' ? '添加' : '扣除';
            
            var modalHtml = `
                <div id="points-modal" class="points-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>${title}</h3>
                            <span class="close">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="points-form">
                                <input type="hidden" name="user_id" value="${userId}">
                                <input type="hidden" name="action_type" value="${action}">
                                
                                <p><label for="points">點數數量：</label></p>
                                <p><input type="number" id="points" name="points" min="0.01" step="0.01" required></p>
                                
                                <p><label for="reason">原因說明：</label></p>
                                <p><textarea id="reason" name="reason" rows="3" placeholder="請輸入${action === 'add' ? '添加' : '扣除'}原因"></textarea></p>
                                
                                <p class="submit">
                                    <button type="submit" class="button-primary">${buttonText}</button>
                                    <button type="button" class="button cancel-btn">取消</button>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#points-modal').show();
            
            // 綁定事件
            $('#points-modal .close, #points-modal .cancel-btn').on('click', function() {
                $('#points-modal').remove();
            });
            
            $('#points-form').on('submit', function(e) {
                e.preventDefault();
                PointsManagement.submitPointsForm($(this));
            });
        },
        
        submitPointsForm: function($form) {
            var formData = $form.serialize();
            var actionType = $form.find('[name="action_type"]').val();
            var ajaxAction = actionType === 'add' ? 'wc_points_rewards_admin_add_points' : 'wc_points_rewards_admin_deduct_points';
            
            formData += '&action=' + ajaxAction + '&nonce=' + wcPointsRewardsAdmin.nonce;
            
            var $button = $form.find('[type="submit"]');
            $button.prop('disabled', true).text(wcPointsRewardsAdmin.processing);
            
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        PointsManagement.showNotice(response.data.message, 'success');
                        $('#points-modal').remove();
                        // 重新載入頁面
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        PointsManagement.showNotice(response.data, 'error');
                    }
                },
                error: function() {
                    PointsManagement.showNotice(wcPointsRewardsAdmin.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(actionType === 'add' ? '添加' : '扣除');
                }
            });
        },
        
        handleUserSearch: function() {
            var query = $(this).val();
            var $results = $('.user-search-results');
            
            if (query.length < 2) {
                $results.hide();
                return;
            }
            
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_search_users',
                    nonce: wcPointsRewardsAdmin.nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success) {
                        PointsManagement.displayUserSearchResults(response.data);
                    }
                }
            });
        },
        
        displayUserSearchResults: function(users) {
            var $results = $('.user-search-results');
            $results.empty();
            
            if (users.length === 0) {
                $results.html('<div class="no-results">找不到用戶</div>');
            } else {
                users.forEach(function(user) {
                    var $item = $('<div class="user-result-item">')
                        .data('user-id', user.ID)
                        .html(user.display_name + ' (' + user.user_email + ')');
                    $results.append($item);
                });
            }
            
            $results.show();
        },
        
        handleFilterChange: function() {
            var $form = $(this).closest('form');
            $form.submit();
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">')
                .html('<p>' + message + '</p>')
                .hide();
            
            $('.wrap h1').after($notice);
            $notice.fadeIn();
            
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        }
    };
    
    // 報表圖表
    var ReportsCharts = {
        
        init: function() {
            this.initCharts();
            this.bindEvents();
        },
        
        bindEvents: function() {
            // 報表篩選
            $(document).on('change', '.report-filters select', this.handleFilterChange);
            
            // 匯出功能
            $(document).on('click', '.export-btn', this.handleExport);
        },
        
        initCharts: function() {
            // 點數趨勢圖表
            if ($('#points-trend-chart').length > 0) {
                this.createPointsTrendChart();
            }
            
            // 會員等級分布圖表
            if ($('#tier-distribution-chart').length > 0) {
                this.createTierDistributionChart();
            }
        },
        
        createPointsTrendChart: function() {
            // 使用 Chart.js 或其他圖表庫
            var ctx = document.getElementById('points-trend-chart').getContext('2d');
            
            // 這裡需要從後端獲取數據
            var chartData = window.pointsTrendData || [];
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(item => item.date),
                    datasets: [{
                        label: '獲得點數',
                        data: chartData.map(item => item.earned),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }, {
                        label: '使用點數',
                        data: chartData.map(item => item.redeemed),
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        createTierDistributionChart: function() {
            var ctx = document.getElementById('tier-distribution-chart').getContext('2d');
            var distributionData = window.tierDistributionData || [];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: distributionData.map(item => item.tier_name),
                    datasets: [{
                        data: distributionData.map(item => item.user_count),
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        handleFilterChange: function() {
            // 重新載入報表數據
            var $form = $(this).closest('form');
            var formData = $form.serialize();
            
            window.location.href = window.location.pathname + '?' + formData;
        },
        
        handleExport: function(e) {
            e.preventDefault();
            
            var exportType = $(this).data('export');
            var params = new URLSearchParams(window.location.search);
            params.append('export', exportType);
            
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    };
    
    // 設定頁面
    var SettingsPage = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
        },
        
        bindEvents: function() {
            // 設定頁籤切換
            $(document).on('click', '.nav-tab', this.handleTabSwitch);
            
            // 表單驗證
            $(document).on('submit', '.settings-form', this.validateForm);
            
            // 重置設定
            $(document).on('click', '.reset-settings-btn', this.handleResetSettings);
        },
        
        initTabs: function() {
            // 設定初始頁籤
            var activeTab = $('.nav-tab-active').data('tab') || 'general';
            this.showTab(activeTab);
        },
        
        handleTabSwitch: function(e) {
            e.preventDefault();
            
            var tab = $(this).data('tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            SettingsPage.showTab(tab);
        },
        
        showTab: function(tab) {
            $('.tab-content').hide();
            $('.tab-content[data-tab="' + tab + '"]').show();
        },
        
        validateForm: function(e) {
            var isValid = true;
            var $form = $(this);
            
            // 驗證必填欄位
            $form.find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            // 驗證數字欄位
            $form.find('input[type="number"]').each(function() {
                var value = parseFloat($(this).val());
                var min = parseFloat($(this).attr('min'));
                var max = parseFloat($(this).attr('max'));
                
                if (isNaN(value) || (min !== undefined && value < min) || (max !== undefined && value > max)) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                SettingsPage.showNotice('請檢查表單中的錯誤項目', 'error');
            }
        },
        
        handleResetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm('確定要重置所有設定嗎？此操作無法復原。')) {
                return;
            }
            
            // 發送重置請求
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_reset_settings',
                    nonce: wcPointsRewardsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SettingsPage.showNotice('設定已重置', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        SettingsPage.showNotice(response.data, 'error');
                    }
                }
            });
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">')
                .html('<p>' + message + '</p>')
                .hide();
            
            $('.wrap h1').after($notice);
            $notice.fadeIn();
        }
    };
    
    // 添加管理後台樣式
    var adminStyles = `
        .tier-row.ui-sortable-helper {
            background: #f0f0f1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .tier-handle {
            cursor: move;
            padding: 5px;
            color: #666;
        }
        
        .tier-handle:hover {
            color: #000;
        }
        
        .points-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 4px;
        }
        
        .modal-header {
            padding: 15px 20px;
            background: #f1f1f1;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .error {
            border-color: #dc3232 !important;
            box-shadow: 0 0 2px rgba(220,50,50,0.8);
        }
        
        .user-search-results {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            width: 100%;
        }
        
        .user-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .user-result-item:hover {
            background: #f0f0f1;
        }
        
        .no-results {
            padding: 10px;
            color: #666;
            font-style: italic;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    `;
    
    $('<style>').text(adminStyles).appendTo('head');
    
    // 初始化各模組
    TierManagement.init();
    PointsManagement.init();
    ReportsCharts.init();
    SettingsPage.init();
    
    // 批量操作管理
    var BulkOperations = {
        
        init: function() {
            this.bindEvents();
            this.initBulkBar();
        },
        
        bindEvents: function() {
            // 全選/取消全選
            $(document).on('change', '.bulk-select-all', this.handleSelectAll);
            
            // 單項選擇
            $(document).on('change', '.bulk-select-checkbox', this.handleSingleSelect);
            
            // 應用批量操作
            $(document).on('click', '.apply-bulk-action', this.handleApplyBulkAction);
            
            // 清除選擇
            $(document).on('click', '.clear-selection', this.handleClearSelection);
        },
        
        initBulkBar: function() {
            // 如果不存在批量操作欄，創建一個
            if ($('.bulk-actions-bar').length === 0) {
                var bulkBar = $(`
                    <div class="bulk-actions-bar">
                        <div class="bulk-selection-info">
                            已選擇 <span class="selected-count">0</span> 項
                        </div>
                        <div class="bulk-actions-controls">
                            <select class="bulk-action-select">
                                <option value="">選擇操作</option>
                                <option value="delete">刪除</option>
                                <option value="export">匯出</option>
                                <option value="add_points">批量加點</option>
                                <option value="deduct_points">批量扣點</option>
                            </select>
                            <button class="apply-bulk-action">應用</button>
                            <button class="clear-selection">清除選擇</button>
                        </div>
                    </div>
                `);
                $('.wp-list-table').before(bulkBar);
            }
        },
        
        handleSelectAll: function() {
            var isChecked = $(this).prop('checked');
            $('.bulk-select-checkbox').prop('checked', isChecked);
            BulkOperations.updateBulkBar();
        },
        
        handleSingleSelect: function() {
            var totalItems = $('.bulk-select-checkbox').length;
            var selectedItems = $('.bulk-select-checkbox:checked').length;
            
            $('.bulk-select-all').prop('checked', selectedItems === totalItems);
            BulkOperations.updateBulkBar();
        },
        
        updateBulkBar: function() {
            var selectedCount = $('.bulk-select-checkbox:checked').length;
            
            if (selectedCount > 0) {
                $('.bulk-actions-bar').addClass('active');
                $('.selected-count').text(selectedCount);
            } else {
                $('.bulk-actions-bar').removeClass('active');
            }
        },
        
        handleApplyBulkAction: function() {
            var action = $('.bulk-action-select').val();
            var selectedIds = [];
            
            $('.bulk-select-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (!action) {
                TierManagement.showNotice('請選擇操作', 'error');
                return;
            }
            
            if (selectedIds.length === 0) {
                TierManagement.showNotice('請選擇要操作的項目', 'error');
                return;
            }
            
            BulkOperations.executeBulkAction(action, selectedIds);
        },
        
        executeBulkAction: function(action, ids) {
            var confirmMessage = '確定要對 ' + ids.length + ' 個項目執行此操作嗎？';
            
            if (action === 'delete') {
                confirmMessage = '確定要刪除這 ' + ids.length + ' 個項目嗎？此操作無法復原。';
            }
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            var $button = $('.apply-bulk-action');
            $button.prop('disabled', true).text('處理中...');
            
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_bulk_action',
                    bulk_action: action,
                    selected_ids: ids,
                    nonce: wcPointsRewardsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TierManagement.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        TierManagement.showNotice(response.data, 'error');
                    }
                },
                error: function() {
                    TierManagement.showNotice('操作失敗，請稍後再試', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('應用');
                }
            });
        },
        
        handleClearSelection: function() {
            $('.bulk-select-checkbox, .bulk-select-all').prop('checked', false);
            BulkOperations.updateBulkBar();
        }
    };
    
    // 增強的表格功能
    var EnhancedTable = {
        
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initSearch();
            this.initFilters();
        },
        
        bindEvents: function() {
            // 表格排序
            $(document).on('click', '.sortable', this.handleSort);
            
            // 即時搜尋
            $(document).on('input', '.table-search input', this.debounce(this.handleSearch, 300));
            
            // 篩選器變更
            $(document).on('change', '.table-filter', this.handleFilter);
            
            // 重置篩選
            $(document).on('click', '.reset-filters', this.handleResetFilters);
        },
        
        initSortable: function() {
            // 為可排序的表頭添加樣式
            $('.wp-list-table th').each(function() {
                if ($(this).data('sortable') !== false) {
                    $(this).addClass('sortable');
                }
            });
        },
        
        initSearch: function() {
            // 如果沒有搜尋框，添加一個
            if ($('.table-search').length === 0 && $('.wp-list-table').length > 0) {
                var searchBox = $(`
                    <div class="table-search">
                        <input type="text" placeholder="搜尋..." />
                        <span class="search-icon">🔍</span>
                    </div>
                `);
                $('.wp-list-table').before(searchBox);
            }
        },
        
        initFilters: function() {
            // 初始化篩選器
            $('.table-filter').each(function() {
                var $filter = $(this);
                var filterType = $filter.data('filter-type');
                
                if (filterType === 'date') {
                    $filter.datepicker({
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true
                    });
                }
            });
        },
        
        handleSort: function() {
            var $header = $(this);
            var column = $header.data('column');
            var currentSort = $header.hasClass('asc') ? 'asc' : ($header.hasClass('desc') ? 'desc' : '');
            var newSort = currentSort === 'asc' ? 'desc' : 'asc';
            
            // 清除其他排序狀態
            $('.sortable').removeClass('asc desc');
            
            // 設置新的排序狀態
            $header.addClass(newSort);
            
            // 執行排序
            EnhancedTable.sortTable(column, newSort);
        },
        
        sortTable: function(column, direction) {
            var $table = $('.wp-list-table tbody');
            var rows = $table.find('tr').get();
            
            rows.sort(function(a, b) {
                var aText = $(a).find('td').eq(EnhancedTable.getColumnIndex(column)).text();
                var bText = $(b).find('td').eq(EnhancedTable.getColumnIndex(column)).text();
                
                // 嘗試轉換為數字
                var aNum = parseFloat(aText);
                var bNum = parseFloat(bText);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // 字符串比較
                return direction === 'asc' 
                    ? aText.localeCompare(bText)
                    : bText.localeCompare(aText);
            });
            
            $.each(rows, function(index, row) {
                $table.append(row);
            });
        },
        
        getColumnIndex: function(column) {
            var index = 0;
            $('.wp-list-table th').each(function(i) {
                if ($(this).data('column') === column) {
                    index = i;
                    return false;
                }
            });
            return index;
        },
        
        handleSearch: function() {
            var query = $(this).val().toLowerCase();
            var $rows = $('.wp-list-table tbody tr');
            
            if (query === '') {
                $rows.show();
                return;
            }
            
            $rows.each(function() {
                var $row = $(this);
                var text = $row.text().toLowerCase();
                
                if (text.indexOf(query) >= 0) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
            
            EnhancedTable.updateNoResultsMessage();
        },
        
        handleFilter: function() {
            var filters = {};
            
            $('.table-filter').each(function() {
                var $filter = $(this);
                var filterKey = $filter.data('filter');
                var filterValue = $filter.val();
                
                if (filterValue) {
                    filters[filterKey] = filterValue;
                }
            });
            
            EnhancedTable.applyFilters(filters);
        },
        
        applyFilters: function(filters) {
            var $rows = $('.wp-list-table tbody tr');
            
            $rows.each(function() {
                var $row = $(this);
                var showRow = true;
                
                for (var filterKey in filters) {
                    var filterValue = filters[filterKey];
                    var cellValue = $row.find('[data-filter="' + filterKey + '"]').text();
                    
                    if (cellValue.indexOf(filterValue) === -1) {
                        showRow = false;
                        break;
                    }
                }
                
                if (showRow) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
            
            EnhancedTable.updateNoResultsMessage();
        },
        
        updateNoResultsMessage: function() {
            var visibleRows = $('.wp-list-table tbody tr:visible').length;
            
            $('.no-results-message').remove();
            
            if (visibleRows === 0) {
                var message = $('<tr class="no-results-message"><td colspan="100%" style="text-align: center; padding: 40px; color: #666; font-style: italic;">沒有找到符合條件的項目</td></tr>');
                $('.wp-list-table tbody').append(message);
            }
        },
        
        handleResetFilters: function() {
            $('.table-filter').val('');
            $('.table-search input').val('');
            $('.wp-list-table tbody tr').show();
            $('.no-results-message').remove();
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // 會員等級升級通知系統
    var TierNotifications = {
        
        init: function() {
            this.bindEvents();
            this.checkUpgradeNotifications();
        },
        
        bindEvents: function() {
            // 發送升級通知
            $(document).on('click', '.send-tier-notification', this.handleSendNotification);
            
            // 關閉通知
            $(document).on('click', '.notification-dismiss', this.handleDismissNotification);
            
            // 查看通知詳情
            $(document).on('click', '.view-notification-details', this.handleViewDetails);
        },
        
        checkUpgradeNotifications: function() {
            // 定期檢查是否有新的等級升級
            setInterval(function() {
                TierNotifications.fetchUpgradeNotifications();
            }, 30000); // 每30秒檢查一次
        },
        
        fetchUpgradeNotifications: function() {
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_check_tier_upgrades',
                    nonce: wcPointsRewardsAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.notifications.length > 0) {
                        TierNotifications.displayNotifications(response.data.notifications);
                    }
                }
            });
        },
        
        displayNotifications: function(notifications) {
            var $container = $('.tier-notifications');
            
            if ($container.length === 0) {
                $container = $('<div class="tier-notifications"></div>');
                $('.wrap').prepend($container);
            }
            
            notifications.forEach(function(notification) {
                if ($('.notification-card[data-id="' + notification.id + '"]').length === 0) {
                    TierNotifications.addNotificationCard($container, notification);
                }
            });
        },
        
        addNotificationCard: function($container, notification) {
            var card = $(`
                <div class="notification-card ${notification.type}" data-id="${notification.id}">
                    <div class="notification-content">
                        <div class="notification-icon">${notification.icon}</div>
                        <div class="notification-text">
                            <div class="notification-title">${notification.title}</div>
                            <div class="notification-message">${notification.message}</div>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <button class="notification-action primary send-tier-notification" data-user-id="${notification.user_id}" data-tier-id="${notification.tier_id}">
                            發送通知
                        </button>
                        <button class="notification-action view-notification-details" data-id="${notification.id}">
                            詳情
                        </button>
                    </div>
                    <button class="notification-dismiss" data-id="${notification.id}">&times;</button>
                </div>
            `);
            
            $container.append(card);
            
            // 添加動畫效果
            card.hide().slideDown(300);
        },
        
        handleSendNotification: function() {
            var $button = $(this);
            var userId = $button.data('user-id');
            var tierId = $button.data('tier-id');
            
            $button.prop('disabled', true).text('發送中...');
            
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_send_tier_notification',
                    user_id: userId,
                    tier_id: tierId,
                    nonce: wcPointsRewardsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TierManagement.showNotice('通知已發送', 'success');
                        $button.closest('.notification-card').slideUp(300, function() {
                            $(this).remove();
                        });
                    } else {
                        TierManagement.showNotice(response.data, 'error');
                        $button.prop('disabled', false).text('發送通知');
                    }
                },
                error: function() {
                    TierManagement.showNotice('發送失敗', 'error');
                    $button.prop('disabled', false).text('發送通知');
                }
            });
        },
        
        handleDismissNotification: function() {
            var notificationId = $(this).data('id');
            var $card = $(this).closest('.notification-card');
            
            $card.slideUp(300, function() {
                $(this).remove();
            });
            
            // 標記為已讀
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_dismiss_notification',
                    notification_id: notificationId,
                    nonce: wcPointsRewardsAdmin.nonce
                }
            });
        },
        
        handleViewDetails: function() {
            var notificationId = $(this).data('id');
            
            // 顯示通知詳情模態框
            TierNotifications.showNotificationDetailsModal(notificationId);
        },
        
        showNotificationDetailsModal: function(notificationId) {
            $.ajax({
                url: wcPointsRewardsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_get_notification_details',
                    notification_id: notificationId,
                    nonce: wcPointsRewardsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var modalHtml = `
                            <div id="notification-details-modal" class="points-modal">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3>通知詳情</h3>
                                        <span class="close">&times;</span>
                                    </div>
                                    <div class="modal-body">
                                        ${response.data.html}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        $('body').append(modalHtml);
                        $('#notification-details-modal').show();
                        
                        $('#notification-details-modal .close').on('click', function() {
                            $('#notification-details-modal').remove();
                        });
                    }
                }
            });
        }
    };
    
    // 性能監控
    var PerformanceMonitor = {
        
        init: function() {
            this.startTime = performance.now();
            this.bindEvents();
            this.monitorAjaxRequests();
        },
        
        bindEvents: function() {
            // 監控頁面載入時間
            $(window).on('load', this.handlePageLoad);
            
            // 監控表格載入時間
            $(document).on('ajaxComplete', this.handleAjaxComplete);
        },
        
        handlePageLoad: function() {
            var loadTime = performance.now() - PerformanceMonitor.startTime;
            console.log('頁面載入時間:', loadTime.toFixed(2) + 'ms');
            
            // 如果載入時間過長，顯示警告
            if (loadTime > 3000) {
                PerformanceMonitor.showPerformanceWarning();
            }
        },
        
        handleAjaxComplete: function(event, xhr, settings) {
            if (settings.url === wcPointsRewardsAdmin.ajaxUrl) {
                var duration = xhr.responseJSON && xhr.responseJSON.debug ? xhr.responseJSON.debug.duration : null;
                if (duration && duration > 2000) {
                    console.warn('AJAX 請求耗時過長:', duration + 'ms', settings.data);
                }
            }
        },
        
        monitorAjaxRequests: function() {
            var originalAjax = $.ajax;
            var requestCount = 0;
            var requestTimes = [];
            
            $.ajax = function(options) {
                var startTime = performance.now();
                requestCount++;
                
                var originalSuccess = options.success || function() {};
                options.success = function(data, textStatus, xhr) {
                    var duration = performance.now() - startTime;
                    requestTimes.push(duration);
                    
                    // 記錄到控制台
                    if (duration > 1000) {
                        console.warn('慢查詢檢測:', options.url, duration.toFixed(2) + 'ms');
                    }
                    
                    originalSuccess.apply(this, arguments);
                };
                
                return originalAjax.call(this, options);
            };
        },
        
        showPerformanceWarning: function() {
            var warning = $(`
                <div class="admin-notice notice-warning is-dismissible">
                    <p>
                        <strong>性能提醒：</strong> 
                        頁面載入時間較長，建議檢查伺服器性能或啟用快取功能。
                        <a href="#" class="performance-tips-link">查看優化建議</a>
                    </p>
                </div>
            `);
            
            $('.wrap h1').after(warning);
            
            warning.find('.performance-tips-link').on('click', function(e) {
                e.preventDefault();
                PerformanceMonitor.showOptimizationTips();
            });
        },
        
        showOptimizationTips: function() {
            var modalHtml = `
                <div id="performance-tips-modal" class="points-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>性能優化建議</h3>
                            <span class="close">&times;</span>
                        </div>
                        <div class="modal-body">
                            <h4>建議的優化措施：</h4>
                            <ul>
                                <li>啟用 WordPress 快取外掛</li>
                                <li>使用 CDN 加速靜態資源</li>
                                <li>定期清理資料庫</li>
                                <li>優化圖片大小</li>
                                <li>減少不必要的外掛</li>
                                <li>使用高性能的主機服務</li>
                            </ul>
                            <p><strong>注意：</strong>這些建議可以顯著提升系統性能。</p>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#performance-tips-modal').show();
            
            $('#performance-tips-modal .close').on('click', function() {
                $('#performance-tips-modal').remove();
            });
        },
        
        getPerformanceReport: function() {
            return {
                pageLoadTime: performance.now() - this.startTime,
                ajaxRequestCount: $('.ajax-request').length,
                memoryUsage: performance.memory ? performance.memory.usedJSHeapSize : 'N/A'
            };
        }
    };
    
    // 初始化新模組
    BulkOperations.init();
    EnhancedTable.init(); 
    TierNotifications.init();
    PerformanceMonitor.init();
    
    // 全域管理後台 API
    window.WCPointsRewardsAdmin = {
        TierManagement: TierManagement,
        PointsManagement: PointsManagement,
        ReportsCharts: ReportsCharts,
        SettingsPage: SettingsPage
    };
});

/* ==========================================================================
   Extracted JavaScript from PHP Files
   ========================================================================== */

// Settings page - Historical orders processing
jQuery(document).ready(function($) {
    $('#process_historical_orders').on('click', function() {
        var startDate = $('#historical_start_date').val();
        var endDate = $('#historical_end_date').val();
        
        if (!startDate || !endDate) {
            alert(wcPointsRewardsAdmin.messages.select_dates || '請選擇開始和結束日期');
            return;
        }
        
        if (startDate > endDate) {
            alert(wcPointsRewardsAdmin.messages.invalid_date_range || '開始日期不能晚於結束日期');
            return;
        }
        
        if (!confirm(wcPointsRewardsAdmin.messages.confirm_historical_process || '確定要處理此日期範圍內的歷史訂單嗎？此操作無法撤銷。')) {
            return;
        }
        
        var $button = $(this);
        var $progress = $('#process_progress');
        var $results = $('#process_results');
        
        $button.prop('disabled', true).text(wcPointsRewardsAdmin.messages.processing || '處理中...');
        $progress.show();
        $results.empty();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_process_historical_orders',
                start_date: startDate,
                end_date: endDate,
                nonce: wcPointsRewardsAdmin.nonces ? wcPointsRewardsAdmin.nonces.historical_orders : ''
            },
            success: function(response) {
                if (response.success) {
                    $('.progress-fill').css('width', '100%');
                    $('.progress-text').text(wcPointsRewardsAdmin.messages.processing_complete || '處理完成');
                    
                    $results.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $results.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $results.html('<div class="notice notice-error"><p>' + (wcPointsRewardsAdmin.messages.processing_failed || '處理失敗，請稍後再試') + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text(wcPointsRewardsAdmin.messages.start_historical_process || '開始處理歷史訂單');
                setTimeout(function() {
                    $progress.hide();
                }, 2000);
            }
        });
    });
});

// Historical orders page - Processing functionality
jQuery(document).ready(function($) {
    $('#historical-orders-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#process-orders-btn');
        var $spinner = $('#processing-spinner');
        var $results = $('#processing-results');
        var $resultsContent = $('#results-content');
        
        // Validation
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (!startDate || !endDate) {
            alert(wcPointsRewardsAdmin.messages.select_dates || '請選擇開始和結束日期');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert(wcPointsRewardsAdmin.messages.invalid_date_range || '開始日期不能晚於結束日期');
            return;
        }
        
        // Confirmation
        var isDryRun = $('#dry_run').is(':checked');
        var confirmMessage = isDryRun 
            ? (wcPointsRewardsAdmin.messages.confirm_dry_run || '確定要執行測試模式嗎？')
            : (wcPointsRewardsAdmin.messages.confirm_historical_process || '確定要開始處理歷史訂單嗎？此操作將會實際發放點數。');
            
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Start processing
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.show();
        $resultsContent.html('<div class="processing-status">正在處理歷史訂單...</div>');
        
        var formData = $form.serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=wc_points_rewards_process_historical_orders',
            success: function(response) {
                if (response.success) {
                    $resultsContent.html('<div class="processing-status success">' + response.data.message + '</div>');
                } else {
                    $resultsContent.html('<div class="processing-status error">' + (response.data.message || response.data) + '</div>');
                }
            },
            error: function() {
                $resultsContent.html('<div class="processing-status error">' + (wcPointsRewardsAdmin.messages.processing_failed || '處理失敗，請稍後再試') + '</div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});

// Tier edit page - Form handling and preview
jQuery(document).ready(function($) {
    // Tier form preview update
    function updateTierPreview() {
        var name = $('#tier_name').val() || 'New Tier';
        var minAmount = $('#minimum_amount').val() || '0';
        var pointsBonus = $('#points_bonus').val() || '0';
        
        $('.preview-tier-name').text(name);
        $('.preview-tier-amount').text('Minimum spend: $' + minAmount);
        $('.preview-tier-bonus').text(pointsBonus + '% bonus points');
    }
    
    // Update preview on input change
    $('#tier_name, #minimum_amount, #points_bonus').on('input', updateTierPreview);
    
    // Initialize preview
    updateTierPreview();
});

// Tiers list page - Enhanced functionality
jQuery(document).ready(function($) {
    // Tier deletion confirmation
    $('.button-delete').on('click', function(e) {
        if (!confirm(wcPointsRewardsAdmin.messages.confirm_tier_delete || '確定要刪除這個會員等級嗎？此操作無法撤銷。')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Enhanced sortable functionality for tier ordering
    if ($('.tiers-sortable').length > 0) {
        $('.tiers-sortable').sortable({
            handle: '.tier-handle',
            axis: 'y',
            placeholder: 'tier-sort-placeholder',
            update: function(event, ui) {
                updateTierOrder();
            }
        });
    }
    
    function updateTierOrder() {
        var order = [];
        $('.tiers-sortable tr').each(function(index) {
            var tierId = $(this).data('tier-id');
            if (tierId) {
                order.push({
                    id: tierId,
                    order: index + 1
                });
            }
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_update_tier_order',
                order: order,
                nonce: wcPointsRewardsAdmin.nonces ? wcPointsRewardsAdmin.nonces.tier_order : ''
            },
            success: function(response) {
                if (response.success) {
                    // Update order numbers in UI
                    $('.tier-order-number').each(function(index) {
                        $(this).text(index + 1);
                    });
                }
            }
        });
    }
});