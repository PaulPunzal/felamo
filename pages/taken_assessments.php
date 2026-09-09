<?php 
include("components/header.php"); 

$level_id = isset($_GET['level']) ? $_GET['level'] : null;
$levelText = "Unknown";

if ($level_id) {
    try {
        if (isset($AuthController) && method_exists($AuthController, 'GetUsingId')) {
            $levelResult = $AuthController->GetUsingId("levels", $level_id);
            
            if ($levelResult && is_object($levelResult) && $levelResult->num_rows > 0) {
                $levelData = $levelResult->fetch_assoc();
                
                $levelNum = $levelData['level'];
                $ordinalMap = [ 1 => "Unang", 2 => "Ikalawang", 3 => "Ikatlong", 4 => "Ika-apat na" ];
                $levelText = isset($ordinalMap[$levelNum]) ? $ordinalMap[$levelNum] : $levelNum;

            } else {
                echo "<script>window.location.href='levels.php';</script>";
                exit();
            }
        }
    } catch (Exception $e) {
        echo "<script>window.location.href='levels.php';</script>";
        exit();
    }
} else {
    echo "<script>window.location.href='levels.php';</script>";
    exit();
}
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_level_id" value="<?= htmlspecialchars($level_id) ?>">

<style>
    /* --- RESET & LAYOUT --- */
    nav.navbar { display: none !important; } 
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; width: 100%; min-height: 100vh; overflow-x: hidden; }
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; background-color: #f8f9fa; transition: margin-left 0.3s ease-in-out; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

    /* --- SIDEBAR --- */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; color: white; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107 !important; color: #440101 !important; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; cursor: pointer; }

    /* --- HEADER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
        font-size: 1.5rem; font-weight: 700; text-transform: uppercase;
    }
    .btn-back-text {
        background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.5);
        font-size: 0.9rem; font-weight: 600; padding: 8px 20px; border-radius: 50px; text-decoration: none;
        transition: all 0.2s; display: flex; align-items: center; gap: 8px;
    }
    .btn-back-text:hover { background-color: white; color: #a71b1b; }

    /* --- FILTER BAR --- */
    .filter-bar {
        background: white;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid #dee2e6;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 16px;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
        min-width: 160px;
    }
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.5px;
    }
    .filter-group .form-control,
    .filter-group .form-select {
        font-size: 0.875rem;
        border-color: #dee2e6;
        border-radius: 6px;
    }
    .filter-group .form-control:focus,
    .filter-group .form-select:focus {
        border-color: #a71b1b;
        box-shadow: 0 0 0 0.2rem rgba(167,27,27,0.15);
    }
    .btn-filter-reset {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #495057;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 8px 18px;
        border-radius: 6px;
        transition: 0.2s;
        align-self: flex-end;
        white-space: nowrap;
    }
    .btn-filter-reset:hover { background: #e9ecef; }

    /* --- TABLE --- */
    .table-container {
        background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden; border: 1px solid #dee2e6;
    }
    .custom-table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
    .custom-table thead {
        background-color: #e9ecef; color: #333; font-weight: 800; text-transform: uppercase; font-size: 0.85rem;
    }
    .custom-table th, .custom-table td {
        padding: 14px 20px; vertical-align: middle; border-bottom: 1px solid #f0f0f0;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }
    .text-date { font-size: 0.88rem; color: #6c757d; }

    /* Score badge */
    .score-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .score-pass { background: #d4edda; color: #155724; }
    .score-fail { background: #f8d7da; color: #721c24; }
    .score-zero { background: #e9ecef; color: #6c757d; }

    /* Buttons */
    .btn-action-red {
        background-color: #c92a2a; color: white; border: none; padding: 6px 14px;
        border-radius: 50px; font-size: 0.82rem; font-weight: 600; text-decoration: none;
        transition: background 0.2s; display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-action-red:hover { background-color: #a71b1b; color: white; }

    @media (max-width: 991.98px) { .main-content { margin-left: 0; padding: 1rem; } .page-header-banner { flex-direction: column; gap: 15px; text-align: center; } }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <a href="levels.php" class="btn-back-text">
                    <i class="bi bi-arrow-left"></i> BACK
                </a>
                <h4 class="m-0 fw-bold text-uppercase">
                    Taken Assessments &mdash; <?= htmlspecialchars($levelText) ?> Markahan
                </h4>
            </div>
            <div class="header-right">
                <button type="button" id="btn-print-report" class="btn-back-text" style="border:none; cursor:pointer;">
                    <i class="bi bi-printer-fill"></i> PRINT REPORT
                </button>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="bi bi-card-checklist me-1"></i>Assessment Title</label>
                <input type="text" id="filter-title" class="form-control" placeholder="Search title...">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-person me-1"></i>Student Name</label>
                <input type="text" id="filter-student" class="form-control" placeholder="Search name...">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-people me-1"></i>Section</label>
                <input type="text" id="filter-section" class="form-control" placeholder="Search section...">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-calendar me-1"></i>Date Taken</label>
                <input type="date" id="filter-date" class="form-control">
            </div>
            <button class="btn-filter-reset" id="btn-reset-filters">
                <i class="bi bi-x-circle me-1"></i> Reset
            </button>
        </div>

        <!-- Record count -->
        <div class="mb-3 text-muted" style="font-size: 0.85rem;">
            Showing <strong id="visible-count">0</strong> of <strong id="total-count">0</strong> records
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Assessment Title</th>
                            <th style="width: 20%;">Student Name</th>
                            <th style="width: 15%;">Section</th>
                            <th style="width: 13%;">Date Taken</th>
                            <th style="width: 15%;">Score</th>
                            <th style="width: 12%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="taken-assessments-list">
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="spinner-border text-secondary" role="status" style="width: 2rem; height: 2rem;"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
$(document).ready(function () {
    const level_id   = $("#hidden_level_id").val();
    const levelText  = <?= json_encode($levelText . " Markahan") ?>;
    const preparedBy = <?= json_encode(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>;
    let allData      = []; // store full dataset for client-side filtering
    let lastFiltered = []; // store whatever is currently visible, for printing

    // ── Sidebar toggle ──────────────────────────────────────────────────────
    $(document).off('click', '.sidebar-toggle');
    $(document).on('click', '.sidebar-toggle', function(e) {
        e.preventDefault(); e.stopPropagation(); 
        $(".dashboard-wrapper").toggleClass("toggled");
    });
    $('a.nav-link-custom[href="levels.php"]').addClass('active');

    // ── Load data ───────────────────────────────────────────────────────────
    const loadTakenAssessments = () => {
        $("#taken-assessments-list").html(`
            <tr>
                <td colspan="6" class="text-center py-5">
                    <div class="spinner-border text-secondary" role="status" style="width:2rem;height:2rem;border-width:.25em;"></div>
                </td>
            </tr>
        `);

        $.ajax({
            type: "POST",
            url: "../backend/api/web/taken_assessment.php",
            data: { 
                requestType: "GetTakenAssessments", 
                level_id:    level_id,
                filter:      "all"    // always fetch all; client-side filtering handles the rest
            },
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    allData = response.data || [];
                    updateStats(allData);
                    applyFilters();
                } else {
                    showError(response.message || "Failed to load data.");
                }
            },
            error: function (xhr) {
                console.error("AJAX Error:", xhr.responseText);
                showError("Server error. Please try again.");
            }
        });
    };

    // ── Render a filtered/sorted subset ─────────────────────────────────────
    const renderRows = (data) => {
        $("#total-count").text(allData.length);
        $("#visible-count").text(data.length);

        if (!data || data.length === 0) {
            $("#taken-assessments-list").html(`
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted fw-bold">
                        <i class="bi bi-inbox fs-4 d-block mb-2 opacity-50"></i>
                        No records match your filters.
                    </td>
                </tr>
            `);
            return;
        }

        let html = "";
        data.forEach((item) => {
            const title       = item.assessment_title || item.aralin_title || "Untitled";
            const studentName = item.student_name || "Unknown";
            const sectionName = item.section_name || "No Section";
            const score       = parseInt(item.points) || 0;
            const total       = parseInt(item.total)  || 0;
            const id          = item.id || item.assessment_id;

            // Date formatting
            let dateTaken = "N/A";
            if (item.created_at) {
                const d = new Date(item.created_at);
                if (!isNaN(d)) {
                    dateTaken = d.toLocaleDateString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric'
                    });
                }
            }

            // Score badge — show 0/0 only when total truly is 0
            let badgeClass = "score-zero";
            let pct        = 0;
            let scoreLabel = total > 0 ? `${score} / ${total}` : "Pending";

            if (total > 0) {
                pct = (score / total) * 100;
                badgeClass = pct >= 80 ? "score-pass" : "score-fail";
                scoreLabel = `${score} / ${total} (${Math.round(pct)}%)`;
            }

            html += `
                <tr data-title="${escapeHtml(title.toLowerCase())}"
                    data-student="${escapeHtml(studentName.toLowerCase())}"
                    data-section="${escapeHtml(sectionName.toLowerCase())}"
                    data-date="${item.created_at ? item.created_at.substring(0, 10) : ''}"
                    data-pct="${pct}">
                    <td class="fw-semibold">${escapeHtml(title)}</td>
                    <td>${escapeHtml(studentName)}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(sectionName)}</span></td>
                    <td class="text-date">${dateTaken}</td>
                    <td>
                        <span class="score-badge ${badgeClass}">
                            ${total > 0 ? (pct >= 80 ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-x-circle-fill me-1"></i>') : '<i class="bi bi-hourglass-split me-1"></i>'}
                            ${scoreLabel}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="view_result.php?id=${escapeHtml(String(id))}" class="btn-action-red">
                            <i class="bi bi-eye-fill"></i> View
                        </a>
                    </td>
                </tr>
            `;
        });

        $("#taken-assessments-list").html(html);
    };

    // ── Client-side filtering ────────────────────────────────────────────────
    const applyFilters = () => {
        const titleQ   = $("#filter-title").val().trim().toLowerCase();
        const studentQ = $("#filter-student").val().trim().toLowerCase();
        const sectionQ = $("#filter-section").val().trim().toLowerCase();
        const dateQ    = $("#filter-date").val();      // YYYY-MM-DD
        const resultQ  = $("#filter-result") ? $("#filter-result").val() : "all"; // Fallback if result filter exists

        const filtered = allData.filter(item => {
            const title       = (item.assessment_title || item.aralin_title || "").toLowerCase();
            const studentName = (item.student_name || "").toLowerCase();
            const sectionName = (item.section_name || "No Section").toLowerCase();
            const dateStr     = item.created_at ? item.created_at.substring(0, 10) : "";
            const score       = parseInt(item.points) || 0;
            const total       = parseInt(item.total)  || 0;
            const pct         = total > 0 ? (score / total) * 100 : 0;

            if (titleQ   && !title.includes(titleQ))         return false;
            if (studentQ && !studentName.includes(studentQ)) return false;
            if (sectionQ && !sectionName.includes(sectionQ)) return false;
            if (dateQ    && dateStr !== dateQ)               return false;

            if (resultQ === "passed" && !(total > 0 && pct >= 80)) return false;
            if (resultQ === "failed" && (total > 0 && pct >= 80))  return false;

            return true;
        });

        lastFiltered = filtered;
        renderRows(filtered);
    };

    // ── Stats summary ────────────────────────────────────────────────────────
    const updateStats = (data) => {
        let passed = 0, failed = 0;
        data.forEach(item => {
            const score = parseInt(item.points) || 0;
            const total = parseInt(item.total)  || 0;
            if (total > 0 && (score / total) >= 0.80) passed++;
            else failed++;
        });
        $("#stat-total").text(data.length);
        $("#stat-passed").text(passed);
    };

    // ── Error helper ─────────────────────────────────────────────────────────
    const showError = (msg) => {
        $("#taken-assessments-list").html(`
            <tr><td colspan="6" class="text-center text-danger py-4 fw-bold">
                <i class="bi bi-exclamation-circle me-2"></i>${escapeHtml(msg)}
            </td></tr>
        `);
    };

    // ── HTML escape ──────────────────────────────────────────────────────────
    const escapeHtml = (str) => {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    };

    // ── PRINT REPORT (Excel-style table report, printed as PDF) ───────────────
    const buildPrintReport = (rows) => {
        const now = new Date();
        const generatedStr = now.toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        }) + ' at ' + now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit'
        });

        // Filename-safe date (used as the default "Save as PDF" filename)
        const pad2 = (n) => String(n).padStart(2, '0');
        const fileDateStr = `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;
        const reportTitle  = `Taken_Assessments_Report_${fileDateStr}`;

        let tableRows = "";
        rows.forEach((item) => {
            const aralinNo    = item.aralin_no ?? "N/A";
            const title       = item.assessment_title || item.aralin_title || "Untitled";
            const lrn         = item.lrn || "N/A";
            const studentName = item.student_name || "Unknown";
            const sectionName = item.section_name || "No Section";
            const score       = parseInt(item.points) || 0;
            const total       = parseInt(item.total)  || 0;
            const scoreLabel  = total > 0 ? `${score} / ${total}` : "Pending";

            let dateTaken = "N/A";
            if (item.created_at) {
                const d = new Date(item.created_at);
                if (!isNaN(d)) {
                    dateTaken = d.toLocaleDateString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric'
                    });
                }
            }

            tableRows += `
                <tr>
                    <td>${escapeHtml(String(aralinNo))}</td>
                    <td>${escapeHtml(title)}</td>
                    <td>${escapeHtml(String(lrn))}</td>
                    <td>${escapeHtml(studentName)}</td>
                    <td>${escapeHtml(sectionName)}</td>
                    <td>${escapeHtml(dateTaken)}</td>
                    <td>${escapeHtml(scoreLabel)}</td>
                </tr>`;
        });

        return `
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>${reportTitle}</title>
        <style>
            @page { size: landscape; margin: 24px; }
            * { box-sizing: border-box; }
            body {
                font-family: Arial, Helvetica, sans-serif;
                color: #212529;
                margin: 0;
                padding: 30px 40px;
            }
            
            .print-tip {
                text-align: center;
                font-size: 11px;
                color: #0d47a1;
                background: #e3f2fd;
                border: 1px solid #90caf9;
                border-radius: 6px;
                padding: 6px 10px;
                margin-bottom: 14px;
            }
            @media print {
                .print-tip { display: none; }
            }
            .report-header { text-align: center; margin-bottom: 6px; }
            
            .report-header h1 {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 2px 0;
                color: #212529;
            }
            .report-header h2 {
                font-size: 15px;
                font-weight: 600;
                margin: 0 0 10px 0;
                color: #495057;
            }
            .report-meta {
                text-align: center;
                font-size: 11px;
                color: #6c757d;
                margin-bottom: 18px;
            }
            .report-summary {
                font-size: 12px;
                font-weight: 700;
                margin-bottom: 12px;
                color: #212529;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }
            thead th {
                background-color: #a71b1b;
                color: #ffffff;
                text-align: left;
                padding: 8px 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                font-size: 10px;
            }
            tbody td {
                padding: 7px 10px;
                border-bottom: 1px solid #e9ecef;
                vertical-align: top;
            }
            tbody tr:nth-child(even) { background-color: #f8f9fa; }
            .print-footer {
                margin-top: 24px;
                font-size: 10px;
                color: #6c757d;
                text-align: right;
            }
        </style>
        </head>
        <body>
            <div class="report-header">
                <h1>Felamo</h1>
                <h2>Taken Assessments Report &mdash; ${escapeHtml(levelText)}</h2>
            </div>
            <div class="report-meta">Generated: ${generatedStr}${preparedBy ? ' &mdash; Printed by: ' + escapeHtml(preparedBy) : ''}</div>
            <div class="print-tip"> For page numbers, click "More settings" in the print dialog and enable "Headers and footers".</div>
            <div class="report-summary">Total Records: ${rows.length}</div>

            <table>
                <thead>
                    <tr>
                        <th>Aralin No.</th>
                        <th>Assessment Title</th>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Section</th>
                        <th>Date Taken</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows || '<tr><td colspan="7" style="text-align:center;padding:20px;">No records found.</td></tr>'}
                </tbody>
            </table>

            <div class="print-footer">Generated by Felamo System</div>
        </body>
        </html>`;
    };

    $("#btn-print-report").on("click", function () {
        const rowsToPrint = lastFiltered.length ? lastFiltered : allData;

        if (!rowsToPrint.length) {
            alert("There are no records to print.");
            return;
        }

        const printWindow = window.open("", "_blank", "width=1100,height=800");
        printWindow.document.open();
        printWindow.document.write(buildPrintReport(rowsToPrint));
        printWindow.document.close();

        printWindow.onload = function () {
            printWindow.focus();
            printWindow.print();
        };
    });

    // ── Events ───────────────────────────────────────────────────────────────
    // Debounce text input filters
    let debounceTimer;
    $("#filter-title, #filter-student, #filter-section").on("input", function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applyFilters, 300);
    });

    $("#filter-date").on("change", applyFilters);

    $("#btn-reset-filters").on("click", function () {
        $("#filter-title").val("");
        $("#filter-student").val("");
        $("#filter-section").val("");
        $("#filter-date").val("");
        if ($("#filter-result").length) $("#filter-result").val("all");
        applyFilters();
    });

    // ── Init ─────────────────────────────────────────────────────────────────
    loadTakenAssessments();
});
</script>

<?php include("components/footer.php"); ?>