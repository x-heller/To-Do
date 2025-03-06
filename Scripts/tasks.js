document.addEventListener('DOMContentLoaded', function() {
    // Task form submission (AJAX)
    document.getElementById('new-task-form').addEventListener('submit', function(event) {
        event.preventDefault();  // Prevent page reload

        let formData = new FormData(this);

        fetch('tasks.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    // Refresh task list or update the DOM
                    fetchTasks();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred: ' + error);
            });
    });

    // Fetch tasks from the server
    function fetchTasks() {
        fetch('tasks.php')
            .then(response => response.json())
            .then(tasks => {
                const tasksList = document.getElementById('tasks-list');
                tasksList.innerHTML = '';
                tasks.forEach(task => {
                    let taskElement = document.createElement('div');
                    taskElement.className = 'task-summary';
                    taskElement.innerHTML = `
                        <h3>${task.name}</h3>
                        <p>${task.description}</p>
                        <p><strong>Deadline:</strong> ${task.deadline}</p>
                        <p><strong>Status:</strong> ${task.completed ? 'Completed' : 'Pending'}</p>
                        <button class="delete-task" data-id="${task._id}">Delete</button>
                    `;
                    tasksList.appendChild(taskElement);
                });
            });
    }

    fetchTasks();
});
