<?php get_header(); ?>

<main id="main" class="site-main">

    <section class="services-hero">
        <div class="container">
            <h1 class="services-hero__title"><?php esc_html_e( 'Política de Privacidade', 'contabilidade-pro' ); ?></h1>
            <p class="services-hero__text"><?php esc_html_e( 'Como tratamos os seus dados pessoais neste site.', 'contabilidade-pro' ); ?></p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="legal-content">

                <p><?php esc_html_e( 'A proteção dos seus dados pessoais é importante para nós. Esta política explica, de forma simples, que dados recolhemos através deste site, para que servem e quais os seus direitos.', 'contabilidade-pro' ); ?></p>

                <h2><?php esc_html_e( 'Quem é o responsável pelo tratamento', 'contabilidade-pro' ); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: 1: company name, 2: contact email */
                        esc_html__( '%1$s é a entidade responsável pelo tratamento dos dados recolhidos através deste site. Para qualquer questão relacionada com esta política ou com os seus dados pessoais, pode contactar-nos através de %2$s.', 'contabilidade-pro' ),
                        esc_html( get_bloginfo( 'name' ) ) . ', Lda.',
                        '<a href="mailto:' . esc_attr( cp_opt( 'cp_email', 'geral@enumeros.pt' ) ) . '">' . esc_html( cp_opt( 'cp_email', 'geral@enumeros.pt' ) ) . '</a>'
                    );
                    ?>
                </p>

                <h2><?php esc_html_e( 'Que dados recolhemos', 'contabilidade-pro' ); ?></h2>
                <p><?php esc_html_e( 'Quando preenche o formulário de contacto neste site, recolhemos:', 'contabilidade-pro' ); ?></p>
                <ul>
                    <li><?php esc_html_e( 'Nome', 'contabilidade-pro' ); ?></li>
                    <li><?php esc_html_e( 'Endereço de e-mail', 'contabilidade-pro' ); ?></li>
                    <li><?php esc_html_e( 'A mensagem que nos envia', 'contabilidade-pro' ); ?></li>
                </ul>
                <p><?php esc_html_e( 'Não recolhemos outros dados pessoais através deste site, e não utilizamos cookies de publicidade nem pixels de redes sociais.', 'contabilidade-pro' ); ?></p>

                <h2><?php esc_html_e( 'Para que usamos estes dados', 'contabilidade-pro' ); ?></h2>
                <p><?php esc_html_e( 'Os dados do formulário de contacto são utilizados exclusivamente para responder ao seu pedido — por e-mail, telefone ou WhatsApp, conforme o meio que preferir indicar. Não utilizamos estes dados para fins de marketing, nem os cedemos a terceiros.', 'contabilidade-pro' ); ?></p>
                <p><?php esc_html_e( 'Quando submete o formulário, a mensagem é guardada no site e é-nos enviada uma notificação por e-mail, para garantir que a sua mensagem não se perde e que lhe respondemos o mais rapidamente possível.', 'contabilidade-pro' ); ?></p>

                <h2><?php esc_html_e( 'Estatísticas de utilização do site', 'contabilidade-pro' ); ?></h2>
                <p><?php esc_html_e( 'Este site pode utilizar o Google Analytics (GA4) para compreender, de forma agregada e anónima, como é que os visitantes utilizam o site — por exemplo, que páginas são mais visitadas. Estes dados não nos permitem identificar diretamente quem o visita, e não são utilizados para publicidade.', 'contabilidade-pro' ); ?></p>

                <h2><?php esc_html_e( 'Durante quanto tempo guardamos os dados', 'contabilidade-pro' ); ?></h2>
                <p><?php esc_html_e( 'As mensagens enviadas através do formulário de contacto são conservadas apenas durante o tempo necessário para tratar o seu pedido e, depois disso, pelo período adicional necessário para cumprir eventuais obrigações legais. Pode solicitar a eliminação dos seus dados em qualquer momento, nos termos descritos abaixo.', 'contabilidade-pro' ); ?></p>

                <h2><?php esc_html_e( 'Os seus direitos', 'contabilidade-pro' ); ?></h2>
                <p><?php esc_html_e( 'Nos termos do Regulamento Geral de Proteção de Dados (RGPD), tem o direito de, em qualquer momento:', 'contabilidade-pro' ); ?></p>
                <ul>
                    <li><?php esc_html_e( 'Aceder aos dados pessoais que temos sobre si', 'contabilidade-pro' ); ?></li>
                    <li><?php esc_html_e( 'Solicitar a correção de dados incorretos ou incompletos', 'contabilidade-pro' ); ?></li>
                    <li><?php esc_html_e( 'Solicitar a eliminação dos seus dados', 'contabilidade-pro' ); ?></li>
                    <li><?php esc_html_e( 'Opor-se ao tratamento dos seus dados', 'contabilidade-pro' ); ?></li>
                    <li><?php esc_html_e( 'Apresentar reclamação junto da Comissão Nacional de Proteção de Dados (CNPD)', 'contabilidade-pro' ); ?></li>
                </ul>
                <p>
                    <?php
                    printf(
                        /* translators: %s: contact email */
                        esc_html__( 'Para exercer qualquer um destes direitos, contacte-nos através de %s.', 'contabilidade-pro' ),
                        '<a href="mailto:' . esc_attr( cp_opt( 'cp_email', 'geral@enumeros.pt' ) ) . '">' . esc_html( cp_opt( 'cp_email', 'geral@enumeros.pt' ) ) . '</a>'
                    );
                    ?>
                </p>

                <h2><?php esc_html_e( 'Alterações a esta política', 'contabilidade-pro' ); ?></h2>
                <p><?php esc_html_e( 'Esta política pode ser atualizada periodicamente. Recomendamos que a consulte de forma regular para se manter informado sobre como protegemos os seus dados.', 'contabilidade-pro' ); ?></p>

                <p class="legal-content__updated"><?php esc_html_e( 'Última atualização: outubro de 2026.', 'contabilidade-pro' ); ?></p>

            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
