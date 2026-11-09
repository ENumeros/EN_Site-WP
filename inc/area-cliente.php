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
			'label' => __( 'Lembretes', 'contabilidade-pro' ),
			'grupo' => '',
			'icon'  => '<rect x="3.5" y="4" width="17" height="16" rx="2"></rect><path d="M7.5 9.5 9 11l2.5-3"></path><path d="M14 9h4"></path><path d="M7.5 15.5 9 17l2.5-3"></path><path d="M14 15h4"></path>',
		],
		'mensagens'          => [
			'label' => __( 'Mensagens', 'contabilidade-pro' ),
			'grupo' => '',
			'icon'  => '<path d="M4 5.5h16v11H8l-4 3.5z"></path>',
		],
		'visao_financeira'   => [
			'label' => __( 'Visão financeira', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<path d="M4 19V5"></path><path d="M4 19h16"></path><path d="M8 19v-6l3.5 2.5L15 9l4 4"></path>',
		],
		'conta_corrente'     => [
			'label' => __( 'Conta Corrente', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<path d="M3 7.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M16 12.5h3v3h-3a1.5 1.5 0 0 1 0-3z"></path><path d="M3 9h15"></path>',
		],
		'calendario_fiscal'  => [
			'label' => __( 'Calendário', 'contabilidade-pro' ),
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

// "work_id - Nome", para a equipa confirmar rapidamente que está na
// empresa certa (o code interno da app Work só interessa à equipa — o
// cliente nunca vê isto, só o nome). Sem work_id (empresa ainda não
// sincronizada), mostra só o nome.
function cp_ac_empresa_label( $empresa_id ) {
	$work_id = get_post_meta( $empresa_id, '_cp_work_id', true );
	$nome    = get_the_title( $empresa_id );
	return $work_id ? $work_id . ' - ' . $nome : $nome;
}

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

		<?php
		// Passado como objeto (não array) de propósito: do_action() tem uma
		// compatibilidade com PHP4 que desembrulha silenciosamente um array
		// de 1 elemento quando esse elemento é um objeto — exatamente o caso
		// de haver só 1 empresa — entregando o WP_Post sozinho ao callback
		// em vez do array. Um objeto evita esse desembrulho em qualquer
		// contagem de empresas.
		do_action( 'cp_ac_matriz_extra', (object) [ 'empresas' => $empresas ] );
		?>
	</div>
	<?php
}


// ─── API de sincronização de Empresas (Work → Área de Cliente) ────────────
// Endpoint próprio em /wp-json/cp/v1/empresas usado pela app interna (Work)
// para sincronizar empresas-cliente. Sincronização é one-way: a Work envia
// dados para aqui, o WordPress nunca escreve de volta na Work. Autenticação
// via WordPress Application Passwords (utilizador com permissão para editar
// o CPT cp_empresa).

function cp_ac_rest_routes() {
    register_rest_route( 'cp/v1', '/empresas', [
        [
            'methods'             => 'GET',
            'callback'            => 'cp_ac_rest_listar_empresas',
            'permission_callback' => 'cp_ac_rest_permissao',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_empresa',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'nome'    => [ 'required' => true, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes' );

function cp_ac_rest_permissao() {
    return current_user_can( 'edit_posts' );
}

function cp_ac_rest_listar_empresas( $request ) {
    $empresas = get_posts( [
        'post_type'      => 'cp_empresa',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ] );
    $resultado = [];
    foreach ( $empresas as $empresa ) {
        $resultado[] = [
            'id'      => $empresa->ID,
            'nome'    => $empresa->post_title,
            'work_id' => get_post_meta( $empresa->ID, '_cp_work_id', true ),
            'ferramentas' => cp_ac_empresa_ferramentas_ativas( $empresa->ID ),
        ];
    }
    return rest_ensure_response( $resultado );
}

function cp_ac_rest_sincronizar_empresa( $request ) {
    $work_id = sanitize_text_field( $request->get_param( 'work_id' ) );
    $nome    = sanitize_text_field( $request->get_param( 'nome' ) );
    $ferramentas_param = $request->get_param( 'ferramentas' );

    if ( '' === $work_id || '' === $nome ) {
        return new WP_Error(
            'cp_dados_invalidos',
            __( 'work_id e nome são obrigatórios.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    $existente = get_posts( [
        'post_type'   => 'cp_empresa',
        'meta_key'    => '_cp_work_id',
        'meta_value'  => $work_id,
        'numberposts' => 1,
        'post_status' => 'any',
    ] );

    if ( ! empty( $existente ) ) {
        $post_id = $existente[0]->ID;
        wp_update_post( [
            'ID'          => $post_id,
            'post_title'  => $nome,
            'post_status' => 'publish',
        ] );
        $criado = false;
    } else {
        $post_id = wp_insert_post( [
            'post_type'   => 'cp_empresa',
            'post_title'  => $nome,
            'post_status' => 'publish',
        ] );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        update_post_meta( $post_id, '_cp_work_id', $work_id );
        $criado = true;
    }

    if ( is_array( $ferramentas_param ) ) {
        cp_ac_set_empresa_ferramentas( $post_id, array_map( 'sanitize_key', $ferramentas_param ) );
    }

    return rest_ensure_response( [
        'id'          => $post_id,
        'criado'      => $criado,
        'ferramentas' => cp_ac_empresa_ferramentas_ativas( $post_id ),
    ] );
}


// ─── API de criação de Utilizadores (Work → Área de Cliente) ──────────────
// Endpoint próprio em /wp-json/cp/v1/utilizadores usado pela app interna
// (Work) para criar a conta de login do cliente no WordPress e associar-lhe
// as empresas a que deve ter acesso (por work_id de empresa, já resolvido
// para o ID do post cp_empresa correspondente). A password gerada nunca é
// devolvida na resposta nem enviada pela Work — ao criar o utilizador
// dispara-se o fluxo nativo do WordPress para o cliente definir a sua
// própria password (link de "definir password").

function cp_ac_rest_routes_utilizadores() {
    register_rest_route( 'cp/v1', '/utilizadores', [
        [
            'methods'             => 'GET',
            'callback'            => 'cp_ac_rest_listar_utilizadores',
            'permission_callback' => 'cp_ac_rest_permissao_utilizadores',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_criar_utilizador',
            'permission_callback' => 'cp_ac_rest_permissao_utilizadores',
            'args'                => [
                'work_id'  => [ 'required' => true, 'type' => 'string' ],
                'nome'     => [ 'required' => true, 'type' => 'string' ],
                'email'    => [ 'required' => true, 'type' => 'string' ],
                'empresas' => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_utilizadores' );

function cp_ac_rest_permissao_utilizadores() {
    return current_user_can( 'create_users' );
}

function cp_ac_rest_listar_utilizadores( $request ) {
    $utilizadores = get_users( [
        'meta_key'     => '_cp_work_id',
        'meta_compare' => 'EXISTS',
    ] );
    $resultado = [];
    foreach ( $utilizadores as $utilizador ) {
        $resultado[] = [
            'id'       => $utilizador->ID,
            'nome'     => $utilizador->display_name,
            'email'    => $utilizador->user_email,
            'work_id'  => get_user_meta( $utilizador->ID, '_cp_work_id', true ),
            'empresas' => get_user_meta( $utilizador->ID, 'cp_empresas', true ),
        ];
    }
    return rest_ensure_response( $resultado );
}

function cp_ac_rest_criar_utilizador( $request ) {
    $work_id       = sanitize_text_field( $request->get_param( 'work_id' ) );
    $nome          = sanitize_text_field( $request->get_param( 'nome' ) );
    $email         = sanitize_email( $request->get_param( 'email' ) );
    $empresas_work = $request->get_param( 'empresas' );
    $empresas_work = is_array( $empresas_work ) ? array_map( 'sanitize_text_field', $empresas_work ) : [];

    if ( '' === $work_id || '' === $nome || ! is_email( $email ) ) {
        return new WP_Error(
            'cp_dados_invalidos',
            __( 'work_id, nome e um email válido são obrigatórios.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    // Resolve os work_id das empresas para os IDs dos posts cp_empresa já
    // sincronizados. Uma empresa ainda não sincronizada é ignorada e
    // reportada em "empresas_nao_encontradas", para a Work saber que tem
    // de a sincronizar primeiro.
    $empresas_ids    = [];
    $nao_encontradas = [];
    foreach ( $empresas_work as $empresa_work_id ) {
        $posts = get_posts( [
            'post_type'   => 'cp_empresa',
            'meta_key'    => '_cp_work_id',
            'meta_value'  => $empresa_work_id,
            'numberposts' => 1,
            'post_status' => 'any',
        ] );
        if ( ! empty( $posts ) ) {
            $empresas_ids[] = $posts[0]->ID;
        } else {
            $nao_encontradas[] = $empresa_work_id;
        }
    }

    // Já existe utilizador para este work_id? Atualiza em vez de duplicar.
    $existentes = get_users( [
        'meta_key'   => '_cp_work_id',
        'meta_value' => $work_id,
        'number'     => 1,
    ] );

    if ( ! empty( $existentes ) ) {
        $user_id = $existentes[0]->ID;
        wp_update_user( [
            'ID'           => $user_id,
            'display_name' => $nome,
            'user_email'   => $email,
        ] );
        if ( ! empty( $empresas_ids ) ) {
            update_user_meta( $user_id, 'cp_empresas', array_map( 'intval', $empresas_ids ) );
        }
        return rest_ensure_response( [
            'id'                       => $user_id,
            'criado'                   => false,
            'empresas_nao_encontradas' => $nao_encontradas,
        ] );
    }

    // Email já usado por outra conta WordPress (sem work_id associado)?
    if ( email_exists( $email ) ) {
        return new WP_Error(
            'cp_email_existente',
            __( 'Já existe uma conta WordPress com este email.', 'contabilidade-pro' ),
            [ 'status' => 409 ]
        );
    }

    $username = sanitize_user( current( explode( '@', $email ) ), true );
    if ( username_exists( $username ) ) {
        $username .= '-' . wp_rand( 100, 999 );
    }

    $user_id = wp_insert_user( [
        'user_login'   => $username,
        'user_email'   => $email,
        'display_name' => $nome,
        'user_pass'    => wp_generate_password( 32, true, true ), // nunca comunicada
        'role'         => 'subscriber',
    ] );

    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    update_user_meta( $user_id, '_cp_work_id', $work_id );
    if ( ! empty( $empresas_ids ) ) {
        update_user_meta( $user_id, 'cp_empresas', array_map( 'intval', $empresas_ids ) );
    }

    // Dispara o fluxo nativo do WordPress para o cliente definir a própria
    // password (a password gerada acima nunca é enviada nem devolvida).
    wp_new_user_notification( $user_id, null, 'user' );

    return rest_ensure_response( [
        'id'                       => $user_id,
        'criado'                   => true,
        'empresas_nao_encontradas' => $nao_encontradas,
    ] );
}


// ─── Util: resolver empresa pelo work_id ───────────────────────────────────

function cp_ac_empresa_by_work_id( $work_id ) {
    $work_id = sanitize_text_field( $work_id );
    if ( '' === $work_id ) return 0;
    $posts = get_posts( [
        'post_type'   => 'cp_empresa',
        'meta_key'    => '_cp_work_id',
        'meta_value'  => $work_id,
        'numberposts' => 1,
        'post_status' => 'any',
    ] );
    return empty( $posts ) ? 0 : $posts[0]->ID;
}

// Resposta de erro consistente quando o work_id não corresponde a nenhuma
// empresa sincronizada (a Work tem de sincronizar a empresa primeiro).
function cp_ac_rest_erro_empresa_nao_encontrada() {
    return new WP_Error(
        'cp_empresa_nao_encontrada',
        __( 'Nenhuma empresa sincronizada com este work_id.', 'contabilidade-pro' ),
        [ 'status' => 404 ]
    );
}

// Guarda um ficheiro recebido por multipart (campo $campo de $_FILES, via
// WP_REST_Request::get_file_params()) na media library e devolve
// [ 'id' => attachment_id, 'url' => attachment_url ] ou um WP_Error.
function cp_ac_rest_guardar_ficheiro( $request, $campo ) {
    $ficheiros = $request->get_file_params();
    if ( empty( $ficheiros[ $campo ] ) || UPLOAD_ERR_OK !== $ficheiros[ $campo ]['error'] ) {
        return new WP_Error(
            'cp_ficheiro_em_falta',
            sprintf( /* translators: %s: nome do campo */ __( 'Ficheiro em falta ou inválido no campo "%s".', 'contabilidade-pro' ), $campo ),
            [ 'status' => 400 ]
        );
    }
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    $anexo_id = media_handle_upload( $campo, 0, [], [
        'test_form' => false,
        'action'    => 'cp_ac_upload',
    ] );
    if ( is_wp_error( $anexo_id ) ) {
        return $anexo_id;
    }
    return [
        'id'  => $anexo_id,
        'url' => wp_get_attachment_url( $anexo_id ),
    ];
}


// ════════════════════════════════════════════════════════════════════════
// Pendentes (Work → Área de Cliente → Work)
// ════════════════════════════════════════════════════════════════════════
// Dois grupos por empresa: "documentos pendentes" (a Work pede um documento
// específico ao cliente) e "movimentos a esclarecer" (linhas do extrato
// bancário que a Work precisa que o cliente explique). A Work envia a
// lista completa (substitui sempre tudo) via POST /pendentes. O cliente
// responde no site (upload e/ou texto); essas respostas ficam guardadas em
// '_cp_pendentes_respostas' até a Work as ir buscar (GET /respostas) e
// confirmar que as processou (POST /respostas/confirmar), altura em que são
// apagadas daqui — o WordPress nunca é a fonte de verdade definitiva, é só
// uma caixa de correio de ida e volta.

function cp_ac_pendentes_documentos( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_documentos', true );
    return is_array( $itens ) ? $itens : [];
}

function cp_ac_pendentes_movimentos( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_movimentos', true );
    return is_array( $itens ) ? $itens : [];
}

function cp_ac_pendentes_respostas( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_respostas', true );
    return is_array( $itens ) ? $itens : [];
}

// Devolve a resposta (se existir) para um item (documento ou movimento).
function cp_ac_pendente_resposta_do_item( $empresa_id, $item_id ) {
    foreach ( cp_ac_pendentes_respostas( $empresa_id ) as $resposta ) {
        if ( $resposta['item_id'] === $item_id ) return $resposta;
    }
    return null;
}

function cp_ac_rest_routes_pendentes() {
    register_rest_route( 'cp/v1', '/pendentes', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_pendentes',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id'    => [ 'required' => true, 'type' => 'string' ],
                'documentos' => [ 'required' => false, 'type' => 'array' ],
                'movimentos' => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/pendentes/respostas', [
        [
            'methods'             => 'GET',
            'callback'            => 'cp_ac_rest_listar_respostas_pendentes',
            'permission_callback' => 'cp_ac_rest_permissao',
        ],
    ] );
    register_rest_route( 'cp/v1', '/pendentes/respostas/confirmar', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_confirmar_respostas_pendentes',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'ids'     => [ 'required' => true, 'type' => 'array' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_pendentes' );

function cp_ac_rest_sincronizar_pendentes( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    // Guardado antes de sobrescrever, só para detetar o que é mesmo novo
    // (para notificar o cliente só quando há novidade a sério).
    $ids_documentos_antes = wp_list_pluck( cp_ac_pendentes_documentos( $empresa_id ), 'id' );
    $ids_movimentos_antes = wp_list_pluck( cp_ac_pendentes_movimentos( $empresa_id ), 'id' );

    $documentos_in = $request->get_param( 'documentos' );
    $movimentos_in = $request->get_param( 'movimentos' );

    $documentos = [];
    if ( is_array( $documentos_in ) ) {
        foreach ( $documentos_in as $doc ) {
            if ( empty( $doc['id'] ) ) continue;
            $documentos[] = [
                'id'        => sanitize_text_field( $doc['id'] ),
                'titulo'    => isset( $doc['titulo'] ) ? sanitize_text_field( $doc['titulo'] ) : '',
                'pedido_em' => isset( $doc['pedido_em'] ) ? sanitize_text_field( $doc['pedido_em'] ) : '',
            ];
        }
    }

    $movimentos = [];
    if ( is_array( $movimentos_in ) ) {
        foreach ( $movimentos_in as $mov ) {
            if ( empty( $mov['id'] ) ) continue;
            $movimentos[] = [
                'id'        => sanitize_text_field( $mov['id'] ),
                'data'      => isset( $mov['data'] ) ? sanitize_text_field( $mov['data'] ) : '',
                'valor'     => isset( $mov['valor'] ) ? (float) $mov['valor'] : 0,
                'descricao' => isset( $mov['descricao'] ) ? sanitize_text_field( $mov['descricao'] ) : '',
                'pergunta'  => isset( $mov['pergunta'] ) ? sanitize_text_field( $mov['pergunta'] ) : '',
            ];
        }
    }

    update_post_meta( $empresa_id, '_cp_pendentes_documentos', $documentos );
    update_post_meta( $empresa_id, '_cp_pendentes_movimentos', $movimentos );

    $ha_novidade = array_diff( wp_list_pluck( $documentos, 'id' ), $ids_documentos_antes )
        || array_diff( wp_list_pluck( $movimentos, 'id' ), $ids_movimentos_antes );
    if ( $ha_novidade && cp_ac_empresa_tem_ferramenta( $empresa_id, 'pendentes' ) ) {
        cp_push_notificar_empresa(
            $empresa_id,
            __( 'Novos pendentes', 'contabilidade-pro' ),
            __( 'Tem documentos ou movimentos novos à espera de resposta.', 'contabilidade-pro' ),
            add_query_arg( 'vista', 'pendentes', home_url( '/area-de-cliente/' ) )
        );
    }

    return rest_ensure_response( [
        'ok'                => true,
        'total_documentos'  => count( $documentos ),
        'total_movimentos'  => count( $movimentos ),
    ] );
}

function cp_ac_rest_listar_respostas_pendentes( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    return rest_ensure_response( [
        'respostas' => cp_ac_pendentes_respostas( $empresa_id ),
    ] );
}

function cp_ac_rest_confirmar_respostas_pendentes( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $ids = array_map( 'sanitize_text_field', (array) $request->get_param( 'ids' ) );

    $restantes = array_values( array_filter( cp_ac_pendentes_respostas( $empresa_id ), function ( $resposta ) use ( $ids ) {
        return ! in_array( $resposta['item_id'], $ids, true );
    } ) );
    update_post_meta( $empresa_id, '_cp_pendentes_respostas', $restantes );

    return rest_ensure_response( [ 'ok' => true, 'restantes' => count( $restantes ) ] );
}

// ─── Resposta do cliente a um pendente (upload e/ou texto) ─────────────────
// Formulário simples no ecrã "Pendentes" — não é REST, é o próprio cliente
// autenticado a submeter via admin-post.php, como o formulário de contacto.

function cp_ac_handle_pendente_resposta() {
    if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
    if ( ! isset( $_POST['cp_pendente_nonce'] ) || ! wp_verify_nonce( $_POST['cp_pendente_nonce'], 'cp_pendente_resposta' ) ) {
        wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
    }

    $empresa_id = cp_ac_empresa_ativa();
    $item_id    = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';
    $tipo       = isset( $_POST['tipo'] ) && 'documento' === $_POST['tipo'] ? 'documento' : 'movimento';
    $texto      = isset( $_POST['texto'] ) ? sanitize_textarea_field( wp_unslash( $_POST['texto'] ) ) : '';
    $voltar     = wp_get_referer() ? wp_get_referer() : home_url( '/' );

    // O item tem mesmo de pertencer à empresa ativa do utilizador.
    $ids_validos = wp_list_pluck( 'documento' === $tipo ? cp_ac_pendentes_documentos( $empresa_id ) : cp_ac_pendentes_movimentos( $empresa_id ), 'id' );
    if ( ! $empresa_id || '' === $item_id || ! in_array( $item_id, $ids_validos, true ) ) {
        wp_safe_redirect( add_query_arg( 'cp_erro', 'pendente_invalido', $voltar ) );
        exit;
    }

    $anexo_id  = 0;
    $anexo_url = '';
    $anexo_nome = '';
    if ( ! empty( $_FILES['anexo'] ) && UPLOAD_ERR_NO_FILE !== $_FILES['anexo']['error'] ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $anexo_nome_original = isset( $_FILES['anexo']['name'] ) ? sanitize_file_name( $_FILES['anexo']['name'] ) : '';
        $resultado = media_handle_upload( 'anexo', 0, [], [ 'test_form' => false ] );
        if ( ! is_wp_error( $resultado ) ) {
            $anexo_id   = $resultado;
            $anexo_url  = wp_get_attachment_url( $resultado );
            $anexo_nome = $anexo_nome_original;
        }
    }

    if ( '' === $texto && ! $anexo_id ) {
        wp_safe_redirect( add_query_arg( 'cp_erro', 'resposta_vazia', $voltar ) );
        exit;
    }

    $respostas = cp_ac_pendentes_respostas( $empresa_id );
    // Substitui uma resposta anterior ao mesmo item, se existir.
    $respostas = array_values( array_filter( $respostas, function ( $r ) use ( $item_id ) {
        return $r['item_id'] !== $item_id;
    } ) );
    $respostas[] = [
        'item_id'       => $item_id,
        'tipo'          => $tipo,
        'texto'         => $texto,
        'anexo_id'      => $anexo_id,
        'anexo_url'     => $anexo_url,
        'anexo_nome'    => $anexo_nome,
        'respondido_em' => current_time( 'mysql' ),
    ];
    update_post_meta( $empresa_id, '_cp_pendentes_respostas', $respostas );

    cp_push_notificar_equipa(
        __( 'Nova resposta de cliente', 'contabilidade-pro' ),
        sprintf(
            /* translators: %s: nome da empresa */
            __( '%s respondeu a um pendente.', 'contabilidade-pro' ),
            get_the_title( $empresa_id )
        ),
        get_edit_post_link( $empresa_id ),
        $empresa_id
    );

    wp_safe_redirect( add_query_arg( 'cp_sucesso', 'resposta_enviada', $voltar ) );
    exit;
}
add_action( 'admin_post_cp_pendente_responder', 'cp_ac_handle_pendente_resposta' );


// ════════════════════════════════════════════════════════════════════════
// Conta Corrente (Work → Área de Cliente)
// ════════════════════════════════════════════════════════════════════════
// Resumo (saldo, faturado, pago) + linhas (faturas/recibos). A Work envia
// tudo de uma vez via POST /conta-corrente (substitui sempre tudo, mas
// preserva o documento já anexado a uma linha que continue a existir). O
// PDF de cada documento é enviado à parte, por multipart, via POST
// /conta-corrente/documento, identificando a linha pelo seu "id".

function cp_ac_conta_corrente( $empresa_id ) {
    $dados = get_post_meta( $empresa_id, '_cp_conta_corrente', true );
    if ( ! is_array( $dados ) ) $dados = [];
    $dados += [ 'resumo' => [], 'linhas' => [] ];
    return $dados;
}

function cp_ac_rest_routes_conta_corrente() {
    register_rest_route( 'cp/v1', '/conta-corrente', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_conta_corrente',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'resumo'  => [ 'required' => false, 'type' => 'object' ],
                'linhas'  => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/conta-corrente/documento', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_conta_corrente_documento',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id'  => [ 'required' => true, 'type' => 'string' ],
                'linha_id' => [ 'required' => true, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_conta_corrente' );

function cp_ac_rest_sincronizar_conta_corrente( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $atual         = cp_ac_conta_corrente( $empresa_id );
    $anexos_por_id = [];
    foreach ( $atual['linhas'] as $linha ) {
        if ( ! empty( $linha['anexo_id'] ) ) {
            $anexos_por_id[ $linha['id'] ] = [
                'anexo_id'  => $linha['anexo_id'],
                'anexo_url' => $linha['anexo_url'],
            ];
        }
    }

    $resumo_in = $request->get_param( 'resumo' );
    $resumo    = [];
    if ( is_array( $resumo_in ) ) {
        foreach ( [ 'saldo_atual', 'saldo_nota', 'faturado_ano', 'faturado_nota', 'pago_ano', 'pago_nota' ] as $campo ) {
            if ( isset( $resumo_in[ $campo ] ) ) {
                $resumo[ $campo ] = in_array( $campo, [ 'saldo_atual', 'faturado_ano', 'pago_ano' ], true )
                    ? (float) $resumo_in[ $campo ]
                    : sanitize_text_field( $resumo_in[ $campo ] );
            }
        }
    }

    $linhas_in = $request->get_param( 'linhas' );
    $linhas    = [];
    if ( is_array( $linhas_in ) ) {
        foreach ( $linhas_in as $linha ) {
            if ( empty( $linha['id'] ) ) continue;
            $id       = sanitize_text_field( $linha['id'] );
            $nova     = [
                'id'        => $id,
                'data'      => isset( $linha['data'] ) ? sanitize_text_field( $linha['data'] ) : '',
                'documento' => isset( $linha['documento'] ) ? sanitize_text_field( $linha['documento'] ) : '',
                'tipo'      => isset( $linha['tipo'] ) ? sanitize_text_field( $linha['tipo'] ) : '',
                'valor'     => isset( $linha['valor'] ) ? (float) $linha['valor'] : 0,
                'estado'    => isset( $linha['estado'] ) ? sanitize_text_field( $linha['estado'] ) : '',
                'anexo_id'  => 0,
                'anexo_url' => '',
            ];
            if ( isset( $anexos_por_id[ $id ] ) ) {
                $nova['anexo_id']  = $anexos_por_id[ $id ]['anexo_id'];
                $nova['anexo_url'] = $anexos_por_id[ $id ]['anexo_url'];
            }
            $linhas[] = $nova;
        }
    }

    update_post_meta( $empresa_id, '_cp_conta_corrente', [ 'resumo' => $resumo, 'linhas' => $linhas ] );

    return rest_ensure_response( [ 'ok' => true, 'total_linhas' => count( $linhas ) ] );
}

function cp_ac_rest_conta_corrente_documento( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $linha_id = sanitize_text_field( $request->get_param( 'linha_id' ) );
    $ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
    if ( is_wp_error( $ficheiro ) ) return $ficheiro;

    $dados      = cp_ac_conta_corrente( $empresa_id );
    $encontrada = false;
    foreach ( $dados['linhas'] as &$linha ) {
        if ( $linha['id'] === $linha_id ) {
            $linha['anexo_id']  = $ficheiro['id'];
            $linha['anexo_url'] = $ficheiro['url'];
            $encontrada = true;
            break;
        }
    }
    unset( $linha );

    if ( ! $encontrada ) {
        return new WP_Error(
            'cp_linha_nao_encontrada',
            __( 'Não existe nenhuma linha com este linha_id. Sincroniza primeiro via POST /conta-corrente.', 'contabilidade-pro' ),
            [ 'status' => 404 ]
        );
    }

    update_post_meta( $empresa_id, '_cp_conta_corrente', $dados );

    return rest_ensure_response( [ 'ok' => true, 'anexo_id' => $ficheiro['id'], 'anexo_url' => $ficheiro['url'] ] );
}


// ════════════════════════════════════════════════════════════════════════
// Mapas de Gestão (Work → Área de Cliente)
// ════════════════════════════════════════════════════════════════════════
// Um mapa por mês (chave "YYYY-MM"), sempre com um PDF. Criar/substituir um
// mês é sempre o mesmo pedido multipart (POST /mapas-gestao); remover um
// mês é POST /mapas-gestao/remover.

function cp_ac_mapas_gestao( $empresa_id ) {
    $mapas = get_post_meta( $empresa_id, '_cp_mapas_gestao', true );
    return is_array( $mapas ) ? $mapas : [];
}

function cp_ac_rest_routes_mapas_gestao() {
    register_rest_route( 'cp/v1', '/mapas-gestao', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_mapa_gestao',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'mes'     => [ 'required' => true, 'type' => 'string' ],
                'titulo'  => [ 'required' => false, 'type' => 'string' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/mapas-gestao/remover', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_remover_mapa_gestao',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'mes'     => [ 'required' => true, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_mapas_gestao' );

function cp_ac_rest_sincronizar_mapa_gestao( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $mes = sanitize_text_field( $request->get_param( 'mes' ) );
    if ( ! preg_match( '/^\d{4}-\d{2}$/', $mes ) ) {
        return new WP_Error(
            'cp_mes_invalido',
            __( 'mes tem de estar no formato AAAA-MM.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    $ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
    if ( is_wp_error( $ficheiro ) ) return $ficheiro;

    $titulo_param = $request->get_param( 'titulo' );
    $titulo       = $titulo_param ? sanitize_text_field( $titulo_param ) : sprintf(
        /* translators: %s: mês no formato AAAA-MM */
        __( 'Mapa de Gestão — %s', 'contabilidade-pro' ),
        $mes
    );

    $mapas = cp_ac_mapas_gestao( $empresa_id );
    $anterior = null;
    $mapas = array_values( array_filter( $mapas, function ( $m ) use ( $mes, &$anterior ) {
        if ( $m['mes'] === $mes ) { $anterior = $m; return false; }
        return true;
    } ) );
    if ( $anterior && ! empty( $anterior['anexo_id'] ) ) {
        wp_delete_attachment( $anterior['anexo_id'], true );
    }

    $mapas[] = [
        'mes'          => $mes,
        'titulo'       => $titulo,
        'publicado_em' => current_time( 'mysql' ),
        'anexo_id'     => $ficheiro['id'],
        'anexo_url'    => $ficheiro['url'],
    ];
    // Mais recente primeiro.
    usort( $mapas, function ( $a, $b ) { return strcmp( $b['mes'], $a['mes'] ); } );

    update_post_meta( $empresa_id, '_cp_mapas_gestao', $mapas );

    if ( cp_ac_empresa_tem_ferramenta( $empresa_id, 'mapas_gestao' ) ) {
        cp_push_notificar_empresa(
            $empresa_id,
            __( 'Novo mapa de gestão', 'contabilidade-pro' ),
            $titulo,
            add_query_arg( 'vista', 'mapas_gestao', home_url( '/area-de-cliente/' ) )
        );
    }

    return rest_ensure_response( [ 'ok' => true, 'mes' => $mes, 'anexo_url' => $ficheiro['url'] ] );
}

function cp_ac_rest_remover_mapa_gestao( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $mes      = sanitize_text_field( $request->get_param( 'mes' ) );
    $mapas    = cp_ac_mapas_gestao( $empresa_id );
    $removido = null;
    $restantes = array_values( array_filter( $mapas, function ( $m ) use ( $mes, &$removido ) {
        if ( $m['mes'] === $mes ) { $removido = $m; return false; }
        return true;
    } ) );

    if ( ! $removido ) {
        return new WP_Error(
            'cp_mapa_nao_encontrado',
            __( 'Não existe nenhum mapa para este mês.', 'contabilidade-pro' ),
            [ 'status' => 404 ]
        );
    }

    if ( ! empty( $removido['anexo_id'] ) ) {
        wp_delete_attachment( $removido['anexo_id'], true );
    }
    update_post_meta( $empresa_id, '_cp_mapas_gestao', $restantes );

    return rest_ensure_response( [ 'ok' => true ] );
}


// ════════════════════════════════════════════════════════════════════════
// Recibos de Vencimento (Work → Área de Cliente)
// ════════════════════════════════════════════════════════════════════════
// Um recibo por colaborador/mês, chave (work_id, cod_emp, ano, mes), sempre
// com um PDF. Upsert: se já existir um recibo com esta chave, substitui o
// ficheiro e os campos — nunca duplica. O "n_processamento" é controlo
// interno da Work e não é enviado para aqui.

function cp_ac_recibos_vencimento( $empresa_id ) {
    $recibos = get_post_meta( $empresa_id, '_cp_recibos_vencimento', true );
    return is_array( $recibos ) ? $recibos : [];
}

function cp_ac_rest_routes_recibos_vencimento() {
    register_rest_route( 'cp/v1', '/recibos-vencimento', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_recibo_vencimento',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id'            => [ 'required' => true, 'type' => 'string' ],
                'cod_emp'            => [ 'required' => true, 'type' => 'string' ],
                'nome_emp'           => [ 'required' => true, 'type' => 'string' ],
                'ano'                => [ 'required' => true, 'type' => 'integer' ],
                'mes'                => [ 'required' => true, 'type' => 'integer' ],
                'tipo_processamento' => [ 'required' => false, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_recibos_vencimento' );

function cp_ac_rest_sincronizar_recibo_vencimento( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $cod_emp  = sanitize_text_field( $request->get_param( 'cod_emp' ) );
    $nome_emp = sanitize_text_field( $request->get_param( 'nome_emp' ) );
    $ano      = (int) $request->get_param( 'ano' );
    $mes      = (int) $request->get_param( 'mes' );

    if ( '' === $cod_emp || $mes < 1 || $mes > 12 ) {
        return new WP_Error(
            'cp_dados_invalidos',
            __( 'cod_emp é obrigatório e mes tem de estar entre 1 e 12.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    $tipo_param         = $request->get_param( 'tipo_processamento' );
    $tipo_processamento = $tipo_param ? sanitize_text_field( $tipo_param ) : '';

    $ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
    if ( is_wp_error( $ficheiro ) ) return $ficheiro;

    $recibos  = cp_ac_recibos_vencimento( $empresa_id );
    $anterior = null;
    $recibos  = array_values( array_filter( $recibos, function ( $r ) use ( $cod_emp, $ano, $mes, &$anterior ) {
        if ( $r['cod_emp'] === $cod_emp && (int) $r['ano'] === $ano && (int) $r['mes'] === $mes ) {
            $anterior = $r;
            return false;
        }
        return true;
    } ) );
    if ( $anterior && ! empty( $anterior['anexo_id'] ) ) {
        wp_delete_attachment( $anterior['anexo_id'], true );
    }

    $recibos[] = [
        'cod_emp'            => $cod_emp,
        'nome_emp'           => $nome_emp,
        'ano'                => $ano,
        'mes'                => $mes,
        'tipo_processamento' => $tipo_processamento,
        'anexo_id'           => $ficheiro['id'],
        'anexo_url'          => $ficheiro['url'],
        'atualizado_em'      => current_time( 'mysql' ),
    ];

    update_post_meta( $empresa_id, '_cp_recibos_vencimento', $recibos );

    return rest_ensure_response( [ 'id' => $ficheiro['id'], 'criado' => ! $anterior ] );
}
