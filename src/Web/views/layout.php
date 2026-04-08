<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var string $title */
/** @var callable $content */
/** @var bool $authed */
/** @var string|null $adminUser */

?>
<!doctype html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title) ?> · HotPlan</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">HotPlan</div>
      <?php if ($authed): ?>
        <div class="nav">
          <a href="/">Dashboard</a>
          <a href="/calendar">Kalendár</a>
          <a href="/employees">Zamestnanci</a>
          <a href="/groups">Skupiny</a>
          <a href="/oncall">On-call</a>
          <a href="/working-hours">Prac. hodiny</a>
          <a href="/holidays">Sviatky</a>
          <a href="/rules">Pravidlá</a>
          <a href="/overrides">Override</a>
          <a href="/logs">Logy</a>
          <a href="/config">Config</a>
        </div>
        <div class="user">
          <span class="badge"><?= View::e($adminUser ?? 'admin') ?></span>
          <a class="btn" href="/logout">Odhlásiť</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="main">
      <?php $content(); ?>
    </div>

    <div class="muted" style="margin-top:12px;font-size:12px">
      Spustenie: <span class="mono">php -S 127.0.0.1:8080 -t public public/index.php</span>
    </div>
  </div>
</body>
</html>
