// Mensagens (Área de Cliente / Backoffice) — lê e envia por REST, sem
// recarregar a página. Depende de window.cpMensagens = { restUrl,
// restNonce, empresaId }, definido inline por cp_enqueue_assets() em
// functions.php. Mesmo estilo de pedido (fetch + X-WP-Nonce) de
// assets/js/push.js.
( function () {
	'use strict';

	var INTERVALO_MS = 12000;

	function pedirApi( metodo, caminho, corpo ) {
		var opcoes = {
			method: metodo,
			headers: { 'X-WP-Nonce': window.cpMensagens.restNonce },
			credentials: 'same-origin',
		};
		if ( corpo ) {
			opcoes.headers['Content-Type'] = 'application/json';
			opcoes.body = JSON.stringify( corpo );
		}
		return fetch( window.cpMensagens.restUrl + caminho, opcoes ).then( function ( resposta ) {
			if ( ! resposta.ok ) throw new Error( 'Pedido falhou' );
			return resposta.json();
		} );
	}

	function escapar( texto ) {
		var div = document.createElement( 'div' );
		div.textContent = texto;
		return div.innerHTML;
	}

	function linhaMensagem( mensagem ) {
		return (
			'<div class="ac-mensagem ac-mensagem-' + mensagem.autor_tipo + '" data-id="' + mensagem.id + '">' +
				'<div class="ac-mensagem-meta">' + escapar( mensagem.autor_nome ) + ' — há ' + escapar( mensagem.enviado_ha ) + '</div>' +
				'<div class="ac-mensagem-texto">' + escapar( mensagem.texto ) + '</div>' +
			'</div>'
		);
	}

	function renderizarLista( lista, mensagens ) {
		if ( ! mensagens.length ) {
			lista.innerHTML = '<div class="ac-mensagens-vazio">Sem mensagens — escreva aqui para falar com a Entre Números.</div>';
			return;
		}
		lista.innerHTML = mensagens.map( linhaMensagem ).join( '' );
	}

	// Fecha a modal a que este formulário pertence, se for o caso — mesma
	// convenção de ids de ac-fab.js (backdrop = id da modal + "-backdrop").
	function fecharModalDoFormulario( form ) {
		var modal = form.closest( '.ac-modal' );
		if ( ! modal ) return;
		var backdrop = document.getElementById( modal.id + '-backdrop' );
		modal.hidden = true;
		if ( backdrop ) backdrop.hidden = true;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var lista     = document.getElementById( 'cp-mensagens-lista' );
		var formulas  = document.querySelectorAll( '[data-cp-mensagens-form]' );
		if ( ! lista || ! formulas.length || ! window.cpMensagens || ! window.cpMensagens.empresaId ) return;

		var ultimoId  = null;
		var timer     = null;

		function atualizar() {
			pedirApi( 'GET', '/mensagens?empresa_id=' + window.cpMensagens.empresaId ).then( function ( dados ) {
				var mensagens = dados.mensagens || [];
				var novoUltimoId = mensagens.length ? mensagens[ mensagens.length - 1 ].id : null;
				renderizarLista( lista, mensagens );
				if ( novoUltimoId !== ultimoId ) {
					lista.scrollTop = lista.scrollHeight;
					ultimoId = novoUltimoId;
				}
			} ).catch( function () { /* próximo poll tenta outra vez */ } );
		}

		function iniciarPolling() {
			if ( timer ) return;
			timer = setInterval( atualizar, INTERVALO_MS );
		}

		function pararPolling() {
			clearInterval( timer );
			timer = null;
		}

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				pararPolling();
			} else {
				atualizar();
				iniciarPolling();
			}
		} );

		formulas.forEach( function ( form ) {
			form.addEventListener( 'submit', function ( evento ) {
				evento.preventDefault();
				var campo = form.querySelector( 'textarea[name="texto"]' );
				var texto = campo.value.trim();
				if ( '' === texto ) return;

				pedirApi( 'POST', '/mensagens', { empresa_id: window.cpMensagens.empresaId, texto: texto } ).then( function () {
					campo.value = '';
					fecharModalDoFormulario( form );
					atualizar();
				} );
			} );
		} );

		atualizar();
		iniciarPolling();
	} );
} )();
