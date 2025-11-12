//[Dashboard Javascript]

//Project:	Doclinic - Responsive Admin Template
//Primary use:   Used only for the main dashboard (index.html)


$(function () {

  'use strict';
	
		var options = {
          series: [44, 55, 41, 17, 15],
          chart: {
          type: 'donut',
        },
		colors: ['#3246D3', '#00D0FF', '#ee3158', '#ffa800', '#05825f'],
		legend: {
		  position: 'bottom'
		},	
			
		  plotOptions: {
			  pie: {
				  donut: {
					size: '45%',
				  }
			  }
		  },
		labels: ["Operation", "Theraphy", "Mediation", "Colestrol", "Heart Beat"],
        responsive: [{
          breakpoint: 480,
          options: {
            chart: {
            },
            legend: {
              position: 'bottom'
            }
          }
        }]
        };

        var chart = new ApexCharts(document.querySelector("#chart123"), options);
        chart.render();
	
	
	
	var options = {
				series: [{
				name: 'Heart',
				data: [4, 3, 10, 9, 50, 19, 22, 9, 17, 2, 7, 15]
			}],
				chart: {
					width: 200,
				toolbar: {
					show: false,
				},
				height: 120,
				type: 'line',
			},
			stroke: {
				width: 4,
				curve: 'smooth',
				colors: ['#05825f']
			},
			
			legend: {
				show: false
			},
			tooltip: {
				enabled: true,
			},
			
			grid: {
		show: false,
		},

			xaxis: {
				show: false,
				lines: {
					show: false,
				},
				labels: {
					show: false,
				},
				axisBorder: {
				  show: false,
				},
				
			},
			yaxis: {
				show: false,
			},
		};

		var chart = new ApexCharts(document.querySelector("#chart"), options);
		chart.render();
	
	
	
	
	
	// Slim scrolling
  	$('.inner-user-div').slimScroll({
		height: '350px'
	  });
  
	  $('.inner-user-div4').slimScroll({
		height: '350px'
	  });
	
	var datepaginator = function() {
		return {
			init: function() {
				$("#paginator1").datepaginator()
			}
		}
	}();
	jQuery(document).ready(function() {
		datepaginator.init()
	}); 

	// === AJAX: cambiar lista de agendados al cambiar de fecha en el paginador ===
	$(document).on('click', '#paginator1 .dp-item, #paginator1 .dp-nav', async function (e) {
	  e.preventDefault();

	  // 1) Detectar la fecha: primero por data-moment (HTML actual), si no, por querystring de href
	  var $a = $(this);
	  var fecha = $a.data('moment');
	  if (!fecha) {
	    try {
	      var url = new URL(this.href, window.location.origin);
	      fecha = url.searchParams.get('fecha');
	    } catch (err) {
	      // href podría ser "#", ignoramos
	    }
	  }
	  if (!fecha) return;

	  // 2) Mostrar loading ligero en el contenedor de la lista
	  var $list = $('.inner-user-div4');
	  $list.html('<div class="text-center py-20 text-fade">Cargando…</div>');

	  // 3) Construir URL actual con ?fecha=...&json=1
	  var basePath = window.location.pathname; // mantiene /doctores/{id} o ruta actual
	  var fetchUrl = basePath + '?fecha=' + encodeURIComponent(fecha) + '&json=1';

	  try {
	    var resp = await fetch(fetchUrl, { headers: { 'Accept': 'application/json' } });
	    var data = await resp.json();

	    // 4) Actualizar encabezado con la etiqueta de fecha
	    $('[data-appointments-date-label]').text((data && data.selectedLabel) ? data.selectedLabel : '');

	    // 5) Reconstruir la lista
	    $list.html(renderAppointments(data && data.appointments ? data.appointments : []));
	  } catch (err) {
	    $list.html('<div class="text-center py-20 text-danger">Error al cargar la agenda.</div>');
	  }
	});

	// Renderizador de ítems de agenda
	function renderAppointments(items) {
	  if (!items || !items.length) {
	    return '<div class="text-center text-fade py-20">Sin agendados.</div>';
	  }
	  return items.map(function(a, i) {
	    var hasDivider = i < items.length - 1;
	    var badgeClass = 'badge badge-' + (a.status_variant || 'secondary') + ' mb-10';
	    var callClasses = 'waves-effect waves-circle btn btn-circle btn-primary-light btn-sm' + (a.call_disabled ? ' disabled opacity-50' : '');
	    var callAttr = a.call_disabled ? 'aria-disabled="true"' : '';
	    return '' +
	      '<div class="' + (hasDivider ? 'mb-15' : '') + '">' +
	        '<div class="d-flex align-items-center mb-10">' +
	          '<div class="me-15">' +
	            '<img src="' + (a.avatar || 'images/avatar/1.jpg') + '" class="avatar avatar-lg rounded10 bg-primary-light" alt="">' +
	          '</div>' +
	          '<div class="d-flex flex-column flex-grow-1 fw-500">' +
	            '<p class="hover-primary text-fade mb-1 fs-14">' + (a.patient || 'Paciente') + '</p>' +
	            '<span class="text-dark fs-16">' + (a.procedure || 'Consulta') + '</span>' +
	            (a.afiliacion_label ? ('<span class="text-fade fs-12">' + a.afiliacion_label + '</span>') : '') +
	          '</div>' +
	          '<div class="text-end">' +
	            (a.status_label ? ('<span class="' + badgeClass + '">' + a.status_label + '</span>') : '') +
	            '<a href="' + (a.call_href || 'javascript:void(0);') + '" class="' + callClasses + '" ' + callAttr + '><i class="fa fa-phone"></i></a>' +
	          '</div>' +
	        '</div>' +
	        '<div class="d-flex justify-content-between align-items-end ' + (hasDivider ? 'py-10 mb-15 bb-dashed border-bottom' : '') + '">' +
	          '<div>' +
	            '<p class="mb-0 text-muted">' +
	              '<i class="fa fa-clock-o me-5"></i> ' + (a.time || '') + ' <span class="mx-20">HC ' + (a.hc_number || '') + '</span>' +
	            '</p>' +
	          '</div>' +
	          '<div class="dropdown">' +
	            '<a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i class="ti-more-alt text-muted"></i></a>' +
	            '<div class="dropdown-menu dropdown-menu-end">' +
	              '<a class="dropdown-item" href="#"><i class="ti-import"></i> Detalles</a>' +
	              '<a class="dropdown-item" href="#"><i class="ti-export"></i> Reportes</a>' +
	              '<a class="dropdown-item" href="#"><i class="ti-printer"></i> Imprimir</a>' +
	              '<div class="dropdown-divider"></div>' +
	              '<a class="dropdown-item" href="#"><i class="ti-settings"></i> Gestionar</a>' +
	            '</div>' +
	          '</div>' +
	        '</div>' +
	      '</div>';
	  }).join('');
	}

	
	$('.inner-user-div3').slimScroll({
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
