$(document).ready(function() {

	function displayGranted($tr) {
		$tr.find('.configuration input.auth-param').attr('disabled', 'disabled').addClass('disabled-success');
	}

	var authorizeUri = null;

	OCA.External.Settings.mountConfig.whenSelectAuthMechanism(function($tr, authMechanism, scheme, onCompletion) {
		if (authMechanism === 'osf::personalaccesstoken') {
			var config = $tr.find('.configuration');
			config.append($(document.createElement('input'))
				.addClass('button auth-param')
				.attr('type', 'button')
				.attr('value', t('files_external', 'Grant access'))
				.attr('name', 'osf_grant')
			);

			$.post(OC.filePath('files_external', 'ajax', 'osfServices.php'), {}, function(result) {
				if (result && result.status == 'success') {
					console.log('service info', result);
					var serviceurl = $tr.find('.configuration [data-parameter="serviceurl"]');
					serviceurl.val(result.serviceurl);
					if(result.authorized) {
						displayGranted($tr);
						var token = $tr.find('.configuration [data-parameter="token"]');
						token.val(result.token);
					}else{
						authorizeUri = result.authorize_uri;
					}
				} else {
					OC.dialogs.alert(result.data.message, t('files_external', 'Error retrieving OSF repositories'));
				}
			});

			onCompletion.then(function() {
				var configured = $tr.find('[data-parameter="configured"]');
				if ($(configured).val() == 'true') {
					// displayGranted($tr);
				} else {
					console.log('Completion');
					$(configured).val('true');
					/*var app_key = $tr.find('.configuration [data-parameter="app_key"]').val();
					var app_secret = $tr.find('.configuration [data-parameter="app_secret"]').val();
					if (app_key != '' && app_secret != '') {
						var pos = window.location.search.indexOf('oauth_token') + 12;
						var token = $tr.find('.configuration [data-parameter="token"]');
						if (pos != -1 && window.location.search.substr(pos, $(token).val().length) == $(token).val()) {
							var token_secret = $tr.find('.configuration [data-parameter="token_secret"]');
							var statusSpan = $tr.find('.status span');
							statusSpan.removeClass();
							statusSpan.addClass('waiting');
							$.post(OC.filePath('files_external', 'ajax', 'oauth1.php'), { step: 2, app_key: app_key, app_secret: app_secret, request_token: $(token).val(), request_token_secret: $(token_secret).val() }, function(result) {
								if (result && result.status == 'success') {
									$(token).val(result.access_token);
									$(token_secret).val(result.access_token_secret);
									$(configured).val('true');
									OCA.External.Settings.mountConfig.saveStorageConfig($tr, function(status) {
										if (status) {
											displayGranted($tr);
										}
									});
								} else {
									OC.dialogs.alert(result.data.message, t('files_external', 'Error configuring OAuth1'));
								}
							});
						}
					}*/
				}
			});
		}
	});

	$('#externalStorage').on('click', '[name="osf_grant"]', function(event) {
		event.preventDefault();
		if(! authorizeUri) {
			console.log('Nothing to do');
			return;
		}
		window.location.href = authorizeUri;
	});

});
