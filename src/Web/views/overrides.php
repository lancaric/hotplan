<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $active */
/** @var array|null $flash */

$types = ['temporary','indefinite','until_time','until_employee'];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Override</h2>
  <form method="post" action="/overrides/clear" onsubmit="return confirm('Clear all overrides?')">
    <button class="btn danger" type="submit">Clear all</button>
  </form>
</div>

<div class="grid" style="margin-top:10px">
  <div class="card" style="grid-column:span 7">
    <h2>Aktívne</h2>
    <table>
      <tr><th>ID</th><th>Type</th><th>Forward</th><th>Starts</th><th>Ends</th><th>Reason</th></tr>
      <?php foreach ($active as $o): ?>
        <tr>
          <td class="mono"><?= View::e($o['id']) ?></td>
          <td class="mono"><?= View::e($o['override_type']) ?></td>
          <td class="mono"><?= View::e($o['forward_to']) ?></td>
          <td class="mono"><?= View::e($o['starts_at'] ?? '') ?></td>
          <td class="mono"><?= View::e($o['ends_at'] ?? $o['expires_at'] ?? '') ?></td>
          <td><?= View::e($o['reason'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Vytvoriť override</h2>
    <form method="post" action="/overrides/create" class="form">
      <div class="col12">
        <select name="override_type">
          <?php foreach ($types as $t): ?>
            <option value="<?= View::e($t) ?>"><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col12"><input name="forward_to" placeholder="forward_to *" required></div>
      <div class="col6"><input name="starts_at" placeholder="starts_at (YYYY-MM-DD HH:MM:SS)"></div>
      <div class="col6"><input name="ends_at" placeholder="ends_at (YYYY-MM-DD HH:MM:SS)"></div>
      <div class="col12"><input name="expires_at" placeholder="expires_at (optional)"></div>
      <div class="col12"><input name="reason" placeholder="reason"></div>
      <div class="col12"><button class="btn primary" type="submit">Vytvoriť</button></div>
    </form>
  </div>
</div>

