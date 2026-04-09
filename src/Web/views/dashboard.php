<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var array $state */
/** @var array $device */
/** @var array $logs */
/** @var array|null $flash */

$reachable = (bool)($device['reachable'] ?? false);

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="row" style="justify-content:space-between">
  <div>
    <div class="k">Stav zariadenia</div>
    <div class="row">
      <span class="badge <?= $reachable ? 'ok' : 'bad' ?>"><?= $reachable ? 'reachable' : 'unreachable' ?></span>
      <span class="muted"><?= View::e(($device['host'] ?? '-') . ':' . ($device['port'] ?? '-')) ?></span>
      <span class="muted"><?= View::e((string)($device['path'] ?? '')) ?></span>
    </div>
  </div>
  <form method="post" action="/actions/run-cycle" class="row" style="gap:8px">
    <button class="btn primary" type="submit">Spustiť cycle</button>
    <button class="btn" type="submit" name="force" value="1" title="Ignoruje zmenu detekcie (vynúti odoslanie na zariadenie)">Force</button>
  </form>
</div>

<div class="grid" style="margin-top:12px">
  <div class="card" style="grid-column:span 6">
    <h2>State</h2>
    <table>
      <tr><th>Key</th><th>Value</th></tr>
      <?php foreach ($state as $k => $v): ?>
        <tr>
          <td class="mono"><?= View::e($k) ?></td>
          <td><?= View::e(is_scalar($v) || $v === null ? (string)$v : json_encode($v)) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card" style="grid-column:span 6">
    <h2>Rýchle odkazy</h2>
    <div class="row">
      <a class="btn" href="/calendar">Kalendár služieb</a>
      <a class="btn" href="/rules">Pravidlá</a>
      <a class="btn" href="/overrides">Override</a>
      <a class="btn" href="/employees">Zamestnanci</a>
    </div>
    <div class="help" style="margin-top:10px">
      Poznámka: ak je VoIP zariadenie nedostupné, cycle skončí chybou (ale UI funguje).
    </div>
  </div>

  <div class="card" style="grid-column:span 12">
    <h2>Logy (posledné riadky)</h2>
    <textarea class="mono" style="white-space:pre-wrap;line-height:1.35" rows="10" readonly>
      <?= View::e(implode("\n", $logs)) ?>
    </textarea>
  </div>
</div>

