<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var string $path */
/** @var string $content */
/** @var array|null $flash */

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Konfigurácia</h2>
<div class="help">Upravuje priamo súbor <span class="mono"><?= View::e($path) ?></span>.</div>

<form method="post" action="/config/save" class="form" style="margin-top:10px">
  <div class="col12">
    <textarea name="content" class="mono"><?= View::e($content) ?></textarea>
  </div>
  <div class="col12">
    <button class="btn primary" type="submit">Uložiť</button>
  </div>
</form>

