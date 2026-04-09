<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $employees */
/** @var array $groups */
/** @var array|null $editEmployee */
/** @var bool $showForm */
/** @var array|null $flash */

$editId = is_array($editEmployee) ? (int)($editEmployee['id'] ?? 0) : 0;
$activeChecked = $editId ? ((int)($editEmployee['is_active'] ?? 0) === 1) : true;
$oncallChecked = $editId ? ((int)($editEmployee['is_oncall'] ?? 0) === 1) : false;
$priorityValue = (int) ($editEmployee['priority'] ?? 100);

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Zamestnanci</h2>
  <div class="row" style="gap:8px">
    <a class="btn primary" href="/employees?add=1#form">Pridať</a>
    <?php if ($showForm): ?>
      <a class="btn" href="/employees">Zavrieť</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid">
  <div class="card" style="grid-column:span 12">
    <div class="table-wrap">
      <table>
      <tr>
        <th>ID</th>
        <th>Meno</th>
        <th>Číslo pre službu</th>
        <th>Skupina</th>
        <th>Poradie</th>
        <th>Aktívny</th>
        <th>Akcie</th>
      </tr>
      <?php foreach ($employees as $e): ?>
        <tr>
          <td class="mono"><?= View::e($e['id']) ?></td>
          <td><?= View::e($e['name']) ?></td>
          <td class="mono"><?= View::e($e['phone_mobile'] ?? '') ?></td>
          <td class="mono"><?= View::e($e['rotation_group_id'] ?? '') ?></td>
          <td class="mono"><?= View::e($e['priority'] ?? '') ?></td>
          <td><?= (int)($e['is_active'] ?? 0) === 1 ? '<span class="badge ok">áno</span>' : '<span class="badge">nie</span>' ?></td>
          <td>
            <div class="row" style="gap:6px;justify-content:flex-end">
              <a class="btn" href="/employees?edit=<?= View::e($e['id']) ?>#form">Upraviť</a>
              <form method="post" action="/employees/delete" onsubmit="return confirm('Zmazať?')">
                <input type="hidden" name="id" value="<?= View::e($e['id']) ?>">
                <button class="btn danger" type="submit">Zmazať</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </table>
    </div>
    <div class="help" style="margin-top:10px">
      Minimum aby to fungovalo: <span class="mono">Meno</span>, <span class="mono">Číslo pre službu</span> (napr. klapka/mobil) a priradená <span class="mono">Skupina</span>.
      <br>Rotácia v skupine ide podľa <span class="mono">Poradie</span> (nižšie číslo = skôr v rotácii).
    </div>
  </div>

  <div class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editId ? 'Upraviť zamestnanca' : 'Pridať zamestnanca' ?></h2>
    <form method="post" action="/employees/save" class="form" id="form">
      <div class="col12">
        <div class="help">
          <?= $editId ? 'Upravuješ záznam #' . View::e((string)$editId) . '.' : 'Vyplň údaje a klikni Uložiť.' ?>
        </div>
      </div>
      <?php if ($editId): ?>
        <input type="hidden" name="id" value="<?= View::e((string) $editId) ?>">
      <?php endif; ?>

      <div class="col8">
        <label class="k">Meno</label>
        <input name="name" placeholder="Meno *" value="<?= View::e((string)($editEmployee['name'] ?? '')) ?>" required>
      </div>
      <div class="col4">
        <label class="k">Poradie v rotácii</label>
        <input name="priority" type="number" value="<?= View::e((string) $priorityValue) ?>" min="1" max="999" step="1" placeholder="nižšie = skôr">
      </div>

      <div class="col6">
        <label class="k">Číslo pre službu (po pracovnej dobe)</label>
        <input name="phone_mobile" placeholder="napr. 101 alebo +421..." value="<?= View::e((string)($editEmployee['phone_mobile'] ?? '')) ?>" required>
      </div>
      <div class="col6">
        <label class="k">Interné (voliteľné)</label>
        <input name="phone_internal" placeholder="napr. 366" value="<?= View::e((string)($editEmployee['phone_internal'] ?? '')) ?>">
      </div>

      <div class="col12">
        <label class="k">Skupina</label>
        <select name="rotation_group_id">
          <option value="">Skupina (žiadna)</option>
          <?php foreach ($groups as $g): ?>
            <?php $sel = (string)($editEmployee['rotation_group_id'] ?? '') === (string)$g['id'] ? 'selected' : ''; ?>
            <option value="<?= View::e($g['id']) ?>" <?= $sel ?>><?= View::e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col12">
        <label class="k">Stav</label>
        <label class="row" style="gap:8px"><input type="checkbox" name="is_active" <?= $activeChecked ? 'checked' : '' ?> style="width:auto"> Aktívny</label>
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozšírené</summary>
        <div class="form" style="margin-top:10px">
          <div class="col12">
            <label class="k">Email (voliteľné)</label>
            <input name="email" placeholder="email@firma.sk" value="<?= View::e((string)($editEmployee['email'] ?? '')) ?>">
          </div>
          <div class="col12">
            <label class="k">Primárne číslo (voliteľné)</label>
            <input name="phone_primary" placeholder="napr. 101" value="<?= View::e((string)($editEmployee['phone_primary'] ?? '')) ?>">
          </div>
          <div class="col12">
            <label class="k">Pokročilé</label>
            <label class="row" style="gap:8px"><input type="checkbox" name="is_oncall" <?= $oncallChecked ? 'checked' : '' ?> style="width:auto"> Manuálne on-call (zvyčajne netreba)</label>
          </div>
        </div>
      </details>
      <div class="col12">
        <button class="btn primary" type="submit">Uložiť</button>
      </div>
    </form>
  </div>
</div>
