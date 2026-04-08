<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $groups */
/** @var array|null $flash */

$types = ['weekly','daily','custom'];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Skupiny (rotation_groups)</h2>

<div class="grid">
  <div class="card" style="grid-column:span 7">
    <table>
      <tr><th>ID</th><th>Name</th><th>Type</th><th>Index</th><th>Start</th><th></th></tr>
      <?php foreach ($groups as $g): ?>
        <tr>
          <td class="mono"><?= View::e($g['id']) ?></td>
          <td><?= View::e($g['name']) ?></td>
          <td class="mono"><?= View::e($g['rotation_type'] ?? '') ?></td>
          <td><?= View::e($g['current_index'] ?? '') ?></td>
          <td class="mono"><?= View::e($g['rotation_start_date'] ?? '') ?></td>
          <td>
            <form method="post" action="/groups/delete" onsubmit="return confirm('Zmazať?')">
              <input type="hidden" name="id" value="<?= View::e($g['id']) ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Pridať / upraviť</h2>
    <form method="post" action="/groups/save" class="form">
      <div class="col12"><div class="help">Na úpravu vyplň ID.</div></div>
      <div class="col3"><input name="id" placeholder="ID (optional)"></div>
      <div class="col9"><input name="name" placeholder="Name *" required></div>
      <div class="col12">
        <select name="rotation_type">
          <?php foreach ($types as $t): ?>
            <option value="<?= View::e($t) ?>"><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col6"><input name="current_index" type="number" value="0" min="0" step="1"></div>
      <div class="col6"><input name="rotation_start_date" placeholder="rotation_start_date (YYYY-MM-DD)"></div>
      <div class="col12"><input name="description" placeholder="description"></div>
      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>

