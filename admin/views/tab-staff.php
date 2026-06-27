<?php
if (!defined('ABSPATH')) exit;

$args = array(
    'role'    => 'wcsom_staff',
    'orderby' => 'user_nicename',
    'order'   => 'ASC'
);
$staff_users = get_users($args);
?>
<div class="wcsom-grid">
    <div class="wcsom-main-content" style="flex: 2;">
        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Authorized Staff Management</h3>
            <p class="wcsom-card-desc mb-4">Emails listed here can log in via OTP to access suppliers and orders based on their designated access level.</p>

            <div class="wcsom-table-responsive">
                <table class="wcsom-table">
                    <thead>
                        <tr>
                            <th>Staff Email</th>
                            <th>Status</th>
                            <th>Access Level</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_users)): ?>
                            <tr>
                                <td colspan="4" class="wcsom-empty-cell">No staff members have been authorized yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_users as $user): 
                                $level = get_user_meta($user->ID, '_wcsom_access_level', true) ?: 'full';
                            ?>
                                <tr id="wcsom-staff-<?php echo esc_attr($user->ID); ?>">
                                    <td><strong><?php echo esc_html($user->user_email); ?></strong></td>
                                    <td><span class="wcsom-badge" style="background:#d1fae5; color:#065f46;">Authorized</span></td>
                                    <td>
                                        <select class="wcsom-input wcsom-staff-role-select" data-id="<?php echo esc_attr($user->ID); ?>" style="padding: 4px 8px; font-size:12px; height:auto;">
                                            <option value="full" <?php selected($level, 'full'); ?>>Full Access (Create/Edit)</option>
                                            <option value="view_only" <?php selected($level, 'view_only'); ?>>View Only (Read)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button class="wcsom-btn wcsom-btn-outline wcsom-remove-staff" data-id="<?php echo esc_attr($user->ID); ?>" style="color:#ef4444; border-color:#fca5a5; padding: 6px 12px; font-size:12px;">Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="wcsom-sidebar" style="flex: 1;">
        <div class="wcsom-card" style="background: #f8fafc;">
            <h3 class="wcsom-card-title">Add Staff Member</h3>
            <p class="wcsom-card-desc mb-4">Grant dashboard access to an email via OTP.</p>
            
            <div class="wcsom-mb-4">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Email Address</label>
                <input type="email" id="wcsom-new-staff-email" class="wcsom-input" placeholder="colleague@example.com">
            </div>

            <button type="button" id="wcsom-btn-add-staff" class="wcsom-btn wcsom-btn-primary" style="width: 100%; justify-content: center;">
                Authorize Email
            </button>
        </div>
    </div>
</div>
