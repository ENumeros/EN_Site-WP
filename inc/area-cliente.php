<?php
/**
 * Área de Cliente — Empresas, Ferramentas e Matriz de Acesso por Empresa
 *
 * Objetivo: controlar que "ferramentas" (ecrãs) da Área de Cliente cada
 * empresa vê. Permite ativar uma funcionalidade nova só numa empresa para
 * testar, e só depois ligar a todas.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Registo de Ferramentas ────────────────────────────────────────────────
// Lista central das ferramentas que podem ser ligadas/desligadas por
// empresa. O Dashboard não entra aqui — está sempre visível para quem tem
// pelo menos uma empresa associada. Para adicionar uma ferramenta nova,
// basta acrescentar uma linha aqui; fica OFF por defeito em todas as
// empresas até a ligares na matriz.

function cp_ac_ferramentas() {
	return [
		'tarefas'            => [
			'label' => __( 'Tarefas', 'contabilidade-pro' ),
			'grupo' => '',
			'icon'  => '<rect x="3.5" y="4" width="17" height="16" rx="2"></rect><path d="M7.5 9.5 9 11l2.5-3"></path><path d="M14 9h4"></path><path d="M7.5 15.5 9 17l2.5-3"></path><path d="M14 15h4"></path>',
		],
		'conta_corrente'     => [
			'label' => __( 'Conta Corrente', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<path d="M3 7.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M16 12.5h3v3h-3a1.5 1.5 0 0 1 0-3z"></path><path d="M3 9h15"></path>',
		],
		'calendario_fiscal'  => [
			'label' => __( 'Calendário Fiscal', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<rect x="3.5" y="5" width="17" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M3.5 10h17"></path>',
		],
		'documentos'         => [
			'label' => __( 'Documentos', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M7.5 17.5a4 4 0 0 1-.8-7.92 5 5 0 0 1 9.72-1.84A4.5 4.5 0 0 1 16.5 17.5h-9z"></path><path d="M12 14v-5M9.5 11.5 12 9l2.5 2.5"></path>',
		],
		'pendentes'          => [
			'label' => __( 'Pendentes', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M4 12h4l1.5 3h5L16 12h4"></path><path d="M5.5 6h13L20 12v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6z"></path>',
		],
		'mapas_gestao'       => [
			'label' => __( 'Mapas de Gestão', 'contabilidade-pro' ),
			'grupo' => __( 'Contabilidade', 'contabilidade-pro' ),
			'icon'  => '<path d="M5 19V9M12 19V5M19 19v-7"></path><path d="M3 19h18"></path>',
		],
		'recibos_vencimento' => [
			'label' => __( 'Recibos de Vencimento', 'contabilidade-pro' ),
			'grupo' => __( 'Recursos Humanos', 'contabilidade-pro' ),
			'icon'  => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M8.5 13h7M8.5 16.5h7M8.5 9.5h3"></path>',
		],
	];
}

// ─── CPT: Empresas ──────────────────────────────────────────────────────────
// Cada empresa-cliente é um post deste tipo. No futuro passa a ser criada
// automaticamente pela Work via API; por agora gere-se à mão em wp-admin.

function cp_ac_registar_cpt_empresa() {
	register_post_type( 'cp_empresa', [
		'labels' => [
			'name'          => __( 'Empresas', 'contabilidade-pro' ),
			'singular_name' => __( 'Empresa', 'contabilidade-pro' ),
			'add_new_item'  => __( 'Adicionar Empresa', 'contabilidade-pro' ),
			'edit_item'     => __( 'Editar Empresa', 'contabilidade-pro' ),
			'search_items'  => __( 'Procurar Empresas', 'contabilidade-pro' ),
			'not_found'     => __( 'Nenhuma empresa encontrada.', 'contabilidade-pro' ),
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'cp_area_cliente',
		'supports'        => [ 'title' ],
		'menu_icon'       => 'dashicons-building',
		'capability_type' => 'post',
	] );
}
add_action( 'init', 'cp_ac_registar_cpt_empresa' );

// ─── Associação Utilizador ↔ Empresas ──────────────────────────────────────
// Lista de IDs de "cp_empresa" guardada no usermeta 'cp_empresas'.

function cp_ac_user_empresas( $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) return [];
	$ids = get_user_meta( $user_id, 'cp_empresas', true );
	$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	return array_values( array_filter( $ids, function ( $id ) {
		return get_post_type( $id ) === 'cp_empresa' && get_post_status( $id ) === 'publish';
	} ) );
}

function cp_ac_set_user_empresas( $user_id, array $empresa_ids ) {
	update_user_meta( $user_id, 'cp_empresas', array_map( 'intval', $empresa_ids ) );
}

// Campo no perfil do utilizador (wp-admin → Utilizadores → Editar) para
// associar empresas. O nonce é o nativo do próprio formulário de perfil.
function cp_ac_user_profile_empresas_field( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) return;
	$empresas     = get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
	$selecionadas = cp_ac_user_empresas( $user->ID );
	?>
	<h2><?php esc_html_e( 'Área de Cliente — Empresas', 'contabilidade-pro' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="cp_empresas"><?php esc_html_e( 'Empresas associadas', 'contabilidade-pro' ); ?></label></th>
			<td>
				<?php if ( empty( $empresas ) ) : ?>
					<p><em><?php esc_html_e( 'Ainda não existem empresas criadas.', 'contabilidade-pro' ); ?></em></p>
				<?php else :
					foreach ( $empresas as $empresa ) : ?>
						<label style="display:block;margin-bottom:4px">
							<input type="checkbox" name="cp_empresas[]" value="<?php echo esc_attr( $empresa->ID ); ?>"
								<?php checked( in_array( $empresa->ID, $selecionadas, true ) ); ?>>
							<?php echo esc_html( $empresa->post_title ); ?>
						</label>
					<?php endforeach;
				endif; ?>
				<p class="description"><?php esc_html_e( 'A que empresa(s) este utilizador tem acesso na Área de Cliente.', 'contabilidade-pro' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'cp_ac_user_profile_empresas_field' );
add_action( 'edit_user_profile', 'cp_ac_user_profile_empresas_field' );

function cp_ac_save_user_profile_empresas( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) ) return;
	$empresas = isset( $_POST['cp_empresas'] ) ? array_map( 'intval', (array) $_POST['cp_empresas'] ) : [];
	cp_ac_set_user_empresas( $user_id, $empresas );
}
add_action( 'personal_options_update', 'cp_ac_save_user_profile_empresas' );
add_action( 'edit_user_profile_update', 'cp_ac_save_user_profile_empresas' );

// ─── Matriz de Ferramentas por Empresa ─────────────────────────────────────
// Guardada como postmeta '_cp_ferramentas_ativas' no post da empresa.
// Uma ferramenta nova, ainda não gravada em nenhuma empresa, fica OFF por
// defeito — é assim que se testa uma funcionalidade nova só num cliente
// antes de a ligar a todos.

function cp_ac_empresa_ferramentas_ativas( $empresa_id ) {
	$ativas = get_post_meta( $empresa_id, '_cp_ferramentas_ativas', true );
	return is_array( $ativas ) ? $ativas : [];
}

function cp_ac_empresa_tem_ferramenta( $empresa_id, $chave ) {
	return in_array( $chave, cp_ac_empresa_ferramentas_ativas( $empresa_id ), true );
}

function cp_ac_set_empresa_ferramentas( $empresa_id, array $chaves ) {
	$validas = array_keys( cp_ac_ferramentas() );
	$chaves  = array_values( array_intersect( $chaves, $validas ) );
	update_post_meta( $empresa_id, '_cp_ferramentas_ativas', $chaves );
}

// ─── Empresa ativa na sessão ────────────────────────────────────────────────
// Guardada no usermeta — sobrevive a trocar de separador, mas é sempre
// confirmada contra as empresas a que o utilizador tem mesmo acesso.

function cp_ac_empresa_ativa( $user_id = null ) {
	$user_id  = $user_id ?: get_current_user_id();
	$empresas = cp_ac_user_empresas( $user_id );
	if ( empty( $empresas ) ) return 0;

	$guardada = (int) get_user_meta( $user_id, 'cp_empresa_ativa', true );
	if ( $guardada && in_array( $guardada, $empresas, true ) ) return $guardada;

	// Sem seleção válida: se só houver uma empresa, essa é a ativa.
	if ( count( $empresas ) === 1 ) {
		update_user_meta( $user_id, 'cp_empresa_ativa', $empresas[0] );
		return $empresas[0];
	}
	return 0; // várias empresas, nenhuma escolhida ainda → mostrar seletor
}

function cp_ac_definir_empresa_ativa( $empresa_id, $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( in_array( (int) $empresa_id, cp_ac_user_empresas( $user_id ), true ) ) {
		update_user_meta( $user_id, 'cp_empresa_ativa', (int) $empresa_id );
		return true;
	}
	return false;
}

// Pedidos via URL numa página da Área de Cliente:
//   ?cp_empresa=123  → troca para essa empresa
//   ?cp_trocar=1     → limpa a seleção (mostra o seletor outra vez)
function cp_ac_handle_pedidos_url() {
	if ( ! is_user_logged_in() ) return;
	if ( isset( $_GET['cp_trocar'] ) ) {
		delete_user_meta( get_current_user_id(), 'cp_empresa_ativa' );
		return;
	}
	if ( isset( $_GET['cp_empresa'] ) ) {
		cp_ac_definir_empresa_ativa( absint( $_GET['cp_empresa'] ) );
	}
}
add_action( 'template_redirect', 'cp_ac_handle_pedidos_url' );

// ─── Gate principal: o utilizador atual tem a ferramenta X na empresa ativa? ──

function cp_ac_tem_ferramenta( $chave ) {
	$empresa = cp_ac_empresa_ativa();
	if ( ! $empresa ) return false;
	return cp_ac_empresa_tem_ferramenta( $empresa, $chave );
}

// ─── Administração: matriz de ferramentas por empresa ─────────────────────

function cp_ac_admin_menu() {
	add_menu_page(
		__( 'Área de Cliente', 'contabilidade-pro' ),
		__( 'Área de Cliente', 'contabilidade-pro' ),
		'manage_options',
		'cp_area_cliente',
		'cp_ac_render_matriz_page',
		'dashicons-groups',
		56
	);
	add_submenu_page(
		'cp_area_cliente',
		__( 'Ferramentas por Empresa', 'contabilidade-pro' ),
		__( 'Ferramentas por Empresa', 'contabilidade-pro' ),
		'manage_options',
		'cp_area_cliente',
		'cp_ac_render_matriz_page'
	);
}
add_action( 'admin_menu', 'cp_ac_admin_menu' );

function cp_ac_render_matriz_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	if ( isset( $_POST['cp_ac_matriz_nonce'] ) && wp_verify_nonce( $_POST['cp_ac_matriz_nonce'], 'cp_ac_matriz' ) ) {
		$empresas = get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1 ] );
		foreach ( $empresas as $empresa ) {
			$chaves = isset( $_POST['ferramentas'][ $empresa->ID ] )
				? array_map( 'sanitize_key', (array) $_POST['ferramentas'][ $empresa->ID ] )
				: [];
			cp_ac_set_empresa_ferramentas( $empresa->ID, $chaves );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Matriz atualizada.', 'contabilidade-pro' ) . '</p></div>';
	}

	$empresas    = get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
	$ferramentas = cp_ac_ferramentas();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ferramentas por Empresa', 'contabilidade-pro' ); ?></h1>
		<p><?php esc_html_e( 'Liga cada ferramenta só às empresas que a devem ver — útil para testar uma funcionalidade nova em 1 cliente antes de a ligar a todos. O Dashboard está sempre visível e não aparece aqui.', 'contabilidade-pro' ); ?></p>

		<?php if ( empty( $empresas ) ) : ?>
			<p><em>
				<?php
				printf(
					/* translators: %s: link para criar empresa */
					esc_html__( 'Ainda não existem empresas. Cria a primeira em %s.', 'contabilidade-pro' ),
					'<a href="' . esc_url( admin_url( 'post-new.php?post_type=cp_empresa' ) ) . '">' . esc_html__( 'Empresas → Adicionar Empresa', 'contabilidade-pro' ) . '</a>'
				);
				?>
			</em></p>
		<?php else : ?>
		<form method="post">
			<?php wp_nonce_field( 'cp_ac_matriz', 'cp_ac_matriz_nonce' ); ?>
			<table class="widefat striped" style="margin-top:16px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Empresa', 'contabilidade-pro' ); ?></th>
						<?php foreach ( $ferramentas as $chave => $info ) : ?>
							<th style="text-align:center">
								<?php echo esc_html( $info['label'] ); ?><br>
								<a href="#" class="cp-ac-marcar-coluna" data-chave="<?php echo esc_attr( $chave ); ?>" data-valor="1" style="font-weight:400;font-size:11px"><?php esc_html_e( 'ligar a todas', 'contabilidade-pro' ); ?></a>
								·
								<a href="#" class="cp-ac-marcar-coluna" data-chave="<?php echo esc_attr( $chave ); ?>" data-valor="0" style="font-weight:400;font-size:11px"><?php esc_html_e( 'desligar de todas', 'contabilidade-pro' ); ?></a>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $empresas as $empresa ) :
						$ativas = cp_ac_empresa_ferramentas_ativas( $empresa->ID ); ?>
						<tr>
							<td><strong><?php echo esc_html( $empresa->post_title ); ?></strong></td>
							<?php foreach ( $ferramentas as $chave => $info ) : ?>
								<td style="text-align:center">
									<input type="checkbox"
										class="cp-ac-check cp-ac-check-<?php echo esc_attr( $chave ); ?>"
										name="ferramentas[<?php echo esc_attr( $empresa->ID ); ?>][]"
										value="<?php echo esc_attr( $chave ); ?>"
										<?php checked( in_array( $chave, $ativas, true ) ); ?>>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar matriz', 'contabilidade-pro' ); ?></button></p>
		</form>
		<script>
		document.querySelectorAll('.cp-ac-marcar-coluna').forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var chave = this.dataset.chave, valor = this.dataset.valor === '1';
				document.querySelectorAll('.cp-ac-check-' + chave).forEach(function (cb) { cb.checked = valor; });
			});
		});
		</script>
		<?php endif; ?>
	</div>
	<?php
}
