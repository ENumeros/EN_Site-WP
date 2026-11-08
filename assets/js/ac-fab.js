// Botão "+" flutuante do Dashboard: abre a lista de ações rápidas; cada
// item da lista abre a sua própria modal (convenção: o backdrop da modal
// com id "X" tem id "X-backdrop"). Genérico — novas ações não precisam de
// alterar este ficheiro.
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var fabBtn  = document.getElementById( 'cp-fab-btn' );
		var fabMenu = document.getElementById( 'cp-fab-menu' );

		function fecharMenu() {
			if ( ! fabMenu ) return;
			fabMenu.hidden = true;
			if ( fabBtn ) fabBtn.setAttribute( 'aria-expanded', 'false' );
		}

		if ( fabBtn && fabMenu ) {
			fabBtn.addEventListener( 'click', function ( evento ) {
				evento.stopPropagation();
				var abrir = fabMenu.hidden;
				fabMenu.hidden = ! abrir;
				fabBtn.setAttribute( 'aria-expanded', abrir ? 'true' : 'false' );
			} );

			document.addEventListener( 'click', function ( evento ) {
				if ( ! fabMenu.hidden && ! fabMenu.contains( evento.target ) && evento.target !== fabBtn ) {
					fecharMenu();
				}
			} );
		}

		function abrirModal( id ) {
			var modal    = document.getElementById( id );
			var backdrop = document.getElementById( id + '-backdrop' );
			if ( modal ) modal.hidden = false;
			if ( backdrop ) backdrop.hidden = false;
		}

		function fecharModal( modal ) {
			if ( ! modal ) return;
			var backdrop = document.getElementById( modal.id + '-backdrop' );
			modal.hidden = true;
			if ( backdrop ) backdrop.hidden = true;
		}

		document.querySelectorAll( '[data-cp-fab-target]' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				fecharMenu();
				abrirModal( item.getAttribute( 'data-cp-fab-target' ) );
			} );
		} );

		document.querySelectorAll( '[data-cp-fab-modal-close]' ).forEach( function ( botao ) {
			botao.addEventListener( 'click', function () {
				fecharModal( botao.closest( '.ac-modal' ) );
			} );
		} );

		document.querySelectorAll( '.ac-modal-backdrop[data-cp-fab-modal]' ).forEach( function ( backdrop ) {
			backdrop.addEventListener( 'click', function () {
				fecharModal( document.getElementById( backdrop.id.replace( /-backdrop$/, '' ) ) );
			} );
		} );

		document.addEventListener( 'keydown', function ( evento ) {
			if ( 'Escape' !== evento.key ) return;
			fecharMenu();
			document.querySelectorAll( '.ac-modal' ).forEach( function ( modal ) {
				if ( ! modal.hidden ) fecharModal( modal );
			} );
		} );
	} );
} )();
