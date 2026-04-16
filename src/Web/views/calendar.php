<?php

declare(strict_types=1);

use HotPlan\Web\View;

/** @var string $ym */
/** @var DateTimeImmutable $first */
/** @var int $startDow */
/** @var int $daysInMonth */
/** @var array $days */
/** @var string $prev */
/** @var string $next */
/** @var array<int, array<string, mixed>> $employees */
/** @var array|null $flash */

$today = (new DateTimeImmutable())->format('Y-m-d');
$dows = ['Po', 'Ut', 'St', 'St', 'Pi', 'So', 'Ne'];
$employeesJson = json_encode($employees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="planner-page">
  <div class="planner-head">
    <div>
      <h2 style="margin:0">Planovanie sluzieb</h2>
      <div class="muted"><?= View::e($first->format('F Y')) ?></div>
    </div>
    <div class="row">
      <a class="btn" href="/calendar?ym=<?= View::e($prev) ?>">&larr; <?= View::e($prev) ?></a>
      <a class="btn" href="/calendar?ym=<?= View::e(date('Y-m')) ?>">Dnes</a>
      <a class="btn" href="/calendar?ym=<?= View::e($next) ?>"><?= View::e($next) ?> &rarr;</a>
    </div>
  </div>

  <div class="planner-summary">
    <div class="planner-card planner-card-inline">
      <div class="planner-card-title">Ako upravovat</div>
      <p class="planner-copy">Klik na plan ho upravi. Klik do prazdneho miesta v dni prida novy plan. Potiahnutie cez viac dni vytvori vyber pre hromadnu akciu.</p>
    </div>
    <div class="planner-card planner-card-inline">
      <div class="planner-card-title">Kopirovanie</div>
      <p class="planner-copy">Zo zvoleneho dna alebo rozsahu vies skopirovat cele rozlozenie planov na iny den, dalsi tyzden alebo opakovane podla intervalu.</p>
    </div>
  </div>

  <div class="planner-layout">
    <section class="planner-board planner-board-wide">
      <div class="planner-board-head">
        <div class="planner-chip-row">
          <span class="planner-chip">Klik do dna = novy plan</span>
          <span class="planner-chip">Klik na plan = upravit</span>
          <span class="planner-chip">Potiahni = hromadny vyber</span>
        </div>
      </div>
      <div class="planner-grid">
        <?php foreach ($dows as $dow): ?>
          <div class="planner-dow"><?= View::e($dow) ?></div>
        <?php endforeach; ?>

        <?php for ($i = 1; $i < $startDow; $i++): ?>
          <div class="planner-day planner-day-empty" aria-hidden="true"></div>
        <?php endfor; ?>

        <?php for ($dayNumber = 1; $dayNumber <= $daysInMonth; $dayNumber++): ?>
          <?php
            $date = $first->setDate((int) $first->format('Y'), (int) $first->format('m'), $dayNumber);
            $key = $date->format('Y-m-d');
            $info = $days[$key] ?? null;
            $isToday = $key === $today;
          ?>
          <div class="planner-day <?= $isToday ? 'is-today' : '' ?>" data-date="<?= View::e($key) ?>" role="button" tabindex="0">
            <div class="planner-day-top">
              <span class="planner-day-number"><?= View::e($dayNumber) ?></span>
              <?php if ($info && $info['holiday']): ?>
                <span class="badge"><?= View::e($info['holiday']) ?></span>
              <?php endif; ?>
            </div>

            <?php if ($info && !empty($info['events'])): ?>
              <div class="planner-section">
                <?php foreach ($info['events'] as $event): ?>
                  <button type="button" class="planner-event" data-plan-event>
                    <div class="planner-event-line">
                      <span class="planner-event-time"><?= View::e(($event['time_from'] ?? '') . ' - ' . ($event['time_to'] ?? '')) ?></span>
                      <span class="planner-event-title"><?= View::e((string) ($event['title'] ?? 'Plan')) ?></span>
                    </div>
                    <div class="planner-event-meta"><?= View::e((string) ($event['employee_name'] ?? '')) ?></div>
                    <span class="hidden" data-calendar-event='<?= View::e(json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></span>
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($info && !empty($info['segments'])): ?>
              <div class="planner-section planner-section-muted planner-day-segments">
                <?php foreach (array_slice($info['segments'], 0, 2) as $segment): ?>
                  <div class="planner-mini">
                    <?= View::e(($segment['from'] ?? '') . '-' . ($segment['to'] ?? '')) ?>
                    <span class="planner-mini-soft"><?= View::e((string) ($segment['forward_to'] ?? '')) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($info && !empty($info['rotations'])): ?>
              <div class="planner-section planner-section-muted">
                <?php foreach ($info['rotations'] as $rotation): ?>
                  <div class="planner-mini"><?= View::e(($rotation['rotation'] ?? '') . ': ' . ($rotation['employee'] ?? '-')) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </section>
  </div>
</div>

<div class="planner-modal hidden" id="planner-modal" role="dialog" aria-modal="true" aria-labelledby="planner-modal-title">
  <div class="planner-backdrop" data-close-modal></div>
  <div class="planner-dialog">
    <div class="planner-dialog-head">
      <div>
        <div class="planner-card-title" id="planner-modal-title">Plan v kalendari</div>
        <div class="muted" id="planner-selection-label">Bez vyberu</div>
      </div>
      <button class="btn" type="button" data-close-modal>Zavriet</button>
    </div>

    <form method="post" action="/calendar/create" class="form planner-form-simple" id="planner-form">
      <input type="hidden" name="selection_start" id="selection_start">
      <input type="hidden" name="selection_end" id="selection_end">
      <input type="hidden" name="ym" value="<?= View::e($ym) ?>">
      <input type="hidden" name="action_mode" id="action_mode" value="add">
      <input type="hidden" name="event_refs" id="event_refs" value="[]">
      <input type="hidden" name="selection_events_data" id="selection_events_data" value="[]">

      <div class="col12">
        <label class="k">Akcia</label>
        <div class="planner-action-switch">
          <button type="button" class="planner-action-card is-active" data-action-mode="add">Pridat</button>
          <button type="button" class="planner-action-card" data-action-mode="copy">Kopirovat</button>
          <button type="button" class="planner-action-card planner-action-card-danger hidden" id="planner-delete-selection" data-action-mode="delete">Vymazat vyber</button>
        </div>
        <div class="help" id="planner-context-note">Vyber dni v kalendari alebo klikni na existujuci plan.</div>
      </div>

      <div class="col12">
        <label class="k">Vybrany rozsah</label>
        <div class="planner-selection-bar">
          <input class="planner-flat-input" type="text" id="planner-selection-start-display" readonly>
          <span class="planner-selection-sep">-</span>
          <input class="planner-flat-input" type="text" id="planner-selection-end-display" readonly>
        </div>
      </div>

      <div class="col12 planner-add-fields">
        <label class="k">Nazov (volitelne)</label>
        <input class="planner-flat-input" type="text" name="title" id="title" placeholder="napr. Ranna smena">
      </div>

      <div class="col12 planner-add-fields">
        <label class="k">Presmerovanie</label>
        <div class="planner-target-switch">
          <label><input type="radio" name="target_mode" value="employee" checked> Zamestnanec</label>
          <label><input type="radio" name="target_mode" value="number"> Priame cislo</label>
        </div>
      </div>

      <div class="col12 planner-add-fields" id="planner-employee-wrap">
        <label class="k">Osoba pre presmerovanie</label>
        <select class="planner-flat-select" name="target_employee_id" id="target_employee_id">
          <option value="">Vyber osobu</option>
          <?php foreach ($employees as $employee): ?>
            <?php
              $disabled = (bool) ($employee['disabled'] ?? false);
              $label = (string) ($employee['name'] ?? '');
              if ($disabled && !empty($employee['disabled_reason'])) {
                  $label .= ' - ' . (string) $employee['disabled_reason'];
              }
            ?>
            <option value="<?= View::e((string) ($employee['id'] ?? '')) ?>" <?= $disabled ? 'disabled' : '' ?>>
              <?= View::e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col12 planner-add-fields hidden" id="planner-number-wrap">
        <label class="k">Priame cislo</label>
        <input class="planner-flat-input" type="text" name="target_number" id="target_number" placeholder="napr. 366 alebo +421...">
      </div>

      <div class="col6 planner-add-fields">
        <label class="k">Cas od</label>
        <input class="planner-flat-input" type="time" name="time_from" id="time_from" value="08:00" required>
      </div>

      <div class="col6 planner-add-fields">
        <label class="k">Cas do</label>
        <input class="planner-flat-input" type="time" name="time_to" id="time_to" value="16:00" required>
      </div>

      <div class="col12 hidden planner-copy-fields">
        <label class="k">Kopirovat od datumu</label>
        <input class="planner-flat-input" type="date" name="copy_target_start" id="copy_target_start">
      </div>

      <div class="col6 planner-repeat-fields">
        <label class="k">Opakovanie</label>
        <select class="planner-flat-select" name="repeat_pattern" id="repeat_pattern">
          <option value="none">Neopakuje sa</option>
          <option value="daily">Denne</option>
          <option value="weekly">Tyzdenne</option>
          <option value="monthly">Mesacne</option>
        </select>
      </div>

      <div class="col6 hidden planner-repeat-fields" id="planner-repeat-end-wrap">
        <label class="k">Skoncit</label>
        <select class="planner-flat-select" name="repeat_end_mode" id="repeat_end_mode">
          <option value="count">Po pocte opakovani</option>
          <option value="until">Do datumu</option>
        </select>
      </div>

      <div class="col6 hidden planner-repeat-fields" id="planner-repeat-count-wrap">
        <label class="k">Pocet opakovani</label>
        <input class="planner-flat-input" type="number" min="1" max="60" name="repeat_count" id="repeat_count" value="1">
      </div>

      <div class="col6 hidden planner-repeat-fields" id="planner-repeat-until-wrap">
        <label class="k">Opakovat do</label>
        <input class="planner-flat-input" type="date" name="repeat_until" id="repeat_until">
      </div>

      <div class="col12 planner-inline-note hidden" id="planner-copy-note">
        Ak su vo vybranom rozsahu ulozene plany, kopirovanie zachova rozlozenie celeho dna alebo viacdnovy vzor presne tak, ako je naplanovany.
      </div>

      <div class="col12 planner-inline-note hidden" id="planner-delete-note">
        Vymazanie odstrani vsetky planovane udalosti v aktualne zvolenom vybere.
      </div>

      <div class="col12 row" style="justify-content:flex-end">
        <button class="btn danger hidden" type="button" id="planner-delete-event-btn">Vymazat udalost</button>
        <button class="btn" type="button" data-close-modal>Zrusit</button>
        <button class="btn primary" type="submit" id="planner-submit">Ulozit</button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const employees = <?= $employeesJson ?: '[]' ?>;
  const modal = document.getElementById('planner-modal');
  const form = document.getElementById('planner-form');
  const selectionStartInput = document.getElementById('selection_start');
  const selectionEndInput = document.getElementById('selection_end');
  const selectionStartDisplay = document.getElementById('planner-selection-start-display');
  const selectionEndDisplay = document.getElementById('planner-selection-end-display');
  const selectionLabel = document.getElementById('planner-selection-label');
  const actionModeInput = document.getElementById('action_mode');
  const eventRefsInput = document.getElementById('event_refs');
  const selectionEventsDataInput = document.getElementById('selection_events_data');
  const titleInput = document.getElementById('title');
  const employeeInput = document.getElementById('target_employee_id');
  const targetNumberInput = document.getElementById('target_number');
  const timeFromInput = document.getElementById('time_from');
  const timeToInput = document.getElementById('time_to');
  const copyTargetStartInput = document.getElementById('copy_target_start');
  const repeatPatternInput = document.getElementById('repeat_pattern');
  const repeatEndModeInput = document.getElementById('repeat_end_mode');
  const repeatCountInput = document.getElementById('repeat_count');
  const repeatUntilInput = document.getElementById('repeat_until');
  const contextNote = document.getElementById('planner-context-note');
  const deleteSelectionCard = document.getElementById('planner-delete-selection');
  const deleteEventButton = document.getElementById('planner-delete-event-btn');
  const deleteNote = document.getElementById('planner-delete-note');
  const copyNote = document.getElementById('planner-copy-note');
  const submitButton = document.getElementById('planner-submit');
  const repeatEndWrap = document.getElementById('planner-repeat-end-wrap');
  const repeatCountWrap = document.getElementById('planner-repeat-count-wrap');
  const repeatUntilWrap = document.getElementById('planner-repeat-until-wrap');
  const employeeWrap = document.getElementById('planner-employee-wrap');
  const numberWrap = document.getElementById('planner-number-wrap');
  const targetModeInputs = Array.from(document.querySelectorAll('input[name="target_mode"]'));
  const addFields = Array.from(document.querySelectorAll('.planner-add-fields'));
  const copyFields = Array.from(document.querySelectorAll('.planner-copy-fields'));
  const repeatFields = Array.from(document.querySelectorAll('.planner-repeat-fields'));
  const actionCards = Array.from(document.querySelectorAll('[data-action-mode]'));
  const dayNodes = Array.from(document.querySelectorAll('.planner-day[data-date]'));
  const planEvents = Array.from(document.querySelectorAll('[data-plan-event]'));

  let dragActive = false;
  let dragMoved = false;
  let dragAnchor = '';
  let suppressNextClick = false;

  const sortDates = (a, b) => a <= b ? [a, b] : [b, a];

  const shiftDate = (date, days) => {
    const value = new Date(`${date}T00:00:00`);
    value.setDate(value.getDate() + days);
    return value.toISOString().slice(0, 10);
  };

  const getSelectionEvents = () => {
    if (!selectionStartInput.value || !selectionEndInput.value) {
      return [];
    }

    const [start, end] = sortDates(selectionStartInput.value, selectionEndInput.value);
    const seen = new Set();
    const events = [];

    dayNodes.forEach((node) => {
      const date = node.dataset.date || '';
      if (date < start || date > end) {
        return;
      }
      node.querySelectorAll('[data-calendar-event]').forEach((eventNode) => {
        try {
          const eventData = JSON.parse(eventNode.getAttribute('data-calendar-event') || '{}');
          const baseDate = eventData.start_date || date;
          const key = `${eventData.id}`;
          if (seen.has(key)) {
            return;
          }
          seen.add(key);
          events.push({ ...eventData, selection_date: baseDate });
        } catch (error) {
        }
      });
    });

    return events;
  };

  const paintSelection = (from, to) => {
    const [start, end] = sortDates(from, to);
    dayNodes.forEach((node) => {
      const date = node.dataset.date || '';
      node.classList.toggle('is-selected', date >= start && date <= end);
    });
    selectionStartInput.value = start;
    selectionEndInput.value = end;
    selectionStartDisplay.value = start;
    selectionEndDisplay.value = end;
    selectionLabel.textContent = start === end ? start : `${start} - ${end}`;
  };

  const syncRepeatFields = () => {
    const repeating = repeatPatternInput.value !== 'none';
    repeatEndWrap.classList.toggle('hidden', !repeating);
    repeatCountWrap.classList.toggle('hidden', !repeating || repeatEndModeInput.value !== 'count');
    repeatUntilWrap.classList.toggle('hidden', !repeating || repeatEndModeInput.value !== 'until');
  };

  const syncTargetMode = () => {
    const mode = targetModeInputs.find((input) => input.checked)?.value || 'employee';
    const isEmployee = mode === 'employee';
    employeeWrap.classList.toggle('hidden', !isEmployee);
    numberWrap.classList.toggle('hidden', isEmployee);
    const requiresTarget = actionModeInput.value !== 'copy' && actionModeInput.value !== 'delete';
    employeeInput.required = isEmployee && requiresTarget;
    targetNumberInput.required = !isEmployee && requiresTarget;
  };

  const syncActionMode = () => {
    const mode = actionModeInput.value;
    actionCards.forEach((card) => card.classList.toggle('is-active', card.dataset.actionMode === mode));
    addFields.forEach((field) => field.classList.toggle('hidden', mode === 'copy' || mode === 'delete'));
    copyFields.forEach((field) => field.classList.toggle('hidden', mode !== 'copy'));
    repeatFields.forEach((field) => field.classList.toggle('hidden', mode === 'delete'));
    copyNote.classList.toggle('hidden', mode !== 'copy');
    deleteNote.classList.toggle('hidden', mode !== 'delete');
    deleteEventButton.classList.toggle('hidden', mode === 'copy' || eventRefsInput.value === '[]');
    submitButton.textContent = mode === 'copy' ? 'Skopirovat' : mode === 'delete' ? 'Vymazat vyber' : mode === 'update' ? 'Ulozit zmeny' : 'Ulozit';
    syncRepeatFields();
    syncTargetMode();
  };

  const openModal = () => {
    modal.classList.remove('hidden');
    document.body.classList.add('planner-lock');
  };

  const closeModal = () => {
    modal.classList.add('hidden');
    document.body.classList.remove('planner-lock');
  };

  const resetFormForAdd = () => {
    actionModeInput.value = 'add';
    eventRefsInput.value = '[]';
    selectionEventsDataInput.value = JSON.stringify(getSelectionEvents());
    titleInput.value = '';
    const employeeMode = targetModeInputs.find((input) => input.value === 'employee');
    if (employeeMode) {
      employeeMode.checked = true;
    }
    employeeInput.value = '';
    targetNumberInput.value = '';
    timeFromInput.value = '08:00';
    timeToInput.value = '16:00';
    repeatPatternInput.value = 'none';
    repeatEndModeInput.value = 'count';
    repeatCountInput.value = '1';
    repeatUntilInput.value = '';
    copyTargetStartInput.value = shiftDate(selectionEndInput.value || selectionStartInput.value, 1);
    const events = JSON.parse(selectionEventsDataInput.value || '[]');
    contextNote.textContent = events.length > 0
      ? `Vo vybere je ${events.length} planov. Mozes pridat novy plan alebo ich skopirovat na iny termin.`
      : 'Pridavas novu udalost do vybraneho rozsahu.';
    deleteSelectionCard.classList.toggle('hidden', events.length === 0);
    syncActionMode();
  };

  const openSelectionEditor = (from, to = from) => {
    paintSelection(from, to);
    resetFormForAdd();
    openModal();
  };

  const openEventEditor = (eventData, date) => {
    paintSelection(date, date);
    actionModeInput.value = 'update';
    eventRefsInput.value = JSON.stringify([{ id: eventData.id }]);
    selectionEventsDataInput.value = JSON.stringify([{ ...eventData, selection_date: date }]);
    titleInput.value = eventData.title || '';
    const isEmployeeTarget = !!eventData.target_employee_id;
    const modeInput = targetModeInputs.find((input) => input.value === (isEmployeeTarget ? 'employee' : 'number'));
    if (modeInput) {
      modeInput.checked = true;
    }
    employeeInput.value = isEmployeeTarget ? String(eventData.target_employee_id) : '';
    targetNumberInput.value = isEmployeeTarget ? '' : (eventData.forward_to || '');
    timeFromInput.value = eventData.time_from || '08:00';
    timeToInput.value = eventData.time_to || '16:00';
    repeatPatternInput.value = 'none';
    repeatEndModeInput.value = 'count';
    repeatCountInput.value = '1';
    repeatUntilInput.value = '';
    copyTargetStartInput.value = shiftDate(date, 1);
    contextNote.textContent = 'Upravujes konkretnu udalost. Tlacidlo Vymazat udalost odstrani iba tento jeden plan.';
    deleteSelectionCard.classList.add('hidden');
    syncActionMode();
    syncTargetMode();
    openModal();
  };

  dayNodes.forEach((node) => {
    node.addEventListener('mousedown', (event) => {
      if (event.target.closest('[data-plan-event]')) {
        return;
      }
      event.preventDefault();
      dragActive = true;
      dragMoved = false;
      dragAnchor = node.dataset.date || '';
      suppressNextClick = false;
      paintSelection(dragAnchor, dragAnchor);
    });

    node.addEventListener('mouseenter', () => {
      if (!dragActive || !dragAnchor) {
        return;
      }
      dragMoved = true;
      paintSelection(dragAnchor, node.dataset.date || '');
    });

    node.addEventListener('click', (event) => {
      if (event.target.closest('[data-plan-event]')) {
        return;
      }
      if (suppressNextClick) {
        suppressNextClick = false;
        return;
      }
      openSelectionEditor(node.dataset.date || '');
    });

    node.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      if (event.target.closest('[data-plan-event]')) {
        return;
      }
      event.preventDefault();
      openSelectionEditor(node.dataset.date || '');
    });
  });

  document.addEventListener('mouseup', () => {
    if (!dragActive) {
      return;
    }
    dragActive = false;
    if (dragMoved && selectionStartInput.value && selectionEndInput.value) {
      suppressNextClick = true;
      openSelectionEditor(selectionStartInput.value, selectionEndInput.value);
    }
    dragAnchor = '';
    dragMoved = false;
  });

  planEvents.forEach((node) => {
    node.addEventListener('click', (event) => {
      event.stopPropagation();
      const payload = node.querySelector('[data-calendar-event]');
      const day = node.closest('.planner-day');
      if (!payload || !day) {
        return;
      }
      try {
        openEventEditor(JSON.parse(payload.getAttribute('data-calendar-event') || '{}'), day.dataset.date || '');
      } catch (error) {
      }
    });
  });

  actionCards.forEach((card) => {
    card.addEventListener('click', () => {
      actionModeInput.value = card.dataset.actionMode || 'add';
      syncActionMode();
    });
  });

  deleteEventButton.addEventListener('click', () => {
    actionModeInput.value = 'delete';
    syncActionMode();
    form.requestSubmit();
  });

  repeatPatternInput.addEventListener('change', syncRepeatFields);
  repeatEndModeInput.addEventListener('change', syncRepeatFields);
  targetModeInputs.forEach((input) => input.addEventListener('change', syncTargetMode));

  modal.querySelectorAll('[data-close-modal]').forEach((node) => {
    node.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeModal();
    }
  });

  form.addEventListener('submit', (event) => {
    if (!selectionStartInput.value || !selectionEndInput.value) {
      event.preventDefault();
      contextNote.textContent = 'Najprv vyber dni v kalendari.';
      return;
    }

    if (actionModeInput.value === 'delete') {
      if (eventRefsInput.value === '[]') {
        eventRefsInput.value = JSON.stringify(getSelectionEvents().map((plan) => ({ id: plan.id })));
      }
      return;
    }

    if (actionModeInput.value === 'copy') {
      selectionEventsDataInput.value = JSON.stringify(getSelectionEvents());
      if (!copyTargetStartInput.value) {
        event.preventDefault();
        contextNote.textContent = 'Zvol datum, od ktoreho sa ma kopia zacat.';
      }
      return;
    }

    const targetMode = targetModeInputs.find((input) => input.checked)?.value || 'employee';
    if (targetMode === 'employee' && !employeeInput.value) {
      event.preventDefault();
      contextNote.textContent = 'Vyber osobu pre presmerovanie.';
      return;
    }
    if (targetMode === 'number' && !targetNumberInput.value.trim()) {
      event.preventDefault();
      contextNote.textContent = 'Zadaj priame cislo pre presmerovanie.';
    }
  });

  syncActionMode();
  syncTargetMode();
})();
</script>
