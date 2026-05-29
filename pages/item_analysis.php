<?php
// pages/item_analysis.php
include("components/header.php");

$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : null;

if (!$assessment_id) {
    echo "<script>window.location.href='levels.php';</script>";
    exit;
}

// Fetch assessment header to verify it belongs to this teacher
// and to display breadcrumb info
include_once('../backend/db/db.php');
$db   = new db_connect();
$conn = $db->connect();

$hdr_stmt = $conn->prepare("
    SELECT
        a.id              AS assessment_id,
        a.assessment_title,
        arl.aralin_no,
        arl.aralin_title,
        l.id              AS level_id,
        l.level           AS markahan_level,
        l.teacher_id
    FROM assessments AS a
    JOIN aralin AS arl ON a.aralin_id  = arl.id
    JOIN levels  AS l  ON arl.level_id = l.id
    WHERE a.id = ?
    LIMIT 1
");
$hdr_stmt->bind_param('i', $assessment_id);
$hdr_stmt->execute();
$hdr = $hdr_stmt->get_result()->fetch_assoc();
$hdr_stmt->close();

if (!$hdr) {
    echo "<script>window.location.href='levels.php';</script>";
    exit;
}

// Auth: teacher must own this level
if ((int)$hdr['teacher_id'] !== (int)$auth_user_id) {
    echo "<script>window.location.href='levels.php';</script>";
    exit;
}

$ordinalMap  = [1 => "Unang", 2 => "Ikalawang", 3 => "Ikatlong", 4 => "Ika-apat na"];
$markahan    = ($ordinalMap[$hdr['markahan_level']] ?? $hdr['markahan_level']) . " Markahan";
?>

<input type="hidden" id="hidden_assessment_id" value="<?= $assessment_id ?>">
<input type="hidden" id="hidden_level_id"       value="<?= htmlspecialchars($hdr['level_id']) ?>">
<input type="hidden" id="hidden_user_id"         value="<?= $auth_user_id ?>">

<style>
/* ── Reset ──────────────────────────────────────────────────────────────── */
nav.navbar       { display: none !important; }
body             { background-color: #f4f6f9; overflow-x: hidden; }
.dashboard-wrapper { display: flex; width: 100%; min-height: 100vh; overflow-x: hidden; }
.main-content    { flex: 1; margin-left: 280px; padding: 30px 40px; background: #f8f9fa; transition: margin-left .3s ease; }
.dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

/* ── Sidebar boilerplate (matches every other page) ─────────────────────── */
.sidebar-profile { display:flex; align-items:center; gap:15px; margin-bottom:30px; padding-bottom:20px; border-bottom:1px solid rgba(255,255,255,.5); }
.sidebar-profile img { width:80px!important; height:80px!important; border-radius:50%; object-fit:cover; border:2px solid white; }
.sidebar-profile h5  { font-weight:bold; margin:0; font-size:1.2rem; text-transform:uppercase; color:white; }
.nav-link-custom { display:flex; align-items:center; padding:12px 15px; color:white; text-decoration:none; font-weight:600; margin-bottom:10px; transition:.3s; border-radius:5px; }
.nav-link-custom:hover  { background:rgba(255,255,255,.2); color:white; }
.nav-link-custom.active { background:#FFC107!important; color:#440101!important; }
.nav-link-custom i { margin-right:15px; font-size:1.2rem; }
.logout-btn { margin-top:auto; background:#FFC107; color:black; font-weight:bold; border:none; width:100%; padding:12px; border-radius:25px; text-align:center; cursor:pointer; }

/* ── Header banner ───────────────────────────────────────────────────────── */
.page-header-banner {
    background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
    color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,.1);
    display: flex; align-items: center; justify-content: space-between;
}
.page-header-banner h4 { margin:0; font-weight:700; text-transform:uppercase; font-size:1.3rem; }
.btn-back-text {
    background:rgba(255,255,255,.2); color:white; border:1px solid rgba(255,255,255,.4);
    font-size:.85rem; font-weight:600; padding:8px 18px; border-radius:50px; text-decoration:none;
    transition:.2s; display:inline-flex; align-items:center; gap:6px;
}
.btn-back-text:hover { background:white; color:#a71b1b; }

/* ── Summary strip ───────────────────────────────────────────────────────── */
.summary-strip {
    display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px;
}
.summary-card {
    flex: 1; min-width: 160px;
    background: white; border-radius: 10px; padding: 18px 22px;
    border: 1px solid #dee2e6; box-shadow: 0 2px 8px rgba(0,0,0,.05);
    text-align: center;
}
.summary-card .val  { font-size: 2.2rem; font-weight: 900; color: #a71b1b; line-height: 1; }
.summary-card .lbl  { font-size: .75rem; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-top: 4px; letter-spacing: .4px; }
.summary-card.sky  .val { color: #1E90D6; }
.summary-card.green .val { color: #28a745; }
.summary-card.red   .val { color: #dc3545; }

/* ── Filter bar ──────────────────────────────────────────────────────────── */
.filter-bar {
    background: white; border-radius: 8px; padding: 14px 20px; margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05); border: 1px solid #dee2e6;
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px;
}
.filter-group { display:flex; flex-direction:column; gap:5px; flex:1; min-width:140px; }
.filter-group label { font-size:.75rem; font-weight:700; text-transform:uppercase; color:#6c757d; letter-spacing:.4px; }
.filter-group .form-select { font-size:.875rem; border-color:#dee2e6; border-radius:6px; }
.filter-group .form-select:focus { border-color:#a71b1b; box-shadow:0 0 0 .2rem rgba(167,27,27,.15); }
.btn-filter-reset {
    background:#f8f9fa; border:1px solid #dee2e6; color:#495057;
    font-size:.85rem; font-weight:600; padding:8px 18px; border-radius:6px;
    cursor:pointer; transition:.2s; align-self:flex-end; white-space:nowrap;
}
.btn-filter-reset:hover { background:#e9ecef; }

/* ── Record count ────────────────────────────────────────────────────────── */
.record-count { font-size:.85rem; color:#6c757d; margin-bottom:12px; }
.record-count strong { color:#212529; }

/* ── Question cards ──────────────────────────────────────────────────────── */
.q-card {
    background: white; border-radius: 10px; margin-bottom: 14px;
    border: 1px solid #dee2e6; border-left-width: 5px;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    overflow: hidden;
    transition: box-shadow .2s;
}
.q-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }

/* Border colour by pass rate */
.q-card.rate-high   { border-left-color: #28a745; }
.q-card.rate-medium { border-left-color: #ffc107; }
.q-card.rate-low    { border-left-color: #dc3545; }

.q-card-header {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 14px 20px 10px 20px;
}
.q-num       { font-weight:800; font-size:.8rem; color:#6c757d; white-space:nowrap; }
.q-text      { font-weight:600; font-size:.97rem; color:#212529; flex:1; }
.q-card-body { padding: 0 20px 16px 20px; }

/* Progress bar */
.stat-row {
    display: flex; align-items: center; gap: 12px;
    margin-top: 12px;
}
.stat-bar-wrap {
    flex: 1; height: 10px; background: #f0f0f0; border-radius: 99px; overflow: hidden;
}
.stat-bar-fill {
    height: 100%; border-radius: 99px;
    transition: width .6s ease;
}
.fill-high   { background: linear-gradient(90deg,#43d477,#28a745); }
.fill-medium { background: linear-gradient(90deg,#ffdd57,#ffc107); }
.fill-low    { background: linear-gradient(90deg,#ff6b6b,#dc3545); }

.stat-nums  { font-size:.82rem; font-weight:700; white-space:nowrap; }
.stat-pct   { font-size:1.1rem; font-weight:900; width:52px; text-align:right; white-space:nowrap; }

.stat-pct.high   { color:#28a745; }
.stat-pct.medium { color:#d69e2e; }
.stat-pct.low    { color:#dc3545; }

/* Answer breakdown grid (MCQ choices etc.) */
.choices-grid {
    display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;
}
.choice-pill {
    padding: 5px 12px; border-radius: 20px; font-size: .82rem; font-weight: 600;
    border: 1px solid #dee2e6; background: #f8f9fa; color: #495057;
}
.choice-pill.is-correct { background:#d4edda; border-color:#28a745; color:#155724; }

/* Badges */
.type-badge {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700;
    white-space:nowrap;
}
.type-mcq    { background:#e8f4fd; color:#1565c0; }
.type-tf     { background:#e8f5e9; color:#2e7d32; }
.type-ident  { background:#fce4ec; color:#ad1457; }
.type-jumble { background:#fff3e0; color:#e65100; }

.diff-badge  {
    padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; white-space:nowrap;
}
.diff-easy   { background:#d4edda; color:#155724; }
.diff-medium { background:#fff3cd; color:#856404; }
.diff-hard   { background:#f8d7da; color:#721c24; }

/* Empty / loading states */
.empty-state { text-align:center; padding:60px 24px; color:#6c757d; }
.empty-state i { font-size:3.5rem; opacity:.3; display:block; margin-bottom:14px; }

/* Print */
@media print {
    .sidebar, .sidebar-toggle, .btn-back-text, .filter-bar, .btn-print { display:none!important; }
    .main-content { margin-left:0!important; padding:0; }
    .q-card { break-inside:avoid; }
}

@media (max-width:991.98px) {
    .main-content { margin-left:0; padding:1rem; }
    .page-header-banner { flex-direction:column; gap:12px; text-align:center; }
}
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">

        <!-- ── Header ──────────────────────────────────────────────────── -->
        <div class="page-header-banner">
            <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                <a href="level_details.php?level=<?= htmlspecialchars($hdr['level_id']) ?>"
                   class="btn-back-text">
                    <i class="bi bi-arrow-left"></i> BACK
                </a>
                <div>
                    <h4 class="m-0">
                        Item Analysis &mdash; <?= htmlspecialchars($hdr['assessment_title']) ?>
                    </h4>
                    <div style="font-size:.8rem;opacity:.8;margin-top:3px;">
                        Aralin <?= (int)$hdr['aralin_no'] ?>:
                        <?= htmlspecialchars($hdr['aralin_title']) ?>
                        &bull; <?= htmlspecialchars($markahan) ?>
                    </div>
                </div>
            </div>
            <button onclick="window.print()"
                    class="btn btn-light btn-sm fw-bold shadow-sm btn-print mt-1">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>

        <!-- ── Summary strip (filled by JS) ───────────────────────────── -->
        <div class="summary-strip">
            <div class="summary-card sky">
                <div class="val" id="stat-takers">—</div>
                <div class="lbl">Students Who Took It</div>
            </div>
            <div class="summary-card">
                <div class="val" id="stat-questions">—</div>
                <div class="lbl">Total Questions</div>
            </div>
            <div class="summary-card green">
                <div class="val" id="stat-avg">—</div>
                <div class="lbl">Avg % Correct</div>
            </div>
            <div class="summary-card red">
                <div class="val" id="stat-hard">—</div>
                <div class="lbl">Difficult Items (&lt;50%)</div>
            </div>
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────── -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Type</label>
                <select id="filter-type" class="form-select form-select-sm">
                    <option value="all">All Types</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True or False</option>
                    <option value="identification">Identification</option>
                    <option value="jumbled_word">Jumbled Word</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Difficulty</label>
                <select id="filter-diff" class="form-select form-select-sm">
                    <option value="all">All Levels</option>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Pass Rate</label>
                <select id="filter-rate" class="form-select form-select-sm">
                    <option value="all">All Items</option>
                    <option value="high">High (&ge;75%)</option>
                    <option value="medium">Medium (50–74%)</option>
                    <option value="low">Difficult (&lt;50%)</option>
                </select>
            </div>
            <button class="btn-filter-reset" id="btn-reset">
                <i class="bi bi-x-circle me-1"></i> Reset
            </button>
        </div>

        <!-- ── Record count ────────────────────────────────────────────── -->
        <div class="record-count">
            Showing <strong id="visible-count">0</strong>
            of <strong id="total-count">0</strong> questions
        </div>

        <!-- ── Question list ───────────────────────────────────────────── -->
        <div id="questions-container">
            <div class="empty-state">
                <div class="spinner-border text-secondary" role="status"
                     style="width:2.5rem;height:2.5rem;border-width:.25em;"></div>
                <p class="mt-3 fw-bold">Loading item analysis…</p>
            </div>
        </div>

    </div><!-- /main-content -->
</div><!-- /dashboard-wrapper -->

<?php include("components/footer-scripts.php"); ?>
<script>
$(document).ready(function () {

    // ── Sidebar toggle ──────────────────────────────────────────────────
    $(document).off('click', '.sidebar-toggle');
    $(document).on('click', '.sidebar-toggle', function (e) {
        e.preventDefault(); e.stopPropagation();
        $('.dashboard-wrapper').toggleClass('toggled');
    });
    $('a.nav-link-custom[href="levels.php"]').addClass('active');

    // ── State ───────────────────────────────────────────────────────────
    const assessmentId = $('#hidden_assessment_id').val();
    let allQuestions   = [];

    // ── Helper: type badge ──────────────────────────────────────────────
    const typeBadge = (type) => {
        const map = {
            multiple_choice : ['MCQ',    'type-mcq'],
            true_false      : ['T / F',  'type-tf'],
            identification  : ['Ident',  'type-ident'],
            jumbled_word    : ['Jumble', 'type-jumble'],
        };
        const [label, cls] = map[type] || [type, ''];
        return `<span class="type-badge ${cls}">${label}</span>`;
    };

    // ── Helper: difficulty badge ────────────────────────────────────────
    const diffBadge = (d) => {
        const map = { easy:'diff-easy', medium:'diff-medium', hard:'diff-hard' };
        return `<span class="diff-badge ${map[d] || ''}">${(d||'').toUpperCase()}</span>`;
    };

    // ── Helper: rate class ──────────────────────────────────────────────
    const rateClass = (pct) =>
        pct >= 75 ? 'high' : pct >= 50 ? 'medium' : 'low';

    // ── Helper: fill class ──────────────────────────────────────────────
    const fillClass = (pct) =>
        pct >= 75 ? 'fill-high' : pct >= 50 ? 'fill-medium' : 'fill-low';

    // ── Render one question card ────────────────────────────────────────
    const renderCard = (q, idx) => {
        const rate  = rateClass(q.percent_correct);
        const fill  = fillClass(q.percent_correct);
        const pctCl = rate; // same classes for color

        // MCQ choices
        let choicesHtml = '';
        if (q.type === 'multiple_choice' && q.choices) {
            const choicesObj = typeof q.choices === 'string'
                ? JSON.parse(q.choices) : q.choices;
            const correct = (q.correct_answer || '').toUpperCase().trim();

            choicesHtml = '<div class="choices-grid">';
            Object.entries(choicesObj).forEach(([letter, text]) => {
                const isCorrect = letter.toUpperCase() === correct;
                choicesHtml += `
                    <span class="choice-pill ${isCorrect ? 'is-correct' : ''}">
                        ${isCorrect ? '<i class="bi bi-check-circle-fill me-1"></i>' : ''}
                        <strong>${letter}.</strong> ${escHtml(text)}
                    </span>`;
            });
            choicesHtml += '</div>';
        } else if (q.type === 'true_false') {
            const isTama = ['1','true','tama'].includes(
                (q.correct_answer || '').toLowerCase().trim()
            );
            choicesHtml = `
                <div class="choices-grid">
                    <span class="choice-pill ${isTama  ? 'is-correct' : ''}">
                        ${isTama  ? '<i class="bi bi-check-circle-fill me-1"></i>' : ''}
                        True (Tama)
                    </span>
                    <span class="choice-pill ${!isTama ? 'is-correct' : ''}">
                        ${!isTama ? '<i class="bi bi-check-circle-fill me-1"></i>' : ''}
                        False (Mali)
                    </span>
                </div>`;
        } else {
            // identification / jumbled
            choicesHtml = `
                <div class="mt-2" style="font-size:.85rem;color:#495057;">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    <strong>Answer:</strong> ${escHtml(q.correct_answer)}
                </div>`;
        }

        // No takers yet
        const noData = q.total_answers === 0;

        return `
        <div class="q-card rate-${rate}"
             data-type="${q.type}"
             data-diff="${q.difficulty}"
             data-rate="${rate}">

            <div class="q-card-header">
                <span class="q-num">#${idx}</span>
                ${typeBadge(q.type)}
                ${diffBadge(q.difficulty)}
                <span class="q-text">${escHtml(q.question_text)}</span>
            </div>

            <div class="q-card-body">
                ${choicesHtml}

                ${noData
                    ? `<p class="text-muted mt-3 mb-0" style="font-size:.85rem;">
                           <i class="bi bi-info-circle me-1"></i>
                           No student answers recorded yet.
                       </p>`
                    : `<div class="stat-row mt-2">
                           <div class="stat-bar-wrap">
                               <div class="stat-bar-fill ${fill}"
                                    style="width:${q.percent_correct}%"></div>
                           </div>
                           <span class="stat-nums text-success">
                               <i class="bi bi-check2 me-1"></i>${q.correct_count} correct
                           </span>
                           <span class="stat-nums text-danger">
                               <i class="bi bi-x me-1"></i>${q.wrong_count} wrong
                           </span>
                           <span class="stat-pct ${pctCl}">${q.percent_correct}%</span>
                       </div>`
                }
            </div>
        </div>`;
    };

    // ── Render filtered list ────────────────────────────────────────────
    const applyFilters = () => {
        const typeF = $('#filter-type').val();
        const diffF = $('#filter-diff').val();
        const rateF = $('#filter-rate').val();

        const filtered = allQuestions.filter(q => {
            if (typeF !== 'all' && q.type !== typeF)               return false;
            if (diffF !== 'all' && q.difficulty !== diffF)         return false;
            if (rateF !== 'all' && rateClass(q.percent_correct) !== rateF) return false;
            return true;
        });

        $('#visible-count').text(filtered.length);

        if (filtered.length === 0) {
            $('#questions-container').html(`
                <div class="empty-state">
                    <i class="bi bi-funnel"></i>
                    <p class="fw-bold">No questions match your filters.</p>
                </div>`);
            return;
        }

        $('#questions-container').html(
            filtered.map((q, i) => renderCard(q, i + 1)).join('')
        );
    };

    // ── Load data ───────────────────────────────────────────────────────
    $.ajax({
        type    : 'POST',
        url     : '../backend/api/web/item_analysis.php',
        data    : { requestType: 'GetItemAnalysis', assessment_id: assessmentId },
        dataType: 'json',
        success : function (res) {
            if (res.status !== 'success') {
                $('#questions-container').html(`
                    <div class="empty-state text-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <p class="fw-bold">${res.message || 'Failed to load data.'}</p>
                    </div>`);
                return;
            }

            allQuestions = res.questions || [];

            // ── Summary strip
            $('#stat-takers').text(res.total_takers);
            $('#stat-questions').text(res.total_questions);
            $('#stat-avg').text(res.avg_percent_correct + '%');
            $('#stat-hard').text(res.hard_question_ids.length);
            $('#total-count').text(res.total_questions);

            applyFilters();
        },
        error : function (xhr) {
            console.error(xhr.responseText);
            $('#questions-container').html(`
                <div class="empty-state text-danger">
                    <i class="bi bi-wifi-off"></i>
                    <p class="fw-bold">Server error. Check console.</p>
                </div>`);
        },
    });

    // ── Filter events ───────────────────────────────────────────────────
    $('#filter-type, #filter-diff, #filter-rate').on('change', applyFilters);

    $('#btn-reset').on('click', function () {
        $('#filter-type, #filter-diff, #filter-rate').val('all');
        applyFilters();
    });

    // ── Escape helper ───────────────────────────────────────────────────
    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
</script>

<?php include("components/footer.php"); ?>