<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $rows */
/** @var array|null $flash */

$names = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Pracovné hodiny</h2>

<table>
  <tr><th>Day</th><th>Working</th><th>Start</th><th>End</th><th>Forward internal</th><th>Forward external</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="mono"><?= View::e($r['day_of_week']) ?> (<?= View::e($names[(int)$r['day_of_week']] ?? '') ?>)</td>
      <td><?= (int)($r['is_working_day'] ?? 0) === 1 ? 'yes' : 'no' ?></td>
      <td class="mono"><?= View::e($r['start_time']) ?></td>
      <td class="mono"><?= View::e($r['end_time']) ?></td>
      <td class="mono"><?= View::e($r['forward_to_internal'] ?? '') ?></td>
      <td class="mono"><?= View::e($r['forward_to_external'] ?? '') ?></td>
      <td class="muted">edit below</td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="grid" style="margin-top:12px">
  <div class="card" style="grid-column:span 12">
    <h2>Uložiť deň</h2>
    <form method="post" action="/working-hours/save" class="form">
      <div class="col3">
        <select name="day_of_week">
          <?php foreach ($names as $k => $n): ?>
            <option value="<?= View::e((string)$k) ?>"><?= View::e($k . ' - ' . $n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col3">
        <label class="row" style="gap:8px"><input type="checkbox" name="is_working_day" checked style="width:auto"> working day</label>
      </div>
      <div class="col3"><input name="start_time" placeholder="08:00" value="08:00"></div>
      <div class="col3"><input name="end_time" placeholder="16:00" value="16:00"></div>
      <div class="col6"><input name="forward_to_internal" placeholder="forward_to_internal"></div>
      <div class="col6"><input name="forward_to_external" placeholder="forward_to_external"></div>
      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>

