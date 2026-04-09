<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $rules */
/** @var array $employees */
/** @var array $groups */
/** @var array $holidays */
/** @var array|null $editRule */
/** @var bool $showForm */
/** @var array|null $flash */

$ruleTypes = ['override','event','oncall_rotation','working_hours','holiday','fallback'];
$targetTypes = ['number','employee','group','voicemail','queue'];
$dow = [
  1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'
];

$editId = is_array($editRule) ? (int)($editRule['id'] ?? 0) : 0;
$activeChecked = $editId ? ((int)($editRule['is_active'] ?? 0) === 1) : true;
$daysSelected = [];
if ($editId && isset($editRule['days_of_week']) && is_string($editRule['days_of_week']) && $editRule['days_of_week'] !== '') {
  $decoded = json_decode($editRule['days_of_week'], true);
  if (is_array($decoded)) {
    $daysSelected = array_map('intval', $decoded);
  }
}

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Pravidlá</h2>
  <div class="row" style="gap:8px">
    <a class="btn primary" href="/rules?add=1#form">Pridať</a>
    <?php if ($showForm): ?>
      <a class="btn" href="/rules">Zavrieť</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid">
  <div class="card" style="grid-column:span 12">
    <div class="table-wrap">
      <table>
      <tr>
        <th>ID</th><th>Názov</th><th>Typ</th><th>Prio</th><th>Aktívne</th><th>Forward</th><th>Okno</th><th>Akcie</th>
      </tr>
      <?php foreach ($rules as $r): ?>
        <tr>
          <td class="mono"><?= View::e($r['id']) ?></td>
          <td><?= View::e($r['name']) ?></td>
          <td class="mono"><?= View::e($r['rule_type']) ?></td>
          <td><?= View::e($r['priority']) ?></td>
          <td><?= (int)($r['is_active'] ?? 0) === 1 ? '<span class="badge ok">áno</span>' : '<span class="badge">nie</span>' ?></td>
          <td class="mono"><?= View::e($r['forward_to']) ?></td>
          <td class="mono">
            <?= View::e(($r['valid_from'] ?? '') . ' → ' . ($r['valid_until'] ?? '')) ?><br>
            <?= View::e(($r['start_time'] ?? '') . ' → ' . ($r['end_time'] ?? '')) ?>
          </td>
          <td>
            <div class="row" style="gap:6px;justify-content:flex-end">
              <a class="btn" href="/rules?edit=<?= View::e($r['id']) ?>#form">Upraviť</a>
              <form method="post" action="/rules/delete" onsubmit="return confirm('Zmazať?')">
                <input type="hidden" name="id" value="<?= View::e($r['id']) ?>">
                <button class="btn danger" type="submit">Zmazať</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editId ? 'Upraviť pravidlo' : 'Pridať pravidlo' ?></h2>
    <form method="post" action="/rules/save" class="form" id="form">
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
        <input name="name" placeholder="Názov *" value="<?= View::e((string)($editRule['name'] ?? '')) ?>" required>
      </div>

      <div class="col6">
        <label class="k">Typ pravidla</label>
        <select name="rule_type">
          <?php foreach ($ruleTypes as $t): ?>
            <?php $sel = (string)($editRule['rule_type'] ?? '') === (string)$t ? 'selected' : ''; ?>
            <option value="<?= View::e($t) ?>" <?= $sel ?>><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col3">
        <label class="k">Priorita</label>
        <input name="priority" type="number" value="<?= View::e((string)($editRule['priority'] ?? 100)) ?>" min="1" max="100">
      </div>
      <div class="col3">
        <label class="k">Stav</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" <?= $activeChecked ? 'checked' : '' ?> style="width:auto"> Aktívne</label>
      </div>

      <div class="col12">
        <label class="k">Presmerovať na</label>
        <input name="forward_to" placeholder="Presmerovať na (číslo)" value="<?= View::e((string)($editRule['forward_to'] ?? '')) ?>">
        <div class="help">Pri type <span class="mono">fallback</span> môže byť prázdne.</div>
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozšírené</summary>
        <div class="form" style="margin-top:10px">
          <div class="col6">
            <label class="k">Platí od (voliteľné)</label>
            <input type="datetime-local" name="valid_from" value="<?= View::e(View::dateTimeLocalValue($editRule['valid_from'] ?? null)) ?>">
          </div>
          <div class="col6">
            <label class="k">Platí do (voliteľné)</label>
            <input type="datetime-local" name="valid_until" value="<?= View::e(View::dateTimeLocalValue($editRule['valid_until'] ?? null)) ?>">
          </div>
          <div class="col6">
            <label class="k">Čas od (voliteľné)</label>
            <input type="time" name="start_time" step="60" value="<?= View::e(View::timeValue($editRule['start_time'] ?? null)) ?>">
          </div>
          <div class="col6">
            <label class="k">Čas do (voliteľné)</label>
            <input type="time" name="end_time" step="60" value="<?= View::e(View::timeValue($editRule['end_time'] ?? null)) ?>">
          </div>

          <div class="col12">
            <div class="help">Dni v týždni:</div>
            <div class="row">
              <?php foreach ($dow as $k => $label): ?>
                <label class="row" style="gap:6px">
                  <input type="checkbox" name="days_of_week[]" value="<?= View::e((string)$k) ?>" <?= in_array((int)$k, $daysSelected, true) ? 'checked' : '' ?> style="width:auto">
                  <span class="mono"><?= View::e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="col6">
            <label class="k">Target type</label>
            <select name="target_type">
              <?php foreach ($targetTypes as $t): ?>
                <?php $sel = (string)($editRule['target_type'] ?? '') === (string)$t ? 'selected' : ''; ?>
                <option value="<?= View::e($t) ?>" <?= $sel ?>><?= View::e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col6">
            <label class="k">Zamestnanec (voliteľné)</label>
            <select name="target_employee_id">
              <option value="">Cieľový zamestnanec (voliteľné)</option>
              <?php foreach ($employees as $e): ?>
                <?php $sel = (string)($editRule['target_employee_id'] ?? '') === (string)$e['id'] ? 'selected' : ''; ?>
                <option value="<?= View::e($e['id']) ?>" <?= $sel ?>><?= View::e($e['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col6">
            <label class="k">Skupina (voliteľné)</label>
            <select name="target_group_id">
              <option value="">Cieľová skupina (voliteľné)</option>
              <?php foreach ($groups as $g): ?>
                <?php $sel = (string)($editRule['target_group_id'] ?? '') === (string)$g['id'] ? 'selected' : ''; ?>
                <option value="<?= View::e($g['id']) ?>" <?= $sel ?>><?= View::e($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col6">
            <label class="k">Sviatok (voliteľné)</label>
            <select name="holiday_id">
              <option value="">Sviatok (voliteľné)</option>
              <?php foreach ($holidays as $h): ?>
                <?php $sel = (string)($editRule['holiday_id'] ?? '') === (string)$h['id'] ? 'selected' : ''; ?>
                <option value="<?= View::e($h['id']) ?>" <?= $sel ?>><?= View::e($h['name'] . ' ' . $h['date']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col12">
            <label class="k">Popis (voliteľné)</label>
            <textarea name="description" placeholder="Popis (voliteľné)"><?= View::e((string)($editRule['description'] ?? '')) ?></textarea>
          </div>
        </div>
      </details>

      <div class="col12">
        <button class="btn primary" type="submit">Uložiť</button>
      </div>
    </form>
  </div>
</div>
