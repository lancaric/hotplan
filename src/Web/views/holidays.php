<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $holidays */
/** @var array|null $editHoliday */
/** @var bool $showForm */
/** @var array|null $flash */

$editId = is_array($editHoliday) ? (int) ($editHoliday['id'] ?? 0) : 0;
$activeChecked = $editId ? ((int) ($editHoliday['is_active'] ?? 0) === 1) : true;
$recurringChecked = $editId ? ((int) ($editHoliday['is_recurring'] ?? 0) === 1) : false;
$workdayChecked = $editId ? ((int) ($editHoliday['is_workday'] ?? 0) === 1) : false;

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Sviatky</h2>
  <div class="row" style="gap:8px">
    <form method="post" action="/holidays/import-sk">
      <button class="btn" type="submit">Import SK sviatkov</button>
    </form>
    <a class="btn primary" href="/holidays?add=1#form">Pridat</a>
    <?php if ($showForm): ?>
      <a class="btn" href="/holidays">Zavriet</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid">
  <div class="card" style="grid-column:span 12">
    <div class="table-wrap">
      <table>
        <tr><th>ID</th><th>Nazov</th><th>Datum</th><th>Forward</th><th>Aktivny</th><th>Akcie</th></tr>
        <?php foreach ($holidays as $holiday): ?>
          <tr>
            <td class="mono"><?= View::e($holiday['id']) ?></td>
            <td><?= View::e($holiday['name']) ?></td>
            <td class="mono"><?= View::e($holiday['date']) ?></td>
            <td class="mono"><?= View::e($holiday['forward_to'] ?? '') ?></td>
            <td><?= (int) ($holiday['is_active'] ?? 0) === 1 ? '<span class="badge ok">ano</span>' : '<span class="badge">nie</span>' ?></td>
            <td>
              <div class="row" style="gap:6px;justify-content:flex-end">
                <a class="btn" href="/holidays?edit=<?= View::e($holiday['id']) ?>#form">Upravit</a>
                <form method="post" action="/holidays/delete" onsubmit="return confirm('Zmazat?')">
                  <input type="hidden" name="id" value="<?= View::e($holiday['id']) ?>">
                  <button class="btn danger" type="submit">Zmazat</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="help" style="margin-top:10px">
      Import SK sviatkov doplni slovenske statne sviatky a dni pracovneho pokoja vratane Velkeho piatku a Velkonocneho pondelka.
    </div>
  </div>

  <div class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editId ? 'Upravit sviatok' : 'Pridat sviatok' ?></h2>
    <form method="post" action="/holidays/save" class="form" id="form">
      <div class="col12">
        <div class="help">
          <?= $editId ? 'Upravujes zaznam #' . View::e((string) $editId) . '.' : 'Vypln udaje a klikni Ulozit.' ?>
        </div>
      </div>
      <?php if ($editId): ?>
        <input type="hidden" name="id" value="<?= View::e((string) $editId) ?>">
      <?php endif; ?>

      <div class="col12">
        <label class="k">Nazov</label>
        <input name="name" placeholder="Nazov *" value="<?= View::e((string) ($editHoliday['name'] ?? '')) ?>" required>
      </div>
      <div class="col6">
        <label class="k">Datum</label>
        <input type="date" name="date" value="<?= View::e(View::dateValue($editHoliday['date'] ?? null)) ?>" required>
      </div>
      <div class="col6">
        <label class="k">Forward pocas sviatku (volitelne)</label>
        <input name="forward_to" placeholder="napr. 366" value="<?= View::e((string) ($editHoliday['forward_to'] ?? '')) ?>">
      </div>

      <div class="col12">
        <label class="k">Volby</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" <?= $activeChecked ? 'checked' : '' ?> style="width:auto"> Aktivny</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_recurring" <?= $recurringChecked ? 'checked' : '' ?> style="width:auto"> Opakuje sa kazdy rok</label>
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozsirene</summary>
        <div class="form" style="margin-top:10px">
          <div class="col6">
            <label class="k">Krajina (volitelne)</label>
            <input name="country" placeholder="SK" value="<?= View::e((string) ($editHoliday['country'] ?? '')) ?>">
          </div>
          <div class="col6">
            <label class="k">Region (volitelne)</label>
            <input name="region" placeholder="BA" value="<?= View::e((string) ($editHoliday['region'] ?? '')) ?>">
          </div>
          <div class="col6">
            <label class="k">Priorita</label>
            <input name="priority" type="number" value="<?= View::e((string) ($editHoliday['priority'] ?? 50)) ?>" min="1" max="100">
          </div>
          <div class="col6">
            <label class="k">Pokrocile</label>
            <label class="row" style="gap:8px"><input type="checkbox" name="is_workday" <?= $workdayChecked ? 'checked' : '' ?> style="width:auto"> Povazovat za pracovny den</label>
          </div>
        </div>
      </details>

      <div class="col12"><button class="btn primary" type="submit">Ulozit</button></div>
    </form>
  </div>
</div>
