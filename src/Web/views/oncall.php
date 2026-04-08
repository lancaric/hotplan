<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $rotations */
/** @var array $groups */
/** @var array|null $flash */

$patterns = ['weekly','daily','biweekly','custom'];
$dirs = ['forward','backward'];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>On-call rotácie</h2>

<div class="grid">
  <div class="card" style="grid-column:span 7">
    <table>
      <tr><th>ID</th><th>Name</th><th>Group</th><th>Pattern</th><th>Start</th><th>24x7</th><th>Active</th><th></th></tr>
      <?php foreach ($rotations as $r): ?>
        <tr>
          <td class="mono"><?= View::e($r['id']) ?></td>
          <td><?= View::e($r['name']) ?></td>
          <td class="mono"><?= View::e($r['group_id']) ?></td>
          <td class="mono"><?= View::e($r['rotation_pattern']) ?></td>
          <td class="mono"><?= View::e($r['rotation_start_date']) ?></td>
          <td><?= (int)($r['is_24x7'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td><?= (int)($r['is_active'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td>
            <form method="post" action="/oncall/delete" onsubmit="return confirm('Zmazať?')">
              <input type="hidden" name="id" value="<?= View::e($r['id']) ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <div class="help" style="margin-top:10px">
      Tip: priraď zamestnancov do skupiny v tabuľke <span class="mono">Zamestnanci</span> (pole <span class="mono">rotation_group_id</span>).
    </div>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Pridať / upraviť</h2>
    <form method="post" action="/oncall/save" class="form">
      <div class="col12"><div class="help">Na úpravu vyplň ID.</div></div>
      <div class="col3"><input name="id" placeholder="ID (optional)"></div>
      <div class="col9"><input name="name" placeholder="Name *" required></div>
      <div class="col12">
        <select name="group_id" required>
          <option value="">Group *</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= View::e($g['id']) ?>"><?= View::e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col6">
        <select name="rotation_pattern">
          <?php foreach ($patterns as $p): ?>
            <option value="<?= View::e($p) ?>"><?= View::e($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col6">
        <select name="rotation_direction">
          <?php foreach ($dirs as $d): ?>
            <option value="<?= View::e($d) ?>"><?= View::e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col12"><input name="rotation_start_date" placeholder="rotation_start_date (YYYY-MM-DD) *" required></div>
      <div class="col6"><input name="default_start_time" placeholder="default_start_time (HH:MM:SS)"></div>
      <div class="col6"><input name="default_end_time" placeholder="default_end_time (HH:MM:SS)"></div>
      <div class="col6"><input name="during_hours_forward_to" placeholder="during_hours_forward_to"></div>
      <div class="col6"><input name="after_hours_forward_to" placeholder="after_hours_forward_to"></div>
      <div class="col12">
        <label class="row" style="gap:8px"><input type="checkbox" name="is_24x7" style="width:auto"> 24x7</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="use_employee_mobile" checked style="width:auto"> use_employee_mobile</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" checked style="width:auto"> active</label>
      </div>
      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>

