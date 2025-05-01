document.addEventListener('DOMContentLoaded', function() {
    const taskList = document.getElementById('task-list');
    if (taskList) {
        taskList.addEventListener('click', function(e) {
            if (e.target.tagName === 'TD') {
                const taskId = e.target.parentElement.cells[0].textContent;
                window.location.href = `<?= BASE_URL ?>/modules/tasks/view.php?id=${taskId}`;
            }
        });
    }
});