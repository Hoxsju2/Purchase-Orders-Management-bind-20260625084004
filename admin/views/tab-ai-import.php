<?php
if (!defined('ABSPATH')) exit;

$categories = get_terms(array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
));

$suppliers = get_users(array(
    'meta_query' => array(
        'relation' => 'OR',
        array('key' => 'company_name', 'compare' => 'EXISTS'),
        array('key' => 'supplier_code', 'compare' => 'EXISTS')
    )
));

// Retrieve saved configurations
$saved_api_key = get_option('wcsom_dashscope_api_key', '');
$saved_model = get_option('wcsom_dashscope_model', 'qwen3.6-plus');
$env_api_key = getenv('DASHSCOPE_API_KEY');
$default_key = $env_api_key ?: $saved_api_key;
?>
<div class="wcsom-grid">
    <div class="wcsom-main-content" style="max-width: 1200px;">
        <div class="wcsom-card">
            
            <div style="background: #f0fdf4; color: #166534; padding: 14px 20px; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 20px; font-weight: 600; font-size: 14px; display:flex; align-items:center; gap:10px;">
                <span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px;"></span> 
                VERSION 1.4.3 - Full-Context AI Parsing Restored for Maximum Accuracy.
            </div>

            <h3 class="wcsom-card-title">AI-Powered Bulk Product Import</h3>
            <p class="wcsom-card-desc wcsom-mb-4">Upload a supplier quotation (Excel .xlsx, PDF, CSV, TXT, or Image format like JPG/PNG). Alibaba's DashScope AI will extract descriptive product names, model numbers, and normalize prices to USD automatically.</p>
            
            <form id="wcsom-ai-upload-form" enctype="multipart/form-data">
                
                <!-- API Configuration Section -->
                <div class="wcsom-mb-4" style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="display:flex; gap: 20px; align-items: flex-end;">
                        <div style="flex: 2;">
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">DashScope API Key</label>
                            <input type="password" id="wcsom-ai-api-key" class="wcsom-input" style="height: 42px;" value="<?php echo esc_attr($default_key); ?>" placeholder="sk-..." required>
                        </div>
                        <div style="flex: 2;">
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">AI Model</label>
                            <select id="wcsom-ai-model" class="wcsom-input" style="height: 42px; padding: 0 10px;">
                                <optgroup label="Advanced Multimodal Models">
                                    <option value="qwen3.6-plus" <?php selected($saved_model, 'qwen3.6-plus'); ?>>Qwen 3.6 Plus (Best for Images, PDFs & Complex Excel)</option>
                                    <option value="qwen-vl-max" <?php selected($saved_model, 'qwen-vl-max'); ?>>Qwen VL Max (Alternative Vision Model)</option>
                                </optgroup>
                                <optgroup label="Text & Document Models">
                                    <option value="qwen-max" <?php selected($saved_model, 'qwen-max'); ?>>Qwen Max (Best for Text & CSV reasoning)</option>
                                    <option value="qwen-plus" <?php selected($saved_model, 'qwen-plus'); ?>>Qwen Plus (Fast Text Processing)</option>
                                </optgroup>
                                <optgroup label="Specialized Models">
                                    <option value="qwen3.5-ocr" <?php selected($saved_model, 'qwen3.5-ocr'); ?>>Qwen 3.5 OCR (Best for structured Receipts/Invoices)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <button type="button" id="wcsom-btn-test-api" class="wcsom-btn wcsom-btn-outline" style="height: 42px; padding: 0 16px;">
                                Test API Connection
                            </button>
                        </div>
                    </div>
                    <div id="wcsom-api-test-result" style="margin-top:10px; font-size:13px; font-weight:600; display:none;"></div>
                </div>

                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">1. Select Default Supplier <span style="color:#ef4444;">*</span></label>
                        <select id="wcsom-ai-supplier" class="wcsom-input" required>
                            <option value="">-- Choose Default Supplier --</option>
                            <?php foreach ($suppliers as $supplier): 
                                $code = get_user_meta($supplier->ID, 'supplier_code', true);
                                $company = get_user_meta($supplier->ID, 'company_name', true) ?: $supplier->display_name;
                                $display = ($code ? '[' . $code . '] ' : '') . $company;
                            ?>
                                <option value="<?php echo esc_attr($supplier->ID); ?>"><?php echo esc_html($display); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size:11px; color:#64748b; margin-top:4px; display:block;">This supplier will be pre-selected for all extracted products, but you can change it individually later.</span>
                    </div>
                    <div style="flex: 2;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">2. Upload Quotation File (Excel, PDF, CSV, TXT, JPG, PNG)</label>
                        <input type="file" id="wcsom-ai-file" accept=".xlsx,.pdf,.csv,.txt,.jpg,.jpeg,.png" class="wcsom-input" style="padding: 7px 14px;" required>
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 15px;">
                    <button type="submit" id="wcsom-ai-btn-parse" class="wcsom-btn wcsom-btn-primary" style="padding: 12px 24px; font-size: 15px;">
                        <span class="dashicons dashicons-admin-generic" style="margin-top:2px;"></span> Parse with AI
                    </button>
                </div>

                <!-- Progress Bar UI -->
                <div id="wcsom-ai-progress-container" style="display:none; margin-top: 20px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="font-size:13px; font-weight:600; color:#4f46e5;" id="wcsom-ai-status-text">Uploading and extracting document data...</span>
                        <span style="font-size:13px; font-weight:600; color:#64748b;" id="wcsom-ai-status-pct">0%</span>
                    </div>
                    <div style="width:100%; background:#e2e8f0; border-radius:99px; height:8px; overflow:hidden;">
                        <div id="wcsom-ai-progress-bar" style="height:100%; width:0%; background:#4f46e5; border-radius:99px; transition: width 0.4s ease;"></div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Interactive Review Table (Hidden until parsed) -->
        <div id="wcsom-ai-review-container" class="wcsom-card wcsom-mt-4" style="display:none; margin-top: 24px;">
            <div class="wcsom-flex-between wcsom-mb-4" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
                <div>
                    <h3 class="wcsom-card-title">Review Extracted Products</h3>
                    <p class="wcsom-card-desc">The AI successfully extracted these products. You can now adjust descriptive names, individually assign suppliers and categories, and manually upload images using the placeholders.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button id="wcsom-ai-btn-discard" class="wcsom-btn wcsom-btn-outline" style="color:#ef4444; border-color:#fca5a5;">Discard & Start Over</button>
                    <button id="wcsom-ai-btn-save" class="wcsom-btn wcsom-btn-primary">Save as Pending Products</button>
                </div>
            </div>

            <!-- Global Category Assignment -->
            <div class="wcsom-mb-4" style="display:flex; align-items:center; gap: 12px; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <label style="font-size:13px; font-weight:600; color:#475569;">Apply Category to All:</label>
                <select id="wcsom-ai-global-category" class="wcsom-input" style="width: 250px; padding:6px; height:auto;">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="wcsom-ai-apply-cat" class="wcsom-btn wcsom-btn-outline" style="padding: 6px 12px; font-size:12px;">Apply</button>
            </div>

            <div class="wcsom-table-responsive" style="margin-top:0; overflow:visible;">
                <table class="wcsom-table" id="wcsom-ai-results-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Image</th>
                            <th style="min-width:250px;">Product Name & Details</th>
                            <th style="width:120px;">Model Number</th>
                            <th style="width:100px;">Price (USD)</th>
                            <th style="width:110px;">SKU</th>
                            <th style="width:140px;">Category</th>
                            <th style="width:180px;">Supplier</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<style>
.wcsom-ai-img-placeholder {
    width: 44px; height: 44px; border-radius: 6px; border: 1px dashed #cbd5e1; background: #f8fafc;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; overflow: hidden;
}
.wcsom-ai-img-placeholder:hover { border-color: #4f46e5; background: #eef2ff; }
.wcsom-ai-img-placeholder img { width: 100%; height: 100%; object-fit: cover; }
</style>