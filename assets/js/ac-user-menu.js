// Topbar (Área de Cliente / Backoffice) — menu do utilizador: agrupa
// "Ativar notificações" e "Instalar app" num dropdown por baixo do
// avatar/nome, em vez de botões soltos a disputar espaço com o nome.
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var botao     = document.getElementById( 'cp-user-menu-btn' );
		var dropdown  = document.getElementById( 'cp-user-dropdown' );
		if ( ! botao || ! dropdown ) return;

		function fechar() {
			dropdown.hidden = true;
			botao.setAttribute( 'aria-expanded', 'false' );
		}

		botao.addEventListener( 'click', function ( evento ) {
			evento.stopPropagation();
			if ( dropdown.hidden ) {
				dropdown.hidden = false;
				botao.setAttribute( 'aria-expanded', 'true' );
			} else {
				fechar();
			}
		} );

		document.addEventListener( 'click', function ( evento ) {
			if ( ! dropdown.hidden && ! dropdown.contains( evento.target ) && evento.target !== botao ) {
				fechar();
			}
		} );

		document.addEventListener( 'keydown', function ( evento ) {
			if ( 'Escape' === evento.key ) fechar();
		} );
	} );
} )();
