// Botão flutuante do Dashboard (ex.: "Adicionar documento") — abre/fecha
// o painel com o formulário, mesmo padrão do menu do utilizador na topbar.
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var botao = document.getElementById( 'cp-doc-fab-btn' );
		var painel = document.getElementById( 'cp-doc-fab-panel' );
		if ( ! botao || ! painel ) return;

		function fechar() {
			painel.hidden = true;
			botao.setAttribute( 'aria-expanded', 'false' );
		}

		botao.addEventListener( 'click', function ( evento ) {
			evento.stopPropagation();
			if ( painel.hidden ) {
				painel.hidden = false;
				botao.setAttribute( 'aria-expanded', 'true' );
			} else {
				fechar();
			}
		} );

		document.addEventListener( 'click', function ( evento ) {
			if ( ! painel.hidden && ! painel.contains( evento.target ) && evento.target !== botao ) {
				fechar();
			}
		} );

		document.addEventListener( 'keydown', function ( evento ) {
			if ( 'Escape' === evento.key ) fechar();
		} );
	} );
} )();
