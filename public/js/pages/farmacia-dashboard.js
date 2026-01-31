(() => {
    const root = document.getElementById('farmacia-dashboard');
    if (!root || typeof Chart === 'undefined') {
        return;
    }

    const parseDataset = (key) => {
        try {
            return JSON.parse(root.dataset[key] || '[]');
        } catch (error) {
            console.error('No se pudo leer dataset', key, error);
            return [];
        }
    };

    const monthly = parseDataset('monthly');
    const products = parseDataset('products');
    const doctors = parseDataset('doctors');

    const buildChart = (canvasId, config) => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            return null;
        }
        return new Chart(canvas, config);
    };

    buildChart('chartFarmaciaMes', {
        type: 'bar',
        data: {
            labels: monthly.map((row) => row.label),
            datasets: [
                {
                    label: 'Recetas',
                    data: monthly.map((row) => row.recetas),
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                },
                {
                    label: 'Unidades',
                    data: monthly.map((row) => row.unidades),
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
        },
    });

    buildChart('chartFarmaciaProductos', {
        type: 'bar',
        data: {
            labels: products.map((row) => row.label),
            datasets: [
                {
                    label: 'Recetas',
                    data: products.map((row) => row.recetas),
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                },
                {
                    label: 'Unidades',
                    data: products.map((row) => row.unidades),
                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                },
            },
        },
    });

    buildChart('chartFarmaciaDoctores', {
        type: 'bar',
        data: {
            labels: doctors.map((row) => row.label),
            datasets: [
                {
                    label: 'Recetas',
                    data: doctors.map((row) => row.recetas),
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                },
                {
                    label: 'Unidades',
                    data: doctors.map((row) => row.unidades),
                    backgroundColor: 'rgba(255, 205, 86, 0.7)',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                },
            },
        },
    });
})();
