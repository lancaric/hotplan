<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $groups */
/** @var array|null $editGroup */
/** @var bool $showForm */
/** @var array|null $flash */

$types = ['weekly', 'daily', 'custom'];

$editId = is_array($editGroup) ? (int)($editGroup['id'] ?? 0) : 0;

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Skupiny</h2>
  <div class="row" style="gap:8px">
    <a class="btn primary" href="/groups?add=1#form">Pridať</a>
    <?php if ($showForm): ?>
      <a class="btn" href="/groups">Zavrieť</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid">
  <div class="card" style="grid-column:span 12">
    <div class="table-wrap">
      <table>
      <tr><th>ID</th><th>Názov</th><th>Typ</th><th>Akcie</th></tr>
      <?php foreach ($groups as $g): ?>
        <tr>
          <td class="mono"><?= View::e($g['id']) ?></td>
          <td><?= View::e($g['name']) ?></td>
          <td class="mono"><?= View::e($g['rotation_type'] ?? 'weekly') ?></td>
          <td>
            <div class="row" style="gap:6px;justify-content:flex-end">
              <a class="btn" href="/groups?edit=<?= View::e($g['id']) ?>#form">Upraviť</a>
              <form method="post" action="/groups/delete" onsubmit="return confirm('Zmazať?')">
                <input type="hidden" name="id" value="<?= View::e($g['id']) ?>">
                <button class="btn danger" type="submit">Zmazať</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </table>
    </div>
    <div class="help" style="margin-top:10px">
      Skupina je len kontajner pre rotáciu. Zamestnancov do skupiny priradíš v <span class="mono">Zamestnanci</span>.
    </div>
  </div>

  <div class="card <?= $showForm ? '' : 'hidden' ?>" style="grid-column:span 12">
    <h2><?= $editId ? 'Upraviť skupinu' : 'Pridať skupinu' ?></h2>
    <form method="post" action="/groups/save" class="form" id="form">
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
        <input name="name" placeholder="Názov *" value="<?= View::e((string)($editGroup['name'] ?? '')) ?>" required>
      </div>
      <div class="col12">
        <label class="k">Popis (voliteľné)</label>
        <input name="description" placeholder="Krátky popis" value="<?= View::e((string)($editGroup['description'] ?? '')) ?>">
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozšírené</summary>
        <div class="form" style="margin-top:10px">
          <div class="col6">
            <label class="k">Typ rotácie</label>
            <select name="rotation_type">
              <?php foreach ($types as $t): ?>
                <?php $sel = (string)($editGroup['rotation_type'] ?? 'weekly') === (string)$t ? 'selected' : ''; ?>
                <option value="<?= View::e($t) ?>" <?= $sel ?>><?= View::e($t) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="help">Väčšinou stačí <span class="mono">weekly</span>.</div>
          </div>
          <div class="col3">
            <label class="k">Index (pokročilé)</label>
            <input name="current_index" type="number" value="<?= View::e((string)($editGroup['current_index'] ?? 0)) ?>" min="0" step="1">
          </div>
          <div class="col3">
            <label class="k">Začiatok rotácie</label>
            <input type="date" name="rotation_start_date" value="<?= View::e(View::dateValue($editGroup['rotation_start_date'] ?? null)) ?>">
          </div>
        </div>
      </details>
      <div class="col12"><button class="btn primary" type="submit">Uložiť</button></div>
    </form>
  </div>
</div>
