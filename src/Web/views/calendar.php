<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var string $ym */
/** @var DateTimeImmutable $first */
/** @var int $startDow */
/** @var int $daysInMonth */
/** @var array $days */
/** @var string $prev */
/** @var string $next */
/** @var array|null $flash */

$today = (new DateTimeImmutable())->format('Y-m-d');
$dows = ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between;align-items:end">
  <div>
    <h2 style="margin:0">Kalendár služieb</h2>
    <div class="muted"><?= View::e($first->format('F Y')) ?></div>
  </div>
  <div class="row">
    <a class="btn" href="/calendar?ym=<?= View::e($prev) ?>">← <?= View::e($prev) ?></a>
    <a class="btn" href="/calendar?ym=<?= View::e(date('Y-m')) ?>">dnes</a>
    <a class="btn" href="/calendar?ym=<?= View::e($next) ?>"><?= View::e($next) ?> →</a>
  </div>
</div>

<div class="cal" style="margin-top:10px">
  <?php foreach ($dows as $d): ?>
    <div class="dow"><?= View::e($d) ?></div>
  <?php endforeach; ?>

  <?php for ($i = 1; $i < $startDow; $i++): ?>
    <div class="day" style="opacity:.35"></div>
  <?php endfor; ?>

  <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
    <?php
      $date = $first->setDate((int)$first->format('Y'), (int)$first->format('m'), $d);
      $key = $date->format('Y-m-d');
      $info = $days[$key] ?? null;
      $isToday = $key === $today;
    ?>
    <div class="day <?= $isToday ? 'today' : '' ?>">
      <div class="n">
        <span><?= View::e($d) ?></span>
        <?php if ($info && $info['holiday']): ?>
          <span class="badge" title="Sviatok"><?= View::e($info['holiday']) ?></span>
        <?php endif; ?>
      </div>
      <div class="items">
        <?php if ($info): ?>
          <?php foreach ($info['rotations'] as $r): ?>
            <div>
              <span class="k"><?= View::e($r['rotation']) ?>:</span>
              <?= View::e($r['employee'] ?? '-') ?>
            </div>
          <?php endforeach; ?>
          <?php if (!empty($info['segments']) && is_array($info['segments'])): ?>
            <div style="margin-top:6px;border-top:1px dashed var(--border);padding-top:6px">
              <?php foreach ($info['segments'] as $s): ?>
                <div>
                  <span class="k"><?= View::e(($s['from'] ?? '') . '–' . ($s['to'] ?? '')) ?>:</span>
                  <span class="mono"><?= View::e($s['forward_to'] ?? '') ?></span>
                  <span class="muted">
                    <?= View::e($s['rule'] ? (' · ' . $s['rule']) : (' · ' . ($s['reason'] ?? ''))) ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="muted">-</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endfor; ?>
</div>

<div class="help" style="margin-top:10px">
  Zobrazuje aktívne on-call rotácie (tab <span class="mono">On-call</span>) a zároveň aj CFWD rozhodnutia v typických oknách (00–08, 08–16, 16–22, 22–24).
</div>
