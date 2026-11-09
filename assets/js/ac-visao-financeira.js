// Visão financeira (WORK-045) — desenha tudo a partir de window.cpVisaoFinanceira
// (o JSON completo da empresa, todos os meses). Trocar de mês nunca recarrega a
// página nem pede nada ao servidor: o Work já manda tudo de uma vez.
// Contrato: D:\EN_Work-APP\docs\visao-financeira\BRIEF-WP.md (WORK-045).
( function () {
	var root = document.getElementById( 'cp-vf-root' );
	if ( ! root ) return;

	var dados = window.cpVisaoFinanceira || {};
	var meses = Array.isArray( dados.meses ) ? dados.meses : [];
	if ( ! meses.length ) return;

	var series        = dados.series || {};
	var pendentes      = parseInt( root.getAttribute( 'data-pendentes' ), 10 ) || 0;
	var indiceAtual    = meses.length - 1;
	var contaSelecionada = null;

	var NOMES_MES = [ 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' ];
	var ABREV_MES = [ 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez' ];

	function fmtEuro( v ) {
		if ( v === null || v === undefined ) return '—';
		return new Intl.NumberFormat( 'pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 0 } ).format( v ) + ' €';
	}
	function fmtEuroAssinado( v ) {
		var sinal = v < 0 ? '−' : '+';
		return sinal + fmtEuro( Math.abs( v ) );
	}
	function fmtPct( v, casas ) {
		return new Intl.NumberFormat( 'pt-PT', { minimumFractionDigits: casas || 1, maximumFractionDigits: casas || 1 } ).format( v ) + '%';
	}
	function partesMes( chaveMes ) {
		var partes = chaveMes.split( '-' );
		return { ano: parseInt( partes[0], 10 ), mes: parseInt( partes[1], 10 ) };
	}
	function fmtDataPT( iso ) {
		if ( ! iso ) return '';
		var p = iso.split( '-' );
		return p[2] + ' de ' + NOMES_MES[ parseInt( p[1], 10 ) - 1 ] + ' de ' + p[0];
	}
	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) n.className = cls;
		if ( html !== undefined ) n.innerHTML = html;
		return n;
	}
	function limpar( node ) { while ( node.firstChild ) node.removeChild( node.firstChild ); }

	// ─── Navegador de mês ─────────────────────────────────────────────────

	var elTitulo   = document.getElementById( 'cp-vf-mes-titulo' );
	var elSub      = document.getElementById( 'cp-vf-mes-sub' );
	var elSlider   = document.getElementById( 'cp-vf-mes-slider' );
	var elBotoes   = document.getElementById( 'cp-vf-mes-botoes' );
	var btnPrev    = document.getElementById( 'cp-vf-mes-prev' );
	var btnNext    = document.getElementById( 'cp-vf-mes-next' );

	elSlider.max = String( meses.length - 1 );

	function montarBotoesMes() {
		limpar( elBotoes );
		for ( var a = 0; a < 12; a++ ) {
			( function ( mesNum ) {
				var btn = el( 'button', 'ac-vf-mes-btn', ABREV_MES[ mesNum - 1 ] );
				btn.type = 'button';
				btn.setAttribute( 'data-mes-num', mesNum );
				btn.disabled = true;
				btn.addEventListener( 'click', function () {
					var idx = parseInt( btn.getAttribute( 'data-indice' ), 10 );
					if ( ! isNaN( idx ) ) irParaMes( idx );
				} );
				elBotoes.appendChild( btn );
			} )( a + 1 );
		}
	}
	montarBotoesMes();

	function atualizarBotoesMes() {
		var botoes = elBotoes.querySelectorAll( '.ac-vf-mes-btn' );
		for ( var i = 0; i < botoes.length; i++ ) {
			botoes[ i ].disabled = true;
			botoes[ i ].classList.remove( 'is-ativo' );
			botoes[ i ].removeAttribute( 'data-indice' );
		}
		meses.forEach( function ( mes, idx ) {
			var mesNum = partesMes( mes.mes ).mes;
			var btn = elBotoes.querySelector( '[data-mes-num="' + mesNum + '"]' );
			if ( ! btn ) return;
			btn.disabled = false;
			btn.setAttribute( 'data-indice', idx );
			if ( idx === indiceAtual ) btn.classList.add( 'is-ativo' );
		} );
	}

	function irParaMes( idx ) {
		if ( idx < 0 || idx >= meses.length ) return;
		indiceAtual = idx;
		contaSelecionada = null;
		render();
	}

	btnPrev.addEventListener( 'click', function () { irParaMes( indiceAtual - 1 ); } );
	btnNext.addEventListener( 'click', function () { irParaMes( indiceAtual + 1 ); } );
	elSlider.addEventListener( 'input', function () { irParaMes( parseInt( elSlider.value, 10 ) ); } );

	// ─── Cascata ("As contas do mês") ──────────────────────────────────────

	var elCascata        = document.getElementById( 'cp-vf-cascata' );
	var elCascataLegenda = document.getElementById( 'cp-vf-cascata-legenda' );
	var elDetalhe        = document.getElementById( 'cp-vf-detalhe' );
	var elEbitda         = document.getElementById( 'cp-vf-ebitda' );
	var ALTURA_PISTA     = 170;

	function renderCascata( mes ) {
		var contas = mes.contas || [];
		limpar( elCascata );

		var nivel = 0, min = 0, max = 0;
		var geom = contas.map( function ( conta ) {
			var inicio, fim;
			if ( conta.tipo === 'vendas' ) { inicio = 0; fim = conta.valor; nivel = fim; }
			else if ( conta.tipo === 'rendimento' ) { inicio = nivel; fim = nivel + conta.valor; nivel = fim; }
			else if ( conta.tipo === 'custo' ) { fim = nivel; inicio = nivel - conta.valor; nivel = inicio; }
			else { inicio = 0; fim = conta.valor; nivel = fim; } // subtotal
			min = Math.min( min, inicio, fim );
			max = Math.max( max, inicio, fim );
			return { conta: conta, inicio: inicio, fim: fim };
		} );
		var amplitude = ( max - min ) || 1;
		var escala = ALTURA_PISTA / amplitude;

		if ( ! contaSelecionada && contas.length ) {
			var porOmissao = contas.filter( function ( c ) { return c.id === 'servicos'; } )[0]
				|| contas.filter( function ( c ) { return c.tipo === 'custo'; } )[0]
				|| contas[0];
			contaSelecionada = porOmissao.id;
		}

		geom.forEach( function ( g ) {
			var conta = g.conta;
			var topo    = Math.max( g.inicio, g.fim );
			var base    = Math.min( g.inicio, g.fim );
			var altura  = Math.max( 2, ( topo - base ) * escala );
			var bottom  = ( base - min ) * escala;

			var botaoCol = el( 'button', 'ac-vf-barra-col' + ( conta.id === contaSelecionada ? ' is-ativa' : '' ) );
			botaoCol.type = 'button';
			var pista = el( 'div', 'ac-vf-barra-pista' );
			var negativo = conta.tipo === 'subtotal' && conta.valor < 0;
			var barra = el( 'div', 'ac-vf-barra tipo-' + conta.tipo + ( negativo ? ' is-negativo' : '' ) );
			barra.style.position = 'absolute';
			barra.style.left = '0'; barra.style.right = '0';
			barra.style.bottom = bottom + 'px';
			barra.style.height = altura + 'px';
			var valorLabel = el( 'span', 'ac-vf-barra-valor', ( conta.valor >= 0 ? '' : '−' ) + Math.round( Math.abs( conta.valor ) / 1000 * 10 ) / 10 + ' mil' );
			barra.appendChild( valorLabel );
			pista.style.position = 'relative';
			pista.appendChild( barra );
			botaoCol.appendChild( pista );
			botaoCol.appendChild( el( 'span', 'ac-vf-barra-label', conta.titulo || conta.nome ) );
			botaoCol.addEventListener( 'click', function () { contaSelecionada = conta.id; render(); } );
			elCascata.appendChild( botaoCol );
		} );

		limpar( elCascataLegenda );
		[ [ 'var(--ac-blue)', 'Vendas' ], [ '#D9A441', 'Custos que saem' ], [ 'var(--ac-navy)', 'O que fica' ] ].forEach( function ( item ) {
			var span = el( 'span', '', '<i style="background:' + item[0] + '"></i>' + item[1] );
			elCascataLegenda.appendChild( span );
		} );
		var notaUnidade = el( 'span', '', 'Valores em milhares de euros, sem IVA. O lucro é antes do imposto sobre o lucro (IRC).' );
		notaUnidade.style.marginLeft = 'auto';
		elCascataLegenda.appendChild( notaUnidade );

		renderDetalhe( contas.filter( function ( c ) { return c.id === contaSelecionada; } )[0] || contas[0], mes );
		renderEbitda( mes );
	}

	function tendenciaMedia( conta ) {
		if ( ! conta.media_12m ) return '';
		var diffPct = ( conta.valor - conta.media_12m ) / conta.media_12m * 100;
		if ( Math.abs( diffPct ) < 5 ) return 'em linha com o habitual';
		var sobeDesce = diffPct > 0 ? '↑' : '↓';
		return sobeDesce + ' ' + fmtPct( Math.abs( diffPct ) ) + ( diffPct > 0 ? ' acima' : ' abaixo' ) + ' da média';
	}

	function renderDetalhe( conta, mes ) {
		limpar( elDetalhe );
		if ( ! conta ) return;
		var nomeMes = NOMES_MES[ partesMes( mes.mes ).mes - 1 ];
		elDetalhe.appendChild( el( 'div', 'ac-stat-label', ( conta.titulo || conta.nome ).toUpperCase() + ' EM ' + nomeMes.toUpperCase() ) );
		elDetalhe.appendChild( el( 'div', 'ac-vf-destaque-val', fmtEuro( conta.valor ) ) );
		if ( conta.explicacao ) {
			var exp = el( 'div', '', conta.explicacao );
			exp.style.cssText = 'font-size:12.5px;color:var(--ac-muted);margin-top:4px';
			elDetalhe.appendChild( exp );
		}
		var meta = el( 'div', '', tendenciaMedia( conta ) + ( conta.acumulado !== undefined && conta.acumulado !== null ? ' · acumulado ' + fmtEuro( conta.acumulado ) : '' ) );
		meta.style.cssText = 'font-size:12px;color:var(--ac-muted);margin-top:10px';
		elDetalhe.appendChild( meta );

		if ( conta.composicao && conta.composicao.length ) {
			var lista = el( 'div', 'ac-vf-detalhe-composicao' );
			conta.composicao.forEach( function ( item ) {
				var linha = el( 'div', 'ac-vf-detalhe-linha' );
				linha.appendChild( el( 'span', 'nome', item.nome ) );
				linha.appendChild( el( 'span', 'valor', item.valor ? fmtEuro( item.valor ) : '—' ) );
				lista.appendChild( linha );
				if ( item.nota ) {
					lista.appendChild( el( 'div', 'ac-vf-detalhe-nota' + ( item.destaque ? ' is-destaque' : '' ), item.nota ) );
				}
			} );
			elDetalhe.appendChild( lista );
		} else if ( conta.formula && conta.formula.length ) {
			var listaF = el( 'div', 'ac-vf-detalhe-composicao' );
			conta.formula.forEach( function ( linha ) {
				var row = el( 'div', 'ac-vf-detalhe-linha' );
				row.appendChild( el( 'span', 'nome', linha.nome ) );
				row.appendChild( el( 'span', 'valor', fmtEuroAssinado( linha.valor ) ) );
				listaF.appendChild( row );
			} );
			var total = el( 'div', 'ac-vf-detalhe-linha' );
			total.style.borderTop = '1px solid var(--ac-hairline)';
			total.style.paddingTop = '8px';
			total.style.marginTop = '4px';
			total.appendChild( el( 'span', 'nome', '= ' + ( conta.titulo || conta.nome ) ) );
			total.appendChild( el( 'span', 'valor', fmtEuro( conta.valor ) ) );
			listaF.appendChild( total );
			elDetalhe.appendChild( listaF );
		}
	}

	function renderEbitda( mes ) {
		limpar( elEbitda );
		var e = mes.ebitda;
		if ( ! e ) return;
		var head = el( 'div' );
		head.style.cssText = 'display:flex;align-items:center;justify-content:space-between';
		head.appendChild( el( 'div', 'ac-stat-label', 'EBITDA' ) );
		head.appendChild( el( 'span', 'ac-badge ' + ( e.valor >= 0 ? 'ac-badge-green' : 'ac-badge-red' ), e.valor >= 0 ? 'Positivo' : 'Negativo' ) );
		elEbitda.appendChild( head );
		elEbitda.appendChild( el( 'div', 'ac-vf-destaque-val', fmtEuro( e.valor ) ) );
		var sub = el( 'div', '', 'Lê-se "ébitda". É o dinheiro que a atividade gera antes de pagar juros e o imposto sobre o lucro, e antes de contar com o desgaste dos equipamentos. Mostra se o negócio em si dá dinheiro, seja qual for a forma como foi financiado ou quanto investiu.' );
		sub.style.cssText = 'font-size:12.5px;color:var(--ac-muted);margin-top:4px';
		elEbitda.appendChild( sub );

		var ponte = el( 'div' );
		ponte.style.cssText = 'margin-top:16px;padding-top:14px;border-top:1px solid var(--ac-hairline);font-size:11px;font-weight:600;color:var(--ac-muted);text-transform:uppercase;letter-spacing:.04em';
		ponte.textContent = 'Do EBITDA ao lucro';
		elEbitda.appendChild( ponte );
		[
			[ 'EBITDA', e.acumulado, false ],
			[ '− Desgaste dos equipamentos', -e.depreciacoes_acumulado, false ],
			[ '− Juros', -e.juros_acumulado, false ],
			[ '= Lucro antes de impostos', e.lucro_acumulado, true ],
		].forEach( function ( linha ) {
			var row = el( 'div', 'ac-vf-detalhe-linha' );
			if ( linha[2] ) { row.style.fontWeight = '700'; row.style.marginTop = '4px'; row.style.paddingTop = '6px'; row.style.borderTop = '1px dashed var(--ac-line)'; }
			row.appendChild( el( 'span', 'nome', linha[0] ) );
			row.appendChild( el( 'span', 'valor', fmtEuro( linha[1] ) ) );
			elEbitda.appendChild( row );
		} );

		if ( e.divida_bancaria ) {
			var grid = el( 'div', 'ac-vf-grid3' );
			grid.style.marginTop = '16px';
			[
				[ 'Dívida ÷ EBITDA', e.divida_em_anos_de_ebitda.toFixed( 1 ) + '×' ],
				[ 'Por cada 100€ de EBITDA', fmtEuro( e.por_cada_100_ebitda ) + ' de dívida' ],
				[ 'Por cada 100€ de lucro', fmtEuro( e.por_cada_100_lucro ) + ' de dívida' ],
			].forEach( function ( item ) {
				var col = el( 'div' );
				col.appendChild( el( 'div', 'ac-stat-label', item[0] ) );
				var v = el( 'div', '', item[1] ); v.style.cssText = 'font-family:\'Fraunces\',serif;font-size:17px;margin-top:2px';
				col.appendChild( v );
				grid.appendChild( col );
			} );
			elEbitda.appendChild( grid );
		}
	}

	// ─── Vendas ─────────────────────────────────────────────────────────────

	var elVendasTotal  = document.getElementById( 'cp-vf-vendas-total' );
	var elVendasTrend   = document.getElementById( 'cp-vf-vendas-trend' );
	var elVendasGrafico = document.getElementById( 'cp-vf-vendas-grafico' );
	var elClientes       = document.getElementById( 'cp-vf-clientes' );

	function renderVendas( mes ) {
		elVendasTotal.textContent = fmtEuro( mes.contas && mes.contas[0] ? mes.contas[0].acumulado : mes.destaques.vendas );
		var pct = mes.destaques.vendas_ano_anterior
			? ( ( mes.destaques.vendas - mes.destaques.vendas_ano_anterior ) / mes.destaques.vendas_ano_anterior * 100 )
			: 0;
		elVendasTrend.className = 'ac-trend ' + ( pct >= 0 ? 'ac-trend-up' : '' );
		elVendasTrend.textContent = ( pct >= 0 ? '↑ ' : '↓ ' ) + fmtPct( Math.abs( pct ) ) + ' que em ' + ( partesMes( mes.mes ).ano - 1 );

		limpar( elVendasGrafico );
		var pontos = series.vendas || [];
		var anoAtual = partesMes( mes.mes ).ano;
		var porMesAno = {};
		pontos.forEach( function ( p ) { porMesAno[ p.mes ] = p.valor; } );
		var max = Math.max.apply( null, pontos.map( function ( p ) { return p.valor; } ).concat( [ 1 ] ) );

		for ( var m = 1; m <= 12; m++ ) {
			var chaveAtual = anoAtual + '-' + ( m < 10 ? '0' + m : m );
			var chaveAnt   = ( anoAtual - 1 ) + '-' + ( m < 10 ? '0' + m : m );
			var vAtual = porMesAno[ chaveAtual ];
			var vAnt   = porMesAno[ chaveAnt ];
			var col = el( 'div', 'ac-vf-vendas-mes' + ( m === partesMes( mes.mes ).mes ? ' is-ativo' : '' ) );
			var par = el( 'div', 'ac-vf-vendas-par' );
			var barAnt = el( 'div', 'ac-vf-vendas-bar ano-anterior' );
			barAnt.style.height = ( vAnt ? vAnt / max * 115 : 0 ) + 'px';
			var barAtual = el( 'div', 'ac-vf-vendas-bar ano-atual' );
			barAtual.style.height = ( vAtual ? vAtual / max * 115 : 0 ) + 'px';
			par.appendChild( barAnt );
			par.appendChild( barAtual );
			if ( vAtual !== undefined ) {
				( function ( mesNum ) {
					par.addEventListener( 'click', function () {
						var idx = meses.findIndex( function ( mm ) { return mm.mes === anoAtual + '-' + ( mesNum < 10 ? '0' + mesNum : mesNum ); } );
						if ( idx > -1 ) irParaMes( idx );
					} );
				} )( m );
				par.style.cursor = 'pointer';
			}
			col.appendChild( par );
			col.appendChild( el( 'span', 'ac-vf-vendas-mes-label', ABREV_MES[ m - 1 ] ) );
			elVendasGrafico.appendChild( col );
		}

		if ( mes.clientes_top && mes.clientes_top.length ) {
			elClientes.hidden = false;
			limpar( elClientes );
			elClientes.appendChild( el( 'div', 'ac-stat-label', 'OS SEUS MAIORES CLIENTES' ) );
			var sub = el( 'div', '', 'Peso nas vendas de janeiro a ' + NOMES_MES[ partesMes( mes.mes ).mes - 1 ] );
			sub.style.cssText = 'font-size:12.5px;color:var(--ac-muted);margin-bottom:10px';
			elClientes.appendChild( sub );
			mes.clientes_top.forEach( function ( c ) {
				var linha = el( 'div' );
				linha.style.margin = '10px 0';
				linha.appendChild( el( 'div', '', c.nome ) ).style.cssText = 'font-size:13.5px;font-weight:600';
				var barraFundo = el( 'div' );
				barraFundo.style.cssText = 'height:6px;border-radius:999px;background:var(--ac-line);margin-top:6px;position:relative';
				var barraFill = el( 'div' );
				barraFill.style.cssText = 'position:absolute;inset:0;border-radius:999px;background:var(--ac-navy);width:' + c.pct + '%';
				barraFundo.appendChild( barraFill );
				linha.appendChild( barraFundo );
				var pct2 = el( 'div', '', fmtPct( c.pct ) );
				pct2.style.cssText = 'font-size:12px;color:var(--ac-muted);margin-top:3px;text-align:right';
				linha.appendChild( pct2 );
				elClientes.appendChild( linha );
			} );
		} else {
			elClientes.hidden = true;
		}
	}

	// ─── Dinheiro / tesouraria ──────────────────────────────────────────────

	function renderTesouraria( mes ) {
		document.getElementById( 'cp-vf-tesouraria-dinheiro' ).textContent = fmtEuro( mes.destaques.dinheiro );
		var diff = mes.destaques.dinheiro - mes.destaques.dinheiro_mes_anterior;
		var trend = document.getElementById( 'cp-vf-tesouraria-trend' );
		trend.className = 'ac-trend ' + ( diff >= 0 ? 'ac-trend-up' : '' );
		trend.textContent = ( diff >= 0 ? '↑ ' : '↓ ' ) + fmtEuro( Math.abs( diff ) ) + ' desde o mês anterior';

		var linhaEl = document.getElementById( 'cp-vf-tesouraria-linha' );
		limpar( linhaEl );
		var pontos = series.dinheiro || [];
		if ( pontos.length > 1 ) {
			var w = 600, h = 120, pad = 8;
			var vals = pontos.map( function ( p ) { return p.valor; } );
			var min = Math.min.apply( null, vals ), max = Math.max.apply( null, vals );
			var amp = ( max - min ) || 1;
			var coords = pontos.map( function ( p, i ) {
				var x = pad + ( i / ( pontos.length - 1 ) ) * ( w - pad * 2 );
				var y = h - pad - ( ( p.valor - min ) / amp ) * ( h - pad * 2 );
				return x + ',' + y;
			} );
			var svg = '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none">'
				+ '<polyline fill="none" stroke="var(--ac-navy)" stroke-width="2.5" points="' + coords.join( ' ' ) + '"></polyline>'
				+ '<circle cx="' + coords[ coords.length - 1 ].split( ',' )[0] + '" cy="' + coords[ coords.length - 1 ].split( ',' )[1] + '" r="4" fill="var(--ac-gold)"></circle>'
				+ '</svg>';
			linhaEl.innerHTML = svg;
		}

		var t = mes.tesouraria || {};
		var folegoBadge = document.getElementById( 'cp-vf-tesouraria-folego-badge' );
		folegoBadge.className = 'ac-badge ' + ( t.folego_meses >= 3 ? 'ac-badge-green' : 'ac-badge-amber' );
		folegoBadge.textContent = t.folego_meses >= 3 ? 'Confortável' : 'Atenção';
		document.getElementById( 'cp-vf-tesouraria-folego' ).textContent = ( t.folego_meses !== undefined ? t.folego_meses.toLocaleString( 'pt-PT' ) : '—' ) + ' meses';

		document.getElementById( 'cp-vf-a-receber' ).textContent = fmtEuro( t.a_receber );
		document.getElementById( 'cp-vf-pmr' ).textContent = t.pmr_dias ? 'Pagam, em média, a ' + t.pmr_dias + ' dias' : '';
		document.getElementById( 'cp-vf-a-pagar' ).textContent = fmtEuro( t.a_pagar );
		document.getElementById( 'cp-vf-pmp' ).textContent = t.pmp_dias ? 'Paga, em média, a ' + t.pmp_dias + ' dias' : '';
		document.getElementById( 'cp-vf-divida' ).textContent = fmtEuro( t.divida_bancaria );
		document.getElementById( 'cp-vf-juros' ).textContent = t.juros_acumulado ? 'Juros pagos este ano: ' + fmtEuro( t.juros_acumulado ) : '';
	}

	// ─── Estado, equipa, solidez ────────────────────────────────────────────

	function renderEstado( mes ) {
		var estado = mes.estado || {};
		document.getElementById( 'cp-vf-estado-total' ).textContent = fmtEuro( estado.total );
		var prazosEl = document.getElementById( 'cp-vf-prazos' );
		limpar( prazosEl );
		( estado.prazos || [] ).forEach( function ( prazo ) {
			var p = prazo.data.split( '-' );
			var linha = el( 'div', 'ac-vf-prazo' );
			linha.innerHTML =
				'<div class="ac-vf-prazo-data"><span class="dia">' + p[2] + '</span><span class="mes">' + ABREV_MES[ parseInt( p[1], 10 ) - 1 ] + '</span></div>'
				+ '<div><div class="ac-vf-prazo-titulo">' + prazo.titulo + '</div><div class="ac-vf-prazo-valor">' + ( prazo.valor ? fmtEuro( prazo.valor ) : ( prazo.nota || '' ) ) + '</div></div>';
			prazosEl.appendChild( linha );
		} );

		var equipa = mes.equipa || {};
		document.getElementById( 'cp-vf-equipa-pessoas' ).textContent = ( equipa.pessoas || 0 ) + ( equipa.pessoas === 1 ? ' pessoa' : ' pessoas' );
		document.getElementById( 'cp-vf-equipa-custo' ).textContent = 'Custaram ' + fmtEuro( equipa.custo_mes ) + ' em ' + NOMES_MES[ partesMes( mes.mes ).mes - 1 ];

		var pct = mes.autonomia_pct || 0;
		document.getElementById( 'cp-vf-autonomia-val' ).textContent = fmtPct( pct );
		document.getElementById( 'cp-vf-autonomia-fill' ).style.width = Math.min( 100, pct ) + '%';
		var badge = document.getElementById( 'cp-vf-autonomia-badge' );
		badge.className = 'ac-badge ' + ( pct >= 30 ? 'ac-badge-green' : 'ac-badge-amber' );
		badge.textContent = pct >= 30 ? 'Saudável' : 'Atenção';
	}

	// ─── Render geral ───────────────────────────────────────────────────────

	function render() {
		var mes = meses[ indiceAtual ];

		elTitulo.textContent = NOMES_MES[ partesMes( mes.mes ).mes - 1 ].replace( /^./, function ( c ) { return c.toUpperCase(); } ) + ' ' + partesMes( mes.mes ).ano;
		elSub.textContent = ( indiceAtual === meses.length - 1 ? 'Último mês fechado' : 'Mês fechado' ) + ( mes.fechado_em ? ' · dados atualizados a ' + fmtDataPT( mes.fechado_em ) : '' );
		elSlider.value = String( indiceAtual );
		btnPrev.disabled = indiceAtual === 0;
		btnNext.disabled = indiceAtual === meses.length - 1;
		atualizarBotoesMes();

		var resumo = mes.resumo || {};
		document.getElementById( 'cp-vf-resumo-label' ).textContent = resumo.tipo === 'equipa' ? 'O MÊS EM POUCAS PALAVRAS' : 'RESUMO AUTOMÁTICO';
		document.getElementById( 'cp-vf-resumo-texto' ).textContent = resumo.texto || '';
		document.getElementById( 'cp-vf-resumo-assinatura' ).textContent = resumo.assinatura || '';

		var d = mes.destaques || {};
		document.getElementById( 'cp-vf-d-vendas-label' ).textContent = 'Vendas de ' + NOMES_MES[ partesMes( mes.mes ).mes - 1 ];
		document.getElementById( 'cp-vf-d-vendas-val' ).textContent = fmtEuro( d.vendas );
		var pctVendas = d.vendas_ano_anterior ? ( ( d.vendas - d.vendas_ano_anterior ) / d.vendas_ano_anterior * 100 ) : 0;
		var elTrendV = document.getElementById( 'cp-vf-d-vendas-trend' );
		elTrendV.className = 'ac-trend ' + ( pctVendas >= 0 ? 'ac-trend-up' : '' );
		elTrendV.textContent = ( pctVendas >= 0 ? '↑ ' : '↓ ' ) + fmtPct( Math.abs( pctVendas ) ) + ' que em ' + NOMES_MES[ partesMes( mes.mes ).mes - 1 ] + ' de ' + ( partesMes( mes.mes ).ano - 1 );

		document.getElementById( 'cp-vf-d-lucro-val' ).textContent = fmtEuro( d.lucro_acumulado );
		var diffLucro = d.lucro_acumulado - d.lucro_acumulado_ano_anterior;
		var elTrendL = document.getElementById( 'cp-vf-d-lucro-trend' );
		elTrendL.className = 'ac-trend ' + ( diffLucro >= 0 ? 'ac-trend-up' : '' );
		elTrendL.textContent = ( diffLucro >= 0 ? '↑ ' : '↓ ' ) + fmtEuro( Math.abs( diffLucro ) ) + ' que no mesmo período de ' + ( partesMes( mes.mes ).ano - 1 );

		document.getElementById( 'cp-vf-d-dinheiro-label' ).textContent = 'Dinheiro no fim de ' + NOMES_MES[ partesMes( mes.mes ).mes - 1 ];
		document.getElementById( 'cp-vf-d-dinheiro-val' ).textContent = fmtEuro( d.dinheiro );
		var diffDinheiro = d.dinheiro - d.dinheiro_mes_anterior;
		var elTrendD = document.getElementById( 'cp-vf-d-dinheiro-trend' );
		elTrendD.className = 'ac-trend ' + ( diffDinheiro >= 0 ? 'ac-trend-up' : '' );
		elTrendD.textContent = ( diffDinheiro >= 0 ? '↑ ' : '↓ ' ) + fmtEuro( Math.abs( diffDinheiro ) ) + ' que no mês anterior';

		document.getElementById( 'cp-vf-d-pendentes-val' ).textContent = pendentes + ( pendentes === 1 ? ' item' : ' itens' );

		renderCascata( mes );
		renderVendas( mes );
		renderTesouraria( mes );
		renderEstado( mes );
	}

	render();
} )();
