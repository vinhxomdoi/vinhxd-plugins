/* XD Analytics – trang Traffic AI: bộ lọc ngày + biểu đồ theo ngày (Chart.js đóng gói sẵn, không dùng CDN) */
( function () {
	var sel = document.getElementById( 'vxd-ai-range' );
	if ( sel ) {
		// Chọn khoảng có sẵn → xem ngay; sửa ô ngày → tự chuyển sang "Tuỳ chọn…"
		sel.addEventListener( 'change', function () { if ( sel.value !== 'custom' ) sel.form.submit(); } );
		Array.prototype.forEach.call( sel.form.querySelectorAll( 'input[type=date]' ), function ( inp ) {
			inp.addEventListener( 'change', function () { sel.value = 'custom'; } );
		} );
	}

	var el = document.getElementById( 'vxd-ai-chart' );
	if ( ! el || typeof Chart === 'undefined' ) return;
	var data;
	try { data = JSON.parse( el.getAttribute( 'data-chart' ) ); } catch ( e ) { return; }
	new Chart( el, {
		type: 'bar',
		data: data,
		options: {
			responsive: true,
			maintainAspectRatio: false,
			interaction: { mode: 'index', intersect: false },
			scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
			plugins: { legend: { position: 'bottom' } }
		}
	} );
} )();
