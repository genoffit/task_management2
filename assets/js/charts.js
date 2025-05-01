document.addEventListener('DOMContentLoaded', function () {
    if (typeof chartData === 'undefined' || !chartData.length) {
        console.error('Chart data is not defined or empty');
        return;
    }

    const labels = chartData.map(item => item.status);
    const data = chartData.map(item => item.count);

    const ctx = document.getElementById('statusChart')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Task Status Distribution',
                    data: data,
                    backgroundColor: ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0'],
                    borderColor: ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
