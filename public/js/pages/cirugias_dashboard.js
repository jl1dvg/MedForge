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
        var trazabilidad = data.facturacionTrazabilidad || {};
        var hasTrazabilidad = Object.keys(trazabilidad).length > 0;

        var estadoSeries = hasTrazabilidad
            ? [
                Number(trazabilidad.atendidos || 0),
                Number(trazabilidad.facturados || 0),
                Number(trazabilidad.pendiente_facturar || trazabilidad.pendiente_pago || 0),
                Number(trazabilidad.cancelados || 0)
            ]
            : [
                Number(data.estadoProtocolos.revisado || 0),
                Number(data.estadoProtocolos['no revisado'] || 0),
                Number(data.estadoProtocolos.incompleto || 0)
            ];

        var estadoOptions = {
            series: estadoSeries,
            labels: hasTrazabilidad
                ? ['Atendidos', 'Facturados', 'Pendiente de facturar', 'Cancelados']
                : ['Revisado', 'No revisado', 'Incompleto'],
            colors: hasTrazabilidad
                ? ['#1e88e5', '#2e7d32', '#ffb300', '#ef5350']
                : ['#2e7d32', '#ff9800', '#ef5350'],
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
        var cirujanosData = data.topCirujanos || { labels: [], totals: [] };
        var cirujanosOptions = {
            series: [{ name: 'Cirugías realizadas', data: cirujanosData.totals || [] }],
            xaxis: { categories: cirujanosData.labels || [] },
            plotOptions: { bar: { horizontal: true } },
            colors: ['#00897b'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'bar', height: 320 })
        };
        new ApexCharts(cirujanosEl, cirujanosOptions).render();
    }

    var doctoresRealizadasEl = document.querySelector('#top-doctores-realizadas');
    if (doctoresRealizadasEl) {
        var doctoresRealizadasData = data.topDoctoresSolicitudesRealizadas || { labels: [], totals: [] };
        var doctoresRealizadasOptions = {
            series: [{ name: 'Solicitudes realizadas', data: doctoresRealizadasData.totals || [] }],
            xaxis: { categories: doctoresRealizadasData.labels || [] },
            plotOptions: { bar: { horizontal: true } },
            colors: ['#5c6bc0'],
            chart: Object.assign({}, chartDefaults.chart, { type: 'bar', height: 320 })
        };
        new ApexCharts(doctoresRealizadasEl, doctoresRealizadasOptions).render();
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
