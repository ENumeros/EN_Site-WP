<?php get_header(); ?>

<main id="main" class="site-main">

    <!-- ═══════════════════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════════════════ -->
    <section class="hero" aria-label="<?php esc_attr_e( 'Introdução', 'contabilidade-pro' ); ?>">
        <div class="hero__banner">
            <img class="hero__bg" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/hero-bg.webp' ); ?>" alt="Pessoa a trabalhar num portátil">
            <div class="hero__overlay" aria-hidden="true"></div>
            <div class="hero__content">
                <h1 class="hero__title">Contabilidade, fiscalidade e recursos humanos, num só lugar.</h1>
                <p class="hero__subtitle">Sediados em Coimbra, trabalhamos com empresas em todo o país, de forma simples e digital — reduzindo a burocracia de um gabinete tradicional.</p>
                <div class="hero__actions">
                    <a href="<?php echo esc_url( cp_opt( 'cp_hero_cta_url', '#contacto' ) ); ?>" class="btn btn--accent">
                        <?php echo esc_html( cp_opt( 'cp_hero_cta', 'Marcar reunião' ) ); ?>
                    </a>
                    <a href="#servicos" class="btn btn--outline-light">
                        <?php esc_html_e( 'Ver serviços', 'contabilidade-pro' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         SERVIÇOS
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--services" id="servicos">
        <div class="container">
            <div class="section__header">
                <h2 class="section__title"><?php esc_html_e( 'O que fazemos', 'contabilidade-pro' ); ?></h2>
            </div>

            <div class="services-grid">
                <div class="service-card service-card--cta">
                    <div class="service-card__num service-card__num--light" aria-hidden="true">01</div>
                    <h3 class="service-card__title service-card__title--light"><?php esc_html_e( 'Contabilidade', 'contabilidade-pro' ); ?></h3>
                    <p class="service-card__desc service-card__desc--light"><?php esc_html_e( 'Organização contabilística completa com um acompanhamento próximo, para que possa focar-se no que realmente importa: o seu negócio.', 'contabilidade-pro' ); ?></p>
                </div>

                <div class="service-card-cta-wrap">
                    <div class="service-card">
                        <div class="service-card__num service-card__num--gold" aria-hidden="true">02</div>
                        <h3 class="service-card__title"><?php esc_html_e( 'Muito mais do que Contabilidade', 'contabilidade-pro' ); ?></h3>
                        <p class="service-card__desc"><?php esc_html_e( 'Vai abrir empresa? Quer mudar de contabilista? Ou só precisa de uma segunda opinião? Estamos aqui para o orientar.', 'contabilidade-pro' ); ?></p>
                    </div>
                    <a href="<?php echo esc_url( home_url( '/servicos' ) ); ?>" class="service-card__link">
                        <?php esc_html_e( 'Ver todos os serviços →', 'contabilidade-pro' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         COMO TRABALHAMOS
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--how">
        <div class="how-bg">
            <img class="how-bg__photo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/sobre-bg.webp' ); ?>" alt="Faturação digital em ecrã de portátil">
            <div class="how-bg__overlay" aria-hidden="true"></div>
        </div>
        <div class="container how-grid">
            <div class="how-intro">
                <h2 class="section__title section__title--light"><?php esc_html_e( 'Como trabalhamos', 'contabilidade-pro' ); ?></h2>
                <p class="how-intro__text"><?php esc_html_e( 'Próximos, digitais e claros — em cada contacto.', 'contabilidade-pro' ); ?></p>
            </div>
            <div class="how-steps">
                <div class="how-step">
                    <div class="how-step__num" aria-hidden="true">01</div>
                    <p><?php esc_html_e( 'Menos papel, menos deslocações — tratamos o essencial à distância.', 'contabilidade-pro' ); ?></p>
                </div>
                <div class="how-step">
                    <div class="how-step__num" aria-hidden="true">02</div>
                    <p><?php esc_html_e( 'Contacto frequente por telemóvel, email ou WhatsApp.', 'contabilidade-pro' ); ?></p>
                </div>
                <div class="how-step">
                    <div class="how-step__num" aria-hidden="true">03</div>
                    <p><?php esc_html_e( 'Quando é preciso, vamos até si.', 'contabilidade-pro' ); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         SOBRE
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--about" id="sobre">
        <div class="container about-grid">
            <div class="about__text-col">
                <h2 class="section__title"><?php esc_html_e( 'Quem somos', 'contabilidade-pro' ); ?></h2>
                <p class="about__text"><?php echo esc_html( cp_opt( 'cp_about_text_1', 'Trabalhamos pelo gosto que temos pela contabilidade — não por obrigação. É essa diferença que trazemos para cada cliente.' ) ); ?></p>
                <p class="about__text"><?php echo esc_html( cp_opt( 'cp_about_text_2', 'Não ficamos só pelos números: construímos consigo os mapas de acompanhamento que fazem sentido para o seu negócio, e ajudamos a implementar as ferramentas de gestão e faturação que tornam esse acompanhamento possível no dia a dia.' ) ); ?></p>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         CTA FINAL / CONTACTO
    ═══════════════════════════════════════════════════════ -->
    <section class="section section--contact" id="contacto">
        <div class="container contact-grid">
            <div class="contact-main">
                <h2 class="section__title section__title--light"><?php esc_html_e( 'Vamos falar sobre o seu negócio?', 'contabilidade-pro' ); ?></h2>
                <p class="contact__text"><?php esc_html_e( 'Preencha o formulário e entraremos em contacto. Se preferir, contacte-nos por telefone, e-mail ou WhatsApp, ou marque de imediato uma reunião online.', 'contabilidade-pro' ); ?></p>

                <?php if ( isset( $_GET['contacto'] ) && 'enviado' === $_GET['contacto'] ) : ?>
                    <p class="contact-form__success"><?php esc_html_e( 'Obrigado! A sua mensagem foi enviada — entraremos em contacto em breve.', 'contabilidade-pro' ); ?></p>
                <?php elseif ( shortcode_exists( 'contact-form-7' ) ) : ?>
                    <?php echo do_shortcode( '[contact-form-7 id="ALTERAR-PARA-ID-DO-FORMULARIO" title="Contacto"]' ); ?>
                <?php else : ?>
                    <form class="contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'cp_contact_form', 'cp_nonce' ); ?>
                        <input type="hidden" name="action" value="cp_contact">
                        <div class="form-row">
                            <div class="form-field">
                                <label for="cf_name"><?php esc_html_e( 'Nome', 'contabilidade-pro' ); ?></label>
                                <input type="text" id="cf_name" name="cf_name" required placeholder="<?php esc_attr_e( 'O seu nome', 'contabilidade-pro' ); ?>">
                            </div>
                            <div class="form-field">
                                <label for="cf_email"><?php esc_html_e( 'E-mail', 'contabilidade-pro' ); ?></label>
                                <input type="email" id="cf_email" name="cf_email" required placeholder="nome@empresa.pt">
                            </div>
                        </div>
                        <div class="form-field">
                            <label for="cf_message"><?php esc_html_e( 'Mensagem', 'contabilidade-pro' ); ?></label>
                            <textarea id="cf_message" name="cf_message" rows="4" placeholder="<?php esc_attr_e( 'Descreva brevemente o que precisa…', 'contabilidade-pro' ); ?>"></textarea>
                        </div>
                        <button type="submit" class="btn btn--accent">
                            <?php esc_html_e( 'Enviar mensagem', 'contabilidade-pro' ); ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php
                $cp_cal_link = cp_opt( 'cp_cal_link', 'enumeros/reuniao' );
                if ( $cp_cal_link ) :
                ?>
                <details class="contact-alt-form">
                    <summary>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <?php esc_html_e( 'Ou prefere marcar já uma reunião?', 'contabilidade-pro' ); ?>
                    </summary>
                    <div class="cal-embed-wrap">
                        <div id="cp-cal-inline"></div>
                    </div>
                    <script type="text/javascript">
                    (function (C, A, L) { let p = function (a, ar) { a.q.push(ar); }; let d = C.document; C.Cal = C.Cal || function () { let cal = C.Cal; let ar = arguments; if (!cal.loaded) { cal.ns = {}; cal.q = cal.q || []; d.head.appendChild(d.createElement("script")).src = A; cal.loaded = true; } if (ar[0] === L) { const api = function () { p(api, arguments); }; const namespace = ar[1]; api.q = api.q || []; if (typeof namespace === "string") { cal.ns[namespace] = cal.ns[namespace] || api; p(cal.ns[namespace], ar); p(cal, ["initNamespace", namespace]); } else p(cal, ar); return; } p(cal, ar); }; })(window, "https://app.cal.com/embed/embed.js", "init");
                    Cal("init", "reuniao", { origin: "https://cal.com" });
                    Cal.ns.reuniao("inline", {
                        elementOrSelector: "#cp-cal-inline",
                        config: { layout: "month_view" },
                        calLink: <?php echo wp_json_encode( $cp_cal_link ); ?>,
                    });
                    Cal.ns.reuniao("ui", { hideEventTypeDetails: false, layout: "month_view" });
                    </script>
                </details>
                <?php endif; ?>
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
                <?php $cp_addr = cp_opt( 'cp_address' ); if ( $cp_addr ) : ?>
                <div class="contact-details__item">
                    <span class="contact-details__label"><?php esc_html_e( 'Morada', 'contabilidade-pro' ); ?></span>
                    <span><?php echo esc_html( $cp_addr ); ?></span>
                </div>
                <?php endif; ?>
                <div class="contact-details__item">
                    <span class="contact-details__label"><?php esc_html_e( 'Siga-nos', 'contabilidade-pro' ); ?></span>
                    <?php get_template_part( 'template-parts/social-icons' ); ?>
                </div>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
