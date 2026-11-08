// Mensagens (Área de Cliente / Backoffice) — várias conversas por
// empresa, cada uma com o seu assunto. Lê e envia por REST, sem
// recarregar a página (exceto ao criar uma conversa no Backoffice, que
// navega para já a mostrar selecionada — mesmo padrão já usado para
// trocar de empresa ali). Depende de window.cpMensagens = { restUrl,
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
		div.textContent = texto || '';
		return div.innerHTML;
	}

	function fecharModal( modal ) {
		if ( ! modal ) return;
		var backdrop = document.getElementById( modal.id + '-backdrop' );
		modal.hidden = true;
		if ( backdrop ) backdrop.hidden = true;
	}

	// ─── Mensagens de uma conversa ──────────────────────────────────────────

	function linhaMensagem( mensagem ) {
		return (
			'<div class="ac-mensagem ac-mensagem-' + mensagem.autor_tipo + '" data-id="' + mensagem.id + '">' +
				'<div class="ac-mensagem-meta">' + escapar( mensagem.autor_nome ) + ' — há ' + escapar( mensagem.enviado_ha ) + '</div>' +
				'<div class="ac-mensagem-texto">' + escapar( mensagem.texto ) + '</div>' +
			'</div>'
		);
	}

	function renderizarMensagens( lista, mensagens ) {
		if ( ! mensagens.length ) {
			lista.innerHTML = '<div class="ac-mensagens-vazio">Ainda sem mensagens nesta conversa.</div>';
			return;
		}
		lista.innerHTML = mensagens.map( linhaMensagem ).join( '' );
	}

	function formularioMensagemHtml( empresaId, conversaId, formId ) {
		return (
			'<form id="' + formId + '" data-cp-mensagens-form class="ac-mensagem-form">' +
				'<input type="hidden" name="empresa_id" value="' + empresaId + '">' +
				'<input type="hidden" name="conversa_id" value="' + conversaId + '">' +
				'<textarea name="texto" required rows="1" class="ac-mensagem-campo" placeholder="Escreva uma mensagem…"></textarea>' +
				'<button class="ac-btn ac-btn-ghost" type="submit">Enviar</button>' +
			'</form>'
		);
	}

	// ─── Conversas ──────────────────────────────────────────────────────────

	function linhaConversa( conversa ) {
		var badge = conversa.por_ler ? '<span class="ac-conversa-badge">' + conversa.por_ler + '</span>' : '';
		return (
			'<button type="button" class="ac-conversa-row" data-conversa-id="' + conversa.id + '">' +
				'<span class="ac-conversa-row-head"><span class="ac-conversa-assunto">' + escapar( conversa.assunto ) + '</span>' + badge + '</span>' +
				'<span class="ac-conversa-resumo">' + escapar( conversa.ultima_mensagem ) + '</span>' +
			'</button>'
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.cpMensagens || ! window.cpMensagens.empresaId ) return;
		var empresaId = window.cpMensagens.empresaId;

		var dropdown         = document.getElementById( 'cp-user-dropdown' );
		var listaConversasEl = document.getElementById( 'cp-conversas-lista' );
		var badge            = document.getElementById( 'cp-mensagens-badge' );
		var conversaModalEl  = document.getElementById( 'cp-conversa-modal' );

		var conversasCache     = [];
		var timerConversas     = null;
		var timerConversaAberta = null;
		var conversaAbertaId   = null;

		// ─── Lista de conversas (dropdown) + badge do avatar ───────────────

		function atualizarConversas() {
			return pedirApi( 'GET', '/conversas?empresa_id=' + empresaId ).then( function ( dados ) {
				conversasCache = dados.conversas || [];
				if ( listaConversasEl ) {
					listaConversasEl.innerHTML = conversasCache.length
						? conversasCache.map( linhaConversa ).join( '' )
						: '<div class="ac-mensagens-vazio">Sem conversas — comece uma abaixo.</div>';
				}
				if ( badge ) badge.hidden = ! ( dados.por_ler_total > 0 );
			} ).catch( function () { /* próximo poll tenta outra vez */ } );
		}

		function iniciarPollConversas() {
			if ( timerConversas ) return;
			timerConversas = setInterval( atualizarConversas, INTERVALO_MS );
		}

		function pararPollConversas() {
			clearInterval( timerConversas );
			timerConversas = null;
		}

		if ( listaConversasEl || badge ) {
			atualizarConversas();
			iniciarPollConversas();
		}

		if ( dropdown ) {
			new MutationObserver( function () {
				if ( ! dropdown.hidden ) atualizarConversas();
			} ).observe( dropdown, { attributes: true, attributeFilter: [ 'hidden' ] } );
		}

		// ─── Mensagens de uma conversa aberta ───────────────────────────────

		function carregarMensagensConversa( conversaId ) {
			pedirApi( 'GET', '/mensagens?empresa_id=' + empresaId + '&conversa_id=' + conversaId ).then( function ( dados ) {
				var lista = document.getElementById( 'cp-mensagens-lista' );
				if ( ! lista ) return;
				renderizarMensagens( lista, dados.mensagens || [] );
				lista.scrollTop = lista.scrollHeight;
			} ).catch( function () { /* próximo poll tenta outra vez */ } );
		}

		function pararPollConversaAberta() {
			clearInterval( timerConversaAberta );
			timerConversaAberta = null;
			conversaAbertaId = null;
		}

		// Área de Cliente: clicar numa conversa do dropdown abre a modal
		// partilhada, com conteúdo montado aqui (não dá para pré-renderizar
		// todas as conversas na página).
		function abrirConversaModal( conversaId, assunto ) {
			if ( ! conversaModalEl ) return;
			var titulo = document.getElementById( 'cp-conversa-modal-title' );
			var corpo  = document.getElementById( 'cp-conversa-modal-corpo' );
			if ( titulo ) titulo.textContent = assunto;
			if ( corpo ) {
				corpo.innerHTML = '<div id="cp-mensagens-lista" class="ac-mensagens-lista"></div><div style="margin-top:14px">' +
					formularioMensagemHtml( empresaId, conversaId, 'cp-mensagens-form-modal' ) + '</div>';
			}
			conversaModalEl.hidden = false;
			document.getElementById( 'cp-conversa-modal-backdrop' ).hidden = false;

			conversaAbertaId = conversaId;
			carregarMensagensConversa( conversaId );
			pedirApi( 'POST', '/mensagens/marcar-lida', { empresa_id: empresaId, conversa_id: conversaId } ).then( atualizarConversas );
			clearInterval( timerConversaAberta );
			timerConversaAberta = setInterval( function () { carregarMensagensConversa( conversaId ); }, INTERVALO_MS );
		}

		if ( conversaModalEl ) {
			new MutationObserver( function () {
				if ( conversaModalEl.hidden && conversaAbertaId ) {
					pararPollConversaAberta();
					atualizarConversas();
				}
			} ).observe( conversaModalEl, { attributes: true, attributeFilter: [ 'hidden' ] } );
		}

		document.addEventListener( 'click', function ( evento ) {
			var linha = evento.target.closest( '.ac-conversa-row' );
			if ( ! linha ) return;
			var id = linha.getAttribute( 'data-conversa-id' );
			var conversa = conversasCache.filter( function ( c ) { return c.id === id; } )[ 0 ];
			if ( conversa ) abrirConversaModal( id, conversa.assunto );
		} );

		// Backoffice: a conversa já vem escolhida (e a lista já renderizada)
		// no carregamento da página — só falta o poll periódico e marcar
		// como lida, sem passar pela modal (que não existe aqui).
		var listaInicial = document.getElementById( 'cp-mensagens-lista' );
		if ( listaInicial && ! conversaModalEl ) {
			var campoConversa = document.querySelector( '[data-cp-mensagens-form] [name="conversa_id"]' );
			var conversaIdBO  = campoConversa ? campoConversa.value : '';
			if ( conversaIdBO ) {
				conversaAbertaId = conversaIdBO;
				pedirApi( 'POST', '/mensagens/marcar-lida', { empresa_id: empresaId, conversa_id: conversaIdBO } );
				timerConversaAberta = setInterval( function () { carregarMensagensConversa( conversaIdBO ); }, INTERVALO_MS );
			}
		}

		// ─── Envio de mensagem / criação de conversa (delegado — os
		// formulários da modal são montados depois deste listener existir) ──

		document.addEventListener( 'submit', function ( evento ) {
			var formMensagem = evento.target.closest( '[data-cp-mensagens-form]' );
			if ( formMensagem ) {
				evento.preventDefault();
				var campo = formMensagem.querySelector( 'textarea[name="texto"]' );
				var texto = campo.value.trim();
				if ( '' === texto ) return;
				var conversaId = formMensagem.querySelector( '[name="conversa_id"]' ).value;
				pedirApi( 'POST', '/mensagens', { empresa_id: empresaId, conversa_id: conversaId, texto: texto } ).then( function () {
					campo.value = '';
					carregarMensagensConversa( conversaId );
				} );
				return;
			}

			var formConversa = evento.target.closest( '[data-cp-conversa-form]' );
			if ( formConversa ) {
				evento.preventDefault();
				var assuntoCampo = formConversa.querySelector( 'input[name="assunto"]' );
				var textoCampo   = formConversa.querySelector( 'textarea[name="texto"]' );
				var assunto = assuntoCampo.value.trim();
				var textoConversa = textoCampo.value.trim();
				if ( '' === assunto || '' === textoConversa ) return;

				pedirApi( 'POST', '/conversas', { empresa_id: empresaId, assunto: assunto, texto: textoConversa } ).then( function ( dados ) {
					var modalDaConversa = formConversa.closest( '.ac-modal' );
					if ( ! modalDaConversa ) {
						// Backoffice: navega já com a conversa nova selecionada —
						// o <select> precisa de recarregar para a ter como opção.
						var url = new URL( window.location.href );
						url.searchParams.set( 'empresa_id', empresaId );
						url.searchParams.set( 'conversa_id', dados.conversa.id );
						window.location.href = url.toString();
						return;
					}
					fecharModal( modalDaConversa );
					abrirConversaModal( dados.conversa.id, dados.conversa.assunto );
				} );
			}
		} );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				pararPollConversas();
				clearInterval( timerConversaAberta );
			} else {
				if ( listaConversasEl || badge ) {
					atualizarConversas();
					iniciarPollConversas();
				}
				if ( conversaAbertaId ) {
					timerConversaAberta = setInterval( function () { carregarMensagensConversa( conversaAbertaId ); }, INTERVALO_MS );
				}
			}
		} );
	} );
} )();
