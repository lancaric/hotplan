<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var string $next */
/** @var array|null $flash */

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="grid">
  <div class="card" style="grid-column:span 12;max-width:520px;margin:0 auto">
    <h2>Admin login</h2>
    <div class="help" style="margin-bottom:10px">
      Default: <span class="mono">admin / admin</span> (odporúčané hneď zmeniť v <span class="mono">config/config.yaml</span> alebo cez env premenné).
    </div>
    <form method="post" action="/login" class="form">
      <input type="hidden" name="next" value="<?= View::e($next) ?>">
      <div class="col12"><input name="username" placeholder="Username" autocomplete="username" required></div>
      <div class="col12"><input name="password" type="password" placeholder="Password" autocomplete="current-password" required></div>
      <div class="col12"><button class="btn primary" type="submit">Prihlásiť</button></div>
    </form>
  </div>
</div>

