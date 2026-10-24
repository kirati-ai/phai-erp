<?php
/**
 * The story of a module above its dashboard's KPI cards (System\Flow::of): one card per "ทำรายการ" screen — what it is —
 * and under the card, small, the numbers of the documents waiting for that screen (its work queues), filled by public/js/flow.js.
 * @var array $flow
 */
if (!$flow) {
    return;
}
// flow.js only needs the queue's API and the column that names the document
$wait = fn (array $q) => [
    'api'    => $q['api'],
    'params' => $q['params'] ?? [],
    'urgent' => $q['urgent'] ?? null,
    'key'    => (array_values(array_filter($q['columns'], fn ($c) => !empty($c['link'])))[0] ?? $q['columns'][0])['key'],
];
?>
<div class="phai-flow mb-3">
    <?php foreach ($flow as $section): ?>
        <?php if (count($flow) > 1): ?><div class="phai-flow-title"><?= e($section['title']) ?></div><?php endif ?>
        <div class="phai-flow-row">
            <?php foreach ($section['steps'] as $s): ?>
                <div class="phai-flow-step">
                    <a class="card phai-flow-card" href="<?= e(url($s['link'])) ?>">
                        <div class="card-body">
                            <div class="phai-flow-name"><i class="<?= e($s['icon']) ?>"></i><?= e($s['name']) ?></div>
                            <div class="phai-flow-desc"><?= e($s['desc']) ?></div>
                        </div>
                    </a>
                    <?php foreach ($s['queues'] as $q): ?>
                        <div class="phai-flow-wait" data-flow="<?= e(json_encode($wait($q), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
</div>
