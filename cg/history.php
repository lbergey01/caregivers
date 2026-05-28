<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }

$is_admin    = cg_isAdmin();
$caregivers  = cg_caregiversAll(true);
$clients     = cg_clientsAll(true);
$default_cid = cg_defaultClientId();

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<style>
  .h-note { border: 1px solid #dee2e6; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
  .h-note .meta { font-size: 13px; color: #6c757d; }
  .h-note .body { white-space: pre-wrap; margin-top: 6px; }
  .h-note .chip {
    display: inline-block; padding: 2px 8px; border-radius: 999px;
    font-size: 12px; color: #fff; margin-right: 6px;
  }
  .h-note .shift-link { font-size: 12px; }
  .h-note .att-thumb {
    width: 88px; height: 88px; object-fit: cover; border-radius: 6px; cursor: pointer;
  }
  .h-note .att-file {
    width: 88px; height: 88px; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    border: 1px solid #ccc; border-radius: 6px; background: #f8f9fa;
    text-decoration: none; padding: 4px;
  }
  mark {
    background: #ffd54f;
    color: #000;
    padding: 0 2px;
    border-radius: 2px;
  }
</style>

<main class="container my-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Shift Log History</h1>
    <a class="btn btn-sm btn-outline-secondary" href="index.php">&larr; Calendar</a>
  </div>

  <div class="mb-2">
    <input type="search" id="search" class="form-control form-control-sm"
           placeholder="Search notes — type multiple words; partial matches OK"
           autocomplete="off">
  </div>

  <form id="filters" class="row g-2 mb-3" autocomplete="off">
    <?php if (count($clients) > 1): ?>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Client</label>
        <select name="client_id" class="form-select form-select-sm">
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c->id ?>" <?= (int)$c->id === $default_cid ? 'selected' : '' ?>>
              <?= htmlspecialchars($c->name) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <input type="hidden" name="client_id" value="<?= $default_cid ?>">
    <?php endif; ?>

    <?php if ($is_admin): ?>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">From</label>
        <input type="datetime-local" name="from" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">To</label>
        <input type="datetime-local" name="to" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Caregiver</label>
        <select name="caregiver_id" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($caregivers as $c): ?>
            <option value="<?= $c->id ?>"><?= htmlspecialchars($c->name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-1 d-flex align-items-end">
        <button class="btn btn-primary btn-sm w-100">Apply</button>
      </div>
    <?php else: ?>
      <div class="col-12">
        <small class="text-muted">Showing notes from the last 7 days across all caregivers.</small>
      </div>
    <?php endif; ?>
  </form>

  <div id="historyList" class="mb-3">
    <div class="text-muted">Loading…</div>
  </div>
</main>

<script>
(function(){
  const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;
  const list = document.getElementById('historyList');
  const filters = document.getElementById('filters');
  const searchEl = document.getElementById('search');

  // Loaded rows are cached so search filtering is purely client-side; only the
  // date-range filters (admin) trigger a refetch.
  let allRows = [];
  let currentTerms = [];

  function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[c]);}

  // Escape, then wrap any case-insensitive matches of the active search terms
  // in <mark>. Safe for user-content slots in innerHTML; do NOT use for
  // attribute-context strings (colors, hrefs).
  function hi(text) {
    const safe = escapeHtml(text == null ? '' : text);
    if (!currentTerms.length) return safe;
    const pattern = currentTerms
      .map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
      .join('|');
    return safe.replace(new RegExp(pattern, 'gi'), m => `<mark>${m}</mark>`);
  }
  function fmtTs(dt){if(!dt)return'';const d=new Date(dt.replace(' ','T'));return isNaN(d)?dt:d.toLocaleString([],{dateStyle:'medium',timeStyle:'short'});}
  function fmtShift(s,e){const a=new Date(s.replace(' ','T')),b=new Date(e.replace(' ','T'));
    const f={dateStyle:'medium',timeStyle:'short'};
    return `${a.toLocaleString([],f)} – ${b.toLocaleString([],f)}`;}

  function load() {
    const params = new URLSearchParams();
    params.set('action', 'recent');
    new FormData(filters).forEach((v,k) => { if (v) params.set(k, v); });
    if (!IS_ADMIN || (!params.get('from') && !params.get('to'))) {
      params.set('hours', '168');   // 7 days
    }
    list.innerHTML = '<div class="text-muted">Loading…</div>';
    fetch('api/notes.php?' + params.toString())
      .then(r => r.json())
      .then(rows => { allRows = rows; applyFilter(); })
      .catch(() => list.innerHTML = '<div class="text-danger">Failed to load.</div>');
  }

  // DataTables-style filter: whitespace-split tokens, each must appear as a
  // substring (case-insensitive) somewhere in the row's searchable fields.
  function applyFilter() {
    const q = (searchEl.value || '').trim().toLowerCase();
    currentTerms = q ? q.split(/\s+/) : [];
    const rows = currentTerms.length === 0 ? allRows : allRows.filter(n => {
      const hay = [
        n.author_name, n.shift_caregiver_name, n.body,
        fmtTs(n.created_at), fmtShift(n.shift_start, n.shift_end)
      ].join(' ').toLowerCase();
      return currentTerms.every(t => hay.includes(t));
    });
    render(rows);
  }

  function render(rows) {
    if (!allRows.length) { list.innerHTML = '<div class="text-muted">No notes in this range.</div>'; return; }
    if (!rows.length)    { list.innerHTML = '<div class="text-muted">No notes match this search.</div>'; return; }
    list.innerHTML = '';
    rows.forEach(n => list.appendChild(renderNote(n)));
  }

  function renderNote(n) {
    const wrap = document.createElement('div');
    wrap.className = 'h-note';
    const shiftDate = n.shift_start ? n.shift_start.slice(0,10) : '';
    wrap.innerHTML =
      `<div class="meta">
         <span class="chip" style="background:${escapeHtml(n.shift_caregiver_color||'#888')};">${hi(n.shift_caregiver_name||'Shift')}</span>
         <strong>${hi(n.author_name)}</strong>
         · ${hi(fmtTs(n.created_at))}
         ${n.edited_at ? `<span class="fst-italic"> (edited ${escapeHtml(fmtTs(n.edited_at))})</span>` : ''}
         · <a class="shift-link" href="index.php?goto=${shiftDate}&shift=${n.shift_id}">View shift (${hi(fmtShift(n.shift_start, n.shift_end))})</a>
       </div>
       <div class="body">${hi(n.body)}</div>`;
    if (n.attachments && n.attachments.length) {
      const strip = document.createElement('div');
      strip.className = 'd-flex flex-wrap gap-2 mt-2';
      n.attachments.forEach(a => {
        const url = `api/attachment.php?action=get&id=${a.id}`;
        if (/^image\//.test(a.mime)) {
          const img = document.createElement('img');
          img.src = url; img.alt = a.orig_name; img.title = a.orig_name;
          img.className = 'att-thumb';
          img.addEventListener('click', () => window.open(url, '_blank'));
          strip.appendChild(img);
        } else {
          const link = document.createElement('a');
          link.href = url; link.target = '_blank'; link.className = 'att-file';
          link.innerHTML = `<div style="font-size:24px;">📎</div>
                            <div class="small text-truncate" style="max-width:80px;">${escapeHtml(a.orig_name)}</div>`;
          strip.appendChild(link);
        }
      });
      wrap.appendChild(strip);
    }
    return wrap;
  }

  filters.addEventListener('submit', (e) => { e.preventDefault(); load(); });
  filters.addEventListener('change', () => { /* admin's caregiver/client picker can auto-apply */
    if (IS_ADMIN) load();
  });
  searchEl.addEventListener('input', applyFilter);
  load();
})();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
