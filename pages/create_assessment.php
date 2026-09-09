<?php 
include("components/header.php"); 

$aralin_id = isset($_GET['aralin']) ? $_GET['aralin'] : null;
$aralinText = "Unknown"; // Default value
$aralin_no = "?"; // Default variable for the Aralin Number

if ($aralin_id) {
    try {
        if (isset($AuthController) && method_exists($AuthController, 'GetUsingId')) {
            $aralinResult = $AuthController->GetUsingId("aralin", $aralin_id);
            
            if ($aralinResult && is_object($aralinResult) && $aralinResult->num_rows > 0) {
                $aralin = $aralinResult->fetch_assoc();
                
                $aralinText = isset($aralin['aralin_title']) ? $aralin['aralin_title'] : "Aralin";
                
                // Fetch the actual aralin_no from the database row
                // (Assuming your column is named 'aralin_no'. Change it if it is named something else)
                $aralin_no = isset($aralin['aralin_no']) ? $aralin['aralin_no'] : "?"; 
                $level_id = isset($aralin['level_id']) ? $aralin['level_id'] : "";
                
            } else {
                echo "<script>window.location.href='levels.php';</script>";
                exit();
            }
        }
    } catch (Exception $e) {
        // Prevent crash on DB error
        echo "<script>window.location.href='levels.php';</script>";
        exit();
    }
} else {
    echo "<script>window.location.href='levels.php';</script>";
    exit();
}
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_aralin_id" value="<?= htmlspecialchars($aralin_id) ?>">
<input type="hidden" id="hidden_assessment_id" name="assessment_id" value="">

<style>
    /* --- LAYOUT & RESET --- */
    nav.navbar { display: none !important; } 
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; width: 100%; min-height: 100vh; overflow-x: hidden; }
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; background-color: #f8f9fa; transition: margin-left 0.3s ease-in-out; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

    /* --- SIDEBAR FIXES --- */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; color: white; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107 !important; color: #440101 !important; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; cursor: pointer; }

    /* --- PAGE HEADER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
        font-size: 1.5rem; font-weight: 700; text-transform: uppercase;
    }
    .header-text { display: flex; align-items: center; }
    .header-text i { margin-right: 15px; font-size: 1.8rem; }
    
    /* Back Button Style */
    .btn-back-text {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 50px;
        text-decoration: none;
        text-transform: uppercase;
        border: 1px solid rgba(255, 255, 255, 0.4);
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-back-text:hover {
        background-color: white;
        color: #a71b1b;
        transform: scale(1.02);
    }

    /* --- TITLES & INPUTS --- */
    .section-title {
        font-weight: 800; color: #a71b1b; text-transform: uppercase; 
        font-size: 1.1rem; letter-spacing: 0.5px; text-align: center;
        margin-bottom: 25px; margin-top: 10px;
    }
    .form-label { font-weight: 600; color: #444; }
    
    #assessment_title, #assessment_description {
        background-color: #d9d9d9; border: 1px solid #bbb; color: #000;
    }
    .form-control:focus { border-color: #a71b1b; box-shadow: 0 0 0 0.2rem rgba(167, 27, 27, 0.25); }

    /* --- QUESTION CARDS --- */
    .q-card {
        background: #d9d9d9; border: 1px solid #dee2e6; border-radius: 8px; 
        padding: 25px 20px; height: 100%; display: flex; flex-direction: column; 
        justify-content: center; transition: transform 0.2s; text-align: center;
    }
    .q-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: #a71b1b; }
    
    .q-card-title {
        font-weight: 800; color: #a71b1b; text-transform: uppercase; 
        font-size: 1rem; letter-spacing: 0.5px; margin-bottom: 20px;
    }

    /* --- BUTTONS & FILE INPUTS --- */
    .btn-create-manual { 
        background-color: #a71b1b; color: white; border: 1px solid #a71b1b;
        font-weight: 600; padding: 6px 12px; border-radius: 4px; 
        transition: 0.2s; white-space: nowrap; font-size: 0.9rem;
    }
    .btn-create-manual:hover { background-color: #880f0b; color: white; border-color: #880f0b; }

    .custom-file-input {
        border: 1px solid #a71b1b; border-radius: 4px; color: #a71b1b;
        font-size: 0.85rem; background-color: white; padding: 0;
    }
    .custom-file-input::file-selector-button {
        background-color: #a71b1b; color: white; border: none;
        border-right: 1px solid #880f0b; padding: 7px 12px;
        margin-right: 10px; font-weight: 600; cursor: pointer; transition: 0.2s;
    }
    .custom-file-input:hover::file-selector-button { background-color: #880f0b; }

    /* --- PREVIEW LIST --- */
    .added-question-item { 
        border-left: 4px solid #a71b1b; 
        background: #fff; 
        padding: 15px; 
        margin-bottom: 10px;
        border-radius: 4px; 
        position: relative; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        height: 100%; 
    }
    .btn-remove-q { position: absolute; top: 10px; right: 10px; color: #dc3545; border: none; background: none; font-size: 1.1rem; }

    /* --- MODALS UI --- */
    .modal-header-custom { background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%); color: white; padding: 15px 20px; }
    .modal-title { font-weight: 700; letter-spacing: 0.5px; }
    .choice-item { display: flex; align-items: center; background-color: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px 12px; margin-bottom: 10px; transition: all 0.2s; cursor: pointer; }
    .choice-item:hover { border-color: #adb5bd; background-color: #f8f9fa; }
    .choice-item.active-choice { border-color: #a71b1b; background-color: rgba(167, 27, 27, 0.03); box-shadow: 0 0 0 1px #a71b1b inset; }
    .form-check-input.choice-radio { width: 1.3em; height: 1.3em; margin-right: 12px; cursor: pointer; border: 2px solid #adb5bd; }
    .form-check-input.choice-radio:checked { background-color: #a71b1b; border-color: #a71b1b; }
    .choice-letter { font-weight: 800; color: #6c757d; width: 25px; margin-right: 8px; }
    .choice-item.active-choice .choice-letter { color: #a71b1b; }
    .choice-input { border: none; background: transparent; width: 100%; font-weight: 500; color: #333; outline: none; }
    
    .btn-submit { background-color: #a71b1b; color: white; padding: 12px 30px; font-weight: bold; border: none; border-radius: 5px; transition: background 0.2s; }
    .btn-submit:hover { background-color: #880f0b; color: white; }
    .btn-main { background-color: #a71b1b; color: white; border: none; }
    .btn-main:hover { background-color: #880f0b; color: white; }

    @media (max-width: 991.98px) { .main-content { margin-left: 0; padding: 1rem; } .page-header-banner { flex-direction: column; gap: 15px; text-align: center; } }
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <div class="page-header-banner">
    
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <a href="level_details.php?level=<?= htmlspecialchars($level_id) ?>" class="btn-back-text">
                    BACK
                </a>
                <h4 class="m-0 fw-bold text-uppercase">
                    Assessment para sa <?= htmlspecialchars($aralinText) ?>
                </h4>
            </div>

            <div class="header-right"></div>

        </div>

        <form id="create-assessment-form" enctype="multipart/form-data">
            
            <div class="mb-5 px-md-3">
                <h5 class="section-title">Assessment Details</h5>
                
                <div class="alert d-flex align-items-center mb-4" role="alert" style="background-color: #e3f2fd; border: 1px solid #90caf9; color: #0d47a1; border-radius: 8px; padding: 12px 20px;">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        Gumagawa ka ng pagsusulit para sa <strong>Aralin <?= htmlspecialchars($aralin_no) ?>: <?= htmlspecialchars($aralinText) ?></strong>.
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary fw-bold text-uppercase fs-7">Title</label>
                    <input type="text" class="form-control" id="assessment_title" name="title" placeholder="e.g. Unang Pagsusulit" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="assessment_description" name="description" rows="2" placeholder="Instructions..."></textarea>
                </div>
            </div>

            <div id="questions-preview-container" class="mb-4 d-none px-md-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-secondary m-0">ADDED QUESTIONS (<span id="q-count">0</span>)</h6>             
                    <div class="d-flex gap-2">
                        <select id="question-filter" class="form-select form-select-sm border-secondary" style="width: 150px;">
                            <option value="ALL">All Types</option>
                            <option value="MCQ">Multiple Choice</option>
                            <option value="TF">Fact or Bluff</option>
                            <option value="IDENT">Identification</option>
                            <option value="JUMBLED">Jumbled Words</option>
                        </select>
                        <select id="difficulty-filter" class="form-select form-select-sm border-secondary" style="width: 120px;">
                            <option value="ALL">All Levels</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Avg</option>
                            <option value="hard">Difficult</option>
                        </select>
                    </div>
                </div>

                <div id="questions-list" class="row g-3"></div>
            </div>

            <h5 class="section-title">Manage Questions</h5>
            
            <div id="empty-state" class="text-center p-5 border rounded mb-4" style="background-color: #e9ecef; border: 2px dashed #bbb !important;">
                <i class="bi bi-folder-x text-secondary" style="font-size: 4rem; opacity: 0.5;"></i>
                <h5 class="text-secondary fw-bold mt-3">No Questions Uploaded Yet</h5>
                <p class="text-muted small mb-0">Create the assessment details above, save it, and then upload your CSV file.</p>
            </div>

            <div class="q-card text-center p-4 border-danger shadow-sm">
                <h6 class="fw-bold text-danger text-uppercase mb-3">
                    <i class="bi bi-file-earmark-spreadsheet-fill fs-4 me-2"></i> Bulk Upload via CSV
                </h6>
                <p class="small text-muted mb-4">
                    Upload your 6-column CSV file. Click <strong>Preview</strong> first to validate your data,
                    then <strong>Confirm Upload</strong> to insert.
                </p>

                <div class="d-flex justify-content-center align-items-center gap-3 mb-3">
                    <input class="form-control form-control-sm custom-file-input w-50"
                        type="file" id="bulk_csv_file" accept=".csv"
                        title="Upload Questions CSV">
                    <button type="button" class="btn btn-create-manual px-4" id="btn-preview-csv">
                        <i class="bi bi-eye me-2"></i> Step 2: Preview CSV
                    </button>
                </div>

                <!-- Preview results panel (hidden until preview runs) -->
                <div id="csv-preview-panel" class="d-none text-start mt-3">
                    <div id="csv-preview-summary" class="alert mb-3"></div>

                    <div id="csv-errors-block" class="d-none mb-3">
                        <h6 class="text-danger fw-bold">
                            <i class="bi bi-x-circle me-1"></i> Errors (must fix before uploading)
                        </h6>
                        <ul id="csv-errors-list" class="small text-danger mb-0"></ul>
                    </div>

                    <div id="csv-warnings-block" class="d-none mb-3">
                        <h6 class="text-warning fw-bold">
                            <i class="bi bi-exclamation-triangle me-1"></i> Warnings (rows skipped)
                        </h6>
                        <ul id="csv-warnings-list" class="small text-warning mb-0"></ul>
                    </div>

                    <div id="csv-valid-block" class="d-none">
                        <h6 class="text-success fw-bold">
                            <i class="bi bi-check-circle me-1"></i> Valid Rows Preview
                        </h6>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-bordered" id="csv-preview-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>Difficulty</th>
                                        <th>Question</th>
                                        <th>Answer</th>
                                    </tr>
                                </thead>
                                <tbody id="csv-preview-tbody"></tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-success mt-3 w-100 fw-bold"
                                id="btn-confirm-upload">
                            <i class="bi bi-cloud-arrow-up-fill me-2"></i>
                            Step 3: Confirm Upload (<span id="confirm-count">0</span> rows)
                        </button>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end pb-5 border-top pt-4">
                <a href="level_details.php?level=<?= htmlspecialchars($level_id) ?>" class="btn btn-light me-2 border py-2 px-4">Cancel</a>
                <button type="submit" class="btn btn-submit shadow px-4">
                    <i class="bi bi-check-circle me-2"></i> Step 1: Save Details
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalMCQ" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-list-ul me-2"></i> Create Multiple Choice Question</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="formMCQ">
            <div class="mb-4">
                <label class="form-label text-secondary fw-bold text-uppercase fs-7">Question</label>
                <textarea class="form-control" id="mcq_question" rows="3" placeholder="Type your question..." style="font-size: 1.1rem; border-color: #ced4da;" required></textarea>
            </div>
            <label class="form-label text-secondary fw-bold text-uppercase fs-7 mb-2">Answer Options</label>
            <div class="choice-item" onclick="selectRadio('A')">
                <input class="form-check-input choice-radio" type="radio" name="mcq_correct" value="A" id="radioA">
                <span class="choice-letter">A.</span>
                <input type="text" class="choice-input" id="mcq_a" placeholder="Option A" required>
            </div>
            <div class="choice-item" onclick="selectRadio('B')">
                <input class="form-check-input choice-radio" type="radio" name="mcq_correct" value="B" id="radioB">
                <span class="choice-letter">B.</span>
                <input type="text" class="choice-input" id="mcq_b" placeholder="Option B" required>
            </div>
            <div class="choice-item" onclick="selectRadio('C')">
                <input class="form-check-input choice-radio" type="radio" name="mcq_correct" value="C" id="radioC">
                <span class="choice-letter">C.</span>
                <input type="text" class="choice-input" id="mcq_c" placeholder="Option C" required>
            </div>
            <div class="choice-item" onclick="selectRadio('D')">
                <input class="form-check-input choice-radio" type="radio" name="mcq_correct" value="D" id="radioD">
                <span class="choice-letter">D.</span>
                <input type="text" class="choice-input" id="mcq_d" placeholder="Option D" required>
            </div>
        </form>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-main px-4 fw-bold shadow-sm" onclick="saveQuestion('MCQ')">Add Question</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTF" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-toggle-on me-2"></i> Fact or Bluff</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="formTF">
            <div class="mb-4">
                <label class="form-label text-secondary fw-bold text-uppercase fs-7">Question</label>
                <textarea class="form-control" id="tf_question" rows="3" placeholder="Type your question..." style="font-size: 1.1rem;" required></textarea>
            </div>
            <label class="form-label text-secondary fw-bold text-uppercase fs-7 mb-2">Correct Answer</label>
            <div class="choice-item" onclick="selectRadio('True')">
                <input class="form-check-input choice-radio" type="radio" name="tf_correct" value="True" id="radioTrue">
                <span class="choice-letter text-success">F</span>
                <span class="fw-bold text-secondary">Fact</span>
            </div>
            <div class="choice-item" onclick="selectRadio('False')">
                <input class="form-check-input choice-radio" type="radio" name="tf_correct" value="False" id="radioFalse">
                <span class="choice-letter text-danger">B</span>
                <span class="fw-bold text-secondary">Bluff</span>
            </div>
        </form>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-main px-4 fw-bold shadow-sm" onclick="saveQuestion('TF')">Add Question</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalIdent" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-input-cursor-text me-2"></i> Identification</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="formIdent">
            <div class="mb-4">
                <label class="form-label text-secondary fw-bold text-uppercase fs-7">Question</label>
                <textarea class="form-control" id="ident_question" rows="3" placeholder="Type the question..." style="font-size: 1.1rem;" required></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label text-secondary fw-bold text-uppercase fs-7">Correct Answer</label>
                <input type="text" class="form-control py-2 fw-bold text-main" id="ident_answer" placeholder="Enter exact answer" style="font-size: 1.1rem;" required>
            </div>
        </form>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-main px-4 fw-bold shadow-sm" onclick="saveQuestion('IDENT')">Add Question</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalJumbled" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header modal-header-custom">
        <h5 class="modal-title"><i class="bi bi-sort-alpha-down me-2"></i> Jumbled Words</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="formJumbled">
            <div class="mb-4">
                <label class="form-label text-secondary fw-bold text-uppercase fs-7">Instruction / Hint</label>
                <textarea class="form-control" id="jumbled_question" rows="2" placeholder="e.g. Arrange the letters..." style="font-size: 1.1rem;" required></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label text-secondary fw-bold text-uppercase fs-7">Correct Word</label>
                <input type="text" class="form-control py-2 fw-bold text-main" id="jumbled_answer" placeholder="e.g. ELEPHANT" style="font-size: 1.2rem; letter-spacing: 1px;" required>
            </div>
        </form>
      </div>
      <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-main px-4 fw-bold shadow-sm" onclick="saveQuestion('JUMBLED')">Add Question</button>
      </div>
    </div>
  </div>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="scripts/create_assessment.js?v=<?= time() ?>"></script>
<script>
    $(document).ready(function () {
        // Sidebar Toggle
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault(); e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });
        // Active State
        $('a.nav-link-custom[href="levels.php"]').addClass('active');
    });
</script>
<?php include("components/footer.php"); ?>