<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $employees */
/** @var array $groups */
/** @var array|null $flash */

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Zamestnanci</h2>

<div class="grid">
  <div class="card" style="grid-column:span 7">
    <table>
      <tr>
        <th>ID</th><th>Meno</th><th>Email</th><th>Interné</th><th>Mobil</th><th>Skupina</th><th>Prio</th><th>Active</th><th>Oncall</th><th></th>
      </tr>
      <?php foreach ($employees as $e): ?>
        <tr>
          <td class="mono"><?= View::e($e['id']) ?></td>
          <td><?= View::e($e['name']) ?></td>
          <td class="mono"><?= View::e($e['email'] ?? '') ?></td>
          <td class="mono"><?= View::e($e['phone_internal'] ?? '') ?></td>
          <td class="mono"><?= View::e($e['phone_mobile'] ?? '') ?></td>
          <td class="mono"><?= View::e($e['rotation_group_id'] ?? '') ?></td>
          <td><?= View::e($e['priority'] ?? '') ?></td>
          <td><?= (int)($e['is_active'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td><?= (int)($e['is_oncall'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td>
            <form method="post" action="/employees/delete" onsubmit="return confirm('Zmazať?')">
              <input type="hidden" name="id" value="<?= View::e($e['id']) ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Pridať / upraviť</h2>
    <form method="post" action="/employees/save" class="form">
      <div class="col12">
        <div class="help">Ak chceš upraviť, vyplň aj ID.</div>
      </div>
      <div class="col3"><input name="id" placeholder="ID (optional)"></div>
      <div class="col9"><input name="name" placeholder="Meno *" required></div>
      <div class="col12"><input name="email" placeholder="Email"></div>
      <div class="col4"><input name="phone_internal" placeholder="Interné"></div>
      <div class="col4"><input name="phone_primary" placeholder="Primary"></div>
      <div class="col4"><input name="phone_mobile" placeholder="Mobil"></div>
      <div class="col6">
        <select name="rotation_group_id">
          <option value="">Rotation group (none)</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= View::e($g['id']) ?>"><?= View::e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col3"><input name="priority" type="number" value="100" min="1" max="999" step="1"></div>
      <div class="col3">
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" checked style="width:auto"> active</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_oncall" style="width:auto"> oncall</label>
      </div>
      <div class="col12">
        <button class="btn primary" type="submit">Uložiť</button>
      </div>
    </form>
  </div>
</div>

