<?php
/**
 * Plugin Name: WP Bear Style Like Button
 * Description: 在文章页底部增加一个居中的点赞按钮和「支持」链接，提供 AJAX 点赞功能和后台「支持」链接设置,点赞改为心形。
 * Version: 2.8
 * Author: baduyifei.com / anotherdayu.com
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 注册前端样式和脚本
function bslb_enqueue_scripts() {
    if (is_single() && 'post' === get_post_type()) {
        wp_enqueue_style('bslb-style', plugin_dir_url(__FILE__).'css/reaction.css');
        wp_enqueue_script('bslb-script', plugin_dir_url(__FILE__).'js/reaction.js', array('jquery'), null, true);
        // 向js传递文章ID和ajax URL
        wp_localize_script('bslb-script', 'bslb_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id'  => get_the_ID(),
            'nonce'    => wp_create_nonce('bslb_like_nonce'),
            'is_single' => true,
            'version'  => '1.6',
        ));
    } else {
        // 在非文章页面也加载脚本，以支持动态加载的情况
        wp_enqueue_script('bslb-script', plugin_dir_url(__FILE__).'js/reaction.js', array('jquery'), null, true);
        wp_localize_script('bslb-script', 'bslb_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id'  => 0,
            'nonce'    => wp_create_nonce('bslb_like_nonce'),
            'is_single' => false,
            'version'  => '1.6',
        ));
    }
}
add_action('wp_enqueue_scripts', 'bslb_enqueue_scripts');

// 为文章内容追加点赞和支持区域
function bslb_append_reaction($content) {
    if (is_single() && 'post' === get_post_type()) {
        // 获取当前文章的ID
        $post_id = get_the_ID();
        
        // 完全禁用缓存，每次页面加载都重新生成HTML
        
        // 获取当前文章的点赞数，默认为 0
        $likes = get_post_meta($post_id, 'bear_style_like_count', true);
        $likes = $likes ? intval($likes) : 0;
        
        // 获取「支持」链接配置，默认地址为 example.com
        $support_url = esc_url(get_option('bslb_support_url', 'https://example.com/support'));
        
        // 检查当前用户是否已点赞
        $ip = bslb_get_user_ip();
        $ips = get_post_meta($post_id, 'bear_style_like_ips', true);
        $already_liked = (!empty($ips) && is_array($ips) && in_array($ip, $ips));
        
        // 生成点赞按钮HTML
        ob_start();
        ?>
        <div class="bslb-reaction">
            <button class="bslb-like-button <?php echo $already_liked ? 'has-liked' : ''; ?>" 
                    data-postid="<?php echo $post_id; ?>" 
                    aria-label="<?php echo __('点赞这篇文章', 'wp-bear-style-like-button'); ?>">
                <span class="bslb-like-icon">
                    <svg class="bslb-like-svg <?php echo $already_liked ? 'has-liked' : ''; ?>" viewBox="0 0 24 24" width="16" height="16" 
             fill="none" stroke="currentColor" 
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
                    <span class="bslb-like-count <?php echo $already_liked ? 'has-liked' : ''; ?>"><?php echo $likes; ?></span>
                </span>
            </button>
            <a href="<?php echo $support_url; ?>" class="bslb-support-link"><?php echo __('支持', 'wp-bear-style-like-button'); ?></a>
        </div>
        <?php
        $reaction_html = ob_get_clean();
        
        $content .= $reaction_html;
    }
    return $content;
}
add_filter('the_content', 'bslb_append_reaction');

// 处理 AJAX 点赞请求
function bslb_handle_like() {
    // 为调试添加错误记录功能
    $debug_log = array();
    $debug_log[] = '点赞请求开始处理 - ' . date('Y-m-d H:i:s');
    
    // 安全校验，可加入非空验证或 nonce 检查
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bslb_like_nonce')) {
        $debug_log[] = '安全验证失败';
        // 记录调试信息
        update_option('bslb_debug_log', $debug_log);
        wp_send_json_error('安全验证失败');
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        $debug_log[] = '无效的文章ID';
        update_option('bslb_debug_log', $debug_log);
        wp_send_json_error('无效的文章ID');
    }
    
    $debug_log[] = '处理文章ID: ' . $post_id;
    
    // 记录用户IP，不限制点赞，但记录状态
    $ip = bslb_get_user_ip();
    $debug_log[] = '用户IP: ' . $ip;
    
    // 获取点赞过的IP列表
    $ips = get_post_meta($post_id, 'bear_style_like_ips', true);
    if (!empty($ips) && is_array($ips)) {
        $ips_array = $ips;
    } else {
        $ips_array = array();
    }
    
    // 检查用户是否已点赞，如果没有则添加到列表中
    $user_has_liked = in_array($ip, $ips_array);
    if (!$user_has_liked) {
        $ips_array[] = $ip;
        update_post_meta($post_id, 'bear_style_like_ips', $ips_array);
        $debug_log[] = '用户IP添加到点赞列表';
    } else {
        $debug_log[] = '用户已在点赞列表中';
    }
    
    // 获取当前点赞数并增加
    $likes = get_post_meta($post_id, 'bear_style_like_count', true);
    $debug_log[] = '当前点赞数: ' . ($likes ? $likes : 0);
    $likes = $likes ? intval($likes) : 0;
    
    // 点赞数加 1
    $likes++;
    $debug_log[] = '新点赞数: ' . $likes;
    
    $update_result = update_post_meta($post_id, 'bear_style_like_count', $likes);
    $debug_log[] = '更新点赞数结果: ' . ($update_result ? '成功' : '失败');
    
    // 清除所有相关缓存
    bslb_clear_caches($post_id);
    $debug_log[] = '已清除缓存';
    
    // 记录调试信息
    update_option('bslb_debug_log', $debug_log);
    
    // 返回成功信息
    wp_send_json_success(array(
        'likes' => $likes,
        'already_liked' => true
    ));
}
add_action('wp_ajax_bslb_like', 'bslb_handle_like');
add_action('wp_ajax_nopriv_bslb_like', 'bslb_handle_like');

// 添加后台设置菜单
function bslb_add_admin_menu() {
    add_options_page('Bear Style Like Button 设置', 'Like Button 设置', 'manage_options', 'bslb_settings', 'bslb_options_page');
}
add_action('admin_menu', 'bslb_add_admin_menu');

function bslb_settings_init() {
    register_setting('bslb_settings_group', 'bslb_support_url');

    add_settings_section(
        'bslb_settings_section',
        '插件设置',
        'bslb_settings_section_callback',
        'bslb_settings'
    );

    add_settings_field(
        'bslb_support_url_field',
        '支持链接地址',
        'bslb_support_url_render',
        'bslb_settings',
        'bslb_settings_section'
    );
}
add_action('admin_init', 'bslb_settings_init');

function bslb_support_url_render() {
    $support_url = get_option('bslb_support_url', 'https://example.com/support');
    echo '<input type="text" name="bslb_support_url" value="' . esc_attr($support_url) . '" size="50">';
}

function bslb_settings_section_callback() {
    echo '设置点击「支持」时跳转的页面地址：';
}

function bslb_options_page() {
    ?>
    <div class="wrap">
        <h1>Bear Style Like Button 设置</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('bslb_settings_group');
            do_settings_sections('bslb_settings');
            submit_button();
            ?>
        </form>
        <hr />
        <h2>导出点赞数据</h2>
        <p><a href="<?php echo esc_url(admin_url('options-general.php?page=bslb_settings&bslb_export=1')); ?>" class="button button-secondary">导出点赞数据 (CSV)</a></p>
    </div>
    <?php
}

// 在插件页面添加"设置"链接
function bslb_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=bslb_settings') . '">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bslb_plugin_action_links');

// 添加导出点赞数据功能
function bslb_export_likes_data() {
    if (! current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['bslb_export']) && $_GET['bslb_export'] === '1') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=like_counts.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Post ID', 'Post Title', 'Like Count'));
        $batch_size = 100; // 每批处理100篇文章
        $offset = 0;
        
        while (true) {
            $posts = get_posts(array(
                'post_type' => 'post',
                'numberposts' => $batch_size,
                'offset' => $offset,
                'meta_key' => 'bear_style_like_count'
            ));
            
            if (empty($posts)) {
                break;
            }
            
            // 处理这批文章数据
            foreach ($posts as $post) {
                $like = get_post_meta($post->ID, 'bear_style_like_count', true);
                if (empty($like)) {
                    $like = '0';
                }
                fputcsv($output, array($post->ID, $post->post_title, $like));
            }
            $offset += $batch_size;
        }
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bslb_export_likes_data');

function bslb_get_user_ip() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// 在插件顶部添加
function bslb_load_textdomain() {
    load_plugin_textdomain('wp-bear-style-like-button', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'bslb_load_textdomain');

function bslb_check_resources() {
    $css_file = plugin_dir_path(__FILE__) . 'css/reaction.css';
    $js_file = plugin_dir_path(__FILE__) . 'js/reaction.js';
    
    if (!file_exists($css_file) || !file_exists($js_file)) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('WP Bear Style Like Button 插件缺少必要的资源文件！', 'wp-bear-style-like-button') . '</p></div>';
        });
    }
}
add_action('admin_init', 'bslb_check_resources');

register_activation_hook(__FILE__, 'bslb_activate');
register_deactivation_hook(__FILE__, 'bslb_deactivate');

function bslb_activate() {
    // 激活时的操作，例如创建自定义数据表
}

function bslb_deactivate() {
    // 停用时的操作，例如清理临时数据
}

// 添加一个函数用于清除所有点赞相关的缓存
function bslb_clear_caches($post_id) {
    // 清除特定的缓存键
    $cache_key = 'bslb_reaction_' . $post_id;
    wp_cache_delete($cache_key);
    
    // 如果使用了WP Super Cache
    if (function_exists('wp_cache_post_change')) {
        wp_cache_post_change($post_id);
    }
    
    // 如果使用了W3 Total Cache
    if (function_exists('w3tc_pgcache_flush_post')) {
        w3tc_pgcache_flush_post($post_id);
    }
}

// 注册一个AJAX操作用于刷新点赞状态
function bslb_check_like_status() {
    // 获取文章ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    // 如果没有提供有效的文章ID，返回错误
    if (!$post_id) {
        wp_send_json_error(array('message' => '无效的文章ID'));
        return;
    }
    
    // 获取用户IP
    $ip = bslb_get_user_ip();
    
    // 检查用户是否已点赞
    $ips = get_post_meta($post_id, 'bear_style_like_ips', true);
    $already_liked = (!empty($ips) && is_array($ips) && in_array($ip, $ips));
    
    // 获取点赞数量
    $likes = get_post_meta($post_id, 'bear_style_like_count', true);
    $likes = $likes ? intval($likes) : 0;
    
    wp_send_json_success(array(
        'likes' => $likes,
        'already_liked' => $already_liked,
        'post_id' => $post_id,
    ));
}
add_action('wp_ajax_bslb_check_like_status', 'bslb_check_like_status');
add_action('wp_ajax_nopriv_bslb_check_like_status', 'bslb_check_like_status');

?>
