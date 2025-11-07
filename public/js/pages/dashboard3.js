//Project:      Doclinic - Responsive Admin Template
//Primary use:   Used only for the main dashboard (index.html)


$(function () {

    'use strict';

    function toNumber(value) {
        var parsed = parseFloat(value);
        return isNaN(parsed) ? 0 : parsed;
    }

    // Procedimientos más realizados
    var procedimientosChart = new ApexCharts(document.querySelector("#patient_statistics"), {
        series: [{
            name: 'Procedimientos',
            data: procedimientos_membrete
        }],
        chart: {
            type: 'bar',
            foreColor: "#bac0c7",
            height: 260,
            stacked: true,
            toolbar: {
                show: false
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '30%'
            }
        },
        dataLabels: {
            enabled: false
        },
        grid: {
            show: true
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        colors: ['#5156be'],
        xaxis: {
            categories: membretes
        },
        legend: {
            show: true,
            position: 'top'
        },
        fill: {
            opacity: 1
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + " procedimientos";
                }
            },
            marker: {
                show: false
            }
        }
    });
    procedimientosChart.render();

    // Embudo de solicitudes
    var etapasOrden = ['recibido', 'llamado', 'en-atencion', 'revision-codigos', 'docs-completos', 'aprobacion-anestesia', 'listo-para-agenda', 'otros'];
    var etapasEtiquetas = {
        'recibido': 'Recibido',
        'llamado': 'Llamado',
        'en-atencion': 'En atención',
        'revision-codigos': 'Revisión de códigos',
        'docs-completos': 'Docs completos',
        'aprobacion-anestesia': 'Aprobación anestesia',
        'listo-para-agenda': 'Listo para agenda',
        'otros': 'Otros'
    };
    var funnelValores = etapasOrden.map(function (clave) {
        return toNumber(solicitudesFunnel.etapas ? solicitudesFunnel.etapas[clave] : 0);
    });
    var funnelCategorias = etapasOrden.map(function (clave) {
        return etapasEtiquetas[clave] || clave;
    });

    var funnelEl = document.querySelector("#solicitudes_funnel_chart");
    if (funnelEl) {
        new ApexCharts(funnelEl, {
            series: [{
                data: funnelValores,
                name: 'Solicitudes'
            }],
            chart: {
                type: 'bar',
                height: 320
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '60%',
                    distributed: true
                }
            },
            colors: ['#9fa8da', '#7986cb', '#5c6bc0', '#3f51b5', '#3949ab', '#303f9f', '#283593', '#1a237e'],
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val;
                }
            },
            xaxis: {
                categories: funnelCategorias
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + ' solicitudes';
                    }
                }
            }
        }).render();
    }

    // Backlog CRM
    var backlogEl = document.querySelector("#crm_backlog_chart");
    if (backlogEl) {
        var backlogPendientes = toNumber(crmBacklog.pendientes);
        var backlogVencidas = toNumber(crmBacklog.vencidas);
        var backlogCompletadas = toNumber(crmBacklog.completadas);

        new ApexCharts(backlogEl, {
            series: [backlogPendientes, backlogVencidas, backlogCompletadas],
            chart: {
                type: 'donut',
                height: 320
            },
            labels: ['Pendientes', 'Vencidas', 'Completadas'],
            colors: ['#42a5f5', '#ef5350', '#66bb6a'],
            dataLabels: {
                enabled: true
            },
            legend: {
                position: 'bottom'
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + ' tareas';
                    }
                }
            }
        }).render();
    }

    // Estado de protocolos
    var revisionEl = document.querySelector("#revision_estado_chart");
    if (revisionEl) {
        var revisionSeries = [
            toNumber(revisionEstados.incompletos),
            toNumber(revisionEstados.revisados),
            toNumber(revisionEstados.no_revisados)
        ];

        new ApexCharts(revisionEl, {
            series: revisionSeries,
            chart: {
                type: 'pie',
                height: 320
            },
            labels: ['Incompletos', 'Revisados', 'Listos sin revisión'],
            colors: ['#FF7043', '#66BB6A', '#FFD54F'],
            legend: {
                position: 'bottom'
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + ' protocolos';
                    }
                }
            }
        }).render();
    }

    // Gráfico de procedimientos por día
    var procedimientosDiaEl = document.querySelector("#total_patient");
    if (procedimientosDiaEl) {
        new ApexCharts(procedimientosDiaEl, {
            series: [{
                name: 'Procedimientos por día',
                type: 'column',
                data: procedimientos_dia
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [0, 4],
                curve: 'smooth'
            },
            colors: ['#E7E4FF', '#5156be'],
            dataLabels: {
                enabled: false
            },
            labels: fechas,
            xaxis: {
                type: 'category'
            },
            legend: {
                show: true,
                position: 'top'
            }
        }).render();
    }

    $('.inner-user-div3').slimScroll({
        height: '310px'
    });

    $('.inner-user-div4').slimScroll({
        height: '200px'
    });

    $('.owl-carousel').owlCarousel({
        loop: true,
        margin: 0,
        responsiveClass: true,
        autoplay: true,
        dots: false,
        nav: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 1
            },
            1000: {
                items: 1
            }
        }
    });

}); // End of use strict
