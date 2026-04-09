<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $holidays */
/** @var array|null $editHoliday */
/** @var bool $showForm */
/** @var array|null $flash */

$editId = is_array($editHoliday) ? (int)($editHoliday['id'] ?? 0) : 0;
$activeChecked = $editId ? ((int)($editHoliday['is_active'] ?? 0) === 1) : true;
$recurringChecked = $editId ? ((int)($editHoliday['is_recurring'] ?? 0) === 1) : false;
$workdayChecked = $editId ? ((int)($editHoliday['is_workday'] ?? 0) === 1) : false;

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Sviatky</h2>
  <div class="row" style="gap:8px">
    <a class="btn primary" href="/holidays?add=1#form">Pridať</a>
    <?php if ($showForm): ?>
      <a class="btn" href="/holidays">Zavrieť</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid">
  <div class="card" style="grid-column:span 12">
    <div class="table-wrap">
      <table>
      <tr><th>ID</th><th>Názov</th><th>Dátum</th><th>Forward</th><th>Aktívny</th><th>Akcie</th></tr>
      <?php foreach ($holidays as $h): ?>
        <tr>
          <td class="mono"><?= View::e($h['id']) ?></td>
          <td><?= View::e($h['name']) ?></td>
          <td class="mono"><?= View::e($h['date']) ?></td>
          <td class="mono"><?= View::e($h['forward_to'] ?? '') ?></td>
          <td><?= (int)($h['is_active'] ?? 0) === 1 ? '<span class="badge ok">áno</span>' : '<span class="badge">nie</span>' ?></td>
          <td>
            <div class="row" style="gap:6px;justify-content:flex-end">
              <a class="btn" href="/holidays?edit=<?= View::e($h['id']) ?>#form">Upraviť</a>
              <form method="post" action="/holidays/delete" onsubmit="return confirm('Zmazať?')">
                <input type="hidden" name="id" value="<?= View::e($h['id']) ?>">
                <button class="btn danger" type="submit">Zmazať</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </table>
    </div>
    <div class="help" style="margin-top:10px">
      Stačí <span class="mono">Názov</span> + <span class="mono">Dátum</span>. Voliteľne nastav <span class="mono">Forward</span> pre presmerovanie počas sviatku.
    </div>
  </div>

  <div class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editId ? 'Upraviť sviatok' : 'Pridať sviatok' ?></h2>
    <form method="post" action="/holidays/save" class="form" id="form">
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
        <input name="name" placeholder="Názov *" value="<?= View::e((string)($editHoliday['name'] ?? '')) ?>" required>
      </div>
      <div class="col6">
        <label class="k">Dátum</label>
        <input type="date" name="date" value="<?= View::e(View::dateValue($editHoliday['date'] ?? null)) ?>" required>
      </div>
      <div class="col6">
        <label class="k">Forward počas sviatku (voliteľné)</label>
        <input name="forward_to" placeholder="napr. 366" value="<?= View::e((string)($editHoliday['forward_to'] ?? '')) ?>">
      </div>

      <div class="col12">
        <label class="k">Voľby</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" <?= $activeChecked ? 'checked' : '' ?> style="width:auto"> Aktívny</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_recurring" <?= $recurringChecked ? 'checked' : '' ?> style="width:auto"> Opakuje sa každý rok</label>
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozšírené</summary>
        <div class="form" style="margin-top:10px">
          <div class="col6">
            <label class="k">Krajina (voliteľné)</label>
            <input name="country" placeholder="SK" value="<?= View::e((string)($editHoliday['country'] ?? '')) ?>">
          </div>
          <div class="col6">
            <label class="k">Región (voliteľné)</label>
            <input name="region" placeholder="BA" value="<?= View::e((string)($editHoliday['region'] ?? '')) ?>">
          </div>
          <div class="col6">
            <label class="k">Priorita</label>
            <input name="priority" type="number" value="<?= View::e((string)($editHoliday['priority'] ?? 50)) ?>" min="1" max="100">
          </div>
          <div class="col6">
            <label class="k">Pokročilé</label>
            <label class="row" style="gap:8px"><input type="checkbox" name="is_workday" <?= $workdayChecked ? 'checked' : '' ?> style="width:auto"> Považovať za pracovný deň</label>
          </div>
        </div>
      </details>
      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>
