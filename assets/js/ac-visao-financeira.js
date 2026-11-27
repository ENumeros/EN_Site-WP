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

	// Meses ainda sem contas fechadas (Work v5.0.158): dados.previsao.meses_previstos
	// = [ { mes, contas } ] — entram no fim da lista com previsto=true e mostram só
	// "As contas do mês" previstas, com o aviso "Previsão". Abre-se no último mês real.
	var ultimoReal = meses[ meses.length - 1 ];
	var mesesPrevistos = dados.previsao && Array.isArray( dados.previsao.meses_previstos ) ? dados.previsao.meses_previstos : [];
	mesesPrevistos.forEach( function ( m ) {
		if ( m && m.mes > ultimoReal.mes && Array.isArray( m.contas ) && m.contas.length ) {
			meses.push( { mes: m.mes, contas: m.contas, previsto: true } );
		}
	} );

	var NOMES_MES = [ 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' ];
	var ABREV_MES = [ 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez' ];

	function fmtEuro( v ) {
		if ( v === null || v === undefined ) return '—';
		return new Intl.NumberFormat( 'pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 0, useGrouping: 'always' } ).format( v ) + ' €';
	}
	function fmtPct( v, casas ) {
		return new Intl.NumberFormat( 'pt-PT', { minimumFractionDigits: casas || 1, maximumFractionDigits: casas || 1 } ).format( v ) + '%';
	}
	function partesMes( chaveMes ) {
		var partes = chaveMes.split( '-' );
		return { ano: parseInt( partes[0], 10 ), mes: parseInt( partes[1], 10 ) };
	}
	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) n.className = cls;
		if ( html !== undefined ) n.innerHTML = html;
		return n;
	}
	function limpar( node ) { while ( node.firstChild ) node.removeChild( node.firstChild ); }

	// Ícones (i): ao abrir, encosta a explicação para dentro do ecrã.
	root.querySelectorAll( '.ac-info' ).forEach( function ( info ) {
		function ajustar() {
			var tip = info.querySelector( '.ac-info-tip' );
			tip.style.setProperty( '--dx', '0px' );
			var r = tip.getBoundingClientRect();
			var margem = 12, larg = document.documentElement.clientWidth;
			var dx = r.left < margem ? margem - r.left : ( r.right > larg - margem ? larg - margem - r.right : 0 );
			tip.style.setProperty( '--dx', dx + 'px' );
		}
		info.addEventListener( 'mouseenter', ajustar );
		info.addEventListener( 'focusin', ajustar );
	} );

	// ─── Navegador de mês ─────────────────────────────────────────────────

	var elTitulo   = document.getElementById( 'cp-vf-mes-titulo' );
	var elTrilho   = document.getElementById( 'cp-vf-mes-trilho' );
	var elPilula   = elTrilho.querySelector( '.ac-vf-trilho-pilula' );
	var elBotoes   = document.getElementById( 'cp-vf-mes-botoes' );
	var btnPrev    = document.getElementById( 'cp-vf-mes-prev' );
	var btnNext    = document.getElementById( 'cp-vf-mes-next' );

	// A fila dos 12 meses (jan–dez do ano do mês escolhido) é o slider: a
	// pílula desliza para o mês escolhido; arrastar, tocar ou usar as setas do
	// teclado. Só pára em meses com dados: com 6 meses fechados, não passa de
	// metade da fila (fora disso, prende no mês com dados mais próximo).

	function indicesDoAno( ano ) {
		var porMes = {};
		meses.forEach( function ( mes, idx ) {
			var p = partesMes( mes.mes );
			if ( p.ano === ano ) porMes[ p.mes ] = idx;
		} );
		return porMes;
	}

	function irParaMesNum( pedido ) {
		var porMes = indicesDoAno( partesMes( meses[ indiceAtual ].mes ).ano );
		var nums = Object.keys( porMes ).map( Number );
		var escolhido = nums.reduce( function ( melhor, n ) {
			return Math.abs( n - pedido ) < Math.abs( melhor - pedido ) ? n : melhor;
		}, nums[0] );
		if ( porMes[ escolhido ] !== indiceAtual ) irParaMes( porMes[ escolhido ] );
	}

	function mesNaPosicao( clientX ) {
		var r = elBotoes.getBoundingClientRect();
		var n = Math.floor( ( clientX - r.left ) / ( r.width / 12 ) ) + 1;
		return Math.max( 1, Math.min( 12, n ) );
	}

	function montarBotoesMes() {
		limpar( elBotoes );
		for ( var a = 0; a < 12; a++ ) {
			var cel = el( 'span', 'ac-vf-trilho-mes', ABREV_MES[ a ] );
			cel.setAttribute( 'data-mes-num', a + 1 );
			elBotoes.appendChild( cel );
		}
	}
	montarBotoesMes();

	function atualizarBotoesMes() {
		var p = partesMes( meses[ indiceAtual ].mes );
		var porMes = indicesDoAno( p.ano );
		var celulas = elBotoes.querySelectorAll( '.ac-vf-trilho-mes' );
		for ( var i = 0; i < celulas.length; i++ ) {
			celulas[ i ].classList.toggle( 'is-vazio', porMes[ i + 1 ] === undefined );
			celulas[ i ].classList.toggle( 'is-ativo', i + 1 === p.mes );
			celulas[ i ].classList.toggle( 'is-previsto', porMes[ i + 1 ] !== undefined && !! meses[ porMes[ i + 1 ] ].previsto );
		}
		elTrilho.style.setProperty( '--vf-pos', p.mes - 1 );
		elTrilho.setAttribute( 'aria-valuenow', p.mes );
		// Sem animação no primeiro desenho (senão a pílula desliza desde jan).
		if ( ! elTrilho.classList.contains( 'is-pronto' ) ) requestAnimationFrame( function () { requestAnimationFrame( function () { elTrilho.classList.add( 'is-pronto' ); } ); } );
		elTrilho.setAttribute( 'aria-valuetext', NOMES_MES[ p.mes - 1 ] + ' de ' + p.ano );
	}

	function irParaMes( idx ) {
		if ( idx < 0 || idx >= meses.length ) return;
		indiceAtual = idx;
		render();
	}

	btnPrev.addEventListener( 'click', function () { irParaMes( indiceAtual - 1 ); } );
	btnNext.addEventListener( 'click', function () { irParaMes( indiceAtual + 1 ); } );

	var aArrastar = false;
	elTrilho.addEventListener( 'pointerdown', function ( e ) {
		aArrastar = true;
		elTrilho.classList.add( 'is-a-arrastar' );
		if ( elTrilho.setPointerCapture ) elTrilho.setPointerCapture( e.pointerId );
		irParaMesNum( mesNaPosicao( e.clientX ) );
	} );
	elTrilho.addEventListener( 'pointermove', function ( e ) {
		if ( aArrastar ) irParaMesNum( mesNaPosicao( e.clientX ) );
	} );
	function largar() { aArrastar = false; elTrilho.classList.remove( 'is-a-arrastar' ); }
	elTrilho.addEventListener( 'pointerup', largar );
	elTrilho.addEventListener( 'pointercancel', largar );
	elTrilho.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'ArrowLeft' || e.key === 'ArrowDown' ) { irParaMes( indiceAtual - 1 ); e.preventDefault(); }
		else if ( e.key === 'ArrowRight' || e.key === 'ArrowUp' ) { irParaMes( indiceAtual + 1 ); e.preventDefault(); }
	} );

	// ─── "As contas do mês" (como o Mapa de exploração da Work) ─────────────
	// Tabela: uma linha por rubrica, de cima para baixo (vendas → lucro), com o
	// mês escolhido, o acumulado do ano e o mesmo período do ano passado. Os
	// totais (margem, EBITDA, lucro) vão a negrito. Tocar numa rubrica com
	// composição abre por baixo as parcelas (nomes simples) e o peso de cada uma.

	var elContas  = document.getElementById( 'cp-vf-contas' );
	var elEbitda  = document.getElementById( 'cp-vf-ebitda' );
	var contasAbertas = {}; // id → true; mantém-se ao mudar de mês

	// Custos com "−", rendimentos com "+", totais com o próprio sinal.
	function valorConta( conta, v ) {
		if ( v === null || v === undefined ) return '—';
		if ( Math.round( v ) === 0 ) return fmtEuro( 0 );
		if ( conta.tipo === 'custo' ) return '−' + fmtEuro( Math.abs( v ) );
		if ( conta.tipo === 'rendimento' ) return '+' + fmtEuro( Math.abs( v ) );
		return ( v < 0 ? '−' : '' ) + fmtEuro( Math.abs( v ) );
	}

	function td( cls, conteudo ) {
		var c = el( 'td', cls );
		if ( conteudo !== undefined ) c.textContent = conteudo;
		return c;
	}

	function renderContas( mes ) {
		limpar( elContas );
		var contas = mes.contas || [];
		if ( ! contas.length ) return;
		var p = partesMes( mes.mes );
		var abrevMes = ABREV_MES[ p.mes - 1 ];
		var periodoAnt = ( p.mes === 1 ? 'jan' : 'jan–' + abrevMes ) + ' ' + ( p.ano - 1 );

		var tabela = el( 'table', 'ac-vf-mapa' );
		var thead = el( 'thead' );
		var trh = el( 'tr' );
		[ [ '', '' ], [ NOMES_MES[ p.mes - 1 ].replace( /^./, function ( c ) { return c.toUpperCase(); } ), 'num' ],
		  [ 'Acumulado ' + p.ano, 'num' ], [ periodoAnt, 'num col-ant' ] ].forEach( function ( h ) {
			trh.appendChild( el( 'th', h[1], h[0] ) );
		} );
		thead.appendChild( trh );
		tabela.appendChild( thead );

		var tbody = el( 'tbody' );
		contas.forEach( function ( conta ) {
			var temDetalhe = !! ( conta.composicao && conta.composicao.length > 1 );
			var aberta = temDetalhe && contasAbertas[ conta.id ];
			var total = conta.tipo === 'subtotal';
			var tr = el( 'tr', 'ac-vf-mapa-linha tipo-' + conta.tipo + ( total ? ' is-total' : '' ) + ( temDetalhe ? ' com-detalhe' : '' ) + ( aberta ? ' is-aberta' : '' ) );

			var nome = td( 'nome' );
			if ( temDetalhe ) nome.appendChild( el( 'span', 'seta', '›' ) );
			nome.appendChild( document.createTextNode( conta.titulo || conta.nome ) );
			tr.appendChild( nome );
			[ [ conta.valor, '' ], [ conta.acumulado, '' ], [ conta.acumulado_ano_anterior, ' col-ant' ] ].forEach( function ( c ) {
				var negativo = total && c[0] !== null && c[0] !== undefined && Math.round( c[0] ) < 0;
				tr.appendChild( td( 'num' + c[1] + ( negativo ? ' is-negativo' : '' ), valorConta( conta, c[0] ) ) );
			} );
			if ( temDetalhe ) {
				tr.tabIndex = 0;
				tr.setAttribute( 'role', 'button' );
				tr.setAttribute( 'aria-expanded', aberta ? 'true' : 'false' );
				var alternar = function () { contasAbertas[ conta.id ] = ! contasAbertas[ conta.id ]; renderContas( mes ); };
				tr.addEventListener( 'click', alternar );
				tr.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); alternar(); } } );
			}
			tbody.appendChild( tr );

			if ( aberta ) {
				if ( conta.explicacao ) {
					var trExp = el( 'tr', 'ac-vf-mapa-exp' );
					var cExp = td( '', conta.explicacao );
					cExp.colSpan = 4;
					trExp.appendChild( cExp );
					tbody.appendChild( trExp );
				}
				var base = conta.composicao.reduce( function ( soma, item ) { return soma + Math.abs( item.valor || 0 ); }, 0 ) || 1;
				conta.composicao.slice().sort( function ( a, b ) { return Math.abs( b.valor || 0 ) - Math.abs( a.valor || 0 ); } ).forEach( function ( item ) {
					var trP = el( 'tr', 'ac-vf-mapa-parcela' );
					var cNome = td( 'nome' );
					cNome.appendChild( document.createTextNode( item.nome ) );
					if ( item.nota ) cNome.appendChild( el( 'div', 'nota' + ( item.destaque ? ' is-destaque' : '' ), item.nota ) );
					trP.appendChild( cNome );
					trP.appendChild( td( 'num', fmtEuro( item.valor || 0 ) ) );
					var pesoPct = Math.round( Math.abs( item.valor || 0 ) / base * 100 );
					var cPeso = td( 'peso' );
					cPeso.colSpan = 2;
					cPeso.innerHTML = '<span class="barra"><i style="width:' + pesoPct + '%"></i></span>' + pesoPct + '%<span class="peso-mes"> de ' + abrevMes + '</span>';
					trP.appendChild( cPeso );
					tbody.appendChild( trP );
				} );
			}
		} );
		tabela.appendChild( tbody );
		elContas.appendChild( tabela );

		var nota = el( 'div', 'ac-vf-mapa-nota', mes.previsto
			? 'Previsão — valores sem IVA. O mês é previsto; o acumulado junta os meses reais até ' + NOMES_MES[ partesMes( ultimoReal.mes ).mes - 1 ] + ' com a previsão daí em diante. O lucro é antes do imposto sobre o lucro (IRC).'
			: 'Valores sem IVA. O lucro é antes do imposto sobre o lucro (IRC). Toque numa linha com › para ver de que é feita.' );
		elContas.appendChild( nota );

		renderEbitda( mes );
	}

	function renderEbitda( mes ) {
		limpar( elEbitda );
		var e = mes.ebitda;
		if ( ! e ) return;
		// Dois blocos iguais: EBITDA e RAI (lucro antes de impostos) — o mês
		// escolhido em grande e o acumulado do ano por baixo.
		function bloco( rotulo, valor, acumulado, texto ) {
			var caixa = el( 'div', 'ac-vf-ebitda-bloco' );
			var head = el( 'div', 'ac-vf-ebitda-head' );
			head.appendChild( el( 'div', 'ac-stat-label', rotulo ) );
			head.appendChild( el( 'span', 'ac-badge ' + ( valor >= 0 ? 'ac-badge-green' : 'ac-badge-red' ), valor >= 0 ? 'Positivo' : 'Negativo' ) );
			caixa.appendChild( head );
			var linha = el( 'div', 'ac-vf-ebitda-valores' );
			linha.appendChild( el( 'div', 'ac-vf-destaque-val' + ( valor < 0 ? ' is-negativo' : '' ), ( valor < 0 ? '−' : '' ) + fmtEuro( Math.abs( valor ) ) ) );
			if ( acumulado !== null && acumulado !== undefined ) {
				linha.appendChild( el( 'div', 'ac-vf-ebitda-acum', 'Acumulado ' + partesMes( mes.mes ).ano + ': <strong>' + ( acumulado < 0 ? '−' : '' ) + fmtEuro( Math.abs( acumulado ) ) + '</strong>' ) );
			}
			caixa.appendChild( linha );
			caixa.appendChild( el( 'div', 'ac-vf-ebitda-texto', texto ) );
			elEbitda.appendChild( caixa );
		}
		bloco( 'EBITDA', e.valor, e.acumulado,
			'Lê-se "ébitda". O dinheiro que a atividade gera antes de juros, impostos e desgaste dos equipamentos: mostra se o negócio em si dá dinheiro.' );
		var subtotais = ( mes.contas || [] ).filter( function ( c ) { return c.tipo === 'subtotal'; } );
		var rai = subtotais.length ? subtotais[ subtotais.length - 1 ] : null;
		if ( rai && rai !== subtotais.filter( function ( c ) { return /ebitda/i.test( c.nome || '' ); } )[0] ) {
			bloco( 'RAI — Lucro antes de impostos', rai.valor, rai.acumulado !== undefined ? rai.acumulado : e.lucro_acumulado,
				'O que fica depois de tirar ao EBITDA o desgaste dos equipamentos e os juros. É sobre este valor que se calcula o imposto sobre o lucro (IRC).' );
		}

		if ( e.divida_bancaria ) {
			var grid = el( 'div', 'ac-vf-grid3' );
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

	// Barras por mês (jan–dez): a clara é o ano passado, a escura este ano.
	// Tocar num mês deste ano com dados vai para esse mês.
	function desenharBarrasAno( elGraf, pontos, mes ) {
		limpar( elGraf );
		var anoAtual = partesMes( mes.mes ).ano;
		var porMesAno = {};
		pontos.forEach( function ( p ) { porMesAno[ p.mes ] = p.valor; } );
		var max = Math.max.apply( null, pontos.map( function ( p ) { return Math.abs( p.valor ); } ).concat( [ 1 ] ) );
		for ( var m = 1; m <= 12; m++ ) {
			var mm = m < 10 ? '0' + m : String( m );
			var vAtual = porMesAno[ anoAtual + '-' + mm ];
			var vAnt   = porMesAno[ ( anoAtual - 1 ) + '-' + mm ];
			var col = el( 'div', 'ac-vf-vendas-mes' + ( m === partesMes( mes.mes ).mes ? ' is-ativo' : '' ) );
			var par = el( 'div', 'ac-vf-vendas-par' );
			var barAnt = el( 'div', 'ac-vf-vendas-bar ano-anterior' );
			barAnt.style.height = ( vAnt ? Math.abs( vAnt ) / max * 115 : 0 ) + 'px';
			var barAtual = el( 'div', 'ac-vf-vendas-bar ano-atual' );
			barAtual.style.height = ( vAtual ? Math.abs( vAtual ) / max * 115 : 0 ) + 'px';
			par.appendChild( barAnt );
			par.appendChild( barAtual );
			if ( vAtual !== undefined ) {
				( function ( chave ) {
					par.addEventListener( 'click', function () {
						var idx = meses.findIndex( function ( x ) { return x.mes === chave; } );
						if ( idx > -1 ) irParaMes( idx );
					} );
				} )( anoAtual + '-' + mm );
				par.style.cursor = 'pointer';
			}
			col.appendChild( par );
			col.appendChild( el( 'span', 'ac-vf-vendas-mes-label', ABREV_MES[ m - 1 ] ) );
			elGraf.appendChild( col );
		}
	}

	// Soma de janeiro ao mês escolhido, num ano, a partir de uma série mensal.
	function somaAteMes( pontos, ano, mesNum ) {
		var total = 0, tem = false;
		pontos.forEach( function ( p ) {
			var q = partesMes( p.mes );
			if ( q.ano === ano && q.mes <= mesNum ) { total += p.valor; tem = true; }
		} );
		return tem ? total : null;
	}

	var elComprasTotal   = document.getElementById( 'cp-vf-compras-total' );
	var elComprasTrend   = document.getElementById( 'cp-vf-compras-trend' );
	var elComprasGrafico = document.getElementById( 'cp-vf-compras-grafico' );

	function renderCompras( mes ) {
		var pontos = series.compras || [];
		var p = partesMes( mes.mes );
		var total = somaAteMes( pontos, p.ano, p.mes );
		var totalAnt = somaAteMes( pontos, p.ano - 1, p.mes );
		elComprasTotal.textContent = total === null ? '—' : fmtEuro( total );
		// Nas compras, subir não é "bom": a seta fica neutra (sem verde).
		if ( total !== null && totalAnt ) {
			var pct = ( total - totalAnt ) / totalAnt * 100;
			elComprasTrend.className = 'ac-trend';
			elComprasTrend.textContent = ( pct >= 0 ? '↑ ' : '↓ ' ) + fmtPct( Math.abs( pct ) ) + ' que em ' + ( p.ano - 1 );
		} else {
			elComprasTrend.textContent = '';
		}
		desenharBarrasAno( elComprasGrafico, pontos, mes );
	}

	function renderVendas( mes ) {
		elVendasTotal.textContent = fmtEuro( mes.contas && mes.contas[0] ? mes.contas[0].acumulado : mes.destaques.vendas );
		var pct = mes.destaques.vendas_ano_anterior
			? ( ( mes.destaques.vendas - mes.destaques.vendas_ano_anterior ) / mes.destaques.vendas_ano_anterior * 100 )
			: 0;
		elVendasTrend.className = 'ac-trend ' + ( pct >= 0 ? 'ac-trend-up' : '' );
		elVendasTrend.textContent = ( pct >= 0 ? '↑ ' : '↓ ' ) + fmtPct( Math.abs( pct ) ) + ' que em ' + ( partesMes( mes.mes ).ano - 1 );

		desenharBarrasAno( elVendasGrafico, series.vendas || [], mes );

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

	function desenharLinha( alvo, pontos, cor ) {
		limpar( alvo );
		if ( pontos.length < 2 ) return;
		var w = 600, h = 120, pad = 8;
		var vals = pontos.map( function ( p ) { return p.valor; } );
		var min = Math.min.apply( null, vals ), max = Math.max.apply( null, vals );
		var amp = ( max - min ) || 1;
		var coords = pontos.map( function ( p, i ) {
			var x = pad + ( i / ( pontos.length - 1 ) ) * ( w - pad * 2 );
			var y = h - pad - ( ( p.valor - min ) / amp ) * ( h - pad * 2 );
			return [ x, y ];
		} );
		var ult = coords[ coords.length - 1 ];
		alvo.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none">'
			+ '<polyline fill="none" stroke="' + cor + '" stroke-width="2.5" vector-effect="non-scaling-stroke" points="' + coords.map( function ( c ) { return c.join( ',' ); } ).join( ' ' ) + '"></polyline>'
			+ '<circle cx="' + ult[0] + '" cy="' + ult[1] + '" r="4" fill="var(--ac-gold)"></circle>'
			+ '</svg>';
	}

	function renderTesouraria( mes ) {
		document.getElementById( 'cp-vf-tesouraria-dinheiro' ).textContent = fmtEuro( mes.destaques.dinheiro );
		var diff = mes.destaques.dinheiro - mes.destaques.dinheiro_mes_anterior;
		var trend = document.getElementById( 'cp-vf-tesouraria-trend' );
		trend.className = 'ac-trend ' + ( diff >= 0 ? 'ac-trend-up' : '' );
		trend.textContent = ( diff >= 0 ? '↑ ' : '↓ ' ) + fmtEuro( Math.abs( diff ) ) + ' desde o mês anterior';

		desenharLinha( document.getElementById( 'cp-vf-tesouraria-linha' ), series.dinheiro || [], 'var(--ac-navy)' );

		var t = mes.tesouraria || {};
		var folegoBadge = document.getElementById( 'cp-vf-tesouraria-folego-badge' );
		folegoBadge.className = 'ac-badge ' + ( t.folego_meses >= 3 ? 'ac-badge-green' : 'ac-badge-amber' );
		folegoBadge.textContent = t.folego_meses >= 3 ? 'Confortável' : 'Atenção';
		document.getElementById( 'cp-vf-tesouraria-folego' ).textContent = ( t.folego_meses !== undefined ? t.folego_meses.toLocaleString( 'pt-PT' ) : '—' ) + ' meses';

		document.getElementById( 'cp-vf-a-receber' ).textContent = fmtEuro( t.a_receber );
		document.getElementById( 'cp-vf-pmr' ).textContent = t.pmr_dias ? 'Pagam, em média, a ' + t.pmr_dias + ' dias' : '';
		document.getElementById( 'cp-vf-a-pagar' ).textContent = fmtEuro( t.a_pagar );
		document.getElementById( 'cp-vf-pmp' ).textContent = t.pmp_dias ? 'Paga, em média, a ' + t.pmp_dias + ' dias' : '';
		var divida = t.divida_bancaria || 0;
		document.getElementById( 'cp-vf-divida' ).textContent = divida ? fmtEuro( divida ) : 'Nada';
		var liquido = ( mes.destaques.dinheiro || 0 ) - divida;
		var elLiquido = document.getElementById( 'cp-vf-liquido' );
		elLiquido.textContent = ( liquido < 0 ? '−' : '' ) + fmtEuro( Math.abs( liquido ) );
		elLiquido.classList.toggle( 'is-negativo', liquido < 0 );
		document.getElementById( 'cp-vf-liquido-nota' ).textContent = divida
			? ( liquido >= 0 ? 'O que ficaria se pagasse hoje todos os empréstimos' : 'O dinheiro ainda não chega para pagar os empréstimos' )
			: 'Sem empréstimos, é todo o dinheiro';

		// Empréstimos bancários: valor do mês, variação e evolução.
		var pontosDiv = series.divida || [];
		document.getElementById( 'cp-vf-divida-total' ).textContent = divida ? fmtEuro( divida ) : 'Sem empréstimos';
		var elDivTrend = document.getElementById( 'cp-vf-divida-trend' );
		var idxDiv = pontosDiv.findIndex( function ( p ) { return p.mes === mes.mes; } );
		var antDiv = idxDiv > 0 ? pontosDiv[ idxDiv - 1 ].valor : null;
		if ( antDiv !== null && Math.round( antDiv - divida ) !== 0 ) {
			var desceu = divida < antDiv;
			elDivTrend.className = 'ac-trend ' + ( desceu ? 'ac-trend-up' : '' );
			elDivTrend.textContent = ( desceu ? '↓ ' : '↑ ' ) + fmtEuro( Math.abs( divida - antDiv ) ) + ' desde o mês anterior';
		} else {
			elDivTrend.textContent = '';
		}
		var temDivida = pontosDiv.some( function ( p ) { return Math.round( p.valor ) > 0; } );
		var elDivLinha = document.getElementById( 'cp-vf-divida-linha' );
		if ( temDivida ) desenharLinha( elDivLinha, pontosDiv, 'var(--ac-blue)' );
		else limpar( elDivLinha );
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

		elTitulo.textContent = NOMES_MES[ partesMes( mes.mes ).mes - 1 ].replace( /^./, function ( c ) { return c.toUpperCase(); } ) + ' ' + partesMes( mes.mes ).ano + ( mes.previsto ? ' · previsão' : '' );
		btnPrev.disabled = indiceAtual === 0;
		btnNext.disabled = indiceAtual === meses.length - 1;
		atualizarBotoesMes();

		root.classList.toggle( 'is-previsao', !! mes.previsto );
		if ( mes.previsto ) {
			var nomeMes = NOMES_MES[ partesMes( mes.mes ).mes - 1 ];
			document.getElementById( 'cp-vf-pv-aviso-titulo' ).textContent = 'Os números de ' + nomeMes + ' são uma previsão';
			document.getElementById( 'cp-vf-pv-aviso-texto' ).textContent = 'Ainda não há contas fechadas de ' + nomeMes + '. Até ' + NOMES_MES[ partesMes( ultimoReal.mes ).mes - 1 ]
				+ ' os valores são reais; daí em diante é a nossa previsão, feita a partir do ritmo deste ano ou do ano passado. Serve para planear, não é uma garantia.';
			renderContas( mes );
			return;
		}

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

		renderContas( mes );
		renderVendas( mes );
		renderCompras( mes );
		renderTesouraria( mes );
		renderEstado( mes );
	}

	render();

	// ─── Previsão para o ano (Work v5.0.137) ────────────────────────────────
	// dados.previsao = { ano, ate_mes, indicadores: [ { id, nome, custo, realizado,
	// projetado, ano_anterior, meses: [ { mes, valor, tipo: real|projetado, ano_anterior } ] } ] }.
	// Não depende do mês escolhido no navegador de cima: é sempre o ano todo.
	( function () {
		var pv = dados.previsao;
		var elTabs = document.getElementById( 'cp-vf-previsao-tabs' );
		if ( ! pv || ! elTabs || ! Array.isArray( pv.indicadores ) || ! pv.indicadores.length ) return;
		var elGraf = document.getElementById( 'cp-vf-previsao-grafico' );
		var nomeAte = NOMES_MES[ pv.ate_mes - 1 ];
		document.getElementById( 'cp-vf-previsao-titulo' ).textContent = 'Previsão para ' + pv.ano;
		document.getElementById( 'cp-vf-previsao-real-label' ).textContent = 'Até ' + nomeAte;
		document.getElementById( 'cp-vf-previsao-ano-label' ).textContent = 'Previsão para o ano';
		document.getElementById( 'cp-vf-previsao-ant-label' ).textContent = 'Ano de ' + ( pv.ano - 1 );

		function mostrar( ind ) {
			elTabs.querySelectorAll( 'button' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', b.dataset.id === ind.id ? 'true' : 'false' ); } );
			document.getElementById( 'cp-vf-previsao-real' ).textContent = fmtEuro( ind.realizado );
			document.getElementById( 'cp-vf-previsao-ano' ).textContent = fmtEuro( ind.projetado );
			document.getElementById( 'cp-vf-previsao-ant' ).textContent = ind.ano_anterior === null ? '—' : fmtEuro( ind.ano_anterior );
			var elTrend = document.getElementById( 'cp-vf-previsao-trend' );
			if ( ind.ano_anterior ) {
				var dif = ind.projetado - ind.ano_anterior;
				// Nos custos, subir não é "bom": sem verde.
				elTrend.className = 'ac-trend' + ( dif >= 0 && ! ind.custo ? ' ac-trend-up' : '' );
				elTrend.textContent = ( dif >= 0 ? '↑ ' : '↓ ' ) + fmtEuro( Math.abs( dif ) ) + ' que em ' + ( pv.ano - 1 );
			} else {
				elTrend.textContent = '';
			}

			limpar( elGraf );
			elGraf.classList.toggle( 'is-custo', !! ind.custo );
			var max = 1;
			ind.meses.forEach( function ( x ) { max = Math.max( max, Math.abs( x.valor || 0 ), Math.abs( x.ano_anterior || 0 ) ); } );
			ind.meses.forEach( function ( x, i ) {
				var previsto = x.tipo === 'projetado';
				var col = el( 'div', 'ac-vf-vendas-mes' + ( previsto ? ' is-previsto' : '' ) );
				var par = el( 'div', 'ac-vf-vendas-par' );
				var barAnt = el( 'div', 'ac-vf-vendas-bar ano-anterior' );
				barAnt.style.height = ( x.ano_anterior ? Math.abs( x.ano_anterior ) / max * 115 : 0 ) + 'px';
				var bar = el( 'div', 'ac-vf-vendas-bar ' + ( previsto ? 'is-previsto' : 'ano-atual' ) + ( x.valor < 0 ? ' is-negativo' : '' ) );
				bar.style.height = ( x.valor ? Math.abs( x.valor ) / max * 115 : 0 ) + 'px';
				par.title = NOMES_MES[ i ] + ': ' + ( x.valor === null ? '—' : fmtEuro( x.valor ) ) + ( previsto ? ' (previsto)' : '' );
				par.appendChild( barAnt );
				par.appendChild( bar );
				col.appendChild( par );
				col.appendChild( el( 'span', 'ac-vf-vendas-mes-label', ABREV_MES[ i ] ) );
				elGraf.appendChild( col );
			} );
		}

		pv.indicadores.forEach( function ( ind ) {
			var b = el( 'button', 'ac-vf-pv-tab', '' );
			b.type = 'button';
			b.dataset.id = ind.id;
			b.textContent = ind.nome;
			b.addEventListener( 'click', function () { mostrar( ind ); } );
			elTabs.appendChild( b );
		} );
		mostrar( pv.indicadores[0] );
	} )();
} )();
