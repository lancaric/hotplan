<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $rotations */
/** @var array $groups */
/** @var array|null $editRotation */
/** @var bool $showForm */
/** @var array|null $flash */

$patterns = ['weekly','daily','biweekly','custom'];
$dirs = ['forward','backward'];

$editId = is_array($editRotation) ? (int)($editRotation['id'] ?? 0) : 0;
$activeChecked = $editId ? ((int)($editRotation['is_active'] ?? 0) === 1) : true;
$is24Checked = $editId ? ((int)($editRotation['is_24x7'] ?? 0) === 1) : false;
$useMobileChecked = $editId ? ((int)($editRotation['use_employee_mobile'] ?? 0) === 1) : true;

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">On-call rotácie</h2>
  <div class="row" style="gap:8px">
    <a class="btn primary" href="/oncall?add=1#form">Pridať</a>
    <?php if ($showForm): ?>
      <a class="btn" href="/oncall">Zavrieť</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid">
  <div class="card" style="grid-column:span 12">
    <div class="table-wrap">
      <table>
      <tr><th>ID</th><th>Názov</th><th>Skupina</th><th>Vzorec</th><th>Štart</th><th>Aktívna</th><th>Akcie</th></tr>
      <?php foreach ($rotations as $r): ?>
        <tr>
          <td class="mono"><?= View::e($r['id']) ?></td>
          <td><?= View::e($r['name']) ?></td>
          <td class="mono"><?= View::e($r['group_id']) ?></td>
          <td class="mono"><?= View::e($r['rotation_pattern']) ?></td>
          <td class="mono"><?= View::e($r['rotation_start_date']) ?></td>
          <td><?= (int)($r['is_active'] ?? 0) === 1 ? '<span class="badge ok">áno</span>' : '<span class="badge">nie</span>' ?></td>
          <td>
            <div class="row" style="gap:6px;justify-content:flex-end">
              <a class="btn" href="/oncall?edit=<?= View::e($r['id']) ?>#form">Upraviť</a>
              <form method="post" action="/oncall/delete" onsubmit="return confirm('Zmazať?')">
                <input type="hidden" name="id" value="<?= View::e($r['id']) ?>">
                <button class="btn danger" type="submit">Zmazať</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </table>
    </div>
    <div class="help" style="margin-top:10px">
      Minimum aby to fungovalo: rotácia + skupina + zamestnanci v skupine.
      Zamestnancov priraď v <span class="mono">Zamestnanci</span> (pole <span class="mono">Skupina</span>).
    </div>
  </div>

  <div class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editId ? 'Upraviť rotáciu' : 'Pridať rotáciu' ?></h2>
    <form method="post" action="/oncall/save" class="form" id="form">
      <div class="col12">
        <div class="help">
          <?= $editId ? 'Upravuješ záznam #' . View::e((string)$editId) . '.' : 'Vyplň údaje a klikni Uložiť.' ?>
        </div>
      </div>
      <?php if ($editId): ?>
        <input type="hidden" name="id" value="<?= View::e((string) $editId) ?>">
      <?php endif; ?>

      <div class="col12">
        <label class="k">Názov</label>
        <input name="name" placeholder="Názov *" value="<?= View::e((string)($editRotation['name'] ?? '')) ?>" required>
      </div>
      <div class="col12">
        <label class="k">Skupina</label>
        <select name="group_id" required>
          <option value="">Skupina *</option>
          <?php foreach ($groups as $g): ?>
            <?php $sel = (string)($editRotation['group_id'] ?? '') === (string)$g['id'] ? 'selected' : ''; ?>
            <option value="<?= View::e($g['id']) ?>" <?= $sel ?>><?= View::e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col12">
        <label class="k">Stav</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" <?= $activeChecked ? 'checked' : '' ?> style="width:auto"> Aktívna</label>
      </div>

      <div class="col12">
        <label class="k">Začiatok rotácie</label>
        <input type="date" name="rotation_start_date" value="<?= View::e(View::dateValue($editRotation['rotation_start_date'] ?? null)) ?>" required>
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozšírené</summary>
        <div class="form" style="margin-top:10px">
          <div class="col6">
            <label class="k">Vzorec</label>
            <select name="rotation_pattern">
              <?php foreach ($patterns as $p): ?>
                <?php $sel = (string)($editRotation['rotation_pattern'] ?? 'weekly') === (string)$p ? 'selected' : ''; ?>
                <option value="<?= View::e($p) ?>" <?= $sel ?>><?= View::e($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col6">
            <label class="k">Smer</label>
            <select name="rotation_direction">
              <?php foreach ($dirs as $d): ?>
                <?php $sel = (string)($editRotation['rotation_direction'] ?? 'forward') === (string)$d ? 'selected' : ''; ?>
                <option value="<?= View::e($d) ?>" <?= $sel ?>><?= View::e($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col6">
            <label class="k">Aktívne od (čas)</label>
            <input type="time" name="default_start_time" step="60" value="<?= View::e(View::timeValue($editRotation['default_start_time'] ?? null)) ?>">
          </div>
          <div class="col6">
            <label class="k">Aktívne do (čas)</label>
            <input type="time" name="default_end_time" step="60" value="<?= View::e(View::timeValue($editRotation['default_end_time'] ?? null)) ?>">
          </div>
          <div class="col6">
            <label class="k">Forward počas pracovnej doby (voliteľné)</label>
            <input name="during_hours_forward_to" placeholder="zvyčajne prázdne" value="<?= View::e((string)($editRotation['during_hours_forward_to'] ?? '')) ?>">
          </div>
          <div class="col6">
            <label class="k">Forward mimo pracovnej doby (voliteľné)</label>
            <input name="after_hours_forward_to" placeholder="zvyčajne prázdne" value="<?= View::e((string)($editRotation['after_hours_forward_to'] ?? '')) ?>">
          </div>
          <div class="col12">
            <label class="k">Voľby</label>
            <label class="row" style="gap:8px"><input type="checkbox" name="is_24x7" <?= $is24Checked ? 'checked' : '' ?> style="width:auto"> 24×7</label>
            <label class="row" style="gap:8px"><input type="checkbox" name="use_employee_mobile" <?= $useMobileChecked ? 'checked' : '' ?> style="width:auto"> Použiť číslo pre službu zo zamestnanca</label>
          </div>
        </div>
      </details>

      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>
