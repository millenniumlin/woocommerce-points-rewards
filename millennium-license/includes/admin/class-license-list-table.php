<?php
/**
 * License List Table
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Millennium_License_List_Table extends WP_List_Table {
    
    /**
     * 建構函式
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'license',
            'plural' => 'licenses',
            'ajax' => false
        ));
    }
    
    /**
     * 獲取欄位
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'license_key' => __('授權碼', 'millennium-license'),
            'product' => __('產品', 'millennium-license'),
            'user' => __('用戶', 'millennium-license'),
            'status' => __('狀態', 'millennium-license'),
            'activations' => __('啟用次數', 'millennium-license'),
            'expires_at' => __('到期時間', 'millennium-license'),
            'created_at' => __('創建時間', 'millennium-license'),
        );
    }
    
    /**
     * 可排序的欄位
     */
    public function get_sortable_columns() {
        return array(
            'license_key' => array('license_key', false),
            'status' => array('status', false),
            'expires_at' => array('expires_at', false),
            'created_at' => array('created_at', true),
        );
    }
    
    /**
     * 準備項目
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $manager = Millennium_License_Manager_Core::instance();
        
        $args = array(
            'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at',
            'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC',
            'limit' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
        );
        
        if (isset($_GET['status']) && $_GET['status']) {
            $args['status'] = sanitize_text_field($_GET['status']);
        }
        
        $this->items = $manager->get_licenses($args);
        
        $total_items = $manager->count_licenses($args);
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }
    
    /**
     * 預設欄位顯示
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'license_key':
                return '<code>' . esc_html($item->license_key) . '</code>';
            
            case 'product':
                if ($item->product_id) {
                    $product = wc_get_product($item->product_id);
                    if ($product) {
                        return '<a href="' . get_edit_post_link($item->product_id) . '">' . esc_html($product->get_name()) . '</a>';
                    }
                }
                return '—';
            
            case 'user':
                if ($item->user_id) {
                    $user = get_userdata($item->user_id);
                    if ($user) {
                        return '<a href="' . get_edit_user_link($item->user_id) . '">' . esc_html($user->display_name) . '</a>';
                    }
                }
                return '—';
            
            case 'status':
                $statuses = array(
                    'active' => '<span class="status-active">' . __('啟用', 'millennium-license') . '</span>',
                    'inactive' => '<span class="status-inactive">' . __('停用', 'millennium-license') . '</span>',
                    'expired' => '<span class="status-expired">' . __('已過期', 'millennium-license') . '</span>',
                );
                return isset($statuses[$item->status]) ? $statuses[$item->status] : esc_html($item->status);
            
            case 'activations':
                return sprintf('%d / %d', $item->activation_count, $item->max_activations);
            
            case 'expires_at':
                if ($item->expires_at) {
                    $time_diff = human_time_diff(strtotime($item->expires_at), current_time('timestamp'));
                    if (strtotime($item->expires_at) > current_time('timestamp')) {
                        return sprintf(__('%s 後到期', 'millennium-license'), $time_diff);
                    } else {
                        return sprintf(__('%s 前已過期', 'millennium-license'), $time_diff);
                    }
                }
                return __('永久', 'millennium-license');
            
            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
            
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }
    
    /**
     * 核取方塊欄位
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="licenses[]" value="%d" />', $item->id);
    }
    
    /**
     * 授權碼欄位（包含操作連結）
     */
    public function column_license_key($item) {
        $actions = array();
        
        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            add_query_arg(array(
                'page' => 'millennium-license',
                'action' => 'view',
                'license_id' => $item->id
            ), admin_url('admin.php')),
            __('查看', 'millennium-license')
        );
        
        if ($item->status === 'active') {
            $actions['deactivate'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(add_query_arg(array(
                    'page' => 'millennium-license',
                    'action' => 'deactivate',
                    'license_id' => $item->id
                ), admin_url('admin.php')), 'update-license-' . $item->id),
                __('停用', 'millennium-license')
            );
        } else {
            $actions['activate'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(add_query_arg(array(
                    'page' => 'millennium-license',
                    'action' => 'activate',
                    'license_id' => $item->id
                ), admin_url('admin.php')), 'update-license-' . $item->id),
                __('啟用', 'millennium-license')
            );
        }
        
        $actions['delete'] = sprintf(
            '<a href="%s" class="delete">%s</a>',
            wp_nonce_url(add_query_arg(array(
                'page' => 'millennium-license',
                'action' => 'delete',
                'license_id' => $item->id
            ), admin_url('admin.php')), 'delete-license-' . $item->id),
            __('刪除', 'millennium-license')
        );
        
        return sprintf(
            '<code>%s</code> %s',
            esc_html($item->license_key),
            $this->row_actions($actions)
        );
    }
    
    /**
     * 批次操作
     */
    public function get_bulk_actions() {
        return array(
            'activate' => __('啟用', 'millennium-license'),
            'deactivate' => __('停用', 'millennium-license'),
            'delete' => __('刪除', 'millennium-license'),
        );
    }
    
    /**
     * 沒有項目時顯示
     */
    public function no_items() {
        _e('找不到授權碼。', 'millennium-license');
    }
}
