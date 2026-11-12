<?php get_header(); ?>

<main id="main" class="site-main">

    <!-- ═══════════════════════════════════════════════════════
         CABEÇALHO — CONTABILIDADE, O SERVIÇO PRINCIPAL
         (imagem com o mesmo tamanho do hero da página inicial)
    ═══════════════════════════════════════════════════════ -->
    <section class="hero hero--servicos" aria-labelledby="servicos-titulo">
        <h1 id="servicos-titulo" class="screen-reader-text"><?php esc_html_e( 'Serviços', 'contabilidade-pro' ); ?></h1>
        <div class="hero__banner">
            <img class="hero__bg" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/servicos-bg.webp' ); ?>" alt="Análise de gráficos financeiros num portátil e telemóvel">
            <div class="hero__overlay" aria-hidden="true"></div>
            <div class="hero__content">
                <div class="service-main">
                    <div class="service-main__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v5h5"/><path d="M9 13h6M9 17h6M9 9h2"/></svg>
                    </div>
                    <div class="service-main__content">
                        <span class="service-main__eyebrow"><?php esc_html_e( 'O nosso serviço principal', 'contabilidade-pro' ); ?></span>
                        <h2 class="service-main__title"><?php esc_html_e( 'Contabilidade', 'contabilidade-pro' ); ?></h2>
                        <p class="service-main__desc"><?php esc_html_e( 'Tratamos de toda a organização contabilística com rigor e um acompanhamento próximo, para que possa focar-se no que realmente importa: a gestão do seu negócio.', 'contabilidade-pro' ); ?></p>
                        <a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>" class="service-main__link"><?php esc_html_e( 'Falar sobre Contabilidade →', 'contabilidade-pro' ); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         NUMA SITUAÇÃO PARTICULAR?
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--client-focus">
        <div class="container">

            <div class="section__header">
                <h2 class="section__title"><?php esc_html_e( 'Numa situação particular?', 'contabilidade-pro' ); ?></h2>
            </div>
            <div class="situacoes-grid">
                <div class="situacao-card-wrap">
                    <div class="situacao-card">
                        <div class="situacao-card__num" aria-hidden="true">01</div>
                        <h3 class="situacao-card__title"><?php esc_html_e( 'Quer abrir empresa?', 'contabilidade-pro' ); ?></h3>
                        <p class="situacao-card__desc"><?php esc_html_e( 'Tratamos de toda a constituição — escolha do tipo de sociedade, registo comercial, início de atividade nas Finanças e Segurança Social — e deixamos tudo pronto para faturar desde o primeiro dia.', 'contabilidade-pro' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>" class="situacao-card__link"><?php esc_html_e( 'Falar sobre abertura de empresa →', 'contabilidade-pro' ); ?></a>
                </div>
                <div class="situacao-card-wrap">
                    <div class="situacao-card">
                        <div class="situacao-card__num" aria-hidden="true">02</div>
                        <h3 class="situacao-card__title"><?php esc_html_e( 'Quer mudar de contabilista?', 'contabilidade-pro' ); ?></h3>
                        <p class="situacao-card__desc"><?php esc_html_e( 'A transição é mais simples do que parece: tratamos de toda a passagem de pasta com o gabinete anterior, sem interrupções nas suas obrigações fiscais.', 'contabilidade-pro' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>" class="situacao-card__link"><?php esc_html_e( 'Falar sobre mudança de contabilista →', 'contabilidade-pro' ); ?></a>
                </div>
            </div>

        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         APOIO ADMINISTRATIVO (faixa a toda a largura, secção própria)
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--admin-support">
        <div class="how-bg">
            <img class="how-bg__photo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/sobre-bg.webp' ); ?>" alt="" loading="lazy">
            <div class="how-bg__overlay" aria-hidden="true"></div>
        </div>
        <div class="container">
            <div class="admin-support">
                <div class="admin-support__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 4v5M16 4v5"/><circle cx="9" cy="14" r="1"/><circle cx="12" cy="14" r="1"/><circle cx="15" cy="14" r="1"/></svg>
                </div>
                <div class="admin-support__content">
                    <span class="admin-support__eyebrow"><?php esc_html_e( 'Apoio ao dia a dia', 'contabilidade-pro' ); ?></span>
                    <h2 class="admin-support__title"><?php esc_html_e( 'Apoio Administrativo', 'contabilidade-pro' ); ?></h2>
                    <p class="admin-support__desc"><?php esc_html_e( 'Ajudamos a escolher e a configurar as ferramentas de faturação e gestão certas para o seu negócio, com apoio direto à sua equipa.', 'contabilidade-pro' ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>" class="admin-support__link"><?php esc_html_e( 'Falar sobre apoio administrativo →', 'contabilidade-pro' ); ?></a>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         RESTANTES SERVIÇOS (segundo plano)
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--services-compact">
        <div class="container">
            <h2 class="services-compact__heading"><?php esc_html_e( 'Também tratamos de', 'contabilidade-pro' ); ?></h2>
            <div class="services-compact-list">
                <?php
                $all_services = [
                    [
                        'title' => 'Fiscalidade',
                        'desc'  => 'Cumprimento de todas as obrigações fiscais — IVA, IRS, IRC e demais declarações.',
                    ],
                    [
                        'title' => 'Recursos Humanos',
                        'desc'  => 'Tratamos da parte burocrática da gestão de pessoas — processamento de salários, admissões, cessações e comunicações obrigatórias.',
                    ],
                    [
                        'title' => 'Acompanhamento Personalizado',
                        'desc'  => 'Indicadores e relatórios mensais sobre a saúde financeira do seu negócio, com reuniões periódicas para rever os números e apoiar as suas decisões.',
                    ],
                ];
                foreach ( $all_services as $service ) : ?>
                <div class="services-compact-item">
                    <h3 class="services-compact-item__title"><?php echo esc_html( $service['title'] ); ?></h3>
                    <p class="services-compact-item__desc"><?php echo esc_html( $service['desc'] ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>" class="services-compact-item__link"><?php esc_html_e( 'Saber mais', 'contabilidade-pro' ); ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         CTA FINAL
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--contact">
        <div class="container contact-grid">
            <div class="contact-main">
                <h2 class="section__title section__title--light"><?php esc_html_e( 'Pronto para simplificar a contabilidade do seu negócio?', 'contabilidade-pro' ); ?></h2>
                <p class="contact__text"><?php esc_html_e( 'Marque uma reunião e conte-nos o que precisa — respondemos sempre no próprio dia útil.', 'contabilidade-pro' ); ?></p>
                <div class="contact__actions">
                    <a href="<?php echo esc_url( home_url( '/#contacto' ) ); ?>" class="btn btn--accent">
                        <?php esc_html_e( 'Marcar reunião', 'contabilidade-pro' ); ?>
                    </a>
                </div>
            </div>
            <div class="contact-details">
                <div class="contact-details__item">
                    <span class="contact-details__label"><?php esc_html_e( 'E-mail', 'contabilidade-pro' ); ?></span>
                    <a href="mailto:<?php echo esc_attr( cp_opt( 'cp_email', 'geral@enumeros.pt' ) ); ?>"><?php echo esc_html( cp_opt( 'cp_email', 'geral@enumeros.pt' ) ); ?></a>
                </div>
                <div class="contact-details__item">
                    <span class="contact-details__label"><?php esc_html_e( 'Telefone', 'contabilidade-pro' ); ?></span>
                    <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', cp_opt( 'cp_phone', '+351 968 339 687' ) ) ); ?>"><?php echo esc_html( cp_opt( 'cp_phone', '+351 968 339 687' ) ); ?></a>
                </div>
                <div class="contact-details__item">
                    <span class="contact-details__label">WhatsApp</span>
                    <?php
                    $cp_whatsapp_phone = preg_replace( '/\D+/', '', cp_opt( 'cp_phone', '+351 968 339 687' ) );
                    if ( $cp_whatsapp_phone ) :
                    ?>
                    <a href="<?php echo esc_url( 'https://wa.me/' . $cp_whatsapp_phone ); ?>" class="contact-whatsapp-btn" target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-1.746-.873-2.888-1.556-4.035-3.527-.305-.526.305-.489.874-1.627.099-.198.05-.371-.05-.52-.1-.149-.669-1.611-.916-2.206-.242-.579-.487-.5-.669-.51-.173-.009-.371-.011-.57-.011-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.057 3.133 5.022 4.274 2.966 1.142 2.966.762 3.934.714.968-.049 1.758-.816 2.055-1.263.297-.446.297-.852.198-.967-.1-.114-.372-.198-.669-.347zM12.054 2C6.5 2 2 6.44 2 11.922c0 2.179.724 4.19 1.95 5.822L2.5 22.5l4.886-1.313c1.56.858 3.35 1.336 5.168 1.336 5.555 0 10.054-4.439 10.054-9.922C22.608 6.44 18.11 2 12.555 2z"/></svg>
                        <?php esc_html_e( 'Falar agora', 'contabilidade-pro' ); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="contact-details__item">
                    <span class="contact-details__label"><?php esc_html_e( 'Siga-nos', 'contabilidade-pro' ); ?></span>
                    <?php get_template_part( 'template-parts/social-icons' ); ?>
                </div>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
