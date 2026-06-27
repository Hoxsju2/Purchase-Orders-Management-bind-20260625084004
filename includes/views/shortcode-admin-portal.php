<?php
if (!defined('ABSPATH')) exit;

if (wcsom_is_staff()) {
    // If authenticated via WP Role or OTP (which sets standard WP auth cookie),
    // load the full dashboard wrapped cleanly so theme styles don't conflict wildly.
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'suppliers';
    echo '<div class="wcsom-frontend-wrapper" style="min-height: 80vh; padding: 20px; background: #f8fafc; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-top:20px;">';
    include WCSOM_PLUGIN_DIR . 'admin/views/main.php';
    echo '</div>';
} else {
    // Show Modern OTP Login Form mapped through Tailwind via CDN to keep it decoupled from theme
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <div class="w-full max-w-md mx-auto mt-20 mb-20 bg-white p-8 rounded-2xl shadow-lg border border-slate-100 text-center font-sans">
        <div class="w-16 h-16 bg-slate-900 rounded-full flex items-center justify-center mx-auto mb-6 text-white">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path></svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Staff Access Portal</h2>
        <p class="text-sm text-slate-500 mb-6">Enter your authorized email to receive a secure login code.</p>
        
        <div id="wcsom-login-error" class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4 border border-red-100 hidden"></div>

        <form id="wcsom-otp-request-form" class="space-y-4">
            <input type="email" id="wcsom-auth-email" required placeholder="colleague@example.com" class="w-full text-center p-3 border border-slate-300 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 outline-none transition">
            <button type="submit" id="wcsom-btn-request-otp" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-xl transition shadow-md">
                Send Login Code
            </button>
        </form>

        <form id="wcsom-otp-verify-form" class="space-y-4 hidden">
            <p class="text-sm text-slate-500">We sent a 6-digit code to your email.</p>
            <input type="text" id="wcsom-auth-otp" required placeholder="000000" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full text-center text-3xl tracking-widest font-mono p-4 border border-slate-300 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 outline-none transition">
            <button type="submit" id="wcsom-btn-verify-otp" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition shadow-md">
                Verify & Access Dashboard
            </button>
            <button type="button" id="wcsom-btn-back-email" class="text-sm text-slate-500 hover:text-slate-800 underline mt-4">Use a different email</button>
        </form>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        const requestForm = document.getElementById('wcsom-otp-request-form');
        const verifyForm = document.getElementById('wcsom-otp-verify-form');
        const errorBox = document.getElementById('wcsom-login-error');
        const emailInput = document.getElementById('wcsom-auth-email');
        const otpInput = document.getElementById('wcsom-auth-otp');
        const btnRequest = document.getElementById('wcsom-btn-request-otp');
        const btnVerify = document.getElementById('wcsom-btn-verify-otp');

        requestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let email = emailInput.value.trim();
            if(!email) return;

            btnRequest.disabled = true;
            btnRequest.innerText = 'Sending...';
            errorBox.classList.add('hidden');

            let formData = new FormData();
            formData.append('action', 'wcsom_send_otp');
            formData.append('email', email);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    requestForm.classList.add('hidden');
                    verifyForm.classList.remove('hidden');
                } else {
                    errorBox.innerText = data.data;
                    errorBox.classList.remove('hidden');
                }
            })
            .finally(() => {
                btnRequest.disabled = false;
                btnRequest.innerText = 'Send Login Code';
            });
        });

        verifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let email = emailInput.value.trim();
            let otp = otpInput.value.trim();
            if(!otp) return;

            btnVerify.disabled = true;
            btnVerify.innerText = 'Verifying...';
            errorBox.classList.add('hidden');

            let formData = new FormData();
            formData.append('action', 'wcsom_verify_otp');
            formData.append('email', email);
            formData.append('otp', otp);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload(); // Reload sets standard WP auth cookie
                } else {
                    errorBox.innerText = data.data;
                    errorBox.classList.remove('hidden');
                    btnVerify.disabled = false;
                    btnVerify.innerText = 'Verify & Access Dashboard';
                }
            });
        });

        document.getElementById('wcsom-btn-back-email').addEventListener('click', function() {
            verifyForm.classList.add('hidden');
            requestForm.classList.remove('hidden');
            otpInput.value = '';
            errorBox.classList.add('hidden');
        });
    });
    </script>
    <?php
}
?>
