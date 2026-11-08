// Menu lateral (Área de Cliente / Backoffice) em telemóvel: por defeito
// escondido (ver media query em area-cliente.css), abre como painel
// deslizante ao tocar no botão de hambúrguer.
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var botao   = document.getElementById( 'ac-sidebar-toggle' );
		var sidebar = document.getElementById( 'ac-sidebar' );
		var fundo   = document.getElementById( 'ac-sidebar-backdrop' );
		if ( ! botao || ! sidebar || ! fundo ) return;

		function abrir() {
			sidebar.classList.add( 'is-open' );
			fundo.classList.add( 'is-open' );
			botao.setAttribute( 'aria-expanded', 'true' );
		}

		function fechar() {
			sidebar.classList.remove( 'is-open' );
			fundo.classList.remove( 'is-open' );
			botao.setAttribute( 'aria-expanded', 'false' );
		}

		botao.addEventListener( 'click', function () {
			if ( sidebar.classList.contains( 'is-open' ) ) {
				fechar();
			} else {
				abrir();
			}
		} );

		fundo.addEventListener( 'click', fechar );

		document.addEventListener( 'keydown', function ( evento ) {
			if ( 'Escape' === evento.key ) fechar();
		} );

		// Fechar ao escolher uma opção do menu (o link navega na mesma).
		var links = sidebar.querySelectorAll( 'a' );
		for ( var i = 0; i < links.length; i++ ) {
			links[ i ].addEventListener( 'click', fechar );
		}
	} );
} )();
