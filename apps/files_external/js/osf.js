$(document).ready(function() {

	function displayGranted($tr) {
		$tr.find('.configuration input.auth-param').attr('disabled', 'disabled').addClass('disabled-success');
	}

	var authorizeUri = null;
	var currentNodes = [];

	OCA.External.Settings.mountConfig.whenSelectAuthMechanism(function($tr, authMechanism, scheme, onCompletion) {
		if (authMechanism === 'osf::personalaccesstoken') {
			var config = $tr.find('.configuration');
			config.append($(document.createElement('input'))
				.addClass('button auth-param')
				.attr('type', 'button')
				.attr('value', t('files_external', 'Grant access'))
				.attr('name', 'osf_grant')
			);
			config.append($(document.createElement('select'))
				.attr('name', 'osf_nodes')
			);
			config.append($(document.createElement('select'))
				.attr('name', 'osf_providers')
			);

			function refreshProvider() {
				var nodesSelector = $tr.find('[name="osf_nodes"]');
				var targetNodes = currentNodes.filter(function(node) {
					return node['id'] == nodesSelector.val();
				});
				var providersSelector = $tr.find('[name="osf_providers"]');
				providersSelector.empty();
				providersSelector.append($('<option>')
															.attr('value', '')
															.text('Provider'));
				var storagetype = $tr.find('.configuration [data-parameter="storagetype"]');
				if(targetNodes.length > 0) {
					var currentNode = targetNodes[0];
					currentNode['providers'].forEach(function(provider) {
						providersSelector.append($('<option>')
																	.attr('value', provider['provider'])
																	.text(provider['provider']));
					});
					providersSelector.val(storagetype.val());
				}else{
					var nodeId = $tr.find('.configuration [data-parameter="nodeId"]');
					var serviceurl = $tr.find('.configuration [data-parameter="serviceurl"]');
					serviceurl.val('');
					nodeId.val('');
					storagetype.val('');
				}
			}

			function selectProvider() {
				var nodesSelector = $tr.find('[name="osf_nodes"]');
				var targetNodes = currentNodes.filter(function(node) {
					return node['id'] == nodesSelector.val();
				});
				var nodeId = $tr.find('.configuration [data-parameter="nodeId"]');
				var storagetype = $tr.find('.configuration [data-parameter="storagetype"]');
				var serviceurl = $tr.find('.configuration [data-parameter="serviceurl"]');
				if(targetNodes.length == 0) {
					serviceurl.val('');
					nodeId.val('');
					storagetype.val('');
				}
				var currentNode = targetNodes[0];
				nodeId.val(currentNode['id']);
				var providersSelector = $tr.find('[name="osf_providers"]');
				var targetProviders = currentNode['providers'].filter(function(provider) {
					return provider['provider'] == providersSelector.val();
				});
				if(targetProviders.length == 0) {
					serviceurl.val('');
					storagetype.val('');
				}else{
					serviceurl.val(targetProviders[0]['base_uri']);
					storagetype.val(targetProviders[0]['provider']);
					console.log('Selected', currentNode, targetProviders[0]);
				}
			}

			onCompletion.then(function() {
				var configured = $tr.find('[data-parameter="configured"]');
				$.post(OC.filePath('files_external', 'ajax', 'osfServices.php'), {}, function(result) {
					if (result && result.status == 'success') {
						console.log('service info', result);
						var nodeId = $tr.find('.configuration [data-parameter="nodeId"]');
						if(result.authorized) {
							var token = $tr.find('.configuration [data-parameter="token"]');
							token.val(result.token);
							$(configured).val('true');
							currentNodes = result.nodes;
							var nodesSelector = $tr.find('[name="osf_nodes"]');
							nodesSelector.empty();
							nodesSelector.append($('<option>')
																		.attr('value', '')
																		.text('Node'));
							currentNodes.forEach(function(node) {
								nodesSelector.append($('<option>')
								                      .attr('value', node['id'])
																		  .text(node['title'] + ' - ' + node['id']));
							});
							nodesSelector.val(nodeId.val());
							refreshProvider();
							nodesSelector.change(refreshProvider);
							var providersSelector = $tr.find('[name="osf_providers"]');
							providersSelector.change(selectProvider);

							OCA.External.Settings.mountConfig.saveStorageConfig($tr, function(status) {
								if (status) {
									displayGranted($tr);
								}
							});
						}else{
							authorizeUri = result.authorize_uri;
						}
					} else {
						OC.dialogs.alert(result.data.message, t('files_external', 'Error retrieving OSF repositories'));
					}
				});
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
