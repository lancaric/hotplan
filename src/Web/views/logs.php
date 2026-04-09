<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $logs */
/** @var int $lines */
/** @var array|null $flash */

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Logy</h2>
  <form method="get" action="/logs" class="row">
    <input name="lines" type="number" min="20" max="2000" value="<?= View::e((string)$lines) ?>" style="width:120px">
    <button class="btn" type="submit">Načítať</button>
  </form>
</div>

<textarea class="mono" style="white-space:pre-wrap;line-height:1.35;margin-top:10px" rows="10" readonly>
<?= View::e(implode("\n", $logs)) ?>
</textarea>

