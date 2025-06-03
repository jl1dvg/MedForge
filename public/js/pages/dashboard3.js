//[Dashboard Javascript]

//Project:	Doclinic - Responsive Admin Template
//Primary use:   Used only for the main dashboard (index.html)


$(function () {

    'use strict';

    var options = {
        series: [{
            name: 'Procedimientos',
            data: procedimientos_membrete  // Datos dinámicos de procedimientos
        }],
        chart: {
            type: 'bar',
            foreColor: "#bac0c7",
            height: 260,
            stacked: true,
            toolbar: {
                show: false,
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '30%',
            },
        },
        dataLabels: {
            enabled: false,
        },
        grid: {
            show: true,
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        colors: ['#5156be'],
        xaxis: {
            categories: membretes,  // Los membretes dinámicos desde PHP
        },
        yaxis: {},
        legend: {
            show: true,
            position: 'top',
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
                show: false,
            },
        }
    };

    var chart = new ApexCharts(document.querySelector("#patient_statistics"), options);
    chart.render();


    // Configuración del gráfico de pastel
    var options = {
        series: [incompletos, revisados, no_revisados],  // Datos dinámicos de estados
        chart: {
            type: 'pie',
            height: 300
        },
        labels: ['Incompletos', 'Revisados', 'No Revisados'],  // Etiquetas del gráfico
        colors: ['#FF6347', '#32CD32', '#FFD700'],  // Colores personalizables para cada estado
        legend: {
            position: 'bottom'
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + " procedimientos";
                }
            }
        }
    };

    var chart = new ApexCharts(document.querySelector("#recovery_statistics"), options);
    chart.render();


    // Gráfico de procedimientos por día
    var options = {
        series: [{
            name: 'Procedimientos por Día',
            type: 'column',
            data: procedimientos_dia  // Aquí cargamos los datos dinámicos
        }],
        chart: {
            height: 350,
            type: 'line',
            toolbar: {
                show: false,
            }
        },
        stroke: {
            width: [0, 4],
            curve: 'smooth'
        },
        colors: ['#E7E4FF', '#5156be'],
        dataLabels: {
            enabled: false,
        },
        labels: fechas,  // Fechas dinámicas cargadas desde PHP
        xaxis: {
            type: 'category'
        },
        legend: {
            show: true,
            position: 'top',
        }
    };

    var chart = new ApexCharts(document.querySelector("#total_patient"), options);
    chart.render();

    $('.inner-user-div3').slimScroll({
        height: '310px'
    });

    $('.inner-user-div4').slimScroll({
        height: '127px'
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
                items: 1,
            },
            600: {
                items: 1,
            },
            1000: {
                items: 1,
            }
        }
    });

}); // End of use strict
