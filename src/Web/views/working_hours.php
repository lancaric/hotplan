<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $rows */
/** @var array|null $editRow */
/** @var bool $showForm */
/** @var array|null $flash */

$names = [
  0 => 'Nedeľa',
  1 => 'Pondelok',
  2 => 'Utorok',
  3 => 'Streda',
  4 => 'Štvrtok',
  5 => 'Piatok',
  6 => 'Sobota',
];

$form = $editRow ?? [
  'id' => '',
  'day_of_week' => 1,
  'is_working_day' => 1,
  'start_time' => '08:00',
  'end_time' => '16:00',
  'forward_to_internal' => '',
  'forward_to_external' => '',
];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<h2>Pracovné hodiny</h2>

<div class="row" style="justify-content:flex-end">
  <a class="btn primary" href="/working-hours?add=1#form">Pridať / upraviť</a>
</div>

<div class="table-wrap" style="margin-top:10px">
  <table>
    <tr>
      <th>Deň</th>
      <th>Pracovný</th>
      <th>Začiatok</th>
      <th>Koniec</th>
      <th>CFWD interné</th>
      <th>CFWD externé</th>
      <th>Akcie</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono"><?= View::e($r['day_of_week']) ?> (<?= View::e($names[(int) $r['day_of_week']] ?? '') ?>)</td>
        <td><?= (int) ($r['is_working_day'] ?? 0) === 1 ? '<span class="badge ok">áno</span>' : '<span class="badge">nie</span>' ?></td>
        <td class="mono"><?= View::e(View::timeValue((string) ($r['start_time'] ?? ''))) ?></td>
        <td class="mono"><?= View::e(View::timeValue((string) ($r['end_time'] ?? ''))) ?></td>
        <td class="mono"><?= View::e($r['forward_to_internal'] ?? '') ?></td>
        <td class="mono"><?= View::e($r['forward_to_external'] ?? '') ?></td>
        <td>
          <a class="btn" href="/working-hours?edit=<?= View::e((string) ($r['id'] ?? '')) ?>#form">Upraviť</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="grid" style="margin-top:12px">
  <div id="form" class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editRow ? 'Upraviť deň' : 'Pridať / upraviť deň' ?></h2>
    <form method="post" action="/working-hours/save" class="form">
      <?php if (!empty($form['id'])): ?>
        <input type="hidden" name="id" value="<?= View::e((string) $form['id']) ?>">
      <?php endif; ?>
      <div class="col3">
        <label class="k">Deň</label>
        <select name="day_of_week">
          <?php foreach ($names as $k => $n): ?>
            <option value="<?= View::e((string) $k) ?>" <?= (int) ($form['day_of_week'] ?? -1) === (int) $k ? 'selected' : '' ?>>
              <?= View::e($k . ' - ' . $n) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col3">
        <label class="k">Typ dňa</label>
        <label class="row" style="gap:8px">
          <input type="checkbox" name="is_working_day" <?= (int) ($form['is_working_day'] ?? 0) === 1 ? 'checked' : '' ?> style="width:auto">
          Pracovný deň
        </label>
      </div>
      <div class="col3">
        <label class="k">Začiatok</label>
        <input type="time" name="start_time" step="60" value="<?= View::e(View::timeValue((string) ($form['start_time'] ?? ''))) ?>">
      </div>
      <div class="col3">
        <label class="k">Koniec</label>
        <input type="time" name="end_time" step="60" value="<?= View::e(View::timeValue((string) ($form['end_time'] ?? ''))) ?>">
      </div>
      <div class="col6">
        <label class="k">CFWD počas pracovnej doby</label>
        <input name="forward_to_internal" value="<?= View::e((string) ($form['forward_to_internal'] ?? '')) ?>" placeholder="napr. 366">
      </div>
      <div class="col6">
        <label class="k">Externé číslo (voliteľné)</label>
        <input name="forward_to_external" value="<?= View::e((string) ($form['forward_to_external'] ?? '')) ?>" placeholder="napr. +421...">
      </div>
      <div class="col12 row" style="justify-content:space-between">
        <a class="btn" href="/working-hours">Zavrieť</a>
        <button class="btn primary" type="submit">Uložiť</button>
      </div>
    </form>
  </div>
</div>
