( function () {
	function el( id ) { return document.getElementById( id ); }
	function fmt( n ) { return Number( n || 0 ).toLocaleString( 'vi-VN' ); }
	/** Escape HTML: dữ liệu GA4 (utm_source, link_domain...) do bên ngoài kiểm soát, KHÔNG được chèn thô vào innerHTML */
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	var chartInstance = null;

	function renderTotals( totals ) {
		el( 'vinhxd-ga4-postbox-totals' ).innerHTML =
			'<span><strong>' + fmt( totals.views ) + '</strong> lượt xem</span>' +
			'<span><strong>' + fmt( totals.users ) + '</strong> người dùng</span>';
	}

	function renderChart( chart ) {
		var canvas = el( 'vinhxd-ga4-postbox-chart' );
		if ( ! canvas || typeof Chart === 'undefined' ) return;
		if ( chartInstance ) chartInstance.destroy();

		if ( ! chart.labels || ! chart.labels.length ) {
			var ctx = canvas.getContext( '2d' );
			ctx.clearRect( 0, 0, canvas.width, canvas.height );
			return;
		}

		chartInstance = new Chart( canvas.getContext( '2d' ), {
			type: 'line',
			data: {
				labels: chart.labels,
				datasets: [
					{
						label: 'Lượt xem',
						data: chart.views,
						borderColor: '#2271b1',
						backgroundColor: 'rgba(34,113,177,0.10)',
						fill: true,
						tension: 0.3,
						pointRadius: 2,
						yAxisID: 'y',
					},
					{
						label: 'Người dùng',
						data: chart.users,
						borderColor: '#00a32a',
						backgroundColor: 'rgba(0,163,42,0.08)',
						fill: true,
						tension: 0.3,
						pointRadius: 2,
						yAxisID: 'y1',
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } } },
				scales: {
					y:  { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Lượt xem' } },
					y1: { type: 'linear', position: 'right', beginAtZero: true, title: { display: true, text: 'Người dùng' }, grid: { drawOnChartArea: false } },
				}
			}
		} );
	}

	function renderList( containerId, items, renderItem, emptyText ) {
		var container = el( containerId );
		if ( ! items || ! items.length ) {
			container.innerHTML = '<p style="color:#787c82;">' + emptyText + '</p>';
			return;
		}
		var html = '<ul style="list-style:none; margin:0; padding:0;">';
		items.forEach( function ( item ) { html += renderItem( item ); } );
		html += '</ul>';
		container.innerHTML = html;
	}

	function renderLinkout( items ) {
		renderList( 'vinhxd-ga4-postbox-linkout', items, function ( i ) {
			return '<li style="display:flex; justify-content:space-between; gap:8px; padding:5px 0; border-bottom:1px solid #f0f0f1; font-size:13px;">'
				+ '<span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + esc( i.domain ) + '</span>'
				+ '<span style="font-weight:600; flex-shrink:0;">' + fmt( i.count ) + '</span></li>';
		}, 'Chưa có dữ liệu.' );
	}

	function renderEvents( items ) {
		renderList( 'vinhxd-ga4-postbox-events', items, function ( i ) {
			return '<li style="display:flex; justify-content:space-between; gap:8px; padding:5px 0; border-bottom:1px solid #f0f0f1; font-size:13px;">'
				+ '<span>' + esc( i.label ) + '</span>'
				+ '<span style="font-weight:600;">' + fmt( i.count ) + '</span></li>';
		}, 'Chưa khai báo chuyển đổi nào.' );
	}

	function renderTraffic( items ) {
		renderList( 'vinhxd-ga4-postbox-traffic', items, function ( i ) {
			return '<li style="display:flex; justify-content:space-between; gap:8px; padding:5px 0; border-bottom:1px solid #f0f0f1; font-size:13px;">'
				+ '<span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + esc( i.source ) + '</span>'
				+ '<span style="font-weight:600; flex-shrink:0;">' + fmt( i.sessions ) + '</span></li>';
		}, 'Chưa có dữ liệu.' );
	}

	function setLoading() {
		el( 'vinhxd-ga4-postbox-totals' ).innerHTML = '<span>Đang tải...</span>';
		el( 'vinhxd-ga4-postbox-linkout' ).innerHTML = '<p style="color:#787c82;">Đang tải...</p>';
		el( 'vinhxd-ga4-postbox-events' ).innerHTML = '<p style="color:#787c82;">Đang tải...</p>';
		el( 'vinhxd-ga4-postbox-traffic' ).innerHTML = '<p style="color:#787c82;">Đang tải...</p>';
	}

	function loadStats( granularity ) {
		var cfg = window.vinhxdGA4PostBox;
		if ( ! cfg ) return;

		var body = new URLSearchParams();
		body.append( 'action', 'vinhxd_ga4_post_stats' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'post_id', cfg.post_id );
		body.append( 'granularity', granularity );

		fetch( cfg.ajax_url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res.success ) {
					el( 'vinhxd-ga4-postbox-totals' ).innerHTML = '<span style="color:#d63638;">' + esc( res.data || 'Lỗi tải dữ liệu.' ) + '</span>';
					return;
				}
				var d = res.data;
				renderTotals( d.totals );
				renderChart( d.chart );
				renderLinkout( d.linkout );
				renderEvents( d.events );
				renderTraffic( d.traffic_sources );
			} )
			.catch( function () {
				el( 'vinhxd-ga4-postbox-totals' ).innerHTML = '<span style="color:#d63638;">Lỗi tải dữ liệu.</span>';
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! el( 'vinhxd-ga4-postbox' ) ) return;

		loadStats( 'day' );

		var buttons = document.querySelectorAll( '.vinhxd-ga4-granularity' );
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				buttons.forEach( function ( b ) { b.classList.remove( 'button-primary' ); } );
				btn.classList.add( 'button-primary' );
				setLoading();
				loadStats( btn.getAttribute( 'data-granularity' ) );
			} );
		} );
	} );
} )();
