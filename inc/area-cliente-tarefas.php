<?php
/**
 * Lembretes — nota rápida e partilhada por empresa, entre o cliente e a
 * equipa ("enviar contrato da renda", etc.) — não é uma gestão de tarefas,
 * só notas de ação simples, geridas a partir do Dashboard. Guardado como
 * postmeta no post da empresa, mesmo princípio de "caixa de correio" usado
 * em Pendentes (inc/area-cliente.php).
 *
 * Nomes internos (funções, meta `_cp_tarefas`, ações `cp_tarefa_*`) ficam
 * como "tarefa" por não serem visíveis ao utilizador — só o texto no ecrã
 * passou a "lembrete". Se no futuro isto crescer para uma gestão de
 * tarefas a sério, faz sentido renomear também o código nessa altura.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Dados ──────────────────────────────────────────────────────────────────

function cp_ac_tarefas( $empresa_id ) {
	$tarefas = get_post_meta( $empresa_id, '_cp_tarefas', true );
	return is_array( $tarefas ) ? $tarefas : [];
}

function cp_ac_tarefa_adicionar( $empresa_id, $titulo, $user_id, $equipa ) {
	$tarefas = cp_ac_tarefas( $empresa_id );
	$tarefa  = [
		'id'                    => wp_generate_uuid4(),
		'titulo'                => $titulo,
		'criado_por'            => $equipa ? 'equipa' : 'cliente',
		'criado_por_user_id'    => (int) $user_id,
		'criado_em'             => current_time( 'mysql' ),
		'concluido'             => false,
		'concluido_em'          => null,
		'concluido_por_user_id' => null,
	];
	$tarefas[] = $tarefa;
	update_post_meta( $empresa_id, '_cp_tarefas', $tarefas );
	return $tarefa;
}

// Pode marcar/desmarcar uma tarefa quem a criou, ou qualquer membro da
// equipa — independentemente de quem a criou.
function cp_ac_tarefa_pode_concluir( array $tarefa, $user_id ) {
	if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) return true;
	return (int) $tarefa['criado_por_user_id'] === (int) $user_id;
}

function cp_ac_tarefa_concluir( $empresa_id, $tarefa_id, $concluido, $user_id ) {
	$tarefas = cp_ac_tarefas( $empresa_id );
	foreach ( $tarefas as $i => $tarefa ) {
		if ( $tarefa['id'] !== $tarefa_id ) continue;
		if ( ! cp_ac_tarefa_pode_concluir( $tarefa, $user_id ) ) return false;
		$tarefas[ $i ]['concluido']             = (bool) $concluido;
		$tarefas[ $i ]['concluido_em']          = $concluido ? current_time( 'mysql' ) : null;
		$tarefas[ $i ]['concluido_por_user_id'] = $concluido ? (int) $user_id : null;
		update_post_meta( $empresa_id, '_cp_tarefas', $tarefas );
		return true;
	}
	return false;
}

// A que empresa um pedido (criar/concluir) se refere: a equipa escolhe
// explicitamente (vários clientes); nunca confiar num empresa_id vindo
// de um cliente — é sempre a empresa ativa da sua própria sessão.
function cp_ac_tarefa_resolver_empresa( $empresa_id_pedido, $user_id ) {
	if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
		return (int) $empresa_id_pedido;
	}
	return cp_ac_empresa_ativa( $user_id );
}

// ─── Criar ──────────────────────────────────────────────────────────────────

function cp_ac_handle_tarefa_criar() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_tarefa_nonce'] ) || ! wp_verify_nonce( $_POST['cp_tarefa_nonce'], 'cp_tarefa_criar' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}

	$user_id    = get_current_user_id();
	$equipa     = cp_backoffice_utilizador_tem_acesso( $user_id );
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	$titulo     = isset( $_POST['titulo'] ) ? sanitize_text_field( wp_unslash( $_POST['titulo'] ) ) : '';
	$voltar     = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! $empresa_id || '' === $titulo ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'tarefa_invalida', $voltar ) );
		exit;
	}

	cp_ac_tarefa_adicionar( $empresa_id, $titulo, $user_id, $equipa );

	if ( $equipa ) {
		cp_push_notificar_empresa(
			$empresa_id,
			__( 'Novo lembrete', 'contabilidade-pro' ),
			$titulo,
			home_url( '/area-de-cliente/' )
		);
	} else {
		cp_push_notificar_equipa(
			__( 'Novo lembrete de cliente', 'contabilidade-pro' ),
			sprintf(
				/* translators: 1: nome da empresa, 2: título da tarefa */
				__( '%1$s: %2$s', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				$titulo
			),
			add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
			$empresa_id
		);
	}

	wp_safe_redirect( add_query_arg( 'cp_sucesso', 'tarefa_criada', $voltar ) );
	exit;
}
add_action( 'admin_post_cp_tarefa_criar', 'cp_ac_handle_tarefa_criar' );

// ─── Concluir / desmarcar ───────────────────────────────────────────────────

function cp_ac_handle_tarefa_concluir() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_tarefa_nonce'] ) || ! wp_verify_nonce( $_POST['cp_tarefa_nonce'], 'cp_tarefa_concluir' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}

	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	$tarefa_id  = isset( $_POST['tarefa_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tarefa_id'] ) ) : '';
	$concluido  = ! empty( $_POST['concluido'] );
	$voltar     = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! $empresa_id || '' === $tarefa_id || ! cp_ac_tarefa_concluir( $empresa_id, $tarefa_id, $concluido, $user_id ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'tarefa_invalida', $voltar ) );
		exit;
	}

	// Só notifica ao concluir — desmarcar fica silencioso (sem ruído).
	if ( $concluido ) {
		$titulo = '';
		foreach ( cp_ac_tarefas( $empresa_id ) as $tarefa ) {
			if ( $tarefa['id'] === $tarefa_id ) {
				$titulo = $tarefa['titulo'];
				break;
			}
		}
		if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
			cp_push_notificar_empresa( $empresa_id, __( 'Lembrete concluído', 'contabilidade-pro' ), $titulo, home_url( '/area-de-cliente/' ) );
		} else {
			cp_push_notificar_equipa(
				__( 'Lembrete concluído pelo cliente', 'contabilidade-pro' ),
				sprintf(
					/* translators: 1: nome da empresa, 2: título da tarefa */
					__( '%1$s: %2$s', 'contabilidade-pro' ),
					get_the_title( $empresa_id ),
					$titulo
				),
				add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
				$empresa_id
			);
		}
	}

	wp_safe_redirect( add_query_arg( 'cp_sucesso', 'tarefa_atualizada', $voltar ) );
	exit;
}
add_action( 'admin_post_cp_tarefa_concluir', 'cp_ac_handle_tarefa_concluir' );

// ─── UI partilhada (Área de Cliente + Backoffice) ──────────────────────────

function cp_ac_render_tarefas( $empresa_id, $mostrar_nome_empresa = false ) {
	$user_id = get_current_user_id();
	$tarefas = cp_ac_tarefas( $empresa_id );
	usort( $tarefas, function ( $a, $b ) {
		if ( $a['concluido'] !== $b['concluido'] ) return $a['concluido'] ? 1 : -1;
		return strcmp( $b['criado_em'], $a['criado_em'] );
	} );
	?>
	<?php if ( $mostrar_nome_empresa ) : ?>
		<h3 style="font-size:16px"><?php echo esc_html( get_the_title( $empresa_id ) ); ?></h3>
	<?php endif; ?>

	<div class="ac-card" style="padding:22px">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:10px;flex-wrap:wrap">
			<?php wp_nonce_field( 'cp_tarefa_criar', 'cp_tarefa_nonce' ); ?>
			<input type="hidden" name="action" value="cp_tarefa_criar">
			<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
			<input type="text" name="titulo" required placeholder="<?php esc_attr_e( 'Novo lembrete — ex.: enviar contrato da renda', 'contabilidade-pro' ); ?>" style="flex:1;min-width:220px;padding:9px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px">
			<button class="ac-btn ac-btn-primary" type="submit" style="padding:9px 16px"><?php esc_html_e( 'Adicionar', 'contabilidade-pro' ); ?></button>
		</form>
	</div>

	<?php if ( empty( $tarefas ) ) : ?>
		<?php cp_ac_vazio( __( 'Sem lembretes no momento', 'contabilidade-pro' ), __( 'Quando a equipa lhe pedir alguma ação, ou deixar uma nota, aparece aqui.', 'contabilidade-pro' ) ); ?>
	<?php else : ?>
		<div class="ac-card" style="padding:22px">
			<?php foreach ( $tarefas as $tarefa ) :
				$pode_concluir = cp_ac_tarefa_pode_concluir( $tarefa, $user_id );
				$concluida     = ! empty( $tarefa['concluido'] );
				?>
				<div class="ac-todo-item">
					<?php if ( $pode_concluir ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'cp_tarefa_concluir', 'cp_tarefa_nonce' ); ?>
							<input type="hidden" name="action" value="cp_tarefa_concluir">
							<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
							<input type="hidden" name="tarefa_id" value="<?php echo esc_attr( $tarefa['id'] ); ?>">
							<input type="hidden" name="concluido" value="<?php echo $concluida ? '0' : '1'; ?>">
							<button type="submit" class="ac-todo-check<?php echo $concluida ? ' done' : ''; ?>" aria-label="<?php echo $concluida ? esc_attr__( 'Marcar como não feita', 'contabilidade-pro' ) : esc_attr__( 'Marcar como feita', 'contabilidade-pro' ); ?>">
								<?php if ( $concluida ) : ?><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg><?php endif; ?>
							</button>
						</form>
					<?php else : ?>
						<span class="ac-todo-check<?php echo $concluida ? ' done' : ''; ?>" style="cursor:default">
							<?php if ( $concluida ) : ?><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"></path></svg><?php endif; ?>
						</span>
					<?php endif; ?>
					<div>
						<div class="ac-todo-title<?php echo $concluida ? ' done' : ''; ?>"><?php echo esc_html( $tarefa['titulo'] ); ?></div>
						<div class="ac-todo-meta">
							<?php
							$quem = 'equipa' === $tarefa['criado_por'] ? __( 'Entre Números', 'contabilidade-pro' ) : __( 'Cliente', 'contabilidade-pro' );
							printf(
								/* translators: 1: quem criou (Entre Números ou Cliente), 2: há quanto tempo, ex. "3 horas" */
								esc_html__( 'Criado por %1$s — há %2$s', 'contabilidade-pro' ),
								esc_html( $quem ),
								esc_html( human_time_diff( strtotime( $tarefa['criado_em'] ), current_time( 'timestamp' ) ) )
							);
							?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif;
}
