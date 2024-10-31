const elem = document.getElementById( "scalooper_ti_test_settings" );

elem.addEventListener(
	"click",
	function () {
		const formData = {
			scalooper_ti_matomo_site_id: document.getElementById( 'scalooper_ti_matomo_site_id' ).value,
			scalooper_ti_matomo_api_url: document.getElementById( 'scalooper_ti_matomo_api_url' ).value,
			scalooper_ti_matomo_api_key: document.getElementById( 'scalooper_ti_matomo_api_key' ).value,
			scalooper_ti_nonce: scalloper_settings_obj.test_nonce
		};

		wp.ajax.post( "scalooper_test_settings", formData )
		.done(
			function (response) {
				alert( response );
			}
		);
	}
);