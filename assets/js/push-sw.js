// Service worker da Área de Cliente — só trata notificações push.
// Servido em /sw-push.js (rota hand-rolled em inc/area-cliente-push.php)
// para ficar com scope "/" e poder abrir qualquer página do site.

self.addEventListener( 'push', function ( event ) {
	var dados = {};
	try {
		dados = event.data ? event.data.json() : {};
	} catch ( e ) {
		dados = {};
	}

	var titulo = dados.titulo || 'Entre Números';
	var opcoes = {
		body: dados.corpo || '',
		icon: '/wp-content/themes/contabilidade-pro/assets/img/favicon-192.png',
		badge: '/wp-content/themes/contabilidade-pro/assets/img/favicon-32.png',
		data: { url: dados.url || '/area-de-cliente/' },
	};

	event.waitUntil( self.registration.showNotification( titulo, opcoes ) );
} );

self.addEventListener( 'notificationclick', function ( event ) {
	event.notification.close();
	var url = ( event.notification.data && event.notification.data.url ) || '/area-de-cliente/';

	event.waitUntil(
		self.clients.matchAll( { type: 'window', includeUncontrolled: true } ).then( function ( janelas ) {
			for ( var i = 0; i < janelas.length; i++ ) {
				if ( janelas[ i ].url.indexOf( url ) !== -1 && 'focus' in janelas[ i ] ) {
					return janelas[ i ].focus();
				}
			}
			if ( self.clients.openWindow ) {
				return self.clients.openWindow( url );
			}
		} )
	);
} );
