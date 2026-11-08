// Botão flutuante do Dashboard (ex.: "Adicionar documento") — abre/fecha
// o modal com o formulário.
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var botao    = document.getElementById( 'cp-doc-fab-btn' );
		var modal    = document.getElementById( 'cp-doc-modal' );
		var backdrop = document.getElementById( 'cp-doc-modal-backdrop' );
		var fechar_btn = document.getElementById( 'cp-doc-modal-close' );
		if ( ! botao || ! modal || ! backdrop ) return;

		function abrir() {
			modal.hidden    = false;
			backdrop.hidden = false;
		}

		function fechar() {
			modal.hidden    = true;
			backdrop.hidden = true;
		}

		botao.addEventListener( 'click', abrir );
		backdrop.addEventListener( 'click', fechar );
		if ( fechar_btn ) fechar_btn.addEventListener( 'click', fechar );

		document.addEventListener( 'keydown', function ( evento ) {
			if ( 'Escape' === evento.key && ! modal.hidden ) fechar();
		} );
	} );
} )();
