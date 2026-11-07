// Área de Cliente — ativar/desativar notificações push.
// Depende de window.cpPush = { vapidPublicKey, restUrl, restNonce },
// definido inline por cp_enqueue_assets() em functions.php.
( function () {
	'use strict';

	function base64UrlParaUint8Array( base64Url ) {
		var padding = '='.repeat( ( 4 - ( base64Url.length % 4 ) ) % 4 );
		var base64  = ( base64Url + padding ).replace( /-/g, '+' ).replace( /_/g, '/' );
		var raw     = window.atob( base64 );
		var saida   = new Uint8Array( raw.length );
		for ( var i = 0; i < raw.length; i++ ) saida[ i ] = raw.charCodeAt( i );
		return saida;
	}

	function pedirApi( caminho, corpo ) {
		return fetch( window.cpPush.restUrl + caminho, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.cpPush.restNonce },
			body: JSON.stringify( corpo || {} ),
			credentials: 'same-origin',
		} );
	}

	function atualizarBotao( botao, ativo ) {
		botao.textContent = ativo ? 'Desativar notificações' : 'Ativar notificações';
		botao.setAttribute( 'aria-pressed', ativo ? 'true' : 'false' );
	}

	async function alternar( botao ) {
		var registo = await navigator.serviceWorker.ready;
		var subscricaoAtual = await registo.pushManager.getSubscription();

		if ( subscricaoAtual ) {
			await pedirApi( '/push/cancelar', { endpoint: subscricaoAtual.endpoint } );
			await subscricaoAtual.unsubscribe();
			atualizarBotao( botao, false );
			return;
		}

		var permissao = await Notification.requestPermission();
		if ( 'granted' !== permissao ) return;

		var subscricao = await registo.pushManager.subscribe( {
			userVisibleOnly: true,
			applicationServerKey: base64UrlParaUint8Array( window.cpPush.vapidPublicKey ),
		} );
		await pedirApi( '/push/subscrever', subscricao.toJSON() );
		atualizarBotao( botao, true );
	}

	document.addEventListener( 'DOMContentLoaded', async function () {
		var botao    = document.getElementById( 'cp-push-btn' );
		var mensagem = document.getElementById( 'cp-push-msg' );
		if ( ! botao || ! window.cpPush ) return;

		if ( ! ( 'serviceWorker' in navigator ) || ! ( 'PushManager' in window ) ) {
			botao.hidden = true;
			if ( mensagem ) {
				var isIOS = /iphone|ipad|ipod/i.test( navigator.userAgent );
				mensagem.hidden = false;
				mensagem.textContent = isIOS
					? 'No iPhone/iPad, adicione a Área de Cliente ao ecrã principal para poder ativar notificações.'
					: 'Este browser não suporta notificações push.';
			}
			return;
		}

		try {
			var registo = await navigator.serviceWorker.register( '/sw-push.js', { scope: '/' } );
			var subscricaoAtual = await registo.pushManager.getSubscription();
			atualizarBotao( botao, !! subscricaoAtual );
			botao.hidden = false;
			botao.addEventListener( 'click', function () { alternar( botao ); } );
		} catch ( erro ) {
			botao.hidden = true;
			if ( mensagem ) {
				mensagem.hidden = false;
				mensagem.textContent = 'Não foi possível preparar as notificações neste dispositivo.';
			}
		}
	} );
} )();
