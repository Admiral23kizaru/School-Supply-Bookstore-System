<div class="header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="page-title"><?= esc($pageTitle ?? 'Dashboard') ?></h1>
        <div class="subtitle"><?= esc($pageSubtitle ?? '') ?></div>
    </div>
    <?php if (!empty($headerRightHtml ?? '')): ?>
        <div><?= $headerRightHtml ?></div>
    <?php endif; ?>
</div>
