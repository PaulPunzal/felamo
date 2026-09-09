const DIFF_LABELS = { easy: 'Easy', medium: 'Avg', hard: 'Difficult' };
let questionsList = []; // Array to store questions temporarily
let editingIndex = -1;  // Tracks which question is currently being edited (-1 means creating new)

$(document).ready(function () {
    
    // --- FETCH EXISTING ASSESSMENT USING ARALIN ID ---
    const aralinId = $("#hidden_aralin_id").val();
    if (aralinId) {
        fetchExistingAssessment(aralinId);
    }

    // --- Listen for dropdown filter changes ---
    $("#question-filter, #difficulty-filter").on("change", function() {
        renderQuestions();
    });

    // --- Reset modal states when closed ---
    $('.modal').on('hidden.bs.modal', function () {
        editingIndex = -1; // Reset editing mode
        $(this).find('form')[0].reset(); // Clear inputs
        $(this).find(".choice-item").removeClass("active-choice"); // Clear UI selection
        $(this).find(".btn-main").text("Add Question"); // Reset button text
    });

    // --- MAIN FORM SUBMIT ---
    $("#create-assessment-form").on("submit", function (e) {
        e.preventDefault();

        const title = $("#assessment_title").val();
        if (!title) { alert("Please enter an Assessment Title."); return; }

        let formData = new FormData(this);
        let assessmentId = $("#hidden_assessment_id").val();

        // REDUNDANCY CHECK - Update if it exists, Create if it doesn't
        if (assessmentId) {
            formData.append('requestType', 'UpdateAssessment');
            formData.append('assessment_id', assessmentId);
        } else {
            formData.append('requestType', 'CreateAssessment');
        }

        // Append Fixed Data
        formData.append('teacher_id', $("#hidden_user_id").val());
        formData.append('aralin_id', $("#hidden_aralin_id").val()); 
        
        // Append Defaults for removed fields
        formData.append('due_date', ''); 
        formData.append('time_limit', 0);
        formData.append('is_active', 0);

        // Append the QUESTIONS LIST as a JSON string
        formData.append('questions_data', JSON.stringify(questionsList));

        // Send AJAX
        $.ajax({
            type: "POST",
            url: "../backend/api/web/asssessments.php", 
            data: formData,
            dataType: "json",
            contentType: false, 
            processData: false, 
            beforeSend: function() {
                $(".btn-submit").prop("disabled", true).html('Saving...');
            },
            success: function (response) {
                $(".btn-submit").prop("disabled", false).html('<i class="bi bi-check-circle me-2"></i> Step 1: Save Details');
                if (response.status === "success") {
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Details Saved!',
                        text: 'Database record created/updated. You can now upload your CSV questions below!',
                        timer: 2500,
                        showConfirmButton: false
                    });
                    
                    // Fetch the newly created ID to unlock the CSV upload button
                    const currentAralinId = $("#hidden_aralin_id").val();
                    fetchExistingAssessment(currentAralinId); 
                    
                } else {
                    alert("Error: " + (response.message || "Unknown error"));
                }
            },
            error: function (xhr) {
                $(".btn-submit").prop("disabled", false).html('<i class="bi bi-check-circle me-2"></i> Step 1: Save Details');
                console.error("Error:", xhr.responseText);
                alert("Server Error.");
            }
        });
    });
});

// --- Fetch existing assessment data using aralin_id ---
function fetchExistingAssessment(aralinId) {
    $.ajax({
        type: "POST",
        url: "../backend/api/web/asssessments.php",
        data: { requestType: 'GetAssessment', aralin_id: aralinId }, 
        dataType: "json",
        success: function(response) {
            if (response.status === "success" && response.data && response.data.length > 0) {
                let assessment = response.data[0]; 
                
                $("#assessment_title").val(assessment.assessment_title);
                $("#assessment_description").val(assessment.description);
                
                let assessmentId = assessment.id; 
                $("#hidden_assessment_id").val(assessmentId);

                fetchQuestionsByType(assessmentId);
            }
        },
        error: function(err) {
            console.log("No existing assessment found for this lesson (or error occurred).", err);
        }
    });
}

// --- Fetch questions from the NEW Unified Table ---
function fetchQuestionsByType(assessmentId) {
    $.ajax({
        type: "POST",
        url: "../backend/api/web/asssessments.php",
        data: { requestType: 'GetUnifiedQuestions', assessment_id: assessmentId },
        dataType: "json",
        success: function(response) {
            if (response.status === "success" && response.data && response.data.length > 0) {
                questionsList = []; 
                response.data.forEach(q => {
                    let qData = { 
                        type: q.type.toUpperCase(), 
                        question: q.question_text, 
                        correct: q.correct_answer,
                        difficulty: (q.difficulty || 'easy').toLowerCase(), 
                        is_existing: true, 
                        id: q.id 
                    };

                    // FIX: Unpack CSV strings or JSON into separate options!
                    if (qData.type === 'MULTIPLE_CHOICE' || qData.type === 'MCQ') {
                        if (q.choices) {
                            try {
                                // 1. Try JSON Parse first (If it was saved via the website interface)
                                let parsed = JSON.parse(q.choices);
                                qData.a = parsed.A || '';
                                qData.b = parsed.B || '';
                                qData.c = parsed.C || '';
                                qData.d = parsed.D || '';
                            } catch (e) {
                                // 2. If JSON fails, slice up the raw CSV string
                                let str = q.choices;
                                let aMatch = str.match(/A:\s*(.*?)(?=\s*,?\s*B:|$)/i);
                                let bMatch = str.match(/B:\s*(.*?)(?=\s*,?\s*C:|$)/i);
                                let cMatch = str.match(/C:\s*(.*?)(?=\s*,?\s*D:|$)/i);
                                let dMatch = str.match(/D:\s*(.*)/i);

                                qData.a = aMatch ? aMatch[1].trim() : '';
                                qData.b = bMatch ? bMatch[1].trim() : '';
                                qData.c = cMatch ? cMatch[1].trim() : '';
                                qData.d = dMatch ? dMatch[1].trim() : '';
                            }

                            // FIX: Map the text answer to the Radio Button Letter (A, B, C, D)
                            qData.correctLetter = '';
                            let ansText = (qData.correct || '').toString().toLowerCase().trim();
                            
                            if (ansText === (qData.a).toLowerCase().trim()) qData.correctLetter = 'A';
                            else if (ansText === (qData.b).toLowerCase().trim()) qData.correctLetter = 'B';
                            else if (ansText === (qData.c).toLowerCase().trim()) qData.correctLetter = 'C';
                            else if (ansText === (qData.d).toLowerCase().trim()) qData.correctLetter = 'D';
                            else if (['a','b','c','d'].includes(ansText)) {
                                qData.correctLetter = ansText.toUpperCase();
                            }
                        }
                    }

                    questionsList.push(qData);
                });
                renderQuestions();
            }
        }
    });
}

// --- HELPER FOR MODAL SELECTION UI ---
function selectRadio(val) {
    let radioBtn = $("#radio" + val);
    radioBtn.prop("checked", true);
    let form = radioBtn.closest("form");
    form.find(".choice-item").removeClass("active-choice");
    radioBtn.closest(".choice-item").addClass("active-choice");
}

$(document).on('change', 'input[type="radio"]', function() {
    let val = $(this).val();
    if ($(this).attr("id") && $(this).attr("id").startsWith("radio")) {
        selectRadio(val);
    }
});

// --- FUNCTION TO SAVE (OR UPDATE) QUESTION FROM MODAL ---
function saveQuestion(type) {
    let qData = { type: type, is_new: true };
    let isValid = true;

    if (type === 'MCQ') {
        qData.question = $("#mcq_question").val();
        qData.difficulty = $("#mcq_difficulty").val();
        qData.a = $("#mcq_a").val();
        qData.b = $("#mcq_b").val();
        qData.c = $("#mcq_c").val();
        qData.d = $("#mcq_d").val();
        
        // FIX: Track the selected letter, but save the FULL TEXT to the database for accuracy!
        qData.correctLetter = $("input[name='mcq_correct']:checked").val();
        if (qData.correctLetter === 'A') qData.correct = qData.a;
        else if (qData.correctLetter === 'B') qData.correct = qData.b;
        else if (qData.correctLetter === 'C') qData.correct = qData.c;
        else if (qData.correctLetter === 'D') qData.correct = qData.d;

        if (!qData.question || !qData.a || !qData.b || !qData.correctLetter) isValid = false;
    } 
    else if (type === 'TF') {
        qData.question = $("#tf_question").val();
        qData.difficulty = $("#mcq_difficulty").val();
        qData.correct = $("input[name='tf_correct']:checked").val();
        if (!qData.question || !qData.correct) isValid = false;
    }
    else if (type === 'IDENT') {
        qData.question = $("#ident_question").val();
        qData.difficulty = $("#mcq_difficulty").val();
        qData.correct = $("#ident_answer").val();
        if (!qData.question || !qData.correct) isValid = false;
    }
    else if (type === 'JUMBLED') {
        qData.question = $("#jumbled_question").val();
        qData.difficulty = $("#mcq_difficulty").val();
        qData.correct = $("#jumbled_answer").val();
        if (!qData.question || !qData.correct) isValid = false;
    }

    if (!isValid) {
        alert("Please fill in all required fields.");
        return;
    }

    let wasEditing = (editingIndex > -1);

    if (wasEditing) {
        if (questionsList[editingIndex].id) {
            qData.id = questionsList[editingIndex].id;
            qData.is_existing = true;
        }
        questionsList[editingIndex] = qData;
    } else {
        questionsList.push(qData);
    }

    renderQuestions();
    $(".modal").modal("hide"); 

    if (wasEditing && qData.id) {
        let choicesJson = null;
        if (qData.type === 'MCQ' || qData.type === 'MULTIPLE_CHOICE') {
            // FIX: Converts options to clean JSON before saving to DB
            choicesJson = JSON.stringify({A: qData.a, B: qData.b, C: qData.c, D: qData.d});
        }
        
        $.ajax({
            url: '../backend/api/web/asssessments.php',
            type: 'POST',
            data: {
                requestType: 'UpdateSingleQuestion',
                question_id: qData.id,
                question_text: qData.question,
                correct_answer: qData.correct, 
                choices: choicesJson,
                difficulty: qData.difficulty
            },
            success: function(res) {
                console.log("Question successfully updated in DB!");
            }
        });
    } else if (wasEditing) {
        $("#create-assessment-form").submit();
    }
}

// --- FUNCTION TO EDIT A QUESTION ---
function editQuestion(index) {
    let q = questionsList[index];
    editingIndex = index; 
    let modalId = '';

    if (q.type === 'MULTIPLE_CHOICE' || q.type === 'MCQ') {
        modalId = '#modalMCQ';
        $("#mcq_question").val(q.question);
        $("#mcq_difficulty").val(q.difficulty);
        if (q.a) $("#mcq_a").val(q.a);
        if (q.b) $("#mcq_b").val(q.b);
        if (q.c) $("#mcq_c").val(q.c);
        if (q.d) $("#mcq_d").val(q.d);
        
        // FIX: Use the calculated letter to trigger the radio button
        if (q.correctLetter) selectRadio(q.correctLetter);
    } 
    else if (q.type === 'TRUE_FALSE' || q.type === 'TF') {
        modalId = '#modalTF';
        $("#tf_question").val(q.question);
        $("#tf_difficulty").val(q.difficulty);
        if (q.correct) selectRadio(q.correct);
    } 
    else if (q.type === 'IDENTIFICATION' || q.type === 'IDENT') {
        modalId = '#modalIdent';
        $("#ident_question").val(q.question);
        $("#ident_difficulty").val(q.difficulty);
        $("#ident_answer").val(q.correct);
    } 
    else if (q.type === 'JUMBLED_WORD' || q.type === 'JUMBLED') {
        modalId = '#modalJumbled';
        $("#jumbled_question").val(q.question);
        $("#jumbled_difficulty").val(q.difficulty);
        $("#jumbled_answer").val(q.correct);
    }

    if (modalId) {
        $(modalId).find(".btn-main").text("Update Question");
        $(modalId).modal('show');
    }
}

// --- FUNCTION TO REMOVE A QUESTION ---
function removeQuestion(index) {
    let q = questionsList[index];
    Swal.fire({
        title: 'Remove Question?',
        text: "This will permanently remove the question from the database.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            
            // FIX: Instantly delete from Database if it exists
            if (q.id) {
                $.ajax({
                    url: '../backend/api/web/asssessments.php',
                    type: 'POST',
                    data: {
                        requestType: 'DeleteSingleQuestion',
                        question_id: q.id
                    },
                    success: function(res) {
                        console.log("Question deleted from DB!");
                    }
                });
            }
            
            questionsList.splice(index, 1);
            renderQuestions();
        }
    });
}

// --- RENDER LIST FUNCTION ---
function renderQuestions() {
    let container = $("#questions-list");
    let wrapper = $("#questions-preview-container");
    let emptyState = $("#empty-state");
    
    container.empty();

    let filterVal = $("#question-filter").val() || "ALL";
    let diffFilterVal = $("#difficulty-filter").val() || "ALL"; // <--- ADD THIS

    if (questionsList.length > 0) {
        wrapper.removeClass("d-none");
        emptyState.addClass("d-none"); 
        
        $("#q-count").text(questionsList.length);

        let visibleCount = 0;

        questionsList.forEach((q, index) => {
            
            // Type Match
            let typeMatches = false;
            if (filterVal === "ALL") typeMatches = true;
            else if (filterVal === "MCQ" && (q.type === "MULTIPLE_CHOICE" || q.type === "MCQ")) typeMatches = true;
            else if (filterVal === "TF" && (q.type === "TRUE_FALSE" || q.type === "TF")) typeMatches = true;
            else if (filterVal === "IDENT" && (q.type === "IDENTIFICATION" || q.type === "IDENT")) typeMatches = true;
            else if (filterVal === "JUMBLED" && (q.type === "JUMBLED_WORD" || q.type === "JUMBLED")) typeMatches = true;

            // Difficulty Match (ADD THIS BLOCK)
            let diffMatches = false;
            if (diffFilterVal === "ALL" || q.difficulty === diffFilterVal) diffMatches = true;

            // Stop if either filter fails
            if (!typeMatches || !diffMatches) return; 
            
            visibleCount++;

            let badgeClass = "bg-secondary";
            if(q.type === 'MULTIPLE_CHOICE' || q.type === 'MCQ') badgeClass = "bg-primary";
            if(q.type === 'TRUE_FALSE' || q.type === 'TF') badgeClass = "bg-success";
            if(q.type === 'IDENTIFICATION' || q.type === 'IDENT') badgeClass = "bg-info text-dark";
            if(q.type === 'JUMBLED_WORD' || q.type === 'JUMBLED') badgeClass = "bg-warning text-dark";
            
            // Difficulty Badge colors
            let diffBadgeClass = q.difficulty === 'hard' ? 'bg-danger' : (q.difficulty === 'medium' ? 'bg-warning text-dark' : 'bg-success');

            const typeDisplayNames = {
                MULTIPLE_CHOICE: 'Multiple Choice', MCQ: 'Multiple Choice',
                TRUE_FALSE: 'Fact or Bluff', TF: 'Fact or Bluff',
                IDENTIFICATION: 'Identification', IDENT: 'Identification',
                JUMBLED_WORD: 'Jumbled Word', JUMBLED: 'Jumbled Word',
            };
        let displayType = typeDisplayNames[q.type] || q.type; 

        // NEW: resolve the answer text for display
        let displayAnswer = q.correct;
        if (q.type === 'TRUE_FALSE' || q.type === 'TF') {
            const answerLower = (q.correct || '').toString().toLowerCase().trim();
            if (['true', '1', 'tama', 'fact'].includes(answerLower)) {
                displayAnswer = 'Fact';
            } else if (['false', '0', 'mali', 'bluff'].includes(answerLower)) {
                displayAnswer = 'Bluff';
            }
        }

        let html = `
            <div class="col-lg-4 col-md-6 col-12">
                <div class="added-question-item">
                    <div class="position-absolute" style="top: 10px; right: 10px;">
                        <button type="button" class="btn btn-sm btn-light text-warning shadow-sm border me-1" onclick="editQuestion(${index})" title="Edit Question">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light text-danger shadow-sm border" onclick="removeQuestion(${index})" title="Remove Question">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                    <span class="badge ${badgeClass} mb-2 me-1">${displayType}</span>
                    <span class="badge ${diffBadgeClass} mb-2">${DIFF_LABELS[q.difficulty] || 'Easy'}</span>
                    <p class="mb-1 fw-bold pe-5">${q.question}</p>
                    <small class="text-muted">Answer: ${displayAnswer}</small>
                </div>
            </div>
        `;
            container.append(html);
        });

        if (visibleCount === 0) {
            container.append(`
                <div class="col-12 text-center text-muted fst-italic my-4">
                    No questions found for this filter.
                </div>
            `);
        }

    } else {
        wrapper.addClass("d-none");
        emptyState.removeClass("d-none"); 
    }
}

// --- UNIFIED BULK CSV UPLOAD ---
// ── STEP 2: Preview CSV (validate without inserting) ─────────────────────────
$(document).on("click", "#btn-preview-csv", function () {
    const assessmentId = $("#hidden_assessment_id").val();

    if (!assessmentId) {
        Swal.fire({
            icon: 'warning',
            title: 'Nag-iintay pa',
            text: 'I-click muna ang "Step 1: Save Details" bago mag-upload ng CSV.',
        });
        return;
    }

    const fileInput = $("#bulk_csv_file")[0];
    if (!fileInput.files.length) {
        Swal.fire({ icon: 'warning', title: 'Walang file', text: 'Pumili muna ng CSV file.' });
        return;
    }

    const formData = new FormData();
    formData.append("csv_file", fileInput.files[0]);
    formData.append("assessment_id", assessmentId);
    formData.append("preview", "1"); // <── preview mode flag

    Swal.fire({
        title: 'Vina-validate ang CSV...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    $.ajax({
        url: '../backend/api/web/upload-questions.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
            Swal.close();
            _renderPreview(res);
        },
        error: function (xhr) {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'Check console for details.' });
            console.error(xhr.responseText);
        },
    });
});

// Render preview panel
function _renderPreview(res) {
    const panel = $("#csv-preview-panel").removeClass("d-none");

    // Summary badge
    const hasErrors   = res.errors   && res.errors.length   > 0;
    const hasWarnings = res.warnings && res.warnings.length > 0;
    const hasValid    = res.valid    && res.valid.length    > 0;

    const summaryClass = hasErrors ? 'alert-danger' : (hasValid ? 'alert-success' : 'alert-warning');
    $("#csv-preview-summary")
        .removeClass("alert-danger alert-success alert-warning")
        .addClass(summaryClass)
        .html(`<strong>${res.message}</strong>`);

    // Errors
    if (hasErrors) {
        $("#csv-errors-block").removeClass("d-none");
        const list = res.errors.map(e => `<li>${e}</li>`).join('');
        $("#csv-errors-list").html(list);
    } else {
        $("#csv-errors-block").addClass("d-none");
    }

    // Warnings
    if (hasWarnings) {
        $("#csv-warnings-block").removeClass("d-none");
        const list = res.warnings.map(w => `<li>${w}</li>`).join('');
        $("#csv-warnings-list").html(list);
    } else {
        $("#csv-warnings-block").addClass("d-none");
    }

    // Valid rows table + confirm button
    if (hasValid && !hasErrors) {
        $("#csv-valid-block").removeClass("d-none");
        $("#confirm-count").text(res.valid.length);

        const rows = res.valid.map((q, i) => {
            const typeBadge = {
                multiple_choice: '<span class="badge bg-primary">MCQ</span>',
                true_false:      '<span class="badge bg-success">F/B</span>',
                identification:  '<span class="badge bg-info text-dark">Ident</span>',
                jumbled_word:    '<span class="badge bg-warning text-dark">Jumbled</span>',
            }[q.type] || q.type;

            const diffBadge = {
                easy:   '<span class="badge bg-success">Easy</span>',
                medium: '<span class="badge bg-warning text-dark">Medium</span>',
                hard:   '<span class="badge bg-danger">Hard</span>',
            }[q.difficulty] || q.difficulty;

            const shortQ = q.question_text.length > 60
                ? q.question_text.substring(0, 60) + '…'
                : q.question_text;

            return `<tr>
                <td>${i + 1}</td>
                <td>${typeBadge}</td>
                <td>${diffBadge}</td>
                <td>${shortQ}</td>
                <td>${q.correct_answer}</td>
            </tr>`;
        }).join('');

        $("#csv-preview-tbody").html(rows);
    } else {
        $("#csv-valid-block").addClass("d-none");
    }
}

// ── STEP 3: Confirm Upload (actual insert) ────────────────────────────────────
$(document).on("click", "#btn-confirm-upload", function () {
    const assessmentId = $("#hidden_assessment_id").val();
    const fileInput    = $("#bulk_csv_file")[0];

    if (!fileInput.files.length) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'File was lost — please re-select it.' });
        return;
    }

    const formData = new FormData();
    formData.append("csv_file", fileInput.files[0]);
    formData.append("assessment_id", assessmentId);
    // No preview flag = real insert

    Swal.fire({
        title: 'Ina-upload ang mga tanong...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    $.ajax({
        url: '../backend/api/web/upload-questions.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
            if (res.status === 200) {
                Swal.fire({
                    icon: 'success',
                    title: 'Matagumpay!',
                    text: res.message,
                }).then(() => {
                    // Reset UI
                    $("#bulk_csv_file").val('');
                    $("#csv-preview-panel").addClass("d-none");
                    questionsList = [];
                    fetchQuestionsByType(assessmentId);
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hindi na-upload',
                    html: res.message + (res.errors
                        ? '<ul class="text-start small mt-2">'
                            + res.errors.map(e => `<li>${e}</li>`).join('')
                            + '</ul>'
                        : ''),
                });
            }
        },
        error: function (xhr) {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'Check console.' });
            console.error(xhr.responseText);
        },
    });
});