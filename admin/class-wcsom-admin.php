<?php
if (!defined('ABSPATH')) exit;

class WCSOM_Admin {

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX Actions
        add_action('wp_ajax_wcsom_get_supplier_products', array($this, 'ajax_get_supplier_products'));
        add_action('wp_ajax_wcsom_search_wc_products', array($this, 'ajax_search_wc_products'));
        add_action('wp_ajax_wcsom_assign_product', array($this, 'ajax_assign_product'));
        add_action('wp_ajax_wcsom_unassign_product', array($this, 'ajax_unassign_product'));
        add_action('wp_ajax_wcsom_get_supplier_packing_lists', array($this, 'ajax_get_supplier_packing_lists'));
        
        add_action('wp_ajax_wcsom_create_po', array($this, 'ajax_create_po'));
        add_action('wp_ajax_wcsom_save_po_edit', array($this, 'ajax_save_po_edit'));
        add_action('wp_ajax_wcsom_delete_po', array($this, 'ajax_delete_po'));
        
        // Global Search & Quick Edit & Duplication
        add_action('wp_ajax_wcsom_global_product_search', array($this, 'ajax_global_product_search'));
        add_action('wp_ajax_wcsom_search_all_wc_products', array($this, 'ajax_search_all_wc_products'));
        add_action('wp_ajax_wcsom_save_product_quick_edit', array($this, 'ajax_save_product_quick_edit'));
        add_action('wp_ajax_wcsom_get_product_details', array($this, 'ajax_get_product_details'));
        add_action('wp_ajax_wcsom_bulk_edit_products', array($this, 'ajax_bulk_edit_products'));
        
        // Quick Create
        add_action('wp_ajax_wcsom_quick_create_product', array($this, 'ajax_quick_create_product'));
        
        // AI Import Actions (Restored to Single-Pass for High Accuracy)
        add_action('wp_ajax_wcsom_test_api', array($this, 'ajax_wcsom_test_api'));
        add_action('wp_ajax_wcsom_ai_parse_quote', array($this, 'ajax_ai_parse_quote'));
        add_action('wp_ajax_wcsom_ai_bulk_save', array($this, 'ajax_ai_bulk_save'));

        // Bulk Actions
        add_action('wp_ajax_wcsom_create_bulk_order', array($this, 'ajax_create_bulk_order'));
        add_action('wp_ajax_wcsom_delete_bulk_order', array($this, 'ajax_delete_bulk_order'));
        add_action('wp_ajax_wcsom_bulk_add_tags', array($this, 'ajax_bulk_add_tags'));
        add_action('wp_ajax_wcsom_bulk_delete_pos', array($this, 'ajax_bulk_delete_pos'));

        // Staff & Visibility Management
        add_action('wp_ajax_wcsom_add_staff', array($this, 'ajax_add_staff'));
        add_action('wp_ajax_wcsom_remove_staff', array($this, 'ajax_remove_staff'));
        add_action('wp_ajax_wcsom_update_staff_access', array($this, 'ajax_update_staff_access'));
        add_action('wp_ajax_wcsom_toggle_supplier_visibility', array($this, 'ajax_toggle_supplier_visibility'));

        // Create Supplier
        add_action('wp_ajax_wcsom_create_supplier', array($this, 'ajax_create_supplier'));
    }

    public function add_admin_menus() {
        add_menu_page(
            'Supplier Orders',
            'Supplier Orders',
            'manage_options',
            'wcsom-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-clipboard',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wcsom-dashboard') return;

        wp_enqueue_media(); 
        wp_enqueue_style('select2');
        wp_enqueue_script('selectWoo');

        wp_enqueue_style('wcsom-admin-css', WCSOM_PLUGIN_URL . 'assets/admin.css', array(), WCSOM_VERSION);
        wp_enqueue_script('wcsom-admin-js', WCSOM_PLUGIN_URL . 'assets/admin.js', array('jquery', 'selectWoo'), WCSOM_VERSION, true);

        // Pre-fetch categories for the AI import category dropdowns
        $cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        $cat_options = '<option value="">-- Cat --</option>';
        foreach($cats as $c) {
            $cat_options .= '<option value="' . esc_attr($c->term_id) . '">' . esc_html($c->name) . '</option>';
        }

        wp_localize_script('wcsom-admin-js', 'wcsom_ajax', array(
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('wcsom_admin_nonce'),
            'url_suppliers'  => wcsom_get_tab_url('suppliers'),
            'url_orders'     => wcsom_get_tab_url('orders'),
            'url_bulk'       => wcsom_get_tab_url('bulk-orders'),
            'url_ai_import'  => wcsom_get_tab_url('search'), // Redirect to search after bulk import
            'cat_options'    => $cat_options
        ));
    }

    public function render_dashboard() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'suppliers';
        include WCSOM_PLUGIN_DIR . 'admin/views/main.php';
    }

    // --- Helpers ---
    private function verify_access() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!wcsom_is_staff()) wp_send_json_error('Unauthorized Access');
    }

    private function verify_edit_access() {
        $this->verify_access();
        if (!wcsom_can_edit()) wp_send_json_error('Action restricted. You have View-Only permissions.');
    }

    // --- Lightweight Parsers for Excel & PDF ---
    private function parse_xlsx_to_text($file_path) {
        if (!class_exists('ZipArchive')) return '';
        $zip = new ZipArchive();
        if ($zip->open($file_path) === true) {
            $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
            $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            $shared_strings = array();
            if ($strings_xml) {
                $xml = @simplexml_load_string($strings_xml);
                if ($xml) {
                    foreach ($xml->si as $si) {
                        $shared_strings[] = (string)$si->t;
                    }
                }
            }

            $text = '';
            if ($sheet_xml) {
                $xml = @simplexml_load_string($sheet_xml);
                if ($xml) {
                    foreach ($xml->sheetData->row as $row) {
                        $row_data = array();
                        foreach ($row->c as $c) {
                            $val = (string)$c->v;
                            if (isset($c['t']) && (string)$c['t'] == 's') {
                                $val = isset($shared_strings[$val]) ? $shared_strings[$val] : '';
                            }
                            $row_data[] = $val;
                        }
                        $text .= implode(" | ", $row_data) . "\n";
                    }
                }
            }
            return $text;
        }
        return '';
    }

    private function parse_pdf_to_text($file_path) {
        $content = file_get_contents($file_path);
        $text = '';
        if (preg_match_all('/stream(.*?)endstream/is', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $stream = ltrim($stream, "\r\n");
                $uncompressed = @gzuncompress($stream);
                if ($uncompressed) {
                    $uncompressed = preg_replace('/[^a-zA-Z0-9\s\.\,\-\_\/\(\)\[\]]/', '', $uncompressed); 
                    if (preg_match_all('/\((.*?)\)/is', $uncompressed, $text_matches)) {
                        $text .= implode(" ", $text_matches[1]) . "\n";
                    }
                }
            }
        }
        // Fallback for tricky PDFs
        if (strlen(trim($text)) < 50) {
            $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F-\xFF]/', ' ', $content);
            $text = preg_replace('/\s+/', ' ', $text);
        }
        return $text;
    }

    // --- AI Import AJAX Handlers ---
    public function ajax_wcsom_test_api() {
        $this->verify_edit_access();
        $api_key = sanitize_text_field($_POST['api_key']);
        $model = sanitize_text_field($_POST['model']) ?: 'qwen3.6-plus';

        if (empty($api_key)) wp_send_json_error('API Key is required.');

        $endpoint = 'https://ws-efvhqx0cwbjtzdwb.cn-beijing.maas.aliyuncs.com/compatible-mode/v1/chat/completions';
        $body = array(
            'model' => $model,
            'messages' => array(array('role' => 'user', 'content' => 'Return the exact word: OK'))
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => json_encode($body),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }

        $res_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($res_data['code']) && !isset($res_data['choices'])) {
            $msg = isset($res_data['message']) ? $res_data['message'] : 'Unknown';
            wp_send_json_error("API Error [{$res_data['code']}]: {$msg}");
        }
        if (isset($res_data['error'])) {
            wp_send_json_error('API Error: ' . (isset($res_data['error']['message']) ? $res_data['error']['message'] : 'Invalid Key'));
        }

        wp_send_json_success('Connection Successful! API Key and Model are ready.');
    }

    public function ajax_ai_parse_quote() {
        // Prevent PHP timeouts for full document processing
        @set_time_limit(300); // Allow up to 5 minutes
        @ini_set('memory_limit', '512M'); 

        $this->verify_edit_access();

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $model = isset($_POST['ai_model']) ? sanitize_text_field($_POST['ai_model']) : 'qwen-vl-max';
        if (empty($api_key)) wp_send_json_error('API Key is required.');

        // Save key and model for future use
        update_option('wcsom_dashscope_api_key', $api_key);
        update_option('wcsom_dashscope_model', $model);

        if (!isset($_FILES['quote_file']) || $_FILES['quote_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload failed.');
        }

        $file_tmp = $_FILES['quote_file']['tmp_name'];
        $file_name = $_FILES['quote_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'pdf', 'jpg', 'jpeg', 'png'])) {
            wp_send_json_error('Only Excel (.xlsx), PDF, CSV, TXT, and Image (.jpg, .png) files are supported.');
        }

        $is_image = in_array($ext, ['jpg', 'jpeg', 'png']);
        
        // Prevent Text-Only models from receiving images
        $text_only_models = ['qwen-max', 'qwen-plus'];
        if ($is_image && in_array($model, $text_only_models)) {
            wp_send_json_error("Incompatible Model: '{$model}' is a Text-Only model and cannot process Images. Please select 'qwen-vl-max' or 'qwen3.6-plus'.");
        }

        // AGGRESSIVE SYSTEM PROMPT FOR MAXIMUM DETAIL EXTRACTION
        $system_prompt = "You are a highly advanced data extraction AI. Your task is to extract a complete list of products from the provided image or document quotation.

CRITICAL INSTRUCTIONS:
0. FULL EXTRACTION: You MUST extract EVERY SINGLE PRODUCT found in the document. Scan every row of any table. Do not summarize or skip items.
1. Product Name ('name'): You MUST synthesize an EXTREMELY detailed and highly descriptive product title. DO NOT just output a single word. You must look at ALL columns and adjacent text related to the product and merge them. Combine the base name with:
   - Dimensions / Size / Weight
   - Colors / Materials
   - Power Specs / Voltage / Wattage
   - Technical Specifications
   - Any visible Remarks, Notes, or Descriptions.
   Format the 'name' exactly like this: \"[Base Name] - [Detailed Specs] - [Notes]\". Make it comprehensive!
2. Model Number ('model'): Extract the exact supplier model/part/item number.
3. Price ('price'): Extract the unit price. If not in USD, estimate the conversion to USD. Output ONLY as a numeric float.

You MUST return ONLY a valid JSON array of objects. Do not include markdown blocks like ```json.
Example:
[
  {
    \"name\": \"Premium Desk Chair - Black Leather, 50x50x100cm, Steel Frame - Includes Armrests\",
    \"model\": \"DC-8809\",
    \"price\": 125.50
  }
]";

        // Build the request messages
        if ($is_image) {
            $base64 = base64_encode(file_get_contents($file_tmp));
            $mime_map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
            $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'image/jpeg';
            
            $messages = array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => array(
                        array('type' => 'image_url', 'image_url' => array('url' => 'data:' . $mime . ';base64,' . $base64)),
                        array('type' => 'text', 'text' => "Extract EVERY product from this image quotation accurately. Pay extremely close attention to the tables. For each product, merge ALL columns (specs, colors, material, size, notes) into the Product Name field to make it highly descriptive.")
                    )
                )
            );
        } else {
            // Extract Data natively in PHP for documents
            $raw_data = '';
            if ($ext === 'csv' || $ext === 'txt') {
                $raw_data = file_get_contents($file_tmp, false, null, 0, 150000);
            } elseif ($ext === 'xlsx') {
                $raw_data = $this->parse_xlsx_to_text($file_tmp);
            } elseif ($ext === 'pdf') {
                $raw_data = $this->parse_pdf_to_text($file_tmp);
            }

            if (empty(trim($raw_data))) {
                wp_send_json_error('Could not extract text from the document. If this is a Scanned PDF (an image inside a PDF), our text-extractor cannot read it. Please convert it to a JPG/PNG and use the Qwen VL Max model instead.');
            }

            $messages = array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => "Extract EVERY product from this raw text data accurately. Merge ALL specifications, notes, and remarks into the highly descriptive product name.\n\nRaw Quotation Data:\n" . substr($raw_data, 0, 150000))
            );
        }

        $endpoint = 'https://ws-efvhqx0cwbjtzdwb.cn-beijing.maas.aliyuncs.com/compatible-mode/v1/chat/completions';
        
        $body = array(
            'model' => $model,
            'messages' => $messages
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode($body),
            'timeout' => 150 // Very high timeout for full document processing
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Server Request failed: ' . $response->get_error_message() . ' | Note: If you received a Timeout error, the file is too large for your web host\'s maximum execution time. Try a smaller file.');
        }

        $body_str = wp_remote_retrieve_body($response);
        $data = json_decode($body_str, true);

        // Catch Dashscope 503 Overload Errors gracefully
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code == 503) {
            wp_send_json_error('API Error (503 Service Unavailable): Alibaba\'s DashScope API is currently overloaded and dropped the request for this specific model. Please try a different model (like Qwen VL Max) or try again later.');
        }

        if (isset($data['code']) && !isset($data['choices'])) {
            $err_msg = isset($data['message']) ? $data['message'] : 'Unknown API error';
            wp_send_json_error("API Request Rejected [Code: {$data['code']}]: {$err_msg}");
        }
        
        if (isset($data['error'])) {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API error';
            wp_send_json_error('API Error: ' . $err_msg);
        }

        $content = isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : '';
        $original_content = trim($content);

        if (empty($original_content) && isset($data['choices'][0]['message']['reasoning_content'])) {
            $content = $data['choices'][0]['message']['reasoning_content'];
            $original_content = trim($content);
        }

        if (empty($original_content)) {
            wp_send_json_error('[EMPTY RESPONSE] The AI failed to generate an output block. Check the document size or validity.');
        }

        // --- EXTREMELY BULLETPROOF JSON EXTRACTION ---
        $start_arr = strpos($original_content, '[');
        $end_arr = strrpos($original_content, ']');
        $start_obj = strpos($original_content, '{');
        $end_obj = strrpos($original_content, '}');

        $extracted_json = '';

        if ($start_arr !== false && $end_arr !== false && $start_arr < $end_arr) {
            $extracted_json = substr($original_content, $start_arr, $end_arr - $start_arr + 1);
        } elseif ($start_obj !== false && $end_obj !== false && $start_obj < $end_obj) {
            $extracted_json = '[' . substr($original_content, $start_obj, $end_obj - $start_obj + 1) . ']';
        } else {
            $extracted_json = $original_content;
        }

        $json_res = json_decode($extracted_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json_res)) {
            $err_msg = json_last_error_msg();
            
            $snippet_start = substr($original_content, 0, 250);
            $snippet_end = strlen($original_content) > 250 ? substr($original_content, -250) : '';
            $display_snippet = esc_html($snippet_start . ($snippet_end ? " \n\n[... content truncated ...] \n\n" . $snippet_end : ''));
            
            wp_send_json_error('JSON Parse Error (' . $err_msg . '). Raw AI Output: ' . $display_snippet);
        }

        wp_send_json_success($json_res);
    }


    public function ajax_ai_bulk_save() {
        $this->verify_edit_access();
        
        $global_supplier_id = intval($_POST['supplier_id']);
        $items = isset($_POST['items']) ? $_POST['items'] : [];

        if (empty($items)) {
            wp_send_json_error('Missing data to save.');
        }

        $saved_count = 0;

        foreach ($items as $item) {
            $title = sanitize_text_field($item['name']);
            if (empty($title)) continue;

            $sku = sanitize_text_field($item['sku']);
            $model = sanitize_text_field($item['model']);
            $price = floatval($item['price']);
            $cat_id = intval($item['category_id']);
            $img_id = intval($item['image_id']);
            
            // Priority to item-level supplier selection, fallback to global selection
            $item_supplier_id = !empty($item['supplier_id']) ? intval($item['supplier_id']) : $global_supplier_id;

            $post_id = wp_insert_post(array(
                'post_title'   => $title,
                'post_type'    => 'product',
                'post_status'  => 'pending',
            ));

            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_sku', $sku);
                update_post_meta($post_id, '_regular_price', $price);
                update_post_meta($post_id, '_price', $price);
                update_post_meta($post_id, '_wcsom_supplier_price', $price);
                if ($model) update_post_meta($post_id, '_wcsom_supplier_model', $model);
                
                wp_set_object_terms($post_id, 'simple', 'product_type');

                if ($cat_id) wp_set_object_terms($post_id, $cat_id, 'product_cat');
                if ($img_id) set_post_thumbnail($post_id, $img_id);
                
                update_post_meta($post_id, '_wcsom_price_type', 'EXW'); // default
                
                if ($item_supplier_id) {
                    update_post_meta($post_id, '_wcsom_supplier_id', $item_supplier_id);
                }
                update_post_meta($post_id, '_wcsom_assigned_date', current_time('mysql'));

                $saved_count++;
            }
        }

        wp_send_json_success($saved_count . ' products saved successfully!');
    }

    // --- Remaining AJAX Handlers ---

    public function ajax_add_staff() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Only administrators can add staff.');
        
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) wp_send_json_error('Invalid email address.');

        $user = get_user_by('email', $email);
        if (!$user) {
            $password = wp_generate_password();
            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());
            $user = get_user_by('id', $user_id);
        }

        $user->add_role('wcsom_staff');
        update_user_meta($user->ID, '_wcsom_access_level', 'full'); // Default to full access
        wp_send_json_success('Staff added successfully.');
    }

    public function ajax_remove_staff() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized.');
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) wp_send_json_error('Invalid user.');

        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->remove_role('wcsom_staff');
            delete_user_meta($user->ID, '_wcsom_access_level');
            wp_send_json_success('Staff access removed.');
        }
        wp_send_json_error('Failed to remove staff.');
    }

    public function ajax_update_staff_access() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized.');
        
        $user_id = intval($_POST['user_id']);
        $level = sanitize_text_field($_POST['level']);
        
        if ($user_id && in_array($level, ['full', 'view_only'])) {
            update_user_meta($user_id, '_wcsom_access_level', $level);
            wp_send_json_success('Access level updated.');
        }
        wp_send_json_error('Invalid data.');
    }

    public function ajax_toggle_supplier_visibility() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized.');
        
        $supplier_id = intval($_POST['supplier_id']);
        $status = sanitize_text_field($_POST['status']); // '1' or '0'
        
        if ($supplier_id) {
            update_user_meta($supplier_id, '_wcsom_staff_visible', $status);
            wp_send_json_success('Visibility updated.');
        }
        wp_send_json_error('Invalid supplier.');
    }

    public function ajax_create_supplier() {
        $this->verify_edit_access();

        $company_name = sanitize_text_field($_POST['company_name']);
        $supplier_code = sanitize_text_field($_POST['supplier_code']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $address = sanitize_text_field($_POST['address']);
        $city = sanitize_text_field($_POST['city']);
        $send_invite = isset($_POST['send_invite']) && $_POST['send_invite'] === '1';

        if (empty($company_name)) {
            wp_send_json_error('Company name is required.');
        }

        // Validate unique email if provided
        if (!empty($email) && email_exists($email)) {
            wp_send_json_error('This email is already registered to another user.');
        }

        // Generate a valid username
        $base_username = !empty($supplier_code) ? sanitize_user(strtolower($supplier_code)) : sanitize_user(strtolower(str_replace(' ', '', $company_name)));
        if (empty($base_username)) $base_username = 'supplier';
        
        $username = $base_username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base_username . $i;
            $i++;
        }

        $password = wp_generate_password();
        
        $userdata = array(
            'user_login' => $username,
            'user_pass'  => $password,
            'role'       => 'subscriber' 
        );
        
        if (!empty($email)) {
            $userdata['user_email'] = $email;
        }

        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // Standard WP & WooCommerce Meta (Maps exactly to Packing List plugin needs)
        update_user_meta($user_id, 'company_name', $company_name);
        if (!empty($supplier_code)) update_user_meta($user_id, 'supplier_code', $supplier_code);
        if (!empty($phone)) update_user_meta($user_id, 'billing_phone', $phone);
        if (!empty($address)) update_user_meta($user_id, 'billing_address_1', $address);
        if (!empty($city)) update_user_meta($user_id, 'billing_city', $city);
        
        // Ensure new suppliers created by staff are instantly visible to them
        update_user_meta($user_id, '_wcsom_staff_visible', '1');

        // Send Email Invitation
        if ($send_invite && !empty($email)) {
            wp_new_user_notification($user_id, null, 'user');
        }

        wp_send_json_success('Supplier created successfully!');
    }

    public function ajax_get_supplier_products() {
        $this->verify_access();
        $supplier_id = intval($_POST['supplier_id']);
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date_desc';
        
        if (!in_array($supplier_id, wcsom_get_visible_suppliers())) {
            wp_send_json_error('Unauthorized Supplier Access.');
        }
        
        $exclude_ids = isset($_POST['exclude_ids']) ? array_map('intval', (array)$_POST['exclude_ids']) : [];
        $return_json = isset($_POST['return_json']) ? true : false; 
        $can_edit = wcsom_can_edit();

        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft'), 
            'posts_per_page' => -1,
            'post__not_in'   => $exclude_ids,
            'meta_query'     => array(
                array(
                    'key'   => '_wcsom_supplier_id',
                    'value' => $supplier_id,
                )
            )
        );

        $products = get_posts($args);
        
        $products_data = [];
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            
            $assigned_date = get_post_meta($post->ID, '_wcsom_assigned_date', true);
            if (!$assigned_date) $assigned_date = $post->post_date;

            $supp_price = get_post_meta($post->ID, '_wcsom_supplier_price', true);
            $supp_model = get_post_meta($post->ID, '_wcsom_supplier_model', true);
            $hs_code = get_post_meta($post->ID, '_wcsom_hs_code', true);
            
            $products_data[] = array(
                'post' => $post,
                'product' => $product,
                'assigned_date' => strtotime($assigned_date),
                'name' => strtolower($product->get_name()),
                'sku' => strtolower($product->get_sku() ?: 'zzzzzz'), 
                'supp_price' => $supp_price,
                'supp_model' => $supp_model,
                'hs_code' => $hs_code
            );
        }

        // Apply Sorting
        usort($products_data, function($a, $b) use ($orderby) {
            if ($orderby === 'name_asc') {
                return strnatcasecmp($a['name'], $b['name']);
            } elseif ($orderby === 'sku_asc') {
                return strnatcasecmp($a['sku'], $b['sku']);
            } else {
                return $b['assigned_date'] <=> $a['assigned_date']; 
            }
        });

        if ($return_json) {
            $data = [];
            foreach ($products_data as $pd) {
                $post = $pd['post'];
                $product = $pd['product'];
                $data[] = array(
                    'id' => $post->ID,
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $pd['supp_price'] > 0 ? floatval($pd['supp_price']) : $product->get_price(),
                    'orig_price' => $pd['supp_price'] ? floatval($pd['supp_price']) : 0,
                    'supplier_model' => $pd['supp_model'],
                    'hs_code' => $pd['hs_code']
                );
            }
            wp_send_json_success($data);
        }

        $html = '';
        if (empty($products_data)) {
            wp_send_json_success('<tr><td colspan="7" style="text-align:center; padding: 40px; color: #6b7280;">No products assigned to this supplier yet.</td></tr>');
        }

        foreach ($products_data as $pd) {
            $post = $pd['post'];
            $product = $pd['product'];
            $supp_price = $pd['supp_price'];
            $supp_model = $pd['supp_model'];
            $hs_code = $pd['hs_code'];
            
            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : '<span style="color:#9ca3af;">N/A</span>';
            $wc_price = $product->get_price();
            $po_default_price = $supp_price > 0 ? floatval($supp_price) : $wc_price;

            $html .= '<tr id="wcsom-row-'.$post->ID.'">';
            
            if ($can_edit) {
                $html .= '<td class="wcsom-td-check"><input type="checkbox" class="wcsom-po-select" value="' . $post->ID . '" data-price="'.$po_default_price.'" data-orig-price="'.floatval($supp_price).'" data-name="'.esc_attr($product->get_name()).'" data-model="'.esc_attr($supp_model).'"></td>';
            } else {
                $html .= '<td class="wcsom-td-check"><span class="dashicons dashicons-lock" style="color:#cbd5e1; font-size:16px;"></span></td>';
            }
            
            $html .= '<td class="wcsom-td-img">' . $img . '</td>';
            $html .= '<td><strong>' . esc_html($product->get_name()) . '</strong>';
            $html .= '</td>';
            
            $html .= '<td>' . $sku;
            if ($hs_code) $html .= '<br><span style="font-size:11px; color:#64748b;">HS: '.esc_html($hs_code).'</span>';
            $html .= '</td>';
            
            $html .= '<td>' . wcsom_format_usd($wc_price) . '</td>';
            
            $html .= '<td>
                <span style="font-weight:600; color:#475569; display:block;">' . wcsom_format_usd($supp_price) . '</span>
                <span style="font-size:11px; color:#64748b;">Mdl: ' . ($supp_model ? esc_html($supp_model) : 'N/A') . '</span>
            </td>';
            
            if ($can_edit) {
                $html .= '<td style="vertical-align:middle;">
                    <div style="display:flex; gap: 6px;">
                        <button type="button" class="wcsom-btn wcsom-btn-outline wcsom-edit-product-btn" 
                            data-id="'.$post->ID.'" 
                            data-title="'.esc_attr($product->get_name()).'" 
                            data-sku="'.esc_attr($product->get_sku()).'" 
                            data-hscode="'.esc_attr($hs_code).'" 
                            data-price="'.esc_attr($supp_price).'" 
                            data-model="'.esc_attr($supp_model).'" 
                            style="padding:6px 10px; font-size:12px;">Edit</button>
                        <button type="button" class="wcsom-btn wcsom-btn-outline wcsom-unassign-btn" data-id="'.$post->ID.'" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; font-size:12px;">Unassign</button>
                    </div>
                </td>';
            } else {
                $html .= '<td><span class="wcsom-badge" style="background:#f1f5f9; color:#94a3b8;">Locked</span></td>';
            }
            
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }

    public function ajax_unassign_product() {
        $this->verify_edit_access();
        $product_id = intval($_POST['product_id']);

        if ($product_id) {
            delete_post_meta($product_id, '_wcsom_supplier_id');
            delete_post_meta($product_id, '_wcsom_assigned_date'); // Clean up meta
            wp_send_json_success('Product unassigned.');
        }
        wp_send_json_error('Missing product ID.');
    }

    public function ajax_save_product_quick_edit() {
        $this->verify_edit_access();
        $product_id = intval($_POST['product_id']);
        $title      = sanitize_text_field($_POST['title']);
        $sku        = sanitize_text_field($_POST['sku']);
        $hs_code    = sanitize_text_field($_POST['hs_code']);
        $price      = floatval($_POST['price']);
        $model      = sanitize_text_field($_POST['model']);
        
        if ($product_id && !empty($title)) {
            wp_update_post(array(
                'ID'         => $product_id,
                'post_title' => $title
            ));

            update_post_meta($product_id, '_sku', $sku);
            update_post_meta($product_id, '_wcsom_hs_code', $hs_code);
            update_post_meta($product_id, '_wcsom_supplier_price', $price);
            update_post_meta($product_id, '_wcsom_supplier_model', $model);
            
            wp_send_json_success('Product details updated successfully.');
        }
        wp_send_json_error('Missing required data.');
    }

    public function ajax_get_product_details() {
        $this->verify_edit_access();
        $product_id = intval($_POST['product_id']);
        if (!$product_id) wp_send_json_error('Invalid Product ID');

        $product = wc_get_product($product_id);
        if (!$product) wp_send_json_error('Product not found');

        $cats = $product->get_category_ids();
        $cat_id = !empty($cats) ? current($cats) : '';

        $img_id = $product->get_image_id();
        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';

        $data = array(
            'title' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => get_post_meta($product_id, '_wcsom_supplier_price', true) ?: $product->get_price(),
            'hs_code' => get_post_meta($product_id, '_wcsom_hs_code', true),
            'model' => get_post_meta($product_id, '_wcsom_supplier_model', true),
            'price_type' => get_post_meta($product_id, '_wcsom_price_type', true) ?: 'EXW',
            'category_id' => $cat_id,
            'supplier_id' => get_post_meta($product_id, '_wcsom_supplier_id', true),
            'image_id' => $img_id,
            'image_url' => $img_url
        );
        wp_send_json_success($data);
    }

    public function ajax_search_wc_products() {
        $this->verify_access();
        $keyword = sanitize_text_field($_POST['keyword']);

        global $wpdb;
        $search_query = $wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'pending', 'draft')
            AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
            LIMIT 15
        ", '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%');

        $product_ids = $wpdb->get_col($search_query);
        $result = array();

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;
                
                $assigned_supplier = get_post_meta($pid, '_wcsom_supplier_id', true);
                $sku_text = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
                
                $assign_text = '';
                if ($assigned_supplier) {
                    $code = get_user_meta($assigned_supplier, 'supplier_code', true);
                    $company = get_user_meta($assigned_supplier, 'company_name', true) ?: 'Assigned';
                    $assign_text = ' <span style="color:#dc2626; font-size:11px;">[Currently: ' . ($code ? $code : $company) . ']</span>';
                }

                $result[] = array(
                    'id'   => $pid,
                    'name' => $product->get_name() . $sku_text . $assign_text
                );
            }
        }

        wp_send_json_success($result);
    }

    public function ajax_search_all_wc_products() {
        $this->verify_access();
        $keyword = sanitize_text_field($_GET['keyword']);

        global $wpdb;
        $search_query = $wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'pending', 'draft')
            AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
            LIMIT 20
        ", '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%');

        $product_ids = $wpdb->get_col($search_query);
        $result = array();

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;
                
                $sku_text = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
                $supp_price = get_post_meta($pid, '_wcsom_supplier_price', true);
                $supp_model = get_post_meta($pid, '_wcsom_supplier_model', true);
                
                $result[] = array(
                    'id'         => $pid,
                    'text'       => $product->get_name() . $sku_text,
                    'price'      => $supp_price > 0 ? floatval($supp_price) : $product->get_price(),
                    'orig_price' => $supp_price ? floatval($supp_price) : 0,
                    'sku'        => $product->get_sku(),
                    'name'       => $product->get_name(),
                    'model'      => $supp_model
                );
            }
        }

        wp_send_json_success($result);
    }

    public function ajax_assign_product() {
        $this->verify_edit_access();
        $supplier_id = intval($_POST['supplier_id']);
        $product_id = intval($_POST['product_id']);

        if ($supplier_id && $product_id) {
            update_post_meta($product_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($product_id, '_wcsom_assigned_date', current_time('mysql')); // Log date for sorting
            wp_send_json_success('Product assigned successfully.');
        }
        wp_send_json_error('Missing data.');
    }

    public function ajax_create_po() {
        $this->verify_edit_access();
        $supplier_id = intval($_POST['supplier_id']);
        $order_ref = isset($_POST['order_ref']) && !empty(trim($_POST['order_ref'])) ? sanitize_text_field(trim($_POST['order_ref'])) : 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $items = isset($_POST['items']) ? $_POST['items'] : array(); 

        if (!$supplier_id) {
            wp_send_json_error('Missing supplier.');
        }

        $total_qty = 0;
        $total_amount = 0;
        $order_items = array();

        if (!empty($items)) {
            foreach ($items as $item) {
                $qty = intval($item['qty']);
                $price = floatval($item['price']);
                $orig_price = isset($item['orig_price']) ? floatval($item['orig_price']) : 0;
                $supp_model = isset($item['supplier_model']) ? sanitize_text_field($item['supplier_model']) : '';
                
                $total_qty += $qty;
                $total_amount += ($qty * $price);
                
                $order_items[] = array(
                    'product_id'        => intval($item['id']),
                    'qty'               => $qty,
                    'price'             => $price,
                    'orig_price'        => $orig_price,
                    'supplier_model'    => $supp_model,
                    'added_by_supplier' => false
                );
            }
        }

        $post_id = wp_insert_post(array(
            'post_title'  => $order_ref,
            'post_type'   => 'wcsom_order',
            'post_status' => 'publish',
        ));

        if ($post_id) {
            $sec_code = mt_rand(1000000, 9999999); 
            
            update_post_meta($post_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($post_id, '_wcsom_total_qty', $total_qty);
            update_post_meta($post_id, '_wcsom_total_amount', $total_amount);
            update_post_meta($post_id, '_wcsom_items', $order_items);
            update_post_meta($post_id, '_wcsom_status', 'waiting_for_quote');
            update_post_meta($post_id, '_wcsom_notes', '');
            update_post_meta($post_id, '_wcsom_tags', []); 
            update_post_meta($post_id, '_wcsom_security_code', $sec_code);
            update_post_meta($post_id, '_wcsom_payments', array());
            update_post_meta($post_id, '_wcsom_attachments', array());
            update_post_meta($post_id, '_wcsom_incoterm', 'EXW'); 
            update_post_meta($post_id, '_wcsom_fob_port', ''); 
            update_post_meta($post_id, '_wcsom_allow_supp_qty', 'no'); 
            
            wp_send_json_success('Purchase order created successfully!');
        }

        wp_send_json_error('Failed to create order.');
    }

    public function ajax_save_po_edit() {
        $this->verify_edit_access();
        $po_id = intval($_POST['po_id']);
        $order_ref = sanitize_text_field($_POST['order_ref']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $incoterm = sanitize_text_field($_POST['incoterm']);
        $fob_port = sanitize_text_field($_POST['fob_port']);
        $allow_supp_qty = isset($_POST['allow_supp_qty']) && $_POST['allow_supp_qty'] === 'yes' ? 'yes' : 'no';
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        
        $tags_raw = sanitize_text_field($_POST['tags']);
        $tags_array = array_filter(array_map('trim', explode(',', $tags_raw)));

        $payments = isset($_POST['payments']) ? $_POST['payments'] : array();
        $attachments = isset($_POST['attachments']) ? $_POST['attachments'] : array();

        $total_qty = 0;
        $total_amount = 0;
        $order_items = array();

        foreach ($items as $item) {
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $orig_price = isset($item['orig_price']) ? floatval($item['orig_price']) : 0;
            $supp_model = isset($item['supplier_model']) ? sanitize_text_field($item['supplier_model']) : '';
            $added = !empty($item['added']) ? true : false;
            
            if ($qty > 0) {
                $total_qty += $qty;
                $total_amount += ($qty * $price);
                $order_items[] = array(
                    'product_id'        => intval($item['id']),
                    'qty'               => $qty,
                    'price'             => $price,
                    'orig_price'        => $orig_price,
                    'supplier_model'    => $supp_model,
                    'added_by_supplier' => $added
                );
            }
        }

        $formatted_payments = [];
        if (!empty($payments)) {
            foreach($payments as $p) {
                $percent = floatval($p['percent']);
                $amount = ($percent > 0) ? ($percent / 100) * $total_amount : floatval($p['amount']);
                
                $formatted_payments[] = array(
                    'title'   => sanitize_text_field($p['title']),
                    'percent' => $percent,
                    'amount'  => $amount,
                    'status'  => sanitize_text_field($p['status'])
                );
            }
        }

        $formatted_attachments = [];
        if (!empty($attachments)) {
            foreach($attachments as $a) {
                $formatted_attachments[] = array(
                    'url'         => esc_url_raw($a['url']),
                    'name'        => sanitize_text_field($a['name']),
                    'type'        => sanitize_text_field($a['type']),
                    'uploaded_by' => sanitize_text_field($a['uploaded_by']),
                    'date'        => sanitize_text_field($a['date'])
                );
            }
        }

        if (!empty($order_ref)) {
            wp_update_post(array(
                'ID'         => $po_id,
                'post_title' => $order_ref
            ));
        }

        update_post_meta($po_id, '_wcsom_items', $order_items);
        update_post_meta($po_id, '_wcsom_total_qty', $total_qty);
        update_post_meta($po_id, '_wcsom_total_amount', $total_amount);
        update_post_meta($po_id, '_wcsom_status', $status);
        update_post_meta($po_id, '_wcsom_notes', $notes);
        update_post_meta($po_id, '_wcsom_tags', $tags_array);
        update_post_meta($po_id, '_wcsom_incoterm', $incoterm);
        update_post_meta($po_id, '_wcsom_fob_port', $fob_port);
        update_post_meta($po_id, '_wcsom_allow_supp_qty', $allow_supp_qty);
        update_post_meta($po_id, '_wcsom_payments', $formatted_payments);
        update_post_meta($po_id, '_wcsom_attachments', $formatted_attachments);

        wp_send_json_success('Purchase order updated successfully!');
    }

    public function ajax_delete_po() {
        $this->verify_edit_access();
        $po_id = intval($_POST['po_id']);
        
        if ($po_id) {
            wp_delete_post($po_id, true);
            wp_send_json_success('Purchase Order deleted successfully.');
        }
        wp_send_json_error('Failed to delete Purchase Order.');
    }

    public function ajax_bulk_delete_pos() {
        $this->verify_edit_access();
        $po_ids = isset($_POST['po_ids']) ? array_map('intval', $_POST['po_ids']) : [];

        if (empty($po_ids)) {
            wp_send_json_error('No orders selected.');
        }

        foreach ($po_ids as $po_id) {
            wp_delete_post($po_id, true);
        }

        wp_send_json_success('Selected orders deleted successfully.');
    }

    public function ajax_global_product_search() {
        $this->verify_access();
        
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $assignment = isset($_POST['assignment']) ? sanitize_text_field($_POST['assignment']) : '';
        $can_edit = wcsom_can_edit();

        $visible_supplier_ids = wcsom_get_visible_suppliers();

        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft'),
            'posts_per_page' => 50, 
        );

        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }

        if ($assignment === 'unassigned') {
            $args['meta_query'] = array(
                array(
                    'key'     => '_wcsom_supplier_id',
                    'compare' => 'NOT EXISTS'
                )
            );
        } elseif ($assignment === 'assigned') {
             $args['meta_query'] = array(
                array(
                    'key'     => '_wcsom_supplier_id',
                    'compare' => 'EXISTS'
                )
            );
        }

        if (!empty($keyword)) {
            global $wpdb;
            
            $tax_join = '';
            $tax_where = '';
            if (!empty($category)) {
                $term = get_term_by('slug', $category, 'product_cat');
                if ($term) {
                    $tax_join = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
                    $tax_where = "AND tr.term_taxonomy_id = " . intval($term->term_taxonomy_id);
                }
            }

            $meta_join = "LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'";
            $assignment_where = '';
            
            if ($assignment === 'unassigned') {
                $meta_join .= " LEFT JOIN {$wpdb->postmeta} pm_supp ON p.ID = pm_supp.post_id AND pm_supp.meta_key = '_wcsom_supplier_id'";
                $assignment_where = "AND pm_supp.meta_id IS NULL";
            } elseif ($assignment === 'assigned') {
                $meta_join .= " INNER JOIN {$wpdb->postmeta} pm_supp ON p.ID = pm_supp.post_id AND pm_supp.meta_key = '_wcsom_supplier_id'";
            }

            $search_query = $wpdb->prepare("
                SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                {$tax_join}
                {$meta_join}
                LEFT JOIN {$wpdb->term_relationships} tr_tags ON p.ID = tr_tags.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt_tags ON tr_tags.term_taxonomy_id = tt_tags.term_taxonomy_id AND tt_tags.taxonomy = 'product_tag'
                LEFT JOIN {$wpdb->terms} t_tags ON tt_tags.term_id = t_tags.term_id
                WHERE p.post_type = 'product' 
                AND p.post_status IN ('publish', 'pending', 'draft')
                {$tax_where}
                {$assignment_where}
                AND (
                    p.post_title LIKE %s 
                    OR pm_sku.meta_value LIKE %s
                    OR t_tags.name LIKE %s
                )
                LIMIT 50
            ", '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%');

            $product_ids = $wpdb->get_col($search_query);
            $products = array();
            if (!empty($product_ids)) {
                $args_override = array(
                    'post_type' => 'product',
                    'post_status' => array('publish', 'pending', 'draft'),
                    'post__in' => $product_ids,
                    'posts_per_page' => -1
                );
                $products = get_posts($args_override);
            }
        } else {
            $products = get_posts($args);
        }

        $html = '';

        if (empty($products)) {
            wp_send_json_success('<tr><td colspan="'.($can_edit ? '7' : '6').'" style="text-align:center; padding: 40px; color:#64748b;">No products match your filters.</td></tr>');
        }

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;

            $supplier_id = get_post_meta($post->ID, '_wcsom_supplier_id', true);
            $supp_price = get_post_meta($post->ID, '_wcsom_supplier_price', true);
            $supp_model = get_post_meta($post->ID, '_wcsom_supplier_model', true);
            $hs_code = get_post_meta($post->ID, '_wcsom_hs_code', true);

            $supplier_display = '<span class="wcsom-badge" style="background:#f1f5f9; color:#64748b;">Unassigned</span>';
            $action_html = '';
            
            if ($supplier_id) {
                if (!in_array($supplier_id, $visible_supplier_ids) && !current_user_can('manage_options')) {
                    $supplier_display = '<div style="display:flex; align-items:center; gap:8px;"><span class="wcsom-badge" style="background:#eef2ff; color:#4f46e5; border:1px solid #c7d2fe;">Assigned (Hidden Supplier)</span></div>';
                    $action_html = '<span style="color:#94a3b8; font-size:12px;">Locked</span>';
                } else {
                    $code = get_user_meta($supplier_id, 'supplier_code', true);
                    $company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'first_name', true) . ' ' . get_user_meta($supplier_id, 'last_name', true);
                    if (!trim($company)) {
                        $user_info = get_userdata($supplier_id);
                        $company = $user_info ? $user_info->display_name : 'Unknown';
                    }
                    
                    $supplier_display = '<div style="display:flex; align-items:center; gap:8px;"><span class="wcsom-badge" style="background:#eef2ff; color:#4f46e5; border:1px solid #c7d2fe;">Assigned: ' . ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company) . '</span></div>';
                    
                    if ($can_edit) {
                        $action_html = '<div style="display:flex; gap:6px; flex-wrap:wrap;">';
                        $action_html .= '<button type="button" class="wcsom-btn wcsom-btn-outline wcsom-edit-product-btn" 
                            data-id="'.$post->ID.'" 
                            data-title="'.esc_attr($product->get_name()).'" 
                            data-sku="'.esc_attr($product->get_sku()).'" 
                            data-hscode="'.esc_attr($hs_code).'" 
                            data-price="'.esc_attr($supp_price).'" 
                            data-model="'.esc_attr($supp_model).'" 
                            style="padding:4px 8px; font-size:11px;">Edit</button>';
                        $action_html .= '<button type="button" class="wcsom-btn wcsom-btn-outline wcsom-duplicate-product-btn" data-id="'.$post->ID.'" style="padding:4px 8px; font-size:11px;">Duplicate</button>';
                        $action_html .= '<button type="button" class="wcsom-btn wcsom-btn-outline wcsom-unassign-global-btn" data-id="'.$post->ID.'" style="padding:4px 8px; font-size:11px; color:#ef4444; border-color:#fca5a5;">Unassign</button>';
                        $action_html .= '</div>';
                    } else {
                        $action_html = '<span style="color:#94a3b8; font-size:12px;">Read Only</span>';
                    }
                }
            } else {
                if ($can_edit) {
                    $action_html = '<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom: 6px;">';
                    $action_html .= '<select class="wcsom-input wcsom-quick-assign-sel" style="width:130px; padding:4px 8px; font-size:11px;">';
                    $action_html .= '<option value="">Select Supplier...</option>';
                    foreach($visible_supplier_ids as $sid) {
                        $c = get_user_meta($sid, 'supplier_code', true);
                        $n = get_user_meta($sid, 'company_name', true) ?: get_userdata($sid)->display_name;
                        $action_html .= '<option value="'.$sid.'">'.esc_html(($c ? "[$c] " : "") . $n).'</option>';
                    }
                    $action_html .= '</select>';
                    $action_html .= '<button type="button" class="wcsom-btn wcsom-btn-primary wcsom-quick-assign-btn" data-id="'.$post->ID.'" style="padding:4px 10px; font-size:11px;">Assign</button>';
                    $action_html .= '</div>';
                    
                    $action_html .= '<div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="wcsom-btn wcsom-btn-outline wcsom-edit-product-btn" 
                            data-id="'.$post->ID.'" 
                            data-title="'.esc_attr($product->get_name()).'" 
                            data-sku="'.esc_attr($product->get_sku()).'" 
                            data-hscode="'.esc_attr($hs_code).'" 
                            data-price="'.esc_attr($supp_price).'" 
                            data-model="'.esc_attr($supp_model).'" 
                            style="padding:4px 8px; font-size:11px;">Edit Details</button>
                            <button type="button" class="wcsom-btn wcsom-btn-outline wcsom-duplicate-product-btn" data-id="'.$post->ID.'" style="padding:4px 8px; font-size:11px;">Duplicate</button>
                        </div>';
                } else {
                    $action_html = '<span style="color:#94a3b8; font-size:12px;">Read Only</span>';
                }
            }

            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : '<span style="color:#9ca3af;">N/A</span>';
            
            $cats = wc_get_product_category_list($post->ID, ', ');
            $cats = $cats ? strip_tags($cats) : '<span style="color:#9ca3af;">None</span>';
            
            $wp_status = $post->post_status;
            $status_color = $wp_status === 'publish' ? '#16a34a' : ($wp_status === 'pending' ? '#d97706' : '#64748b');

            $html .= '<tr id="wcsom-global-row-'.$post->ID.'">';
            
            if ($can_edit) {
                $html .= '<td class="wcsom-td-check"><input type="checkbox" class="wcsom-search-select" value="' . $post->ID . '"></td>';
            }

            $html .= '<td style="display:flex; align-items:center; gap:12px;">' . $img . ' <div><strong><a href="'.get_edit_post_link($post->ID).'" target="_blank" style="text-decoration:none; color:inherit;">' . esc_html($product->get_name()) . '</a></strong>'.($supp_model ? '<br><span style="font-size:11px; color:#64748b;">Model: '.esc_html($supp_model).'</span>' : '').'</div></td>';
            $html .= '<td style="font-size:13px; color:#475569;">' . $cats . '</td>';
            
            $html .= '<td>' . $sku;
            if ($hs_code) $html .= '<br><span style="font-size:11px; color:#64748b;">HS: '.esc_html($hs_code).'</span>';
            $html .= '</td>';

            $html .= '<td><span style="font-weight:600; font-size:12px; color:'.$status_color.'; text-transform:uppercase;">' . esc_html($wp_status) . '</span></td>';
            $html .= '<td>' . $supplier_display . '</td>';
            $html .= '<td>' . $action_html . '</td>';
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }

    public function ajax_bulk_edit_products() {
        $this->verify_edit_access();
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : [];
        if (empty($product_ids)) wp_send_json_error('No products selected.');

        $sku         = isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '';
        $tags        = isset($_POST['tags']) ? sanitize_text_field($_POST['tags']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $supplier_id = isset($_POST['supplier_id']) ? sanitize_text_field($_POST['supplier_id']) : '';
        $image_id    = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

        foreach ($product_ids as $pid) {
            if ($sku !== '') update_post_meta($pid, '_sku', $sku);

            if ($supplier_id === 'unassign') {
                delete_post_meta($pid, '_wcsom_supplier_id');
            } elseif ($supplier_id !== '') {
                update_post_meta($pid, '_wcsom_supplier_id', intval($supplier_id));
            }

            if ($image_id > 0) set_post_thumbnail($pid, $image_id);

            if ($category_id > 0) wp_set_object_terms($pid, $category_id, 'product_cat', false);

            if ($tags !== '') {
                $tags_array = array_filter(array_map('trim', explode(',', $tags)));
                wp_set_object_terms($pid, $tags_array, 'product_tag', false);
            }
        }

        wp_send_json_success('Products updated successfully.');
    }

    public function ajax_quick_create_product() {
        $this->verify_edit_access();

        $title = sanitize_text_field($_POST['title']);
        $sku = sanitize_text_field($_POST['sku']);
        $hs_code = sanitize_text_field($_POST['hs_code']);
        $price = floatval($_POST['price']);
        $price_type = sanitize_text_field($_POST['price_type']);
        $category_id = intval($_POST['category_id']);
        $supplier_id = intval($_POST['supplier_id']);
        $image_id = intval($_POST['image_id']);
        $model_number = isset($_POST['model_number']) ? sanitize_text_field($_POST['model_number']) : '';

        if (empty($title)) wp_send_json_error('Product Title is required.');

        $post_id = wp_insert_post(array(
            'post_title'   => $title,
            'post_type'    => 'product',
            'post_status'  => 'pending',
        ));

        if (is_wp_error($post_id)) wp_send_json_error('Failed to create product.');

        update_post_meta($post_id, '_sku', $sku);
        update_post_meta($post_id, '_wcsom_hs_code', $hs_code);
        update_post_meta($post_id, '_regular_price', $price);
        update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_wcsom_supplier_price', $price);
        if ($model_number) update_post_meta($post_id, '_wcsom_supplier_model', $model_number);
        wp_set_object_terms($post_id, 'simple', 'product_type');

        if ($category_id) wp_set_object_terms($post_id, $category_id, 'product_cat');
        if ($image_id) set_post_thumbnail($post_id, $image_id);
        
        update_post_meta($post_id, '_wcsom_price_type', $price_type);
        if ($supplier_id) {
            update_post_meta($post_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($post_id, '_wcsom_assigned_date', current_time('mysql')); // Log date for sorting
        }

        wp_send_json_success('Product created successfully and set to Pending.');
    }

    public function ajax_create_bulk_order() {
        $this->verify_edit_access();
        $po_ids = isset($_POST['po_ids']) ? array_map('intval', $_POST['po_ids']) : [];
        $title = sanitize_text_field($_POST['title']);

        if (empty($po_ids) || empty($title)) wp_send_json_error('Missing data.');

        $bulk_id = wp_insert_post(array(
            'post_title'  => $title,
            'post_type'   => 'wcsom_bulk_order',
            'post_status' => 'publish',
        ));

        if ($bulk_id) {
            update_post_meta($bulk_id, '_wcsom_po_ids', $po_ids);
            wp_send_json_success('Bulk Order created!');
        }
        wp_send_json_error('Failed to create Bulk Order.');
    }

    public function ajax_delete_bulk_order() {
        $this->verify_edit_access();
        $bulk_id = intval($_POST['bulk_id']);

        if ($bulk_id) {
            wp_delete_post($bulk_id, true);
            wp_send_json_success('Bulk Order deleted.');
        }
        wp_send_json_error('Failed to delete Bulk Order.');
    }

    public function ajax_bulk_add_tags() {
        $this->verify_edit_access();
        $po_ids = isset($_POST['po_ids']) ? array_map('intval', $_POST['po_ids']) : [];
        $tags_raw = sanitize_text_field($_POST['tags']);
        $new_tags = array_filter(array_map('trim', explode(',', $tags_raw)));

        if (empty($po_ids) || empty($new_tags)) wp_send_json_error('Missing data.');

        foreach ($po_ids as $po_id) {
            $existing_tags = get_post_meta($po_id, '_wcsom_tags', true) ?: [];
            $combined_tags = array_unique(array_merge($existing_tags, $new_tags));
            update_post_meta($po_id, '_wcsom_tags', array_values($combined_tags));
        }

        wp_send_json_success('Tags added successfully!');
    }

    public function ajax_get_supplier_packing_lists() {
        $this->verify_access();
        $supplier_id = intval($_POST['supplier_id']);
        if (!$supplier_id) wp_send_json_error('Missing supplier ID');

        $packing_lists = wcsom_get_supplier_packing_lists($supplier_id);
        
        $html = '';
        if (empty($packing_lists)) {
            $html = '<div style="color:#94a3b8; font-size:13px; font-style:italic;">No packing lists found for this supplier.</div>';
        } else {
            $html .= '<ul style="margin:0; padding:0; list-style:none;">';
            foreach ($packing_lists as $pl) {
                $edit_url = get_edit_post_link($pl->ID);
                $html .= '<li style="margin-bottom:8px; padding:8px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">';
                $html .= '<div><a href="'.esc_url($edit_url).'" target="_blank" style="font-weight:600; font-size:13px; color:#4f46e5; text-decoration:none;">'.esc_html($pl->post_title).'</a>';
                $html .= '<div style="font-size:11px; color:#64748b;">'.date('M d, Y', strtotime($pl->post_date)).'</div></div>';
                $html .= '<a href="'.esc_url($edit_url).'" target="_blank" class="wcsom-btn wcsom-btn-outline" style="padding:4px 8px; font-size:11px;">View</a>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        wp_send_json_success($html);
    }
}
