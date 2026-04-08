<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $holidays */
/** @var array|null $flash */

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Sviatky</h2>

<div class="grid">
  <div class="card" style="grid-column:span 7">
    <table>
      <tr><th>ID</th><th>Name</th><th>Date</th><th>Recurring</th><th>Workday</th><th>Forward</th><th>Prio</th><th>Active</th><th></th></tr>
      <?php foreach ($holidays as $h): ?>
        <tr>
          <td class="mono"><?= View::e($h['id']) ?></td>
          <td><?= View::e($h['name']) ?></td>
          <td class="mono"><?= View::e($h['date']) ?></td>
          <td><?= (int)($h['is_recurring'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td><?= (int)($h['is_workday'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td class="mono"><?= View::e($h['forward_to'] ?? '') ?></td>
          <td><?= View::e($h['priority'] ?? '') ?></td>
          <td><?= (int)($h['is_active'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td>
            <form method="post" action="/holidays/delete" onsubmit="return confirm('Zmazať?')">
              <input type="hidden" name="id" value="<?= View::e($h['id']) ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Pridať / upraviť</h2>
    <form method="post" action="/holidays/save" class="form">
      <div class="col12"><div class="help">Na úpravu vyplň ID.</div></div>
      <div class="col3"><input name="id" placeholder="ID (optional)"></div>
      <div class="col9"><input name="name" placeholder="Name *" required></div>
      <div class="col12"><input name="date" placeholder="YYYY-MM-DD *" required></div>
      <div class="col6"><input name="country" placeholder="country"></div>
      <div class="col6"><input name="region" placeholder="region"></div>
      <div class="col6"><input name="forward_to" placeholder="forward_to"></div>
      <div class="col3"><input name="priority" type="number" value="50" min="1" max="100"></div>
      <div class="col3">
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" checked style="width:auto"> active</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_recurring" style="width:auto"> recurring</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_workday" style="width:auto"> workday</label>
      </div>
      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>

