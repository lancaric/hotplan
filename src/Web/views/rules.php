<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $rules */
/** @var array $employees */
/** @var array $groups */
/** @var array $holidays */
/** @var array|null $flash */

$ruleTypes = ['override','event','oncall_rotation','working_hours','holiday','fallback'];
$targetTypes = ['number','employee','group','voicemail','queue'];
$dow = [
  1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'
];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Pravidlá</h2>

<div class="grid">
  <div class="card" style="grid-column:span 7">
    <table>
      <tr>
        <th>ID</th><th>Name</th><th>Type</th><th>Prio</th><th>Active</th><th>Forward to</th><th>Window</th><th></th>
      </tr>
      <?php foreach ($rules as $r): ?>
        <tr>
          <td class="mono"><?= View::e($r['id']) ?></td>
          <td><?= View::e($r['name']) ?></td>
          <td class="mono"><?= View::e($r['rule_type']) ?></td>
          <td><?= View::e($r['priority']) ?></td>
          <td><?= (int)($r['is_active'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
          <td class="mono"><?= View::e($r['forward_to']) ?></td>
          <td class="mono">
            <?= View::e(($r['valid_from'] ?? '') . ' → ' . ($r['valid_until'] ?? '')) ?><br>
            <?= View::e(($r['start_time'] ?? '') . ' → ' . ($r['end_time'] ?? '')) ?>
          </td>
          <td>
            <form method="post" action="/rules/delete" onsubmit="return confirm('Zmazať?')">
              <input type="hidden" name="id" value="<?= View::e($r['id']) ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Pridať / upraviť</h2>
    <form method="post" action="/rules/save" class="form">
      <div class="col12"><div class="help">Na úpravu vyplň ID.</div></div>
      <div class="col3"><input name="id" placeholder="ID (optional)"></div>
      <div class="col9"><input name="name" placeholder="Name *" required></div>

      <div class="col6">
        <select name="rule_type">
          <?php foreach ($ruleTypes as $t): ?>
            <option value="<?= View::e($t) ?>"><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col3"><input name="priority" type="number" value="100" min="1" max="100"></div>
      <div class="col3">
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" checked style="width:auto"> active</label>
      </div>

      <div class="col12"><input name="forward_to" placeholder="forward_to (number)"></div>

      <div class="col6"><input name="valid_from" placeholder="valid_from (YYYY-MM-DD HH:MM:SS)"></div>
      <div class="col6"><input name="valid_until" placeholder="valid_until (YYYY-MM-DD HH:MM:SS)"></div>

      <div class="col6"><input name="start_time" placeholder="start_time (HH:MM:SS)"></div>
      <div class="col6"><input name="end_time" placeholder="end_time (HH:MM:SS)"></div>

      <div class="col12">
        <div class="help">Days of week:</div>
        <div class="row">
          <?php foreach ($dow as $k => $label): ?>
            <label class="row" style="gap:6px">
              <input type="checkbox" name="days_of_week[]" value="<?= View::e((string)$k) ?>" style="width:auto">
              <span class="mono"><?= View::e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col6">
        <select name="target_type">
          <?php foreach ($targetTypes as $t): ?>
            <option value="<?= View::e($t) ?>"><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col6">
        <select name="target_employee_id">
          <option value="">target_employee (optional)</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= View::e($e['id']) ?>"><?= View::e($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col6">
        <select name="target_group_id">
          <option value="">target_group (optional)</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= View::e($g['id']) ?>"><?= View::e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col6">
        <select name="holiday_id">
          <option value="">holiday (optional)</option>
          <?php foreach ($holidays as $h): ?>
            <option value="<?= View::e($h['id']) ?>"><?= View::e($h['name'] . ' ' . $h['date']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col12"><textarea name="description" placeholder="description"></textarea></div>

      <div class="col12">
        <button class="btn primary" type="submit">Uložiť</button>
      </div>
    </form>
  </div>
</div>

