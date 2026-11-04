<?php
/**
 * Área de Cliente — conteúdo de cada "vista" (ferramenta) do dashboard.
 *
 * Importante: estas ferramentas ainda não têm uma fonte de dados real por
 * trás (isso liga-se quando a empresa estiver sincronizada com a app
 * interna). Por isso cada vista mostra apenas a estrutura/UI e um estado
 * vazio honesto — nunca números, documentos ou movimentos inventados.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Util: cartão de estado vazio ──────────────────────────────────────────

function cp_ac_vazio( $titulo, $texto = '' ) {
	?>
	<div class="ac-card" style="padding:32px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:8px">
		<div style="width:40px;height:40px;border-radius:50%;background:var(--ac-bg);display:flex;align-items:center;justify-content:center;color:var(--ac-muted)">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M9 12h6"></path></svg>
		</div>
		<div style="font-size:14.5px;font-weight:600"><?php echo esc_html( $titulo ); ?></div>
		<?php if ( $texto ) : ?>
			<div style="font-size:13px;color:var(--ac-muted);max-width:380px"><?php echo esc_html( $texto ); ?></div>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Dashboard ──────────────────────────────────────────────────────────────

function cp_ac_vista_dashboard( $nome_utilizador, $ferramentas ) {
	?>
	<div>
		<h1 style="font-size:23px"><?php printf( /* translators: %s: nome do utilizador */ esc_html__( 'Olá, %s', 'contabilidade-pro' ), esc_html( $nome_utilizador ) ); ?></h1>
	</div>

	<?php
	$ativas = array_filter( array_keys( $ferramentas ), 'cp_ac_tem_ferramenta' );
	if ( empty( $ativas ) ) :
		cp_ac_vazio( __( 'Ainda não há ferramentas ativadas para esta empresa.', 'contabilidade-pro' ), __( 'Contacte a Entre Números se estiver à espera de acesso a alguma funcionalidade.', 'contabilidade-pro' ) );
	else :
		?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
			<?php foreach ( $ativas as $chave ) :
				$info = $ferramentas[ $chave ];
				?>
				<a href="<?php echo esc_url( cp_ac_vista_url( $chave ) ); ?>" class="ac-card" style="padding:20px;display:flex;flex-direction:column;gap:10px;color:var(--ac-text)">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--ac-blue)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo isset( $info['icon'] ) ? $info['icon'] : ''; ?></svg>
					<div style="font-size:14.5px;font-weight:600"><?php echo esc_html( $info['label'] ); ?></div>
					<span style="font-size:12.5px;color:var(--ac-blue);font-weight:600"><?php esc_html_e( 'Abrir →', 'contabilidade-pro' ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif;
}

// ─── Tarefas ────────────────────────────────────────────────────────────────

function cp_ac_vista_tarefas() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Tarefas', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Ações que precisa de concluir — pedidas pela Entre Números ou criadas por si.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div style="display:flex;flex-wrap:wrap;gap:12px">
			<div class="ac-field">
				<label><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></label>
				<select><option><?php esc_html_e( 'Todas', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Por fazer', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Concluídas', 'contabilidade-pro' ); ?></option></select>
			</div>
			<div class="ac-field">
				<label><?php esc_html_e( 'Prioridade', 'contabilidade-pro' ); ?></label>
				<select><option><?php esc_html_e( 'Todas', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Alta', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Média', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Baixa', 'contabilidade-pro' ); ?></option></select>
			</div>
		</div>
	</div>

	<?php cp_ac_vazio( __( 'Sem tarefas no momento', 'contabilidade-pro' ), __( 'Quando a equipa lhe pedir alguma ação, ou criar uma tarefa, aparece aqui.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Conta Corrente ─────────────────────────────────────────────────────────

function cp_ac_vista_conta_corrente() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Conta Corrente', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Consulte as faturas e recibos emitidos pela Entre Números.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px;display:flex;flex-direction:column;gap:20px">
		<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between">
			<div style="display:flex;flex-wrap:wrap;gap:12px">
				<div class="ac-field">
					<label><?php esc_html_e( 'Período', 'contabilidade-pro' ); ?></label>
					<select><option><?php esc_html_e( 'Este ano', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Últimos 3 meses', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Todos os períodos', 'contabilidade-pro' ); ?></option></select>
				</div>
				<div class="ac-field">
					<label><?php esc_html_e( 'Tipo', 'contabilidade-pro' ); ?></label>
					<select><option><?php esc_html_e( 'Todos', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Faturas', 'contabilidade-pro' ); ?></option><option><?php esc_html_e( 'Recibos', 'contabilidade-pro' ); ?></option></select>
				</div>
			</div>
			<button class="ac-btn ac-btn-ghost" type="button"><?php esc_html_e( 'Exportar', 'contabilidade-pro' ); ?></button>
		</div>
	</div>

	<?php cp_ac_vazio( __( 'Sem documentos a mostrar', 'contabilidade-pro' ), __( 'As faturas e recibos emitidos pela Entre Números vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Recibos de Vencimento ──────────────────────────────────────────────────

function cp_ac_vista_recibos_vencimento() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Recibos de Vencimento', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Consulte e descarregue os recibos de vencimento mensais dos colaboradores.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div style="display:flex;flex-wrap:wrap;gap:12px">
			<div class="ac-field">
				<label><?php esc_html_e( 'Ano', 'contabilidade-pro' ); ?></label>
				<select><option><?php echo esc_html( gmdate( 'Y' ) ); ?></option></select>
			</div>
			<div class="ac-field">
				<label><?php esc_html_e( 'Colaborador', 'contabilidade-pro' ); ?></label>
				<select><option><?php esc_html_e( 'Todos', 'contabilidade-pro' ); ?></option></select>
			</div>
		</div>
	</div>

	<?php cp_ac_vazio( __( 'Sem recibos disponíveis', 'contabilidade-pro' ), __( 'Os recibos de vencimento processados aparecem aqui, mês a mês.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Documentos ─────────────────────────────────────────────────────────────

function cp_ac_vista_documentos() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Documentos', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Envie e acompanhe os documentos correntes da sua contabilidade.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Documentos da Contabilidade', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Envie aqui, a qualquer momento, faturas, despesas e outros documentos correntes.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>
		<div class="ac-dropzone">
			<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="var(--ac-blue)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path></svg>
			<div style="font-size:13.5px;font-weight:600"><?php esc_html_e( 'Arraste ficheiros para aqui ou carregue manualmente', 'contabilidade-pro' ); ?></div>
			<button class="ac-btn ac-btn-primary" type="button"><?php esc_html_e( 'Carregar Documento', 'contabilidade-pro' ); ?></button>
		</div>
		<div style="margin-top:16px;color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não enviou nenhum documento.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Extratos Bancários & SAF-T', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Todos os meses, carregue o extrato bancário e o ficheiro SAF-T da sua contabilidade.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>
		<div style="color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Ainda não há meses registados.', 'contabilidade-pro' ); ?></div>
	</div>
	<?php
}

// ─── Pendentes ──────────────────────────────────────────────────────────────

function cp_ac_vista_pendentes() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Pendentes', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'O que a Entre Números precisa de si, em dois grupos: documentos e movimentos bancários.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Documentos Pendentes', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Documentos que a nossa equipa precisa especificamente de si.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>
		<div style="color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Sem pedidos pendentes de momento.', 'contabilidade-pro' ); ?></div>
	</div>

	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Movimentos a Esclarecer', 'contabilidade-pro' ); ?></h3>
				<div class="ac-sec-sub"><?php esc_html_e( 'Linhas do extrato bancário — pode responder por texto, por anexo, ou ambos.', 'contabilidade-pro' ); ?></div>
			</div>
		</div>
		<div style="color:var(--ac-muted);font-size:13px"><?php esc_html_e( 'Sem movimentos a esclarecer de momento.', 'contabilidade-pro' ); ?></div>
	</div>
	<?php
}

// ─── Mapas de Gestão ────────────────────────────────────────────────────────

function cp_ac_vista_mapas_gestao() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Mapas de Gestão', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Acompanhamento financeiro construído à medida do seu negócio.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_vazio( __( 'Ainda não há mapas publicados', 'contabilidade-pro' ), __( 'Os mapas de gestão preparados pela Entre Números vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Calendário Fiscal ──────────────────────────────────────────────────────

function cp_ac_vista_calendario_fiscal() {
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Calendário Fiscal', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Prazos e obrigações fiscais acompanhados pela Entre Números.', 'contabilidade-pro' ); ?></div>
	</div>

	<?php cp_ac_vazio( __( 'Sem prazos registados', 'contabilidade-pro' ), __( 'Os próximos prazos e obrigações fiscais da sua empresa vão aparecer aqui.', 'contabilidade-pro' ) ); ?>
	<?php
}

// ─── Util: URL de uma vista ─────────────────────────────────────────────────

function cp_ac_vista_url( $chave ) {
	return add_query_arg( 'vista', $chave, get_permalink() );
}
