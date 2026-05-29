// pages/scripts/studentProgress.js

document.addEventListener("DOMContentLoaded", function() {
    // 1. Initialize DataTable (Assuming you are using jQuery DataTables in your project)
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('#progressTable').DataTable({
            "order": [[ 0, "asc" ]] // Sort by Name by default
        });
    }

    // 2. Handle View Details Modal
    const modalElement = document.getElementById('progressModal');
    const bsModal = new bootstrap.Modal(modalElement);
    const modalTitle = document.getElementById('modalStudentName');
    const modalBody = document.getElementById('modalProgressBody');

    // Use event delegation for dynamically created buttons (like in DataTables)
    document.querySelector('body').addEventListener('click', function(e) {
        if (e.target.classList.contains('view-details-btn')) {
            const btn = e.target;
            const userId = btn.getAttribute('data-user-id');
            const lrn = btn.getAttribute('data-lrn');
            const studentName = btn.getAttribute('data-name');

            // Reset modal state
            modalTitle.textContent = `Progress Breakdown: ${studentName}`;
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-danger" role="status"></div>
                    <p class="mt-2">Fetching data...</p>
                </div>
            `;
            
            bsModal.show();

            // Fetch Data
            fetch(`../backend/api/web/get_student_progress_details.php?user_id=${userId}&lrn=${lrn}`)
                .then(response => response.json())
                .then(res => {
                    if (res.error) {
                        modalBody.innerHTML = `<div class="alert alert-danger">${res.error}</div>`;
                        return;
                    }

                    const data = res.data;
                    let html = '';

                    // Loop through each Markahan
                    for (const [markahan, lessons] of Object.entries(data)) {
                        html += `<h5 class="mt-3 border-bottom pb-2 text-danger">Markahan ${markahan}</h5>`;
                        html += `
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Aralin</th>
                                        <th>Video Status</th>
                                        <th>Quiz Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;

                        lessons.forEach(lesson => {
                            // Check Video Status
                            const videoStatus = lesson.video_watched_date 
                                ? `<span class="text-success"><i class="bi bi-check-circle-fill"></i> Watched</span><br><small class="text-muted">${new Date(lesson.video_watched_date).toLocaleDateString()}</small>` 
                                : `<span class="text-secondary">Not Started</span>`;

                            // Check Quiz Status
                            let quizStatus = `<span class="text-secondary">Not Taken</span>`;
                            if (lesson.points !== null) {
                                const percentage = (lesson.points / lesson.total) * 100;
                                const textColor = percentage >= 75 ? 'text-success' : 'text-danger';
                                quizStatus = `
                                    <span class="${textColor} fw-bold">${lesson.points} / ${lesson.total}</span>
                                    <br><small class="text-muted">${new Date(lesson.quiz_taken_date).toLocaleDateString()}</small>
                                `;
                            }

                            html += `
                                <tr>
                                    <td><strong>Aralin ${lesson.aralin_no}:</strong> ${lesson.aralin_title}</td>
                                    <td>${videoStatus}</td>
                                    <td>${quizStatus}</td>
                                </tr>
                            `;
                        });

                        html += `</tbody></table>`;
                    }

                    if (html === '') {
                        html = '<p class="text-muted">No data available for this student.</p>';
                    }

                    modalBody.innerHTML = html;
                })
                .catch(err => {
                    modalBody.innerHTML = `<div class="alert alert-danger">Error fetching data. Check network console.</div>`;
                });
        }
    });
});