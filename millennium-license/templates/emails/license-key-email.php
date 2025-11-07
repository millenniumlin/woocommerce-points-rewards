<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php _e('您的授權碼', 'millennium-license'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
            <?php _e('您的授權碼', 'millennium-license'); ?>
        </h2>
        
        <?php if (isset($license->order)) : ?>
            <p><?php printf(__('感謝您的購買！以下是訂單 #%s 的授權碼：', 'millennium-license'), $license->order->get_order_number()); ?></p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border: 1px solid #ddd;"><?php _e('產品', 'millennium-license'); ?></th>
                        <th style="padding: 12px; text-align: left; border: 1px solid #ddd;"><?php _e('授權碼', 'millennium-license'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($license->license_keys as $key_data) : ?>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #ddd;"><?php echo esc_html($key_data['product_name']); ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;">
                                <code style="background-color: #f8f9fa; padding: 5px 10px; border-radius: 3px; font-size: 14px;">
                                    <?php echo esc_html($key_data['license_key']); ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('您的授權碼已成功啟用。', 'millennium-license'); ?></p>
            
            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <strong><?php _e('授權碼：', 'millennium-license'); ?></strong>
                <code style="background-color: #fff; padding: 5px 10px; border-radius: 3px; font-size: 16px; display: inline-block; margin-left: 10px;">
                    <?php echo esc_html($license->license_key); ?>
                </code>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background-color: #e8f4f8; border-left: 4px solid #3498db; border-radius: 3px;">
            <h3 style="margin-top: 0; color: #2c3e50;"><?php _e('如何使用授權碼', 'millennium-license'); ?></h3>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li><?php _e('將授權碼複製到您的應用程式中', 'millennium-license'); ?></li>
                <li><?php _e('按照應用程式的啟用指示操作', 'millennium-license'); ?></li>
                <li><?php _e('完成啟用後即可開始使用', 'millennium-license'); ?></li>
            </ol>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <p><?php printf(__('此郵件由 %s 自動發送', 'millennium-license'), get_bloginfo('name')); ?></p>
            <p><?php _e('如有任何問題，請聯繫我們的客服團隊。', 'millennium-license'); ?></p>
        </div>
    </div>
</body>
</html>
