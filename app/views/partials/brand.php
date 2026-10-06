<?php
/** Logo lembaga bila sudah diunggah (Pengaturan), jika tidak: lengkung sederhana sebagai placeholder. */
$logoV = \App\Services\SettingsService::logoVersion();
if ($logoV !== null): ?>
<span class="brand-mark brand-mark--img" aria-hidden="true"><img src="<?= e(url('/brand/logo') . '?v=' . rawurlencode($logoV)) ?>" alt="" width="34" height="34"></span>
<?php else: ?>
<span class="brand-mark" aria-hidden="true">
    <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 25V15a9 9 0 0 1 18 0v10"/><path d="M4.5 25h23"/><path d="M12.5 25v-6.5a3.5 3.5 0 0 1 7 0V25"/>
    </svg>
</span>
<?php endif; ?>
