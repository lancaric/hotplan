<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $active */
/** @var array|null $flash */

$types = ['temporary','indefinite','until_time','until_employee'];

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <h2 style="margin:0">Override</h2>
  <form method="post" action="/overrides/clear" onsubmit="return confirm('Vymazať všetky override?')">
    <button class="btn danger" type="submit">Vymazať všetky</button>
  </form>
</div>

<div class="grid" style="margin-top:10px">
  <div class="card" style="grid-column:span 7">
    <h2>Aktívne</h2>
    <table>
      <tr><th>ID</th><th>Type</th><th>Forward</th><th>Starts</th><th>Ends</th><th>Reason</th></tr>
      <?php foreach ($active as $o): ?>
        <tr>
          <td class="mono"><?= View::e($o['id']) ?></td>
          <td class="mono"><?= View::e($o['override_type']) ?></td>
          <td class="mono"><?= View::e($o['forward_to']) ?></td>
          <td class="mono"><?= View::e($o['starts_at'] ?? '') ?></td>
          <td class="mono"><?= View::e($o['ends_at'] ?? $o['expires_at'] ?? '') ?></td>
          <td><?= View::e($o['reason'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 5">
    <h2>Vytvoriť override</h2>
    <form method="post" action="/overrides/create" class="form">
      <div class="col12">
        <label class="k">Presmerovať na</label>
        <input name="forward_to" placeholder="číslo *" required>
      </div>
      <div class="col12">
        <label class="k">Platí do (voliteľné)</label>
        <input type="datetime-local" name="expires_at" placeholder="Do kedy (nechaj prázdne = neobmedzene)">
        <div class="help">Ak necháš prázdne, override platí až do manuálneho zrušenia.</div>
      </div>
      <div class="col12">
        <label class="k">Poznámka (voliteľné)</label>
        <input name="reason" placeholder="napr. test">
      </div>

      <details class="col12">
        <summary class="btn" style="list-style:none">Rozšírené</summary>
        <div class="form" style="margin-top:10px">
          <div class="col6">
            <label class="k">Začiatok (voliteľné)</label>
            <input type="datetime-local" name="starts_at">
          </div>
          <div class="col6">
            <label class="k">Koniec (voliteľné)</label>
            <input type="datetime-local" name="ends_at">
          </div>
          <div class="col12">
            <label class="k">Typ override</label>
            <select name="override_type">
              <option value="">auto</option>
              <?php foreach ($types as $t): ?>
                <option value="<?= View::e($t) ?>"><?= View::e($t) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="help">Nechaj <span class="mono">auto</span>, ak nevieš čo vybrať.</div>
          </div>
        </div>
      </details>

      <div class="col12"><button class="btn primary" type="submit">Vytvoriť</button></div>
    </form>
  </div>
</div>
