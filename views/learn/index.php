<?php
/**
 * "เรียนรู้ ERP" — the big picture for learners. The text is per language in views/learn/<lang>.php (like the help pages);
 * each chain row links to the screen that makes the document.
 */
use Phai\Core\Lang;

$file = PHAI_ROOT . '/views/learn/' . Lang::locale() . '.php';
$learn = require (is_file($file) ? $file : PHAI_ROOT . '/views/learn/th.php');
?>
<div class="phai-learn">
    <section class="card mb-3">
        <div class="card-body">
            <h2 class="card-title"><?= e($learn['erp']['title']) ?></h2>
            <?php foreach ($learn['erp']['text'] as $p): ?><p class="mb-2"><?= e($p) ?></p><?php endforeach ?>
            <div class="phai-learn-map">
                <?php foreach ($learn['modules'] as $m): ?>
                    <a class="phai-learn-module" href="<?= e(url($m['link'])) ?>">
                        <i class="ti <?= e($m['icon']) ?>"></i>
                        <strong><?= e($m['name']) ?></strong>
                        <span class="text-secondary"><?= e($m['term']) ?></span>
                        <small><?= e($m['does']) ?></small>
                    </a>
                <?php endforeach ?>
            </div>
            <h3 class="mt-3"><?= e($learn['links_title']) ?></h3>
            <ul class="mb-0">
                <?php foreach ($learn['links'] as $l): ?><li><?= e($l) ?></li><?php endforeach ?>
            </ul>
        </div>
    </section>

    <?php foreach ($learn['chains'] as $chain): ?>
        <section class="card mb-3">
            <div class="card-body">
                <h2 class="card-title"><?= e($chain['title']) ?></h2>
                <p class="mb-2"><?= e($chain['intro']) ?></p>
                <div class="phai-learn-flow">
                    <?php foreach ($chain['steps'] as $i => $s): ?>
                        <?php if ($i): ?><i class="ti ti-arrow-right text-secondary"></i><?php endif ?>
                        <span class="phai-learn-step"><?= e($s['doc']) ?></span>
                    <?php endforeach ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter mb-0">
                        <thead>
                        <tr><th>#</th><th><?= e($learn['cols']['doc']) ?></th><th><?= e($learn['cols']['official']) ?></th>
                            <th><?= e($learn['cols']['what']) ?></th><th><?= e($learn['cols']['gl']) ?></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($chain['steps'] as $i => $s): ?>
                            <tr>
                                <td class="text-secondary"><?= $i + 1 ?></td>
                                <td class="text-nowrap"><?php if (!empty($s['link'])): ?><a href="<?= e(url($s['link'])) ?>"><?= e($s['doc']) ?></a><?php else: ?><?= e($s['doc']) ?><?php endif ?></td>
                                <td><?= e($s['official']) ?></td>
                                <td><?= e($s['what']) ?></td>
                                <td><?= e($s['gl']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endforeach ?>

    <section class="card mb-3">
        <div class="card-body">
            <h2 class="card-title"><?= e($learn['glossary_title']) ?></h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($learn['glossary'] as [$term, $meaning]): ?>
                        <tr><td class="text-nowrap"><?= e($term) ?></td><td><?= e($meaning) ?></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
