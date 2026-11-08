// Área de Cliente — atalho "Instalar app" no topbar.
// Android/Chrome: usa o prompt nativo (beforeinstallprompt).
// iOS/Safari: não existe esse evento — mostra instruções (Partilhar →
// Adicionar ao Ecrã Principal) em vez de fingir que o botão instala.
( function () {
	'use strict';
	var pedidoAdiado = null;

	function jaInstalada() {
		return ( window.matchMedia && window.matchMedia( '(display-mode: standalone)' ).matches ) || window.navigator.standalone === true;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var botao       = document.getElementById( 'cp-pwa-install-btn' );
		var instrucoes  = document.getElementById( 'cp-pwa-ios-instrucoes' );
		if ( ! botao ) return;

		if ( jaInstalada() ) {
			botao.hidden = true;
			return;
		}

		var isIOS = /iphone|ipad|ipod/i.test( navigator.userAgent );
		if ( isIOS ) {
			// Sem beforeinstallprompt no Safari — mostra sempre; ao
			// clicar, explica o caminho manual em vez de não fazer nada.
			botao.hidden = false;
		}

		window.addEventListener( 'beforeinstallprompt', function ( evento ) {
			evento.preventDefault();
			pedidoAdiado = evento;
			botao.hidden = false;
		} );

		window.addEventListener( 'appinstalled', function () {
			botao.hidden = true;
			pedidoAdiado = null;
			if ( instrucoes ) instrucoes.hidden = true;
		} );

		botao.addEventListener( 'click', function () {
			if ( pedidoAdiado ) {
				var prompt = pedidoAdiado;
				pedidoAdiado = null;
				prompt.prompt();
				return;
			}
			if ( instrucoes ) {
				instrucoes.hidden = ! instrucoes.hidden;
			}
		} );
	} );
} )();
