function validateTaskForm() {
    const title = document.getElementById('title');
    const description = document.getElementById('description');
    const dueDate = document.getElementById('due_date');

    if (!title.value.trim()) {
        alert('Title is required');
        return false;
    }

    if (!description.value.trim()) {
        alert('Description is required');
        return false;
    }

    if (!dueDate.value) {
        alert('Due date is required');
        return false;
    }

    return true;
}