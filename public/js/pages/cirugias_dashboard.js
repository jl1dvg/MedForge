window.initCirugiasDashboard = function (data) {
    if (!data) {
        return;
    }

    var chartDefaults = {
        chart: {
            toolbar: { show: false },
            fontFamily: 'inherit'
        },
        dataLabels: { enabled: false }
    };

    var monthlyEl = document.querySelector('#cirugias-por-mes');
    if (monthlyEl) {
        var monthlyOptions = {
            series: [{ name: 'Cirugías', data: data.cirugiasPorMes.totals || [] }],
            xaxis: { categories: data.cirugiasPorMes.labels || [] },
            colors: ['#1e88e5'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'bar', height: 300 })
        };
        new ApexCharts(monthlyEl, monthlyOptions).render();
    }

    var estadoEl = document.querySelector('#estado-protocolos');
    if (estadoEl) {
        var estadoSeries = [
            data.estadoProtocolos.revisado || 0,
            data.estadoProtocolos['no revisado'] || 0,
            data.estadoProtocolos.incompleto || 0
        ];
        var estadoOptions = {
            series: estadoSeries,
            labels: ['Revisado', 'No revisado', 'Incompleto'],
            colors: ['#2e7d32', '#ff9800', '#ef5350'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'donut', height: 300 })
        };
        new ApexCharts(estadoEl, estadoOptions).render();
    }

    var procedimientosEl = document.querySelector('#top-procedimientos');
    if (procedimientosEl) {
        var procedimientosOptions = {
            series: [{ name: 'Cirugías', data: data.topProcedimientos.totals || [] }],
            xaxis: { categories: data.topProcedimientos.labels || [] },
            colors: ['#26a69a'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'bar', height: 320 })
        };
        new ApexCharts(procedimientosEl, procedimientosOptions).render();
    }

    var cirujanosEl = document.querySelector('#top-cirujanos');
    if (cirujanosEl) {
        var cirujanosOptions = {
            series: [{ name: 'Cirugías', data: data.topCirujanos.totals || [] }],
            xaxis: { categories: data.topCirujanos.labels || [] },
            plotOptions: { bar: { horizontal: true } },
            colors: ['#5c6bc0'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'bar', height: 320 })
        };
        new ApexCharts(cirujanosEl, cirujanosOptions).render();
    }

    var convenioEl = document.querySelector('#cirugias-por-convenio');
    if (convenioEl) {
        var convenioOptions = {
            series: [{ name: 'Cirugías', data: data.cirugiasPorConvenio.totals || [] }],
            xaxis: { categories: data.cirugiasPorConvenio.labels || [] },
            colors: ['#8e24aa'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'bar', height: 320 })
        };
        new ApexCharts(convenioEl, convenioOptions).render();
    }
};
