( function () {
	function buildChart( canvasId, data ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas || typeof Chart === 'undefined' || ! data ) return;

		new Chart( canvas.getContext( '2d' ), {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [
					{
						label: 'Lượt xem',
						data: data.pageviews,
						borderColor: '#2271b1',
						backgroundColor: 'rgba(34,113,177,0.10)',
						fill: true,
						tension: 0.3,
						pointRadius: 2,
						pointHoverRadius: 5,
						yAxisID: 'y',
					},
					{
						label: 'Người dùng',
						data: data.users,
						borderColor: '#00a32a',
						backgroundColor: 'rgba(0,163,42,0.08)',
						fill: true,
						tension: 0.3,
						pointRadius: 2,
						pointHoverRadius: 5,
						yAxisID: 'y1',
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } },
					tooltip: {
						backgroundColor: '#1d2327',
						padding: 10,
						titleFont: { weight: '600' },
						callbacks: {
							title: function ( items ) {
								return 'Ngày ' + items[ 0 ].label;
							}
						}
					}
				},
				scales: {
					y: {
						type: 'linear',
						position: 'left',
						beginAtZero: true,
						title: { display: true, text: 'Lượt xem' },
						grid: { color: '#f0f0f1' },
					},
					y1: {
						type: 'linear',
						position: 'right',
						beginAtZero: true,
						title: { display: true, text: 'Người dùng' },
						grid: { drawOnChartArea: false },
					}
				}
			}
		} );
	}

	function buildDonut( canvasId, data ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas || typeof Chart === 'undefined' || ! data ) return;

		new Chart( canvas.getContext( '2d' ), {
			type: 'doughnut',
			data: {
				labels: data.labels,
				datasets: [ {
					data: data.values,
					backgroundColor: data.colors,
					borderWidth: 0,
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '68%',
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#1d2327',
						padding: 8,
					}
				}
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof window.vinhxdGA4ChartData !== 'undefined' ) {
			buildChart( 'vinhxd-ga4-chart', window.vinhxdGA4ChartData );
		}
		if ( typeof window.vinhxdGA4WidgetChartData !== 'undefined' ) {
			buildChart( 'vinhxd-ga4-widget-chart', window.vinhxdGA4WidgetChartData );
		}
		if ( typeof window.vinhxdGA4DonutData !== 'undefined' ) {
			buildDonut( 'vinhxd-ga4-donut', window.vinhxdGA4DonutData );
		}
	} );
} )();
