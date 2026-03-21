(function () {
    'use strict';

    var charts = {};

    function destroyChart(id) {
        if (charts[id] && typeof charts[id].destroy === 'function') {
            charts[id].destroy();
        }
        delete charts[id];
    }

    function renderChart(id, configBuilder, hasData) {
        var canvas = document.getElementById(id);
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        destroyChart(id);

        if (!hasData) {
            var wrapper = canvas.parentNode;
            if (wrapper) {
                wrapper.innerHTML = '<p class="text-muted small mb-0">Sin datos para el rango seleccionado.</p>';
            }
            return;
        }

        charts[id] = new window.Chart(canvas.getContext('2d'), configBuilder());
    }

    function normalizeArray(value) {
        return Array.isArray(value) ? value : [];
    }

    function initDataTable(selector, options) {
        if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
            return;
        }

        var $table = window.jQuery(selector);
        if (!$table.length) {
            return;
        }

        if (window.jQuery.fn.dataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        $table.DataTable(Object.assign({
            language: window.medforgeDataTableLanguageEs ? window.medforgeDataTableLanguageEs() : {},
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            deferRender: true,
            responsive: false,
        }, options || {}));
    }

    function colors() {
        return [
            '#0d6efd',
            '#20c997',
            '#fd7e14',
            '#6f42c1',
            '#dc3545',
            '#198754',
            '#ffc107',
            '#0dcaf0',
        ];
    }

    window.initFarmaciaDashboard = function (data) {
        var chartsData = data && typeof data === 'object' && data.charts ? data.charts : {};
        var serie = chartsData.serie_diaria || {};
        var topProductos = chartsData.top_productos || {};
        var topDoctores = chartsData.top_doctores || {};
        var vias = chartsData.vias || {};
        var afiliacion = chartsData.afiliacion || {};
        var departamento = chartsData.departamento || {};
        var tiposMatch = chartsData.tipos_match || {};
        var serieEconomica = chartsData.serie_economica || {};
        var netoAfiliacion = chartsData.neto_afiliacion || {};
        var netoSede = chartsData.neto_sede || {};
        var netoDoctores = chartsData.neto_doctores || {};
        var departamentoFactura = chartsData.departamento_factura || {};

        initDataTable('#tablaFarmaciaDetalle', {
            order: [[0, 'desc']],
            pageLength: 10
        });

        initDataTable('#tablaFarmaciaConciliacion', {
            order: [[0, 'desc']],
            pageLength: 10
        });

        var serieLabels = normalizeArray(serie.labels);
        var serieRecetas = normalizeArray(serie.recetas);
        var serieCantidad = normalizeArray(serie.cantidad);
        var serieFarmacia = normalizeArray(serie.farmacia);

        renderChart(
            'chartFarmaciaSerieDiaria',
            function () {
                return {
                    type: 'line',
                    data: {
                        labels: serieLabels,
                        datasets: [
                            {
                                label: 'Ítems receta',
                                data: serieRecetas,
                                borderColor: '#0d6efd',
                                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                                borderWidth: 2,
                                tension: 0.25
                            },
                            {
                                label: 'Unidades prescritas',
                                data: serieCantidad,
                                borderColor: '#fd7e14',
                                backgroundColor: 'rgba(253, 126, 20, 0.12)',
                                borderWidth: 2,
                                tension: 0.25
                            },
                            {
                                label: 'Unidades farmacia',
                                data: serieFarmacia,
                                borderColor: '#20c997',
                                backgroundColor: 'rgba(32, 201, 151, 0.12)',
                                borderWidth: 2,
                                tension: 0.25
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    }
                };
            },
            serieLabels.length > 0
        );

        var topProductosLabels = normalizeArray(topProductos.labels);
        var topProductosValues = normalizeArray(topProductos.values);
        renderChart(
            'chartFarmaciaTopProductos',
            function () {
                return {
                    type: 'bar',
                    data: {
                        labels: topProductosLabels,
                        datasets: [
                            {
                                label: 'Ítems',
                                data: topProductosValues,
                                backgroundColor: 'rgba(13, 110, 253, 0.75)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    }
                };
            },
            topProductosLabels.length > 0
        );

        var topDoctoresLabels = normalizeArray(topDoctores.labels);
        var topDoctoresValues = normalizeArray(topDoctores.values);
        renderChart(
            'chartFarmaciaTopDoctores',
            function () {
                return {
                    type: 'bar',
                    data: {
                        labels: topDoctoresLabels,
                        datasets: [
                            {
                                label: 'Ítems',
                                data: topDoctoresValues,
                                backgroundColor: 'rgba(111, 66, 193, 0.75)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    }
                };
            },
            topDoctoresLabels.length > 0
        );

        var viasLabels = normalizeArray(vias.labels);
        var viasValues = normalizeArray(vias.values);
        renderChart(
            'chartFarmaciaVias',
            function () {
                return {
                    type: 'pie',
                    data: {
                        labels: viasLabels,
                        datasets: [
                            {
                                data: viasValues,
                                backgroundColor: colors()
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                };
            },
            viasLabels.length > 0
        );

        var afiliacionLabels = normalizeArray(afiliacion.labels);
        var afiliacionValues = normalizeArray(afiliacion.values);
        renderChart(
            'chartFarmaciaAfiliacion',
            function () {
                return {
                    type: 'bar',
                    data: {
                        labels: afiliacionLabels,
                        datasets: [
                            {
                                label: 'Ítems',
                                data: afiliacionValues,
                                backgroundColor: 'rgba(32, 201, 151, 0.75)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    }
                };
            },
            afiliacionLabels.length > 0
        );

        var tiposMatchLabels = normalizeArray(tiposMatch.labels);
        var tiposMatchValues = normalizeArray(tiposMatch.values);
        renderChart(
            'chartFarmaciaTiposMatch',
            function () {
                return {
                    type: 'doughnut',
                    data: {
                        labels: tiposMatchLabels,
                        datasets: [
                            {
                                data: tiposMatchValues,
                                backgroundColor: colors()
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                };
            },
            tiposMatchLabels.length > 0
        );

        var departamentoLabels = normalizeArray(departamento.labels);
        var departamentoValues = normalizeArray(departamento.values);
        renderChart(
            'chartFarmaciaDepartamento',
            function () {
                return {
                    type: 'bar',
                    data: {
                        labels: departamentoLabels,
                        datasets: [
                            {
                                label: 'Ítems',
                                data: departamentoValues,
                                backgroundColor: 'rgba(13, 110, 253, 0.75)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 }
                            }
                        }
                    }
                };
            },
            departamentoLabels.length > 0
        );

        var serieEconomicaLabels = normalizeArray(serieEconomica.labels);
        var serieEconomicaNeto = normalizeArray(serieEconomica.neto);
        var serieEconomicaDescuentos = normalizeArray(serieEconomica.descuentos);
        renderChart(
            'chartFarmaciaSerieEconomica',
            function () {
                return {
                    type: 'line',
                    data: {
                        labels: serieEconomicaLabels,
                        datasets: [
                            {
                                label: 'Neto facturado',
                                data: serieEconomicaNeto,
                                borderColor: '#198754',
                                backgroundColor: 'rgba(25, 135, 84, 0.12)',
                                borderWidth: 2,
                                tension: 0.25
                            },
                            {
                                label: 'Descuentos',
                                data: serieEconomicaDescuentos,
                                borderColor: '#dc3545',
                                backgroundColor: 'rgba(220, 53, 69, 0.12)',
                                borderWidth: 2,
                                tension: 0.25
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                };
            },
            serieEconomicaLabels.length > 0
        );

        function renderMoneyBarChart(id, labels, values, color) {
            renderChart(
                id,
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Neto',
                                    data: values,
                                    backgroundColor: color
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            scales: {
                                x: {
                                    beginAtZero: true
                                }
                            }
                        }
                    };
                },
                labels.length > 0
            );
        }

        renderMoneyBarChart(
            'chartFarmaciaNetoAfiliacion',
            normalizeArray(netoAfiliacion.labels),
            normalizeArray(netoAfiliacion.values),
            'rgba(13, 110, 253, 0.75)'
        );

        renderMoneyBarChart(
            'chartFarmaciaNetoSede',
            normalizeArray(netoSede.labels),
            normalizeArray(netoSede.values),
            'rgba(32, 201, 151, 0.75)'
        );

        renderMoneyBarChart(
            'chartFarmaciaNetoDoctores',
            normalizeArray(netoDoctores.labels),
            normalizeArray(netoDoctores.values),
            'rgba(111, 66, 193, 0.75)'
        );

        renderMoneyBarChart(
            'chartFarmaciaDepartamentoFactura',
            normalizeArray(departamentoFactura.labels),
            normalizeArray(departamentoFactura.values),
            'rgba(253, 126, 20, 0.75)'
        );
    };
})();
