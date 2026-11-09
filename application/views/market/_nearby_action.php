<button type="button" class="shop-category-item shop-nearby-tile <?= !empty($nearby)?'selected':'' ?>" data-nearby-target="<?= html_escape($target) ?>" aria-label="Urutkan yang terdekat dengan lokasi Anda">
    <span class="shop-category-art shop-nearby-art" aria-hidden="true">
        <svg viewBox="0 0 72 72"><circle cx="36" cy="36" r="26"/><circle cx="36" cy="36" r="17"/><path d="M36 17v8m0 22v8M17 36h8m22 0h8"/><path d="M36 27a9 9 0 0 0-9 9c0 6 9 13 9 13s9-7 9-13a9 9 0 0 0-9-9Z"/><circle cx="36" cy="36" r="3"/></svg>
    </span>
    <span>Terdekat</span>
    <small class="shop-nearby-message" role="status" aria-live="polite"></small>
</button>
<script src="<?= base_url('assets/market-nearby.js?v=2') ?>" defer></script>
